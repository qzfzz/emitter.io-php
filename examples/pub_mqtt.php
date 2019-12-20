<?php

include "../vendor/autoload.php";

$emitter = new \emitter\emitter(array(
    'server' => '127.0.0.1',
    'port'   => '8080',
));

$emitterChannel = 'device/paver/SN100';
$emitterKey = 'vsqk2rExvRg8qra4LoQLcbx1enNUapz8';

\Workerman\Lib\Timer::add(1, function()use(&$emitter, $emitterKey, $emitterChannel){
    $time = time();
    try{
RETRY:

        $emitter->publish(
            array(
                'key'     => $emitterKey,
                'channel' => $emitterChannel,
                'ttl' => 3600,
                'message' => array(
                    'blah'    => 'gggg  ' . $time,
                    'name'     => 'jimbob  ' . $time,
                ),
            )
        );
    }
    catch(\Exception|\Error $e)
    {
        echo $e->getMessage(), PHP_EOL;
        echo $e->getTraceAsString(), PHP_EOL;

        $emitter->reconnect();

        goto RETRY;
    }

    echo 'publishing message: ', $time, PHP_EOL;
});

\Workerman\Worker::runAll();
