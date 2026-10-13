<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Random;

/**
 * Token接口
 */
class Token extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 检测Token是否过期
     *
     */
    public function check()
    {
        $token = $this->auth->getToken();
        $tokenInfo = \app\common\library\Token::get($token);
        $this->success('', ['token' => $tokenInfo['token'], 'expires_in' => $tokenInfo['expires_in']]);
    }

    /**
     * 刷新Token
     *
     */
    public function refresh()
    {
        //删除源Token
        $token = $this->auth->getToken();
        \app\common\library\Token::delete($token);
        //创建新Token
        $attempts = 0;
        do {
            $token = Random::uuid();
            $result = \app\common\library\Token::set($token, $this->auth->id, 2592000);
        } while (!$result && ++$attempts < 5);
        if (!$result) {
            $this->error('Failed to generate token');
        }
        $tokenInfo = \app\common\library\Token::get($token);
        $this->success('', ['token' => $tokenInfo['token'], 'expires_in' => $tokenInfo['expires_in']]);
    }
}
