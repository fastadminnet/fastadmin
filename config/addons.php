<?php

// 本文件由插件管理自动生成，请勿手动修改。
return [
    // 是否自动加载所有插件
    'autoload' => false,
    // 钩子 => 插件列表
    'hooks'    => [
        'demo_hook' => [
            'demo',
        ],
    ],
    // 插件路由规则（URL => 插件/控制器/方法）
    'route'    => [
        '/example$'          => 'example/index/index',
        '/example/d/[:name]'  => 'example/demo/index',
        '/example/d1/[:name]' => 'example/demo/demo1',
        '/example/d2/[:name]' => 'example/demo/demo2',
    ],
    // 钩子优先级
    'priority' => [],
    // 插件绑定域名（留空表示不限制）
    'domain'   => '',
];
