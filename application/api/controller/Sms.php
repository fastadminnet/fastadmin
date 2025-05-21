<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\Hook;

/**
 * 手机短信接口
 */
class Sms extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
        if (!$this->request->isPost()) {
            $this->error(__('请求错误'));
        }
    }

    /**
     * 发送验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     * @ApiParams (name="type", type="string", required=false, description="验证类型，auto为自动验证，system为系统验证码")
     * @ApiParams (name="source_id", type="string", required=false, description="来源ID")
     */
    public function send()
    {
        $mobile = $this->request->post("mobile");
        $captcha = $this->request->post("captcha");
        $event = $this->request->post("event");
        $event = $event ?: 'register';
        $type = $this->request->post("type", 'auto');
        $source_id = $this->request->post("source_id", '');

        //发送前验证码
        if (config('fastadmin.user_api_captcha')) {
            $valid = $type === 'auto' ? \think\Validate::is($captcha, 'captcha') : captcha_check($captcha, $source_id);
            if (!$valid) {
                $this->error("验证码不正确");
            }
        }

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机号不正确'));
        }
        if (!preg_match("/^[a-z0-9_\-]{3,30}\$/i", $event)) {
            $this->error(__('事件名称错误'));
        }
        $last = Smslib::get($mobile, $event);
        if ($last && time() - $last['createtime'] < 60) {
            $this->error(__('发送频繁'));
        }
        $ipSendTotal = \app\common\model\Sms::where(['ip' => $this->request->ip()])->whereTime('createtime', '-1 hours')->count();
        if ($ipSendTotal >= 5) {
            $this->error(__('发送频繁'));
        }
        if ($event) {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('已被注册'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('已被占用'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        if (!Hook::get('sms_send')) {
            $this->error(__('请在后台插件管理安装短信验证插件'));
        }
        $ret = Smslib::send($mobile, null, $event);
        if ($ret) {
            $this->success(__('发送成功'));
        } else {
            $this->error(__('发送失败，请检查短信配置是否正确'));
        }
    }

    /**
     * 检测验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function check()
    {
        $mobile = $this->request->post("mobile");
        $event = $this->request->post("event");
        $event = $event ?: 'register';
        $captcha = $this->request->post("captcha");

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机号不正确'));
        }
        if (!preg_match("/^[a-z0-9_\-]{3,30}\$/i", $event)) {
            $this->error(__('事件名称错误'));
        }
        if (!preg_match("/^[a-z0-9]{4,6}\$/i", $captcha)) {
            $this->error(__('验证码格式错误'));
        }
        
        if ($event) {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('已被注册'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('已被占用'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Smslib::check($mobile, $captcha, $event);
        if ($ret) {
            $this->success(__('成功'));
        } else {
            $this->error(__('验证码不正确'));
        }
    }
}
