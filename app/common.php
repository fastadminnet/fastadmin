<?php

require_once __DIR__ . '/common/helper.php';

if (!class_exists('\think\Addons', false)) {
    class_alias(\fastaddons\Addons::class, '\think\Addons');
}
if (!class_exists('\think\addons\Controller', false)) {
    class_alias(\fastaddons\addons\Controller::class, '\think\addons\Controller');
}
if (!class_exists('\think\addons\Service', false)) {
    class_alias(\fastaddons\addons\Service::class, '\think\addons\Service');
}
if (!class_exists('\think\addons\Route', false)) {
    class_alias(\fastaddons\addons\Route::class, '\think\addons\Route');
}

if (!function_exists('fastadmin_subdir_pathinfo')) {
    /**
     * TP5 controller_auto_search 兼容：把 "auth/admin/index" 改写为 "auth.admin/index"。
     * TP8 不再支持 controller_auto_search，子目录控制器需用点号访问。
     */
    function fastadmin_subdir_pathinfo(\think\Request $request, string $appName): void
    {
        $pathinfo = (string)$request->pathinfo();
        // 部分环境下 pathinfo() 会带上查询串（如 ?ref=addtabs），先去掉再解析
        $pathinfo = explode('?', $pathinfo, 2)[0];
        if ($pathinfo === '' || strpos($pathinfo, '.') !== false) {
            return;
        }
        $segments = explode('/', $pathinfo);
        if (count($segments) < 2) {
            return;
        }
        $controllerDir = __DIR__ . DIRECTORY_SEPARATOR . $appName . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . $segments[0];
        if (is_dir($controllerDir) && is_file($controllerDir . DIRECTORY_SEPARATOR . ucfirst($segments[1]) . '.php')) {
            $segments[0] = $segments[0] . '.' . $segments[1];
            array_splice($segments, 1, 1);
            if (count($segments) < 2) {
                $segments[] = 'index';
            }
            $request->setPathinfo(implode('/', $segments));
        }
    }
}
