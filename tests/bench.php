<?php

include dirname(__DIR__) . '/ipip.php';
include __DIR__ . '/IP.class.php';

$o = new Ipip(__DIR__ . '/17monipdb.dat');

echo "generating test data...\n";
$ips = [];
for ($i=0; $i < 10000; $i++) {
    $ips[] = long2ip(rand(0, 0xffffffff));
}

echo "benching ipip.php...\n";
$t1 = microtime(true);
foreach ($ips as $ip) {
    $o->find($ip);
}
echo 'time: ' . (microtime(true) - $t1) . "\n";

echo "benching IP.class.php...\n";
$t1 = microtime(true);
foreach ($ips as $ip) {
    IP::find($ip);
}
echo 'time: ' . (microtime(true) - $t1) . "\n";
