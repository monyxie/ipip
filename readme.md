#IPIP.net IP归属地数据库查询优化版


###使用
```php
include "ipip.php";
$o = new Ipip(); // 也可以把dat文件的路径传给构造函数
var_dump($o->find('8.8.8.8'));
var_dump($o->dump());
```

###运行测试
```
>php tests/test.php
```

###运行基准测试
```
>php tests/bench.php
```