<?php
ini_set('zend.assertions', 1);

include dirname(__DIR__) . '/ipip.php';
include __DIR__ . '/IP.class.php';

$o = new Ipip(__DIR__ . '/17monipdb.dat');

$ips = [
    // invalid input
    '',
    null,
    '-1.0.0.0',
    'non-existing-host',
    // known segment borders
    '0.0.0.0',
    '0.255.255.254',
    '0.255.255.255',
    '1.0.0.0',
    '255.255.255.254',
    '255.255.255.255',
    // random manually chosen input
    '8.8.8.8',
    '114.114.114.114',
    '58.0.15.208',
    '58.58.114.28',
    '192.168.0.0',
    'localhost',
    'www.baidu.com',
];
// random input
for ($i=0; $i < 1000; $i++) {
    $ips[] = long2ip(rand(0, 0xffffffff));
}

foreach($ips as $ip) {
    assert($o->find($ip) === IP::find($ip), "IP: $ip");
}
