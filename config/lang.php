<?php

/**
 * 语言包配置
 */
return [
    // 默认语言
    'default_lang'        => 'zh-cn',
    // 允许使用的语言列表
    'allow_lang_list'     => ['zh-cn', 'en-us'],
    // 是否使用 Cookie 记住语言选择
    'use_cookie'          => true,
    // 语言包扩展列表
    'extend_list'         => [],
    // 浏览器语言到项目语言的映射
    'accept_language'     => ['zh-hans-cn' => 'zh-cn', 'zh-cn' => 'zh-cn', 'en-us' => 'en-us'],
    // 是否按模块分组加载语言包
    'allow_group'         => false,
    // 语言检测使用的参数名
    'detect_var'          => 'lang',
];
