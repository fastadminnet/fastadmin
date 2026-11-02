<?php

/**
 * 队列配置
 */
return [
    // 默认队列连接
    'default'     => env('queue.default', 'sync'),
    // 队列连接列表
    'connections' => [
        // 同步执行（不进入队列，当前请求内直接执行）
        'sync'  => [
            'type' => 'sync',
        ],
        // Redis 队列
        'redis' => [
            'type'       => 'redis',
            // 队列名称
            'queue'      => 'default',
            // Redis 主机
            'host'       => env('queue.host', '127.0.0.1'),
            // Redis 端口
            'port'       => env('queue.port', 6379),
            // Redis 密码
            'password'   => env('queue.password', ''),
            // 使用的数据库编号
            'select'     => env('queue.select', 0),
            // 连接超时时间
            'timeout'    => env('queue.timeout', 0),
            // 是否使用长连接
            'persistent' => env('queue.persistent', false),
        ],
    ],
    // 失败任务存储
    'failed'      => [
        // 存储类型：none 不存储
        'type'  => 'none',
        // 失败任务表名
        'table' => 'failed_jobs',
    ],
];
