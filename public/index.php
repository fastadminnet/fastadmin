<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

// 判断是否安装
if (!is_file($app->getAppPath() . 'admin/command/Install/install.lock')) {
    header("location:./install.php");
    exit;
}

$request = $app->make('request', [], true);
if (in_array($request->pathinfo(), ['', 'index.php'], true)) {
    $request->setPathinfo('index/index');
}
$__pathinfo = $request->pathinfo();
if (strpos($__pathinfo, 'index/') === 0 && $__pathinfo !== 'index/index') {
    $request->setPathinfo(substr($__pathinfo, 6));
}
fastadmin_subdir_pathinfo($request, 'index');

$response = $app->http->name('index')->run($request);
$response->send();
$app->http->end($response);
