<?php

/**
 * IPIP.net dat数据库查询器.
 */
class Ipip
{
    /**
     * 文件头长度
     */
    const SIZE_HEADER = 4;
    /**
     * 一级索引项长度
     */
    const SIZE_INDEX1_ENTRY = 4;
    /**
     * 一级索引总长度
     */
    const SIZE_INDEX1 = 1024; // SIZE_INDEX1_ENTRY * 256
    /**
     * 二级索引项长度
     */
    const SIZE_INDEX2_ENTRY = 8;
    /**
     * 数据库文件名
     * @var null|string
     */
    private $filename = NULL;
    /**
     * @var null 数据库文件句柄
     */
    private $fp = NULL;
    /**
     * @var null 数据区偏移
     */
    private $data_offset = NULL;
    /**
     * @var null 一级索引
     */
    private $index1 = NULL;
    /**
     * @var null 二级索引
     */
    private $index2 = NULL;
    /**
     * @var null 二级索引大小
     */
    private $index2_length = NULL;

    /**
     * @param null $path_to_datfile
     */
    public function __construct($path_to_datfile = null)
    {
        $this->filename = $path_to_datfile ? $path_to_datfile : __DIR__ . '/17monipdb.dat';

        $this->open_file();
        $this->init();
    }

    public function __destruct()
    {
        $this->close_file();
    }

    /**
     * 查询数据库
     * @param $ip
     * @return array|string
     */
    public function find($ip)
    {
        $ip = gethostbyname($ip);
        $index2_entry = $this->search_index2_for_ip($ip);

        if ($index2_entry === NULL) {
            return $this->na();
        }

        return $this->get_data_entry($index2_entry[0], $index2_entry[1]);
    }

    /**
     * 二分查找
     * @param $ip
     * @return array|null
     */
    private function search_index2_for_ip($ip)
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        $ip_long = ip2long($ip);

        $iph = (int) explode('.', $ip)[0];
        $p_lb = $this->get_index1_entry_for_iph($iph);
        $p_ub = $iph >= 255 ? ($this->index2_length / self::SIZE_INDEX2_ENTRY) - 1 : $this->get_index1_entry_for_iph($iph + 1);
        $p_candi = $p_ub;

        while ($p_lb < $p_ub ) {
            $p = floor(($p_lb + $p_ub) / 2);

            // get_index2_entry() is slow
            $boundary = current(unpack('Nv', substr($this->index2, $p * self::SIZE_INDEX2_ENTRY, 4)));

            if ($ip_long < $boundary) {
                $p_candi = $p;
                $p_ub = $p;
            }
            else if ($ip_long > $boundary) {
                if ($p_lb === $p) {
                    break;
                }
                $p_lb = $p;
            }
            else {
                $p_candi = $p;
                break;
            }
        }

        $offset = $p_candi * self::SIZE_INDEX2_ENTRY;
        $data_offset = current(unpack('Vv', substr($this->index2, $offset + 4, 3) . "\x0"));
        $length = current(unpack('Cv', substr($this->index2, $offset + 7, 1)));
        $index2_entry = [
            $data_offset,
            $length,
        ];

        return $index2_entry;
    }

    /**
     * 获取整个数据库的dump
     * @return array of array (boundary, data)
     */
    public function dump() {
        $entries = array();
        for ($offset = 0; $offset < $this->index2_length - 4; $offset += self::SIZE_INDEX2_ENTRY) {
            $index = $this->get_index2_entry($offset);
            $data  = $this->get_data_entry($index[1], $index[2]);
            $entries []= array('boundary' => $index[0], 'data' => $data);
        }

        return $entries;
    }

    /**
     * @throws Exception
     */
    private function init()
    {
        $header = unpack('Noffset', $this->read(0, self::SIZE_HEADER));
        if ($header['offset'] < self::SIZE_HEADER) {
            throw new Exception('Invalid 17monipdb.dat file');
        }

        $this->data_offset = $header['offset'] - 1024;
        $this->index1 = $this->read(self::SIZE_HEADER, self::SIZE_INDEX1);
        $this->index2_length = $this->data_offset - self::SIZE_HEADER - self::SIZE_INDEX1;
        $this->index2 = $this->read(self::SIZE_HEADER + self::SIZE_INDEX1, $this->index2_length);

//        assert($this->index2_length % self::SIZE_INDEX2_ENTRY == 0);
    }

    /**
     * @return string
     */
    private function na() {
        return 'N/A';
    }

    /**
     * 获取位置数据
     * @param int $index2_entry_offset 偏移
     * @param $len
     * @return array
     */
    private function get_data_entry($index2_entry_offset, $len) {
        return explode("\t", $this->read($this->data_offset + $index2_entry_offset, $len));
    }

    /**
     * 获取一级索引项
     * @param int $iph ip最高位的一个字节值，即点分四段格式中的第一段
     * @return mixed
     */
    private function get_index1_entry_for_iph($iph) {
        $index1_offset = (int)$iph * self::SIZE_INDEX1_ENTRY;
        $index1_value = unpack('Vindex', substr($this->index1, $index1_offset, self::SIZE_INDEX1_ENTRY));
        return $index1_value['index'];
    }

    /**
     * 获取二级索引项
     * @param int $offset 偏移
     * @return array
     */
    private function get_index2_entry($offset) {
        $entry_bin = substr($this->index2, $offset, self::SIZE_INDEX2_ENTRY);

        $boundary = unpack('Nv', substr($entry_bin, 0, 4))['v'];
        $offset = unpack('Vv', substr($entry_bin, 4, 3) . "\x0")['v'];
        $length = unpack('Cv', substr($entry_bin, 7, 1))['v'];

        return [
            $boundary,
            $offset,
            $length,
        ];
    }

    /**
     * 读取数据库文件
     * @param $offset
     * @param $len
     * @return bool|string
     */
    private function read($offset, $len) {
        fseek($this->fp, $offset);
        return fread($this->fp, $len);
    }

    /**
     * 打开文件
     * @throws Exception
     */
    private function open_file() {

        if (! file_exists($this->filename)) {
            throw new Exception($this->filename  .' doesn\'t exist!');
        }

        $this->fp = fopen($this->filename, 'rb');
        if ($this->fp === FALSE) {
            throw new Exception('Invalid 17monipdb.dat file!');
        }
    }

    /**
     * 关闭文件
     */
    private function close_file() {
        if ($this->fp !== NULL) {
            fclose($this->fp);
            $this->fp = NULL;
        }
    }
}
