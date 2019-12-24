<?php
/**
 * Created by PhpStorm.
 * User: bob
 * Date: 22/05/2018
 * Time: 12:07
 */

namespace emitter;


use qhelpers\HttpRequest;
use qhelpers\StringUtils;

class Emitter
{
    protected $phpMQTT;
    protected $prefix = '';
    protected $topics = [];

    protected $is_subscribed = false;

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
        $this->phpMQTT = new PhpMQTT($server, $port, $uniqueId);
        if (! $this->phpMQTT->connect(true, NULL, $username, $password)) {
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
     * @param $private_key
     * @param $channel
     * @param $url
     * @param $ttl
     * @return array
     */
    private static function prepareDataToPost($private_key, $channel, $url, $ttl): array
    {
        assert(StringUtils::endsWith($channel, '/#/'), 'channel name should ends with /#/');

        $data_to_post = ['key' => $private_key, 'channel' => $channel];

        if($ttl) {
            $data_to_post['ttl'] = $ttl . '';
        }

        $fields = ['sub', 'pub', 'store', 'load', 'presence'];

        foreach($fields as $field => $defaultValue) {
            if(${$field} || ${$field} == strtolower('on')) {
                $data_to_post[$field] = 'on';
            }
        }

        $hr = HttpRequest::getInstance();
        $ret = $hr->post($url, $data_to_post);
        return $ret;
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

    public function reconnect($auto_subscribe = true)
    {
        $this->phpMQTT->connect();

        if( $this->is_subscribed && $auto_subscribe )
        {
            $this->phpMQTT->subscribe($this->topics);
        }
    }

    public function disconnect()
    {
        $this->phpMQTT->close();
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

        return $this->phpMQTT->publish($key . $channel, $message, 0);
    }


    /**
     * @deprecated
     * @bugs inside please do not use it before fixed
     * @param $topics
     * @param int $qos
     */
    public function subscribe($topics, $qos = 0)
    {
        $this->is_subscribed = true;

        foreach( $topics as $key => $val )
        {
            $this->topics[$key] = $val;
        }

        return $this->phpMQTT->subscribe($topics, $qos);
    }


    public function unsubscribe($topics)
    {
        $this->phpMQTT->unsubscribe($topics);
    }




    /**
     * do process
     * @param bool $loop
     */
    public function proc($loop = false)
    {
        $this->phpMQTT->proc($loop);
    }

    /**
     * params = $type
     */
    public function keygen($private_key,
                           $channel,
                           $ttl = 0,
                           $sub = true,
                           $pub = true,
                           $store = true,
                           $load = true,
                           $presence = true )
    {
        $messages = ['key' => $private_key, 'channel' => $channel, 'type' => '', 'ttl' => $ttl];

        $fields = ['sub' => 'r', 'pub' => 'w', 'store' => 's', 'load' => 'l', 'presence' => 'p'];
        $type = '';
        foreach( $fields as $param => $v )
        {
            $type .= ( ${$param} != false ) ? $v : '';
        }

        $messages['type'] = $type;
        $keygen_channel = 'emitter/keygen/';

        if (is_array($messages) || is_object($messages))
        {
            $messages = json_encode($messages);
        }

        //////////////////////////////
        $retReal = ['topic' => '', 'message'  => '' ];
        $found = false;
        $topics = [ $keygen_channel =>
            [
                'qos' => 0,
                'channel' => $keygen_channel,
                'function' => function($topic, $message) use(&$found, &$retReal){

                    echo 'received message: ', $topic, ' ', $message, PHP_EOL;

                    $retReal['topic'] = $topic;
                    $retReal['message'] = $message;

                    $found = true;
                }]];

        $this->phpMQTT->subscribe( $topics );
        $this->phpMQTT->publish($keygen_channel, $messages, 0);

        while( !$found )
        {
            $this->phpMQTT->proc();
        }

        return json_decode($retReal['message'], true)['key'];
    }

    /**
     * @param $key
     * @param $channel
     * @param string $url
     * @param null $ttl
     * @param bool $sub
     * @param bool $pub
     * @param bool $store
     * @param bool $load
     * @param bool $presence
     * @return string | bool
     */
    public static function getChannelKeyFromJson($private_key,
                                                 $channel,
                                                 $url = 'http://127.0.0.1:8080/keygen_json',
                                                 $ttl = null,
                                                 $sub = true,
                                                 $pub = true,
                                                 $store = true,
                                                 $load = true,
                                                 $presence = true )
    {
        $ret = self::prepareDataToPost($private_key,
            $channel,
            $url,
            $ttl,
            $sub,
            $pub,
            $store,
            $load,
            $presence);

        if( $ret['code'] == 0 )
        {
            $data = $ret['data'];

            $ret = json_decode( $data, true );

            if( $ret['code']  == 0 )
            {
                return $ret['message'];
            }
        }

        return false;
    }


    /**
     * @param $key
     * @param $channel
     * @param string $url
     * @param null $ttl
     * @param bool $sub
     * @param bool $pub
     * @param bool $store
     * @param bool $load
     * @param bool $presence
     * @return string | bool
     */
    public static function getChannelKeyFromHtml( $private_key,
                                                  $channel,
                                                  $url = 'http://127.0.0.1:8080/keygen',
                                                  $ttl = null,
                                                  $sub = true,
                                                  $pub = true,
                                                  $store = true,
                                                  $load = true,
                                                  $presence = true)
    {
        $ret = self::prepareDataToPost($private_key,
            $channel,
            $url,
            $ttl,
            $sub,
            $pub,
            $store,
            $load,
            $presence);

        if( $ret['code'] == 0 )
        {
            $html = $ret['data'];

            $pattern = "/key\s*:\s*(.*)<\/pre>/";
            if(preg_match($pattern, $html, $matches))
            {
                return $matches[1];
            }

        }
        else
        {
            return false;
        }
    }

}