<?php

namespace app\common\model;

use think\Model;

class Version extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 检查版本兼容性
     * @param string $version
     * @return array
     */
    public static function check($version)
    {
        return [
            'current' => '1.0.0',
            'latest' => '1.0.0',
            'upgrade' => false,
        ];
    }
}
