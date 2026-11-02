<?php

namespace app\admin\model;

use fast\Tree;
use think\Model;

class UserRule extends Model
{
    // Table name
    protected $name = 'user_rule';
    // Enable auto write timestamp
    protected $autoWriteTimestamp = 'int';
    // Timestamp field names
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // Append attributes
    protected $append = [
        'status_text'
    ];

    public static function onAfterInsert($row)
    {
        if (!$row['weigh']) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        }
    }

    public function getTitleAttr($value, $data)
    {
        return __($value);
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : $data['status'];
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public static function getTreeList($selected = [])
    {
        $ruleList = self::where('status', 'normal')->order('weigh desc,id desc')->select()->toArray();
        $nodeList = [];
        Tree::instance()->init($ruleList);
        $ruleList = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'name');
        $hasChildrens = [];
        foreach ($ruleList as $k => $v) {
            if ($v['haschild'])
                $hasChildrens[] = $v['id'];
        }
        foreach ($ruleList as $k => $v) {
            $state = array('selected' => in_array($v['id'], $selected) && !in_array($v['id'], $hasChildrens));
            $nodeList[] = array('id' => $v['id'], 'parent' => $v['pid'] ? $v['pid'] : '#', 'text' => __($v['title']), 'type' => 'menu', 'state' => $state);
        }
        return $nodeList;
    }
}
