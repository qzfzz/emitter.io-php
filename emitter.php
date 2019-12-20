<?php
/**
 * Created by PhpStorm.
 * User: bob
 * Date: 22/05/2018
 * Time: 12:07
 */

namespace emitter;


use emitter\phpMQTT;

class emitter
{


    protected $emitter;
    protected $prefix = '';

    /**
     * Emitter constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {

        $this->_config = $config;


        $server = $config['server'];
        $port = $config['port'];
        $uniqueId = isset($config['uniqueId']) ? $config['uniqueId'] : sha1(microtime() . $server . $port);
        $this->prefix = isset($config['prefix']) ? $this->parsePrefix($config['prefix']) : '';




        $username = '';
        $password = '';
        $this->emitter = new phpMQTT($server, $port, $uniqueId);
        if (! $this->emitter->connect(true, NULL, $username, $password)) {
            return false;
        }
        return true;
    }


    /**
     * @param string $channel
     * @return string
     */
    private function parsePrefix($prefix)
    {
        if (! $this->startsWith($prefix, '/')) {
            $prefix = '/' . $prefix;
        }

        return $prefix;
    }


    /**
     * @param string $channel
     * @return string
     */
    private function parseChannel($channel)
    {
        if (! $this->startsWith($channel, '/')) {
            $channel = '/' . $channel;
        }

        if (! $this->endsWith($channel, '/')) {
            $channel .= '/';
        }

        return $channel;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);

        return (substr($haystack, 0, $length) === $needle);
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function endsWith($haystack, $needle)
    {
        $length = strlen($needle);

        return $length === 0 ||
            (substr($haystack, -$length) === $needle);
    }


    public function disconnect()
    {
        $this->emitter->close();
    }


    /**
     * @param array $data
     */
    public function publish(array $data)
    {
        $key = $data['key'];
        $channel = $this->prefix . $this->parseChannel($data['channel']);
        $message = $data['message'];

        $ttl = isset($data['ttl']) ? $data['ttl'] : '0';

        $channel.= '?ttl=' . $ttl;



        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        $this->emitter->publish($key . $channel, $message, 0);
    }


    public function subscribe($topics, $qos = 0)
    {
        return $this->emitter->subscribe($topics, $qos);
    }

    public function proc($loop = true)
    {
        $this->emitter->proc($loop);
    }


}