<?php

namespace app\admin\model;

use think\facade\Cache;
use think\Model;

class AuthRule extends Model
{
    // Enable auto write timestamp
    protected $autoWriteTimestamp = 'int';
    // Timestamp field names
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // Auto complete fields
    protected $insert = ['py', 'pinyin'];
    protected $update = ['py', 'pinyin'];
    // Pinyin instance
    protected static $pinyin = null;

    /**
     * Get the shared pinyin instance.
     * @return \Overtrue\Pinyin\Pinyin
     */
    protected static function pinyin()
    {
        if (self::$pinyin === null) {
            self::$pinyin = new \Overtrue\Pinyin\Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
        }
        return self::$pinyin;
    }

    public static function onBeforeWrite($row)
    {
        self::pinyin();
        if (isset($_POST['row']) && is_array($_POST['row']) && isset($_POST['row']['condition'])) {
            $originRow = $_POST['row'];
            $row['condition'] = $originRow['condition'] ?? '';
        }
    }

    public static function onAfterWrite($row)
    {
        Cache::delete('__menu__');
    }

    public function getTitleAttr($value, $data)
    {
        return __($value);
    }

    public function getMenutypeList()
    {
        return ['addtabs' => __('Addtabs'), 'dialog' => __('Dialog'), 'ajax' => __('Ajax'), 'blank' => __('Blank')];
    }

    public function setPyAttr($value, $data)
    {
        if (isset($data['title']) && $data['title']) {
            return self::pinyin()->abbr(__($data['title']));
        }
        return '';
    }

    public function setPinyinAttr($value, $data)
    {
        if (isset($data['title']) && $data['title']) {
            return self::pinyin()->permalink(__($data['title']), '');
        }
        return '';
    }
}
