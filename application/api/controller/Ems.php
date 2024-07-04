<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems as Emslib;
use app\common\model\User;
use think\Hook;

/**
 * 邮箱验证码接口
 */
class Ems extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 发送验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     */
    public function send()
    {
        $email = $this->request->post("email");
        $event = $this->request->post("event");
        $event = $event ? $event : 'register';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error(__('邮箱格式错误'));
        }
        if (!preg_match("/^[a-z0-9_\-]{3,30}\$/i", $event)) {
            $this->error(__('事件名称错误'));
        }

        //发送前验证码
        if (config('fastadmin.user_api_captcha')) {

            if (!preg_match("/^[a-z0-9]{4,6}\$/i", $captcha)) {
                $this->error(__('验证码格式错误'));
            }

            if (!\think\Validate::is($captcha, 'captcha')) {
                $this->error("验证码不正确");
            }
        }

        $last = Emslib::get($email, $event);
        if ($last && time() - $last['createtime'] < 60) {
            $this->error(__('发送频繁'));
        }

        $ipSendTotal = \app\common\model\Ems::where(['ip' => $this->request->ip()])->whereTime('createtime', '-1 hours')->count();
        if ($ipSendTotal >= 5) {
            $this->error(__('发送频繁'));
        }

        if ($event) {
            $userinfo = User::getByEmail($email);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('已被注册'));
            } elseif (in_array($event, ['changeemail']) && $userinfo) {
                //被占用
                $this->error(__('已被占用'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Emslib::send($email, null, $event);
        if ($ret) {
            $this->success(__('发送成功'));
        } else {
            $this->error(__('发送失败'));
        }
    }

    /**
     * 检测验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function check()
    {
        $email = $this->request->post("email");
        $event = $this->request->post("event");
        $event = $event ? $event : 'register';
        $captcha = $this->request->post("captcha");

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error(__('邮箱格式错误'));
        }
        if (!preg_match("/^[a-z0-9_\-]{3,30}\$/i", $event)) {
            $this->error(__('事件名称错误'));
        }

        if (!preg_match("/^[a-z0-9]{4,6}\$/i", $captcha)) {
            $this->error(__('验证码格式错误'));
        }

        if ($event) {
            $userinfo = User::getByEmail($email);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('已被注册'));
            } elseif (in_array($event, ['changeemail']) && $userinfo) {
                //被占用
                $this->error(__('已被占用'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Emslib::check($email, $captcha, $event);
        if ($ret) {
            $this->success(__('成功'));
        } else {
            $this->error(__('验证码不正确'));
        }
    }
}
