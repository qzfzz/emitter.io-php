<?php

include "../vendor/autoload.php";

$emitter = new \emitter\Emitter(array(
    'server' => '127.0.0.1',
    'port'   => '8080',
));

$emitterChannel = 'device/paver/SN100';
$emitterKey = 'vsqk2rExvRg8qra4LoQLcbx1enNUapz8';

TryAgain:
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

$emitter->subscribe( $topics );

\Workerman\Lib\Timer::add(0.01, function()use(&$emitter){
    try
    {
        RETRY:
        $emitter->proc();
    }
    catch(\Exception|\Error $e)
    {
        echo $e->getMessage(), PHP_EOL;
        echo $e->getTraceAsString(), PHP_EOL;

        $emitter->reconnect(true);


        goto RETRY;
    }

});

\Workerman\Worker::runAll();
