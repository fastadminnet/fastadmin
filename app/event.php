<?php

use think\event\AppInit;
use think\event\HttpEnd;

return [
    'listen' => [
        AppInit::class => [
            \app\common\listener\AppInit::class,
            \app\common\listener\AddonsInit::class,
        ],
        HttpEnd::class => [
            \app\common\listener\AdminLog::class,
        ],
    ],
];