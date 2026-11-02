<?php

namespace app\common\model;

use think\Model;

class Attachment extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public static function getMimetypeList()
    {
        return [
            'image/*' => 'Image',
            'video/*' => 'Video',
            'audio/*' => 'Audio',
            'application/*' => 'Application',
        ];
    }

    public static function getCategoryList()
    {
        return [
            'category1' => 'Category1',
            'category2' => 'Category2',
            'custom' => 'Custom',
        ];
    }
}
