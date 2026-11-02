<?php

declare(strict_types=1);

namespace app\common\library;

use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Request;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    public function render(Request $request, Throwable $e): Response
    {
        // HttpResponseException 应直接返回其携带的响应
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        $accept = (string)$request->header('accept', '');
        $isApi = app()->http->getName() === 'api'
            || $request->isJson()
            || stripos($accept, 'application/json') !== false;

        if (!$isApi) {
            return parent::render($request, $e);
        }

        $status = 500;
        $code = 500;
        $msg = $this->app->isDebug() ? $e->getMessage() : 'An error occurred';

        if ($e instanceof ValidateException) {
            $status = 200;
            $code = 0;
            $msg = $e->getError();
        } elseif ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $code = $status;
        }

        return json([
            'code' => $code,
            'msg'  => $msg,
            'time' => time(),
            'data' => null,
        ], $status);
    }
}
