<?php

namespace fastaddons\addons;

use think\facade\Config;
use think\exception\HttpException;
use think\facade\Event;
use think\helper\Str;

/**
 * 插件执行默认控制器
 * @package fastaddons\addons
 */
class Route
{
    /**
     * 插件执行
     */
    public function execute($addon = null, $controller = null, $action = null)
    {
        $request = app('request');
        // 是否自动转换控制器和操作名
        $convert = Config::get('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';

        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) : 'index';
        $action = $action ? trim(call_user_func($filter, $action)) : 'index';

        Event::trigger('addon_begin', $request);
        if (!empty($addon) && !empty($controller) && !empty($action)) {
            $info = get_addon_info($addon);
            if (!$info) {
                throw new HttpException(404, __('addon %s not found', $addon));
            }
            if (!$info['state']) {
                throw new HttpException(500, __('addon %s is disabled', $addon));
            }

            $routeVars = (array)$request->route();
            if ($routeVars) {
                $request->setRoute(array_diff_key($routeVars, array_flip(['addon', 'controller', 'action'])));
            }

            // 设置当前请求的控制器、操作
            $request->setController($controller);
            $request->setAction($action);

            // 监听addon_module_init
            Event::trigger('addon_module_init', $request);
            // 兼容旧版本行为,即将移除,不建议使用
            Event::trigger('addons_init', $request);

            $class = get_addon_class($addon, 'controller', $controller);
            if (!$class) {
                throw new HttpException(404, __('addon controller %s not found', Str::snake($controller, 1)));
            }

            $instance = app($class);

            $vars = [];
            if (is_callable([$instance, $action])) {
                // 执行操作方法
                $call = [$instance, $action];
            } elseif (is_callable([$instance, '_empty'])) {
                // 空操作
                $call = [$instance, '_empty'];
                $vars = [$action];
            } else {
                // 操作不存在
                throw new HttpException(404, __('addon action %s not found', get_class($instance) . '->' . $action . '()'));
            }

            Event::trigger('addon_action_begin', $call);

            return call_user_func_array($call, $vars);
        } else {
            abort(500, lang('addon can not be empty'));
        }
    }
}