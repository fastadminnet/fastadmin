<?php

namespace app\common\library;

use app\common\library\token\Driver;
use think\App;
use think\facade\Log;

/**
 * Token操作类
 */
class Token
{
    /**
     * @var array Token的实例
     */
    public static $instance = [];

    /**
     * @var object 操作句柄
     */
    public static $handler;

    /**
     * 连接Token驱动
     * @access public
     * @param array       $options 配置数组
     * @param bool|string $name    Token连接标识 true 强制重新连接
     * @return Driver
     */
    public static function connect(array $options = [], $name = false)
    {
        $type = !empty($options['type']) ? $options['type'] : 'File';

        if (false === $name) {
            $name = md5(serialize($options));
        }

        if (true === $name || !isset(self::$instance[$name])) {
            $class = false === strpos($type, '\\') ?
                '\\app\\common\\library\\token\\driver\\' . ucwords($type) :
                $type;

            // 记录初始化信息
            if (function_exists('config') && config('app.app_debug')) {
                Log::record('[ TOKEN ] INIT ' . $type, 'info');
            }

            if (true === $name) {
                return new $class($options);
            }

            self::$instance[$name] = new $class($options);
        }

        return self::$instance[$name];
    }

    /**
     * 自动初始化Token
     * @access public
     * @param array $options 配置数组
     * @return Driver
     */
    public static function init(array $options = [])
    {
        if (is_null(self::$handler)) {
            if (empty($options) && 'complex' == (function_exists('config') ? config('token.type') : 'File')) {
                $default = function_exists('config') ? config('token.default') : [];
                // 获取默认Token配置，并连接
                $options = function_exists('config') ? (config('token.' . $default['type']) ?: $default) : [];
            } elseif (empty($options)) {
                $options = function_exists('config') ? config('token') : [];
            }

            self::$handler = self::connect($options);
        }

        return self::$handler;
    }

    /**
     * 判断Token是否可用(check别名)
     * @access public
     * @param string $token   Token标识
     * @param int    $user_id 会员ID
     * @return bool
     */
    public static function has($token, $user_id)
    {
        return self::check($token, $user_id);
    }

    /**
     * 判断Token是否可用
     * @param string $token   Token标识
     * @param int    $user_id 会员ID
     * @return bool
     */
    public static function check($token, $user_id)
    {
        return self::init()->check($token, $user_id);
    }

    /**
     * 读取Token
     * @access public
     * @param string $token   Token标识
     * @param mixed  $default 默认值
     * @return mixed
     */
    public static function get($token, $default = false)
    {
        return self::init()->get($token) ?: $default;
    }

    /**
     * 写入Token
     * @access public
     * @param string   $token   Token标识
     * @param mixed    $user_id 会员ID
     * @param int|null $expire  有效时间 0为永久
     * @return boolean
     */
    public static function set($token, $user_id, $expire = null)
    {
        return self::init()->set($token, $user_id, $expire);
    }

    /**
     * 删除Token(delete别名)
     * @access public
     * @param string $token Token标识
     * @return boolean
     */
    public static function rm($token)
    {
        return self::delete($token);
    }

    /**
     * 删除Token
     * @param string $token 标签名
     * @return bool
     */
    public static function delete($token)
    {
        return self::init()->delete($token);
    }

    /**
     * 清除Token
     * @access public
     * @param int $user_id 会员ID
     * @return boolean
     */
    public static function clear($user_id = null)
    {
        return self::init()->clear($user_id);
    }

}