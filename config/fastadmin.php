<?php

/**
 * FastAdmin 业务配置
 */
return [
    // 是否启用会员中心
    'usercenter'            => true,
    // 会员注册验证码类型：text 文字验证码、click 点击验证码、disable 关闭
    'user_register_captcha' => 'text',
    // 后台登录是否开启验证码
    'login_captcha'         => true,
    // 登录失败是否限制重试次数
    'login_failure_retry'   => true,
    // 是否限制同一账号只能在一处登录
    'login_unique'          => false,
    // 是否校验登录 IP 变化
    'loginip_check'         => true,
    // 后台登录页背景图地址（留空使用默认）
    'login_background'      => '',
    // 短信验证码长度
    'sms_captcha_length'    => 6,
    // 邮件验证码长度
    'ems_captcha_length'    => 6,
    // 是否启用多级导航
    'multiplenav'           => false,
    // 是否启用多标签页
    'multipletab'           => true,
    // 是否始终显示子菜单
    'show_submenu'          => false,
    // 后台默认皮肤（留空使用默认）
    'adminskin'             => '',
    // 是否显示面包屑导航
    'breadcrumb'            => false,
    // 是否允许未知来源的请求（安全选项）
    'unknownsources'        => false,
    // 插件启用/禁用时是否备份全局文件
    'backup_global_files'   => true,
    // 是否自动记录后台操作日志
    'auto_record_log'       => true,
    // 插件是否使用纯净模式（文件从插件目录复制/回收）
    'addon_pure_mode'       => true,
    // 允许跨域请求的域名列表（逗号分隔，* 表示全部）
    'cors_request_domain'   => 'localhost,127.0.0.1',
    // FastAdmin 版本号
    'version'               => '1.6.8.20261019',
    // FastAdmin 官方 API 地址
    'api_url'               => 'https://api.fastadmin.net',
];
