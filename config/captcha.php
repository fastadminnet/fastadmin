<?php

/**
 * 验证码配置
 */
return [
    // 验证码字符个数
    'length'   => 4,
    // 可用字符集合（去除了易混淆字符）
    'codeSet'  => '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY',
    // 验证码有效期（秒）
    'expire'   => 1800,
    // 是否使用中文验证码
    'useZh'    => false,
    // 是否使用数学运算验证码
    'math'     => false,
    // 是否使用背景图片
    'useImgBg' => false,
    // 验证码字体大小
    'fontSize' => 18,
    // 是否使用混淆曲线
    'useCurve' => false,
    // 是否添加噪点
    'useNoise' => true,
    // 验证码字体文件（留空使用默认字体）
    'fontttf'  => '',
    // 背景颜色（RGB）
    'bg'       => [243, 251, 254],
    // 验证码图片高度
    'imageH'   => 40,
    // 验证码图片宽度
    'imageW'   => 130,
    // 图片透明度（0 表示不透明）
    'alpha'    => 0,
    // 是否使用 API 模式
    'api'      => false,
    // 验证成功后是否重置
    'reset'    => true,
];
