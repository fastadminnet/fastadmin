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

        $pinyin = new \Overtrue\Pinyin\Pinyin();
        if ($onlyfirst) {
            $result = $pinyin->abbr($chinese, $delimiter);
            // overtrue/pinyin v6 返回 Collection，转换为字符串
            if (is_object($result) && method_exists($result, 'all')) {
                $result = implode($delimiter, $result->all());
            }
        } else {
            $result = $pinyin->permalink($chinese, $delimiter);
        }
        if ($ucfirst && is_string($result)) {
            $pinyinArr = explode($delimiter, $result);
            $result = implode($delimiter, array_map('ucfirst', $pinyinArr));
        }

        return $result;
    }

}
