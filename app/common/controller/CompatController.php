<?php

declare(strict_types=1);

namespace app\common\controller;

use think\App;
use think\exception\HttpResponseException;
use think\Response;

abstract class CompatController
{
    protected $app;
    protected $request;
    protected $view;
    protected $layout = '';
    protected $beforeActionList = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->view = $app->view;

        !defined('IS_ADDTABS') && define('IS_ADDTABS', (bool)$this->request->param('addtabs'));
        !defined('IS_DIALOG') && define('IS_DIALOG', (bool)$this->request->param('dialog'));
        !defined('IS_AJAX') && define('IS_AJAX', $this->request->isAjax());

        $this->_initialize();

        foreach ($this->beforeActionList as $method => $options) {
            if (is_numeric($method)) {
                $this->beforeAction((string)$options);
            } else {
                $this->beforeAction((string)$method, (array)$options);
            }
        }
    }

    protected function _initialize()
    {
    }

    protected function beforeAction(string $method, array $options = []): void
    {
        $action = $this->request->action();

        if (isset($options['only'])) {
            $only = is_array($options['only']) ? $options['only'] : explode(',', (string)$options['only']);
            if (!in_array($action, $only, true)) {
                return;
            }
        }

        if (isset($options['except'])) {
            $except = is_array($options['except']) ? $options['except'] : explode(',', (string)$options['except']);
            if (in_array($action, $except, true)) {
                return;
            }
        }

        call_user_func([$this, $method]);
    }

    protected function assign($name, $value = null)
    {
        $this->view->assign($name, $value);
        return $this;
    }

    protected function fetch(string $template = '', array $vars = [])
    {
        if ($this->layout !== '') {
            $this->view->engine()->layout($this->layout);
        }
        return $this->view->fetch($template, $vars);
    }

    protected function display(string $content = '', array $vars = [])
    {
        return $this->view->display($content, $vars);
    }

    protected function success($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
        if (is_null($url)) {
            $url = $this->request->server('HTTP_REFERER');
        } elseif (is_string($url) && $url !== '' && !str_contains($url, '://') && !str_starts_with($url, '/')) {
            $url = (string)url($url);
        }

        $this->jump(1, (string)$msg, $url, $data, (int)$wait, $header);
    }

    protected function error($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
        if (is_null($url)) {
            $url = $this->request->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif (is_string($url) && $url !== '' && !str_contains($url, '://') && !str_starts_with($url, '/')) {
            $url = (string)url($url);
        }

        $this->jump(0, (string)$msg, $url, $data, (int)$wait, $header);
    }

    protected function result($data, int $code = 0, string $msg = '', string $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => $this->request->time(),
            'data' => $data,
        ];

        $type = $type ?: $this->getResponseType();
        throw new HttpResponseException(Response::create($result, $type, 200)->header($header));
    }

    protected function redirect(string $url, $params = [], int $code = 302, array $with = [])
    {
        if (is_int($params)) {
            $code = $params;
            $params = [];
        }

        if (is_array($params) && $params) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        $response = redirect($url, $code);
        if ($with) {
            $response->with($with);
        }

        throw new HttpResponseException($response);
    }

    protected function getResponseType(): string
    {
        $accept = (string)$this->request->header('accept', '');
        if ($this->request->isAjax() || $this->request->isJson() || stripos($accept, 'application/json') !== false) {
            return (string)config('app.default_ajax_return', 'json');
        }
        return (string)config('app.default_return_type', 'html');
    }

    protected function jump(int $code, string $msg, $url, $data, int $wait, array $header): void
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
            'wait' => $wait,
        ];

        $type = strtolower($this->getResponseType());
        if ($type !== 'html') {
            throw new HttpResponseException(Response::create($result, $type, 200)->header($header));
        }

        $template = (string)config($code === 1 ? 'app.dispatch_success_tmpl' : 'app.dispatch_error_tmpl');
        $html = '';
        if ($template !== '' && is_file($template)) {
            // 保留 {__NOLAYOUT__} 让模板引擎关闭布局，提示页本身是完整 HTML 页面
            $this->view->engine()->config(['layout_on' => false]);
            $content = (string)file_get_contents($template);
            $html = $this->view->display($content, $result);
        }

        throw new HttpResponseException(Response::create($html, 'html', 200)->header($header));
    }
}
