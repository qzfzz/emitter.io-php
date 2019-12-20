<?php

include "../vendor/autoload.php";

$emitter = new \emitter\emitter(array(
    'server' => '127.0.0.1',
    'port'   => '8080',
));

$emitterChannel = 'device/paver/SN100';
$emitterKey = 'vsqk2rExvRg8qra4LoQLcbx1enNUapz8';

$topics = [ $emitterChannel =>
    [
        'key' => $emitterKey,
        'qos' => 0,
        'channel' => $emitterChannel,
        'from' => 1576822781,
        'until' => 1576822787,
        'last' => 10,
        'function' => function($topic, $message){
    echo 'received message: ', $topic, ' ', $message, PHP_EOL;
}]];

$qos = 0;
$emitter->subscribe($topics, $qos);

while(true){
    $emitter->proc();
}



echo __LINE__, PHP_EOL;
