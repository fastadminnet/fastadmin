<?php

declare(strict_types=1);

namespace app\common\library;

abstract class LegacyLoader
{
    protected static array $instance = [];

    public static function model(string $name = '', string $layer = 'model', bool $appendSuffix = false, string $common = 'common')
    {
        $uid = $name . $layer;
        if (isset(self::$instance[$uid])) {
            return self::$instance[$uid];
        }

        [$module, $class] = self::getModuleAndClass($name, $layer, $appendSuffix);
        $class = self::resolveClass($name, $layer, $class, $module, $common, $appendSuffix);
        $model = new $class();

        return self::$instance[$uid] = $model;
    }

    public static function validate(string $name = '', string $layer = 'validate', bool $appendSuffix = false, string $common = 'common')
    {
        if ($name === '') {
            return new \think\Validate();
        }

        $uid = $name . $layer;
        if (isset(self::$instance[$uid])) {
            return self::$instance[$uid];
        }

        [$module, $class] = self::getModuleAndClass($name, $layer, $appendSuffix);
        $class = self::resolveClass($name, $layer, $class, $module, $common, $appendSuffix);

        return self::$instance[$uid] = new $class();
    }

    public static function parseName(?string $name, int $type = 0, bool $ucfirst = true): string
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name ?? '');

            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace('/[A-Z]/', '_\0', $name ?? ''), '_'));
    }

    public static function parseClass(string $module, string $layer, string $name, bool $appendSuffix = false): string
    {
        $array = explode('\\', str_replace(['/', '.'], '\\', $name));
        $class = self::parseName(array_pop($array), 1);
        $class .= (config('app.class_suffix') || $appendSuffix ? ucfirst($layer) : '');
        $path = $array ? implode('\\', $array) . '\\' : '';

        return app()->getNamespace() . '\\'
            . ($module ? $module . '\\' : '')
            . $layer . '\\' . $path . $class;
    }

    /**
     * 兼容 TP5 的短名解析：app()->getNamespace() 在部分上下文为 app\common，
     * 会拼出 app\common\admin\model\X 这类不存在的类，这里按候选命名空间回退。
     */
    protected static function resolveClass(string $name, string $layer, string $class, string $module, string $common, bool $appendSuffix = false): string
    {
        if (class_exists($class)) {
            return $class;
        }

        $candidates = [];
        $namespace = rtrim((string)app()->getNamespace(), '\\');
        if ($namespace !== '' && str_starts_with($class, $namespace . '\\common\\')) {
            $candidates[] = $namespace . '\\' . substr($class, strlen($namespace . '\\common\\'));
        }

        if (!str_contains($name, '\\')) {
            $array = explode('\\', str_replace(['/', '.'], '\\', $name));
            $short = self::parseName(array_pop($array), 1);
            $short .= ((config('app.class_suffix') || $appendSuffix) ? ucfirst($layer) : '');
            $path = $array ? implode('\\', $array) . '\\' : '';
            if (preg_match('/^[a-zA-Z0-9_]+$/', $module)) {
                $candidates[] = 'app\\' . $module . '\\' . $layer . '\\' . $path . $short;
            }
            $candidates[] = 'app\\common\\' . $layer . '\\' . $path . $short;
            $candidates[] = 'app\\admin\\' . $layer . '\\' . $path . $short;
            $candidates[] = 'app\\index\\' . $layer . '\\' . $path . $short;
        }

        $candidates[] = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);
        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && class_exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('class not exists:' . $class);
    }

    protected static function getModuleAndClass(string $name, string $layer, bool $appendSuffix): array
    {
        if (str_contains($name, '\\')) {
            $module = app()->http->getName();
            $class = $name;
        } else {
            if (str_contains($name, '/')) {
                [$module, $name] = explode('/', $name, 2);
            } else {
                $module = app()->http->getName();
            }

            $class = self::parseClass($module, $layer, $name, $appendSuffix);
        }

        return [$module, $class];
    }
}
