<?php

/**
 * 模板视图配置
 */
return [
    // 模板引擎类型
    'type'          => 'think',
    // 模板文件后缀
    'view_suffix'   => 'html',
    // 模板目录分隔符
    'view_depr'     => DIRECTORY_SEPARATOR,
    // 是否开启模板编译缓存
    'tpl_cache'     => false,
    // 模板标签开始符
    'tpl_begin'     => '{',
    // 模板标签结束符
    'tpl_end'       => '}',
    // 默认输出过滤（置空以保留原始 HTML，FastAdmin 依赖此设置输出菜单等）
    'default_filter' => '',
    // 标签库开始符
    'taglib_begin'  => '{',
    // 标签库结束符
    'taglib_end'    => '}',
    // 模板字符串替换
    'tpl_replace_string' => [
        // 公共资源目录
        '__PUBLIC__' => '/',
        // 项目根路径
        '__ROOT__'   => '',
        // CDN 地址
        '__CDN__'    => '',
    ],
];
