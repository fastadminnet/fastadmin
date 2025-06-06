<?php

namespace app\common\model;

use think\Db;
use think\Model;

/**
 * 会员模型
 * @method static mixed getByUsername($str) 通过用户名查询用户
 * @method static mixed getByNickname($str) 通过昵称查询用户
 * @method static mixed getByMobile($str) 通过手机查询用户
 * @method static mixed getByEmail($str) 通过邮箱查询用户
 */
class User extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'url',
    ];

    /**
     * 获取个人URL
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getUrlAttr($value, $data)
    {
        return str_replace("{uid}", $data['id'], config('fastadmin.user_home_url') ?: "/u/{uid}");
    }

    /**
     * 获取头像
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getAvatarAttr($value, $data)
    {
        if (!$value) {
            $value = config('fastadmin.user_letter_avatar') ? letter_avatar($data['nickname']) : (config('fastadmin.user_default_avatar') ?: '/assets/img/avatar.png');
        }
        return cdnurl($value, true);
    }

    /**
     * 获取会员的组别
     */
    public function getGroupAttr($value, $data)
    {
        return UserGroup::get($data['group_id']);
    }

    /**
     * 获取验证字段数组值
     * @param string $value
     * @param array  $data
     * @return  object
     */
    public function getVerificationAttr($value, $data)
    {
        $value = array_filter((array)json_decode($value, true));
        $value = array_merge(['email' => 0, 'mobile' => 0], $value);
        return (object)$value;
    }

    /**
     * 设置验证字段
     * @param mixed $value
     * @return string
     */
    public function setVerificationAttr($value)
    {
        $value = is_object($value) || is_array($value) ? json_encode($value) : $value;
        return $value;
    }

    /**
     * 判断指定字段的值是否存在
     * @param string $field 字段名
     * @param string $value 字段值
     */
    public static function checkExists($field, $value)
    {
        return self::lock(true)->where($field, $value)->find();
    }

    /**
     * 变更会员余额
     * @param int    $money   余额
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function money($money, $user_id, $memo)
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $money != 0) {
                $before = $user->money;
                //$after = $user->money + $money;
                $after = function_exists('bcadd') ? bcadd($user->money, $money, 2) : $user->money + $money;
                //更新会员信息
                $user->save(['money' => $after]);
                //写入日志
                MoneyLog::create(['user_id' => $user_id, 'money' => $money, 'before' => $before, 'after' => $after, 'memo' => $memo]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 变更会员积分
     * @param int    $score   积分
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function score($score, $user_id, $memo)
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $score != 0) {
                $before = $user->score;
                $after = $user->score + $score;
                $data = ['score' => $after];
                $levelrule = config('fastadmin.user_level_rule') ?: '';
                if (in_array($levelrule, ['auto', 'up'])) {
                    $level = self::nextlevel($after);
                    if ($levelrule == 'auto' || $level > $user['level']) {
                        $data['level'] = $level;
                    }
                }
                //更新会员信息
                $user->save($data);
                //写入日志
                ScoreLog::create(['user_id' => $user_id, 'score' => $score, 'before' => $before, 'after' => $after, 'memo' => $memo]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 根据积分获取等级
     * @param int $score 积分
     * @return int
     */
    public static function nextlevel($score = 0)
    {
        $levelDict = config('fastadmin.user_level_dict') ?: [];
        $level = 1;
        foreach ($levelDict as $key => $value) {
            if ($score >= $value) {
                $level = $key;
            }
        }
        return $level;
    }
}
