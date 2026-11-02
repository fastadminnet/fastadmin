<?php

declare(strict_types=1);

namespace app\common\listener;

use think\App;

class AppInit
{
    public function handle(App $app): void
    {
        $allowLangList = $app->config->get('app.allow_lang_list', ['zh-cn', 'en']);
        $app->config->set(['allow_lang_list' => $allowLangList]);
        $app->config->set(['allow_lang_list' => $allowLangList], 'lang');
    }
}
