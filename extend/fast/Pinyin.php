<?php

namespace fast;

/**
 * 中文转拼音类
 */
class Pinyin
{

    /**
     * 获取文字的拼音
     * @param string  $chinese   中文汉字
     * @param boolean $onlyfirst 是否只返回拼音首字母
     * @param string  $delimiter 分隔符
     * @param bool    $ucfirst   是否首字母大写
     * @return string
     */
    public static function get($chinese, $onlyfirst = false, $delimiter = '', $ucfirst = false)
    {
        // PHP8.1 起向内部函数传入 null 会触发弃用告警，提前归一化
        $chinese = (string)$chinese;
        $pinyin = new \Overtrue\Pinyin\Pinyin();
        if ($onlyfirst) {
            $result = $pinyin->abbr($chinese, $delimiter);
        } else {
            $result = $pinyin->permalink($chinese, $delimiter);
        }
        if ($ucfirst) {
            $pinyinArr = explode($delimiter, $result);
            $result = implode($delimiter, array_map('ucfirst', $pinyinArr));
        }

        return $result;
    }

}
