<?php

/**
 * Session 配置
 */
return [
    // Session 名称（Cookie 名）
    'name'           => env('session.name', 'PHPSESSID'),
    // 存储驱动：file 文件、redis 等
    'type'           => env('session.type', 'file'),
    // 存储连接（对应缓存/Redis 配置，null 表示默认）
    'store'          => null,
    // 过期时间（秒）
    'expire'         => 86400,
    // Session 前缀
    'prefix'         => 'think',
    // 是否自动开启 Session
    'auto_start'     => true,
    // 序列化选项
    'serialize'      => [],
    // 传递 Session ID 的变量名（留空使用 Cookie）
    'var_session_id' => '',
];
