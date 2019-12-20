<?php
/**
 * Created by PhpStorm.
 * User: bob
 * Date: 22/05/2018
 * Time: 12:07
 */

namespace emitter;

use emitter\phpMQTT;
use qhelpers\HttpRequest;
use qhelpers\StringUtils;

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

    public function reconnect()
    {
        $this->emitter->connect();
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

    /**
     * do process
     * @param bool $loop
     */
    public function proc($loop = true)
    {
        $this->emitter->proc($loop);
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