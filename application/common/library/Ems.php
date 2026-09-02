<?php

namespace app\common\library;

use fast\Random;
use think\Db;
use think\Hook;

/**
 * 邮箱验证码类
 */
class Ems
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
     * 获取最后一次邮箱发送的数据
     *
     * @param int    $email 邮箱
     * @param string $event 事件
     * @return  Ems|null
     */
    public static function get($email, $event = 'default')
    {
        $ems = \app\common\model\Ems::where(['email' => $email, 'event' => $event])
            ->order('id', 'DESC')
            ->find();
        Hook::listen('ems_get', $ems, null, true);
        return $ems ?: null;
    }

    /**
     * 发送验证码
     *
     * @param int    $email 邮箱
     * @param int    $code  验证码,为空时将自动生成4位数字
     * @param string $event 事件
     * @return  boolean
     */
    public static function send($email, $code = null, $event = 'default')
    {
        $code = is_null($code) ? Random::numeric(config('fastadmin.ems_captcha_length') ?: 6) : $code;
        $time = time();
        $ip = request()->ip();
        $ems = \app\common\model\Ems::create(['event' => $event, 'email' => $email, 'code' => $code, 'ip' => $ip, 'createtime' => $time]);
        if (!Hook::get('ems_send')) {
            //采用框架默认的邮件推送
            Hook::add('ems_send', function ($params) {
                $obj = new Email();
                $result = $obj
                    ->to($params->email)
                    ->subject('请查收你的验证码！')
                    ->message("你的验证码是：" . $params->code . "，" . ceil(self::$expire / 60) . "分钟内有效。")
                    ->send();
                return $result;
            });
        }
        $result = Hook::listen('ems_send', $ems, null, true);
        if (!$result) {
            $ems->delete();
            return false;
        }
        return true;
    }

    /**
     * 发送通知
     *
     * @param mixed  $email    邮箱,多个以,分隔
     * @param string $msg      消息内容
     * @param string $template 消息模板
     * @return  boolean
     */
    public static function notice($email, $msg = '', $template = null)
    {
        $params = [
            'email'    => $email,
            'msg'      => $msg,
            'template' => $template
        ];
        if (!Hook::get('ems_notice')) {
            //采用框架默认的邮件推送
            Hook::add('ems_notice', function ($params) {
                $subject = '你收到一封新的邮件！';
                $content = $params['msg'];
                $email = new Email();
                $result = $email->to($params['email'])
                    ->subject($subject)
                    ->message($content)
                    ->send();
                return $result;
            });
        }
        $result = Hook::listen('ems_notice', $params, null, true);
        return (bool)$result;
    }

    /**
     * 校验验证码
     *
     * @param int    $email 邮箱
     * @param int    $code  验证码
     * @param string $event 事件
     * @param boolean $flush 验证成功是否删除
     * @return  boolean
     */
    public static function check($email, $code, $event = 'default', $flush = false)
    {
        if (empty($email) || empty($code)) {
            return false;
        }

        $expireTime = time() - self::$expire;

        // 开启事务
        Db::startTrans();

        try {
            // 使用行锁查询最新的验证码记录
            $ems = \app\common\model\Ems::where([
                'email' => $email,
                'event'  => $event
            ])
                ->order('id', 'desc')
                ->lock(true)  // 行锁
                ->find();

            // 验证码记录不存在
            if (!$ems) {
                Db::rollback();
                return false;
            }

            // 验证码已过期
            if ($ems['createtime'] <= $expireTime) {
                self::flush($email, $event);
                Db::rollback();
                return false;
            }

            // 验证次数已超限
            if ($ems['times'] >= self::$maxCheckNums) {
                Db::rollback();
                return false;
            }

            // 先增加验证次数（无论验证成功失败都计数）
            Db::name('ems')
                ->where('id', $ems['id'])
                ->setInc('times');

            // 验证码不匹配
            if ($code != $ems['code']) {
                Db::commit();  // 提交次数增加
                return false;
            }

            // 删除验证码
            if ($flush) {
                self::flush($email, $event);
            }

            // 验证成功，提交事务
            Db::commit();

            // 触发验证成功钩子
            Hook::listen('ems_check', $ems);

            return true;

        } catch (\Exception $e) {
            Db::rollback();
            // 记录错误日志
            \think\Log::record('EMS验证异常: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 清空指定邮箱验证码
     *
     * @param int    $email 邮箱
     * @param string $event 事件
     * @return  boolean
     */
    public static function flush($email, $event = 'default')
    {
        \app\common\model\Ems::where(['email' => $email, 'event' => $event])
            ->delete();
        Hook::listen('ems_flush');
        return true;
    }
}
