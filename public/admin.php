<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

$request = $app->make('request', [], true);
if (in_array($request->pathinfo(), ['', 'admin.php'], true)) {
    $request->setPathinfo('index/index');
}
fastadmin_subdir_pathinfo($request, 'admin');

$response = $app->http->name('admin')->run($request);
$response->send();
$app->http->end($response);
