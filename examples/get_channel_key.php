<?php
include '../vendor/autoload.php';
//
//$hr = \phpcommlibs\chelpers\HttpRequest::getInstance();
//
//for( $i = 0; $i < 1; ++$i )
//{
//    $url = 'http://127.0.0.1:8080/keygen_json?&&';
//    //echo $url, PHP_EOL;
//    $data_to_post = ['key' => 'jt6k2rExvRg8qra4XoQLTrreI8pUapz8', 'channel' => 'devices/#/', 'ttl' => '120', 'sub'=>'on', 'pub' => 'on' ];
//    $ret = $hr->post($url, $data_to_post);
//
//    $data = $ret['data'];
//}

$private_key = 'jt6k2rExvRg8qra4XoQLTrreI8pUapz8';
$channel = 'devices2/#/';

$ret = \emitter\emitter::getChannelKeyFromJson($private_key, $channel);

var_dump($ret);

$ret2 =\emitter\emitter::getChannelKeyFromHtml($private_key, $channel);

var_dump($ret2);