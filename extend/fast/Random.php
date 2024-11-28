<?php

namespace fast;

/**
 * 随机生成类
 */
class Random
{

    /**
     * 生成数字和字母
     *
     * @param int $len 长度
     * @return string
     */
    public static function alnum(int $len = 6): string
    {
        return self::build('alnum', $len);
    }

    /**
     * 仅生成字符
     *
     * @param int $len 长度
     * @return string
     */
    public static function alpha(int $len = 6): string
    {
        return self::build('alpha', $len);
    }

    /**
     * 生成指定长度的随机数字
     *
     * @param int $len 长度
     * @return string
     */
    public static function numeric(int $len = 4): string
    {
        return self::build('numeric', $len);
    }

    /**
     * 生成指定长度的无0随机数字
     *
     * @param int $len 长度
     * @return string
     */
    public static function nozero(int $len = 4): string
    {
        return self::build('nozero', $len);
    }

    /**
     * 能用的随机数生成
     * @param string $type 类型 alpha/alnum/numeric/nozero/unique/md5/encrypt/sha1
     * @param int    $len  长度
     * @return string
     */
    public static function build(string $type = 'alnum', int $len = 8): string
    {
        switch ($type) {
            case 'alpha':
            case 'alnum':
            case 'numeric':
            case 'nozero':
                switch ($type) {
                    case 'alpha':
                        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        break;
                    case 'alnum':
                        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        break;
                    case 'numeric':
                        $pool = '0123456789';
                        break;
                    case 'nozero':
                        $pool = '123456789';
                        break;
                }
                return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
            case 'unique':
            case 'md5':
                return md5(uniqid(mt_rand()));
            case 'encrypt':
            case 'sha1':
                return sha1(uniqid(mt_rand(), true));
        }
    }

    /**
     * 获取全球唯一标识
     * @return string
     */
    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
