<?php

/**
 * 文件存储配置
 */
return [
    // 默认文件磁盘
    'default' => 'local',
    // 磁盘列表
    'disks'   => [
        // 本地私有存储（runtime 目录）
        'local' => [
            'type' => 'local',
            'root' => runtime_path() . 'storage',
        ],
        // 本地公开存储（public/uploads，可直接访问）
        'public' => [
            'type'       => 'local',
            'root'       => public_path() . 'uploads',
            // 对应的访问 URL 前缀
            'url'        => '/uploads',
            // 可见性：public 表示可公开访问
            'visibility' => 'public',
        ],
    ],
];
