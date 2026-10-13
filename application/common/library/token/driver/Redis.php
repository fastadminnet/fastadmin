<?php

namespace app\common\library\token\driver;

use app\common\library\token\Driver;

/**
 * Token操作类
 */
class Redis extends Driver
{

    protected $options = [
        'host'        => '127.0.0.1',
        'port'        => 6379,
        'password'    => '',
        'select'      => 0,
        'timeout'     => 0,
        'expire'      => 0,
        'persistent'  => false,
        'userprefix'  => 'up:',
        'tokenprefix' => 'tp:',
    ];

    /**
     * 构造函数
     * @param array $options 缓存参数
     * @throws \BadFunctionCallException
     * @access public
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->handler = new \Redis;
        if ($this->options['persistent']) {
            $this->handler->pconnect($this->options['host'], $this->options['port'], $this->options['timeout'], 'persistent_id_' . $this->options['select']);
        } else {
            $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        }

        if ('' != $this->options['password']) {
            $this->handler->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->handler->select($this->options['select']);
        }
    }

    /**
     * 获取加密后的Token
     * @param string $token Token标识
     * @return string
     */
    protected function getEncryptedToken($token)
    {
        $config = \think\Config::get('token');
        $token = $token ?? ''; // 为兼容 php8
        return $this->options['tokenprefix'] . hash_hmac($config['hashalgo'], $token, $config['key']);
    }

    /**
     * 获取会员的key
     * @param $user_id
     * @return string
     */
    protected function getUserKey($user_id)
    {
        return $this->options['userprefix'] . $user_id;
    }

    /**
     * 存储Token
     * @param   string $token   Token
     * @param   int    $user_id 会员ID
     * @param   int    $expire  过期时长,0表示无限,单位秒
     * @return bool
     */
    public function set($token, $user_id, $expire = 0)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp() - time();
        }
        $key = $this->getEncryptedToken($token);
        if ($this->handler->exists($key)) {
            return false;
        }
        if ($expire) {
            $result = $this->handler->setex($key, $expire, $user_id);
        } else {
            $result = $this->handler->setnx($key, $user_id);
            if (!$result) {
                return false;
            }
        }
        //写入会员关联的token
        $this->handler->sAdd($this->getUserKey($user_id), $key);
        return (bool)$result;
    }

    /**
     * 获取Token内的信息
     * @param   string $token
     * @return  array
     */
    public function get($token)
    {
        $key = $this->getEncryptedToken($token);
        $value = $this->handler->get($key);
        if (is_null($value) || false === $value) {
            return [];
        }
        //获取有效期
        $expire = $this->handler->ttl($key);
        $expire = $expire < 0 ? 365 * 86400 : $expire;
        $expiretime = time() + $expire;
        //解决使用redis方式储存token时api接口Token刷新与检测因expires_in拼写错误报错的BUG
        $result = ['token' => $token, 'user_id' => $value, 'expiretime' => $expiretime, 'expires_in' => $expire];

        return $result;
    }

    /**
     * 判断Token是否可用
     * @param   string $token   Token
     * @param   int    $user_id 会员ID
     * @return  boolean
     */
    public function check($token, $user_id)
    {
        $data = self::get($token);
        return $data && $data['user_id'] == $user_id ? true : false;
    }

    /**
     * 删除Token
     * @param   string $token
     * @return  boolean
     */
    public function delete($token)
    {
        $data = $this->get($token);
        if ($data) {
            $key = $this->getEncryptedToken($token);
            $user_id = $data['user_id'];
            $this->handler->del($key);
            $this->handler->sRem($this->getUserKey($user_id), $key);
        }
        return true;

    }

    /**
     * 删除指定用户的所有Token
     * @param   int $user_id
     * @return  boolean
     */
    public function clear($user_id)
    {
        $keys = $this->handler->sMembers($this->getUserKey($user_id));
        $this->handler->del($this->getUserKey($user_id));
        $this->handler->del($keys);
        return true;
    }

}
