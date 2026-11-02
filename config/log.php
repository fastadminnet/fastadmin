<?php

/**
 * 日志配置
 */
return [
    // 默认日志通道
    'default'  => 'file',
    // 日志通道列表
    'channels' => [
        'file' => [
            // 驱动类型：文件日志
            'type'       => 'File',
            // 日志文件目录
            'path'       => runtime_path() . 'log' . DIRECTORY_SEPARATOR,
            // 需要记录的日志级别（空数组表示全部）
            'level'      => [],
            // 需要独立分文件记录的级别
            'apart_level' => [],
            // 最多保留的日志文件数量
            'max_files'  => 30,
            // 是否以 JSON 格式记录
            'json'       => false,
            // 日志处理器（可自定义）
            'processor'  => null,
            // 是否关闭日志
            'close'      => false,
            // 日志格式
            'format'     => '[%s][%s] %s',
        ],
    ],
];
