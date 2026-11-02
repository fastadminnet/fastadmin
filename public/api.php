<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

$request = $app->make('request', [], true);
// 如果 pathinfo 为空或为 api.php，重定向到 index/index
$pathinfo = $request->pathinfo();
if ($pathinfo === '' || $pathinfo === 'api.php' || $pathinfo === '/') {
    $request->setPathinfo('index/index');
}
fastadmin_subdir_pathinfo($request, 'api');

$response = $app->http->name('api')->run($request);
$response->send();
$app->http->end($response);
