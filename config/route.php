<?php

/**
 * 路由配置
 */
return [
    // PATH_INFO 分隔符
    'pathinfo_depr'       => '/',
    // 是否延迟解析路由
    'url_lazy_route'      => false,
    // 是否必须使用路由（关闭时允许默认控制器/方法访问）
    'url_route_must'      => false,
    // 路由是否完整匹配
    'url_complete_match'  => false,
    // URL 伪静态后缀（本项目目标为无后缀）
    'url_html_suffix'     => '',
    // URL 是否包含公共参数
    'url_common_param'    => false,
    // URL 是否自动转换（控制器/方法名转为小写+下划线）
    'url_convert'         => true,
    // 是否合并路由规则
    'route_rule_merge'    => false,
    // 是否缓存路由检查结果
    'route_check_cache'   => false,
    // 默认路由变量匹配规则
    'default_route_pattern' => '[\w\.]+',
    // 控制器后缀：开启后控制器类名自动追加 Controller
    'controller_suffix'   => false,
    // 控制器所在目录层
    'controller_layer'    => 'controller',
    // 空控制器名称
    'empty_controller'    => 'Error',
    // 操作方法后缀
    'action_suffix'       => '',
    // 请求方法伪装参数名（如 _method=PUT）
    'var_method'          => '_method',
    // AJAX 请求标识参数名
    'var_ajax'            => '_ajax',
    // PJAX 请求标识参数名
    'var_pjax'            => '_pjax',
    // PATH_INFO 参数名
    'var_pathinfo'        => 's',
    // 获取 PATH_INFO 的服务器变量优先级
    'pathinfo_fetch'      => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
];
