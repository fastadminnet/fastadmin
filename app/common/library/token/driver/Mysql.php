<?php

namespace app\common\library\token\driver;

use app\common\library\token\Driver;

/**
 * Token操作类 - Mysql驱动
 */
class Mysql extends Driver
{

    protected $options = [
        'table'      => 'user_token',
        'expire'     => 2592000,
        'connection' => [],
    ];

    public function __construct($options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $time = time();
        $tokentime = function_exists('cache') ? cache('tokentime') : null;
        if (!$tokentime || $tokentime < $time - 86400) {
            if (function_exists('cache')) {
                cache('tokentime', $time);
            }
            $this->table()->where('expiretime', '<', $time)->where('expiretime', '>', 0)->delete();
        }
    }

    /**
     * 返回一个全新的查询对象，避免复用 Query 造成 where 条件累积
     * @return \think\db\Query
     */
    protected function table()
    {
        if ($this->options['connection']) {
            return \think\facade\Db::connect($this->options['connection'])->name($this->options['table']);
        }
        return \think\facade\Db::name($this->options['table']);
    }

    public function set($token, $user_id, $expire = null)
    {
        $expiretime = !is_null($expire) && $expire !== 0 ? time() + $expire : 0;
        $token = $this->getEncryptedToken($token);
        $exists = $this->table()->where('token', $token)->count();
        if ($exists) {
            return false;
        }
        $this->table()->insert(['token' => $token, 'user_id' => $user_id, 'createtime' => time(), 'expiretime' => $expiretime]);
        return true;
    }

    public function get($token)
    {
        $data = $this->table()->where('token', $this->getEncryptedToken($token))->find();
        if ($data) {
            if (!$data['expiretime'] || $data['expiretime'] > time()) {
                $data['token'] = $token;
                $data['expires_in'] = $this->getExpiredIn($data['expiretime']);
                return $data;
            } else {
                $this->delete($token);
            }
        }
        return [];
    }

    public function check($token, $user_id)
    {
        $data = $this->get($token);
        return $data && $data['user_id'] == $user_id ? true : false;
    }

    public function delete($token)
    {
        $this->table()->where('token', $this->getEncryptedToken($token))->delete();
        return true;
    }

    public function clear($user_id)
    {
        $this->table()->where('user_id', $user_id)->delete();
        return true;
    }

}
