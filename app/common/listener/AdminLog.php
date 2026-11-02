<?php

declare(strict_types=1);

namespace app\common\listener;

use think\Request;
use think\Response;

class AdminLog
{
    public function handle(Response $response, Request $request): void
    {
        if ($request->isPost()
            && config('fastadmin.auto_record_log')
            && app()->http->getName() === 'admin'
            && class_exists('\app\admin\model\AdminLog')
            && method_exists('\app\admin\model\AdminLog', 'record')
        ) {
            \app\admin\model\AdminLog::record();
        }
    }
}
