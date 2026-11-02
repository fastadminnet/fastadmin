<?php

declare(strict_types=1);

namespace app\common\listener;

use think\App;

class AddonsInit
{
    public function handle(App $app): void
    {
        \fastaddons\Bootstrap::register();
    }
}