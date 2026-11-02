<?php

// +----------------------------------------------------------------------
// | FastAdmin Addons (ThinkPHP 8 fork)
// +----------------------------------------------------------------------

use app\common\library\LegacyLoader;
use fastaddons\Addons;
use think\facade\Config;
use think\facade\Db;
use think\facade\Event;
use think\facade\Log;

// 插件根目录
if (!function_exists('addons_path')) {
    /**
     * 获取插件根目录
     * @return string
     */
    function addons_path()
    {
        $path = root_path() . 'addons' . DIRECTORY_SEPARATOR;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }
}

// 保留 ADDON_PATH 常量作为兼容别名
if (!defined('ADDON_PATH')) {
    define('ADDON_PATH', addons_path());
}

/**
 * 处理插件钩子
 * @param string $hook   钩子名称
 * @param mixed  $params 传入参数
 * @return mixed
 */
if (!function_exists('hook')) {
    function hook($hook, $params = [])
    {
        try {
            $result = Event::trigger($hook, $params);
            if (is_array($result) && !$result) {
                return '';
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('addon hook error: ' . $hook . ' ' . $e->getMessage());
            return '';
        }
    }
}

/**
 * 移除空目录
 * @param string $dir 目录
 */
if (!function_exists('remove_empty_folder')) {
    function remove_empty_folder($dir)
    {
        try {
            $isDirEmpty = !(new \FilesystemIterator($dir))->valid();
            if ($isDirEmpty) {
                @rmdir($dir);
                remove_empty_folder(dirname($dir));
            }
        } catch (\UnexpectedValueException $e) {

        } catch (\Exception $e) {

        }
    }
}

/**
 * 获得插件列表
 * @return array
 */
if (!function_exists('get_addon_list')) {
    function get_addon_list()
    {
        $basePath = addons_path();
        $results = is_dir($basePath) ? scandir($basePath) : [];
        $list = [];
        foreach ($results as $name) {
            if ($name === '.' or $name === '..') {
                continue;
            }
            if (is_file($basePath . $name)) {
                continue;
            }
            $addonDir = $basePath . $name . DIRECTORY_SEPARATOR;
            if (!is_dir($addonDir)) {
                continue;
            }

            if (!is_file($addonDir . ucfirst($name) . '.php')) {
                continue;
            }

            $info_file = $addonDir . 'info.ini';
            if (!is_file($info_file)) {
                continue;
            }

            $info = (array)parse_ini_file($info_file, true);
            if (!isset($info['name'])) {
                continue;
            }
            $info['url'] = addon_url($name);
            $list[$name] = $info;
        }
        return $list;
    }
}

/**
 * 获得插件自动加载的配置
 * @param bool $truncate 是否清除手动配置的钩子
 * @return array
 */
if (!function_exists('get_addon_autoload_config')) {
    function get_addon_autoload_config($truncate = false)
    {
        // 读取addons的配置
        $config = (array)Config::get('addons');
        if ($truncate) {
            // 清空手动配置的钩子
            $config['hooks'] = [];
        }

        // 伪静态优先级
        $priority = isset($config['priority']) && $config['priority'] ? (is_array($config['priority']) ? $config['priority'] : explode(',', $config['priority'])) : [];

        $route = [];
        // 读取插件目录及钩子列表
        $base = get_class_methods(Addons::class);
        $base = array_merge($base, ['install', 'uninstall', 'enable', 'disable']);

        $url_domain_deploy = Config::get('url_domain_deploy');
        $addons = get_addon_list();
        $domain = [];

        $priority = array_merge($priority, array_keys($addons));

        $orderedAddons = [];
        foreach ($priority as $key) {
            if (!isset($addons[$key])) {
                continue;
            }
            $orderedAddons[$key] = $addons[$key];
        }

        foreach ($orderedAddons as $name => $addon) {
            if (!$addon['state']) {
                continue;
            }

            // 读取出所有公共方法
            $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . ucfirst($name));
            // 跟插件基类方法做比对，得到差异结果
            $hooks = array_diff($methods, $base);
            // 循环将钩子方法写入配置中
            foreach ($hooks as $hook) {
                $hook = LegacyLoader::parseName($hook, 0, false);
                if (!isset($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = [];
                }
                // 兼容手动配置项
                if (is_string($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                }
                if (!in_array($name, $config['hooks'][$hook])) {
                    $config['hooks'][$hook][] = $name;
                }
            }
            $conf = get_addon_config($addon['name']);
            if ($conf) {
                $conf['rewrite'] = isset($conf['rewrite']) && is_array($conf['rewrite']) ? $conf['rewrite'] : [];
                $rule = array_map(function ($value) use ($addon) {
                    return "{$addon['name']}/{$value}";
                }, array_flip($conf['rewrite']));
                if ($url_domain_deploy && isset($conf['domain']) && $conf['domain']) {
                    $domain[] = [
                        'addon'  => $addon['name'],
                        'domain' => $conf['domain'],
                        'rule'   => $rule
                    ];
                } else {
                    $route = array_merge($route, $rule);
                }
            }
        }
        $config['route'] = $route;
        $config['route'] = array_merge($config['route'], $domain);
        return $config;
    }
}

/**
 * 获取插件类的类名
 * @param string $name  插件名
 * @param string $type  返回命名空间类型
 * @param string $class 当前类名
 * @return string
 */
if (!function_exists('get_addon_class')) {
    function get_addon_class($name, $type = 'hook', $class = null)
    {
        $name = LegacyLoader::parseName($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);

            $class[count($class) - 1] = LegacyLoader::parseName(end($class), 1);
            $class = implode('\\', $class);
        } else {
            $class = LegacyLoader::parseName(is_null($class) ? $name : $class, 1);
        }
        switch ($type) {
            case 'controller':
                $namespace = "\\addons\\" . $name . "\\controller\\" . $class;
                break;
            default:
                $namespace = "\\addons\\" . $name . "\\" . $class;
        }
        return class_exists($namespace) ? $namespace : '';
    }
}

/**
 * 读取插件的基础信息
 * @param string $name 插件名
 * @return array
 */
if (!function_exists('get_addon_info')) {
    function get_addon_info($name)
    {
        $addon = get_addon_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getInfo($name);
    }
}

/**
 * 获取插件类的配置数组
 * @param string $name 插件名
 * @return array
 */
if (!function_exists('get_addon_fullconfig')) {
    function get_addon_fullconfig($name)
    {
        $addon = get_addon_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getFullConfig($name);
    }
}

/**
 * 获取插件类的配置值
 * @param string $name 插件名
 * @return array
 */
if (!function_exists('get_addon_config')) {
    function get_addon_config($name)
    {
        $addon = get_addon_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getConfig($name);
    }
}

/**
 * 获取插件的单例
 * @param string $name 插件名
 * @return mixed|null
 */
if (!function_exists('get_addon_instance')) {
    function get_addon_instance($name)
    {
        static $_addons = [];
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        $class = get_addon_class($name);
        if (class_exists($class)) {
            $_addons[$name] = new $class();
            return $_addons[$name];
        } else {
            return null;
        }
    }
}

/**
 * 获取插件创建的表
 * @param string $name 插件名
 * @return array
 */
if (!function_exists('get_addon_tables')) {
    function get_addon_tables($name)
    {
        $addonInfo = get_addon_info($name);
        if (!$addonInfo) {
            return [];
        }
        $regex = "/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z_]+)`?/mi";
        $sqlFile = addons_path() . $name . DIRECTORY_SEPARATOR . 'install.sql';
        $tables = [];
        if (is_file($sqlFile)) {
            preg_match_all($regex, file_get_contents($sqlFile), $matches);
            if ($matches && isset($matches[2]) && $matches[2]) {
                $prefix = config('database.prefix');
                $tables = array_map(function ($item) use ($prefix) {
                    return str_replace("__PREFIX__", $prefix, $item);
                }, $matches[2]);
            }
        }
        return $tables;
    }
}

/**
 * 插件显示内容里生成访问插件的url
 * @param string      $url    地址 格式：插件名/控制器/方法
 * @param array       $vars   变量参数
 * @param bool|string $suffix 生成的URL后缀
 * @param bool|string $domain 域名
 * @return bool|string
 */
if (!function_exists('addon_url')) {
    function addon_url($url, $vars = [], $suffix = true, $domain = false)
    {
        $url = ltrim($url, '/');
        $addon = substr($url, 0, stripos($url, '/'));
        if (!is_array($vars)) {
            parse_str($vars, $params);
            $vars = $params;
        }
        $params = [];
        foreach ($vars as $k => $v) {
            if (substr($k, 0, 1) === ':') {
                $params[$k] = $v;
                unset($vars[$k]);
            }
        }
        $val = "@addons/{$url}";
        $config = get_addon_config($addon);
        $indomain = request()->route('indomain') && request()->route('addon') == $addon ? true : false;
        //优先取插件配置中的domain，没有的情况下取全局的域名前缀配置
        $domainprefix = $config && isset($config['domain']) && $config['domain'] ? $config['domain'] : Config::get('addons.domain');
        $domain = $domainprefix && Config::get('url_domain_deploy') ? $domainprefix : $domain;
        $rewrite = $config && isset($config['rewrite']) && $config['rewrite'] ? $config['rewrite'] : [];
        if ($rewrite) {
            $path = substr($url, stripos($url, '/') + 1);
            if (isset($rewrite[$path]) && $rewrite[$path]) {
                $val = $rewrite[$path];
                array_walk($params, function ($value, $key) use (&$val) {
                    $val = str_replace("[{$key}]", $value, $val);
                });
                $val = str_replace(['^', '$'], '', $val);
                if (substr($val, -1) === '/') {
                    $suffix = false;
                }
            } else {
                // 如果采用了域名部署,则需要去掉前两段
                if ($indomain && $domainprefix) {
                    $arr = explode("/", $val);
                    $val = implode("/", array_slice($arr, 2));
                }
            }
        } else {
            // 如果采用了域名部署,则需要去掉前两段
            if ($indomain && $domainprefix) {
                $arr = explode("/", $val);
                $val = implode("/", array_slice($arr, 2));
            }
            foreach ($params as $k => $v) {
                $vars[substr($k, 1)] = $v;
            }
        }
        $url = url($val, [], $suffix, $domain) . ($vars ? '?' . http_build_query($vars) : '');
        $url = preg_replace("/\/((?!index)[\w]+)\.php\//i", "/", $url);
        return $url;
    }
}

/**
 * 获取插件配置文件路径
 * @param string $name 插件名
 * @return string
 */
if (!function_exists('get_addon_config_file')) {
    function get_addon_config_file($name)
    {
        return addons_path() . $name . DIRECTORY_SEPARATOR . 'config.php';
    }
}

/**
 * 设置基础配置信息
 * @param string $name  插件名
 * @param array  $array 配置数据
 * @return boolean
 * @throws Exception
 */
if (!function_exists('set_addon_info')) {
    function set_addon_info($name, $array)
    {
        $file = addons_path() . $name . DIRECTORY_SEPARATOR . 'info.ini';
        $addon = get_addon_instance($name);
        $array = $addon->setInfo($name, $array);
        if (!isset($array['name']) || !isset($array['title']) || !isset($array['version'])) {
            throw new \Exception("插件配置写入失败");
        }
        $res = [];
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $res[] = "[$key]";
                foreach ($val as $skey => $sval) {
                    $res[] = "$skey = " . (is_numeric($sval) ? $sval : $sval);
                }
            } else {
                $res[] = "$key = " . (is_numeric($val) ? $val : $val);
            }
        }
        Event::trigger($name . '_info_before_write', $array);
        if (file_put_contents($file, implode("\n", $res) . "\n", LOCK_EX)) {
            //清空当前配置缓存
            Config::set([$name => null], 'addoninfo');
        } else {
            throw new \Exception("文件没有写入权限");
        }
        return true;
    }
}

