<?php

declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;

class ModuleInit
{
    public function handle(Request $request, Closure $next)
    {
        mb_internal_encoding('UTF-8');

        $url = (string)preg_replace("/\/(\w+)\.php$/i", '', $request->root());

        $replace = (array)config('view.tpl_replace_string', []);
        if (empty($replace['__CDN__'])) {
            $replace['__CDN__'] = $url;
        }
        if (empty($replace['__PUBLIC__'])) {
            $replace['__PUBLIC__'] = $url . '/';
        }
        if (empty($replace['__ROOT__'])) {
            $replace['__ROOT__'] = (string)preg_replace("/\/public\/$/", '', $url . '/');
        }
        app()->config->set(['tpl_replace_string' => $replace], 'view');

        if (!config('site.cdnurl')) {
            app()->config->set(['cdnurl' => $url], 'site');
        }
        if (!config('upload.cdnurl')) {
            app()->config->set(['cdnurl' => $url], 'upload');
        }

        if (app()->isDebug()) {
            app()->config->set(['version' => time()], 'site');
        }

        if (config('app.app_trace') && $request->isAjax()) {
            app()->config->set(['app_trace' => false], 'app');
        }

        if (config('app.lang_switch_on')) {
            $lang = (string)$request->get('lang', '');
            if (preg_match("/^([a-zA-Z\-_]{2,10})\$/i", $lang)) {
                cookie('think_var', $lang);
            }
        }

        if (!config('app.url_domain_deploy')) {
            $pathinfo = explode('/', (string)$request->pathinfo());
            if ($pathinfo && in_array($pathinfo[0], ['index', 'api'], true)) {
                app()->config->set(['with_route' => false], 'app');
            }
        }

        if (!class_exists('Form', false) && class_exists('\fast\Form')) {
            class_alias('\fast\Form', 'Form');
        }

        return $next($request);
    }
}
