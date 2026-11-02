<?php

/**
 * 应用配置
 *
 * 说明：本文件控制应用的基础运行行为，修改后建议清理运行缓存。
 */
return [
    // 应用命名空间（多应用模式下由框架自动设置为 app\{appName}，通常无需配置）
    // 'app_namespace' => 'app',
    // 调试模式：开启后显示详细错误信息，生产环境务必关闭
    'app_debug'              => env('app_debug', false),
    // 页面 Trace：开启后在页面底部输出调试信息
    'app_trace'              => env('app_trace', false),
    // 应用状态标识（用于区分开发/测试/生产等环境）
    'app_status'             => '',
    // 应用映射：请求的应用名映射到实际应用目录
    'app_map'                => [],
    // 域名绑定：将指定域名绑定到对应应用
    'domain_bind'            => [],
    // 禁止直接访问的应用列表（common 为公共应用）
    'deny_app_list'          => ['common'],
    // 默认应用：未匹配到应用时使用
    'default_app'            => 'index',
    // 自动绑定模块：根据域名自动绑定应用
    'auto_bind_module'       => false,
    // 默认时区
    'default_timezone'       => 'Asia/Shanghai',
    // 普通请求默认响应类型
    'default_return_type'    => 'html',
    // AJAX 请求默认响应类型
    'default_ajax_return'    => 'json',
    // 默认 JSONP 回调函数名
    'default_jsonp_handler'  => 'jsonpReturn',
    // JSONP 回调参数名
    'var_jsonp_handler'      => 'callback',
    // 语言切换开关：开启后允许通过参数切换语言
    'lang_switch_on'         => false,
    // 默认输入过滤规则（FastAdmin 置空，由各控制器自行过滤）
    'default_filter'         => '',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 允许的语言列表
    'allow_lang_list'        => ['zh-cn', 'en'],
    // 类名后缀：开启后控制器/模型类名自动追加 Controller/Model
    'class_suffix'           => false,
    // 控制器后缀：开启后控制器类名自动追加 Controller
    'controller_suffix'      => false,
    // 获取客户端 IP 时使用的服务器变量
    'http_agent_ip'          => 'REMOTE_ADDR',
    // 控制器自动搜索：兼容 TP5 的子目录控制器访问方式
    'controller_auto_search' => true,
    // URL 伪静态后缀（本项目目标为无后缀）
    'url_html_suffix'        => '',
    // URL 是否包含公共参数
    'url_common_param'       => false,
    // URL 参数类型：0 普通模式，1 路径参数模式
    'url_param_type'         => 0,
    // 路由是否完整匹配
    'route_complete_match'   => false,
    // 是否必须使用路由（关闭时允许默认控制器/方法访问）
    'url_route_must'         => false,
    // 是否开启域名部署
    'url_domain_deploy'      => false,
    // URL 是否自动转换（控制器/方法名转为小写+下划线）
    'url_convert'            => true,
    // 控制器所在目录层
    'url_controller_layer'   => 'controller',
    // 请求方法伪装参数名（如 _method=PUT）
    'var_method'             => '_method',
    // AJAX 请求标识参数名
    'var_ajax'               => '_ajax',
    // PJAX 请求标识参数名
    'var_pjax'               => '_pjax',
    // 请求缓存开关
    'request_cache'          => false,
    // 请求缓存有效期（null 表示使用默认值）
    'request_cache_expire'   => null,
    // 操作成功提示页模板
    'dispatch_success_tmpl'  => app_path() . 'common/view/tpl/dispatch_jump.tpl',
    // 操作失败提示页模板
    'dispatch_error_tmpl'    => app_path() . 'common/view/tpl/dispatch_jump.tpl',
    // 异常页面模板
    'exception_tmpl'         => app_path() . 'common/view/tpl/think_exception.tpl',
    // HTTP 异常模板映射（状态码 => 模板路径）
    'http_exception_template' => [],
    // 默认错误提示文字
    'error_message'          => '页面错误！请稍后再试~',
    // 是否显示具体错误信息（生产环境建议关闭）
    'show_error_msg'         => false,
    // 异常处理类
    'exception_handle'       => \app\common\library\ExceptionHandle::class,
    // 是否将路由信息保留到请求对象
    'with_route'             => true,
];
