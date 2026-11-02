<?php

/**
 * 控制台命令配置
 */
return [
    // 注册到 think 命令的指令类
    'commands' => [
        // CRUD 代码生成
        'app\admin\command\Crud',
        // 后台菜单生成/更新
        'app\admin\command\Menu',
        // 安装程序
        'app\admin\command\Install',
        // 资源压缩
        'app\admin\command\Min',
        // 插件管理
        'app\admin\command\Addon',
        // API 文档生成
        'app\admin\command\Api',
    ],
];
