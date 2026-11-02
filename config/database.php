<?php

/**
 * 数据库配置
 *
 * 说明：实际连接参数优先读取根目录 .env，未配置时使用此处的默认值。
 */
return [
    // 默认连接名（对应下方 connections 的键）
    'default'         => env('database.driver', 'mysql'),
    // 时间查询规则
    'time_query_rule' => [],
    // 是否自动写入时间戳（由模型单独控制）
    'auto_timestamp'  => false,
    // 日期时间格式（false 表示使用默认格式）
    'datetime_format' => false,
    // 结果集类型：array 数组、collection 集合
    'resultset_type'  => 'array',
    // 是否记录 SQL 执行计划
    'sql_explain'     => false,
    // 数据库连接列表
    'connections'     => [
        'mysql' => [
            // 数据库类型
            'type'            => 'mysql',
            // 数据库主机
            'hostname'        => env('database.hostname', '127.0.0.1'),
            // 数据库名
            'database'        => env('database.database', 'fastadmin'),
            // 数据库用户名
            'username'        => env('database.username', 'root'),
            // 数据库密码
            'password'        => env('database.password', ''),
            // 数据库端口
            'hostport'        => env('database.hostport', '3306'),
            // 自定义 DSN（配置后优先使用）
            'dsn'             => '',
            // PDO 连接参数
            'params'          => [],
            // 数据库字符集
            'charset'         => env('database.charset', 'utf8mb4'),
            // 数据表前缀
            'prefix'          => env('database.prefix', 'fa_'),
            // 数据库调试模式
            'debug'           => env('database.debug', false),
            // 部署方式：0 单机、1 分布式
            'deploy'          => 0,
            // 是否读写分离
            'rw_separate'     => false,
            // 主库数量
            'master_num'      => 1,
            // 指定从库编号（留空表示自动选择）
            'slave_no'        => '',
            // 是否严格检查字段是否存在
            'fields_strict'   => true,
            // 是否自动断线重连
            'break_reconnect' => false,
            // 是否记录 SQL 日志（跟随调试模式）
            'trigger_sql'     => env('app_debug', false),
            // 是否缓存数据表字段
            'fields_cache'    => false,
        ],
    ],
];
