<?php

/**
 * 缓存配置
 */
return [
    // 默认缓存驱动
    'default' => 'file',
    // 缓存驱动列表
    'stores'  => [
        'file' => [
            // 驱动类型：文件缓存
            'type'   => 'file',
            // 缓存文件存放目录
            'path'   => runtime_path() . 'cache' . DIRECTORY_SEPARATOR,
            // 缓存键前缀
            'prefix' => '',
            // 默认过期时间（秒，0 表示永久）
            'expire' => 0,
        ],
    ],
];
