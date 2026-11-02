<?php

namespace app\admin\model;

use think\Model;

class Admin extends Model
{
    // Auto write timestamp field
    protected $autoWriteTimestamp = 'int';
    // Timestamp field names
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $hidden = [
        'password',
        'salt'
    ];

    /**
     * Clear token when username or password changed.
     */
    public static function onBeforeWrite($row)
    {
        $changed = $row->getChangedData();
        if (isset($changed['username']) || isset($changed['password']) || isset($changed['salt'])) {
            $row->token = '';
        }
    }
}
