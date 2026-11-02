<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

// 安装成功后本文件会被安装程序自动删除
if (is_file(__DIR__ . '/../app/admin/command/Install/install.lock')) {
    exit('The system has been installed.');
}

$app->make('request', [], true);

$installer = new \app\admin\command\Install();
$result = $installer->index();

if ($result instanceof \think\Response) {
    $result->send();
} else {
    echo $result;
}
