<?php

namespace app\common\model;

use think\Model;

class UserGroup extends Model
{

    // 表名
    protected $name = 'user_group';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

}
