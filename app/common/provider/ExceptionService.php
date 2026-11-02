<?php

declare(strict_types=1);

namespace app\common\provider;

use think\Service;
use think\exception\Handle;

class ExceptionService extends Service
{
    public function register(): void
    {
        $this->app->bind(Handle::class, \app\common\library\ExceptionHandle::class);
    }
}