/**
 * 写入配置文件
 * @param string  $name      插件名
 * @param array   $config    配置数据
 * @param boolean $writefile 是否写入配置文件
 * @return bool
 * @throws Exception
 */
if (!function_exists('set_addon_config')) {
    function set_addon_config($name, $config, $writefile = true)
    {
        $addon = get_addon_instance($name);
        $addon->setConfig($name, $config);
        $fullconfig = get_addon_fullconfig($name);
        foreach ($fullconfig as $k => &$v) {
            if (isset($config[$v['name']])) {
                $value = $v['type'] !== 'array' && is_array($config[$v['name']]) ? implode(',', $config[$v['name']]) : $config[$v['name']];
                $v['value'] = $value;
            }
        }
        if ($writefile) {
            // 写入配置文件
            set_addon_fullconfig($name, $fullconfig);
        }
        return true;
    }
}

/**
 * 写入配置文件
 *
 * @param string $name  插件名
 * @param array  $array 配置数据
 * @return boolean
 * @throws Exception
 */
if (!function_exists('set_addon_fullconfig')) {
    function set_addon_fullconfig($name, $array)
    {
        $config = [];
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $config[$value['name']] = $value['value'];
            }
        }
        Event::trigger($name . '_config_before_write', $config);
        $file = addons_path() . $name . DIRECTORY_SEPARATOR . 'config.php';
        $ret = file_put_contents($file, "<?php\n\n" . "return " . var_export_short($array, true) . ";\n", LOCK_EX);
        if (!$ret) {
            throw new \Exception("配置写入失败");
        }
        return true;
    }
}