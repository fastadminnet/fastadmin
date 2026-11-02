<?php

namespace fastaddons;

use app\common\library\LegacyLoader;
use think\facade\Config;
use think\facade\Event;
use think\facade\Log;
use think\facade\Route;

/**
 * 插件系统启动引导
 * 在应用初始化后显式调用，避免 Composer files 自动加载在框架启动前执行
 * @package fastaddons
 */
class Bootstrap
{
    protected static $registered = false;

    /**
     * 注册插件系统
     * @return void
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        // 显式加载 helper（不使用 Composer files autoload）
        require_once __DIR__ . '/common.php';

        // 注册默认插件路由
        self::registerDefaultRoute();

        $config = (array)Config::get('addons', []);

        if (!empty($config['autoload'])) {
            // 自动扫描插件，生成 hooks/route 配置
            $config = get_addon_autoload_config();
        }

        // 注册插件钩子
        self::registerHooks((array)($config['hooks'] ?? []));

        // 注册插件自定义路由
        self::registerRoutes((array)($config['route'] ?? []));

        // 触发 addon_init
        self::triggerSafe('addon_init');
    }

    /**
     * 注册默认插件路由
     * @return void
     */
    protected static function registerDefaultRoute()
    {
        Route::rule('addons/:addon/[:controller]/[:action]', self::dispatcher(null, null, null));
    }

    /**
     * 创建插件调度闭包
     * @param string|null $addon
     * @param string|null $controller
     * @param string|null $action
     * @return \Closure
     */
    protected static function dispatcher($addon = null, $controller = null, $action = null)
    {
        $fixedAddon = $addon;
        $fixedController = $controller;
        $fixedAction = $action;

        return function ($addon = null, $controller = null, $action = null) use ($fixedAddon, $fixedController, $fixedAction) {
            $route = new \fastaddons\addons\Route();
            return $route->execute(
                $fixedAddon !== null ? $fixedAddon : $addon,
                $fixedController !== null ? $fixedController : $controller,
                $fixedAction !== null ? $fixedAction : $action
            );
        };
    }

    /**
     * 注册钩子监听
     * @param array $hooks
     * @return void
     */
    protected static function registerHooks(array $hooks)
    {
        foreach ($hooks as $hookName => $addonList) {
            $addonList = is_string($addonList) ? explode(',', $addonList) : (array)$addonList;
            foreach (array_filter($addonList) as $addon) {
                if (!is_string($addon)) {
                    continue;
                }
                $class = get_addon_class($addon);
                if (!$class) {
                    continue;
                }
                Event::listen($hookName, function ($params = []) use ($class, $hookName) {
                    try {
                        $instance = new $class();
                        $method = LegacyLoader::parseName($hookName, 1, false);
                        if (method_exists($instance, $method)) {
                            return $instance->$method($params);
                        }
                    } catch (\Throwable $e) {
                        Log::error('addon hook failed: ' . $hookName . ' ' . $e->getMessage());
                    }
                });
            }
        }
    }

    /**
     * 注册插件自定义路由
     * @param array $routeArr
     * @return void
     */
    protected static function registerRoutes(array $routeArr)
    {
        $domains = [];

        foreach ($routeArr as $rule => $value) {
            // 域名部署配置
            if (is_array($value) && isset($value['addon'], $value['domain'], $value['rule'])) {
                $domainRules = [];
                foreach ((array)$value['rule'] as $ruleName => $target) {
                    $urlArr = explode('/', $target);
                    if (count($urlArr) < 3) {
                        continue;
                    }
                    list($addon, $controller, $action) = $urlArr;
                    $domainRules[$ruleName] = self::dispatcher($addon, $controller, $action);
                }
                $domainRules[':controller/[:action]'] = self::dispatcher($value['addon'], null, null);
                $domains[$value['domain']] = $domainRules;
                continue;
            }

            if (!$value || !is_string($value)) {
                continue;
            }
            $urlArr = explode('/', $value);
            if (count($urlArr) < 3) {
                continue;
            }
            list($addon, $controller, $action) = $urlArr;
            Route::rule((string)$rule, self::dispatcher($addon, $controller, $action));
        }

        foreach ($domains as $domain => $rules) {
            Route::domain((string)$domain, function () use ($rules) {
                foreach ($rules as $rule => $dispatch) {
                    Route::rule((string)$rule, $dispatch);
                }
            });
        }
    }

    /**
     * 安全触发事件
     * @param string $event
     * @param mixed  $params
     * @return mixed
     */
    protected static function triggerSafe($event, $params = [])
    {
        try {
            return Event::trigger($event, $params);
        } catch (\Throwable $e) {
            Log::error('addon event failed: ' . $event . ' ' . $e->getMessage());
            return [];
        }
    }
}