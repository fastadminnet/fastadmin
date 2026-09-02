<?php

namespace app\common\library;

use fast\Random;
use think\Db;
use think\Hook;

/**
 * 短信验证码类
 */
class Sms
{

    /**
     * 验证码有效时长
     * @var int
     */
    protected static $expire = 120;

    /**
     * 最大允许检测的次数
     * @var int
     */
    protected static $maxCheckNums = 10;

    /**
     * 获取最后一次手机发送的数据
     *
     * @param int    $mobile 手机号
     * @param string $event  事件
     * @return  Sms
     */
    public static function get($mobile, $event = 'default')
    {
        $sms = \app\common\model\Sms::where(['mobile' => $mobile, 'event' => $event])
            ->order('id', 'DESC')
            ->find();
        Hook::listen('sms_get', $sms, null, true);
        return $sms ?: null;
    }

    /**
     * 发送验证码
     *
     * @param int    $mobile 手机号
     * @param int    $code   验证码,为空时将自动生成4位数字
     * @param string $event  事件
     * @return  boolean
     */
    public static function send($mobile, $code = null, $event = 'default')
    {
        $code = is_null($code) ? Random::numeric(config('fastadmin.sms_captcha_length') ?: 6) : $code;
        $time = time();
        $ip = request()->ip();
        $sms = \app\common\model\Sms::create(['event' => $event, 'mobile' => $mobile, 'code' => $code, 'ip' => $ip, 'createtime' => $time]);
        $result = Hook::listen('sms_send', $sms, null, true);
        if (!$result) {
            $sms->delete();
            return false;
        }
        return true;
    }

    /**
     * 发送通知
     *
     * @param mixed  $mobile   手机号,多个以,分隔
     * @param string $msg      消息内容
     * @param string $template 消息模板
     * @return  boolean
     */
    public static function notice($mobile, $msg = '', $template = null)
    {
        $params = [
            'mobile'   => $mobile,
            'msg'      => $msg,
            'template' => $template
        ];
        $result = Hook::listen('sms_notice', $params, null, true);
        return (bool)$result;
    }

    /**
     * 校验验证码
     *
     * @param int     $mobile 手机号
     * @param int     $code   验证码
     * @param string  $event  事件
     * @param boolean $flush  验证成功是否删除
     * @return  boolean
     */
    public static function check($mobile, $code, $event = 'default', $flush = false)
    {
        if (empty($mobile) || empty($code)) {
            return false;
        }

        $expireTime = time() - self::$expire;

        // 开启事务
        Db::startTrans();

        try {
            // 使用行锁查询最新的验证码记录
            $sms = \app\common\model\Sms::where([
                'mobile' => $mobile,
                'event'  => $event
            ])
                ->order('id', 'desc')
                ->lock(true)  // 行锁
                ->find();

            // 验证码记录不存在
            if (!$sms) {
                Db::rollback();
                return false;
            }

            // 验证码已过期
            if ($sms['createtime'] <= $expireTime) {
                self::flush($mobile, $event);
                Db::rollback();
                return false;
            }

            // 验证次数已超限
            if ($sms['times'] >= self::$maxCheckNums) {
                Db::rollback();
                return false;
            }


            // 先增加验证次数（无论验证成功失败都计数）
            Db::name('sms')
                ->where('id', $sms['id'])
                ->setInc('times');

            // 验证码不匹配
            if ($code != $sms['code']) {
                Db::commit();  // 提交次数增加
                return false;
            }

            // 删除验证码
            if ($flush) {
                self::flush($mobile, $event);
            }

            // 验证成功，提交事务
            Db::commit();

            // 触发验证成功钩子
            Hook::listen('sms_check', $sms);

            return true;

        } catch (\Exception $e) {
            Db::rollback();
            // 记录错误日志
            \think\Log::record('SMS验证异常: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 清空指定手机号验证码
     *
     * @param int    $mobile 手机号
     * @param string $event  事件
     * @return  boolean
     */
    public static function flush($mobile, $event = 'default')
    {
        \app\common\model\Sms::where(['mobile' => $mobile, 'event' => $event])
            ->delete();
        Hook::listen('sms_flush');
        return true;
    }
}
