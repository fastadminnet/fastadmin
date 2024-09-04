<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Lang;
use think\Loader;
use think\Response;

/**
 * Ajax异步请求接口
 * @internal
 */
class Ajax extends Frontend
{

    protected $noNeedLogin = ['lang', 'upload'];
    protected $noNeedRight = ['*'];
    protected $layout = '';

    /**
     * 加载语言包
     */
    public function lang()
    {
        $this->request->get(['callback' => 'define']);
        $header = ['Content-Type' => 'application/javascript'];
        if (!config('app_debug')) {
            $offset = 30 * 60 * 60 * 24; // 缓存一个月
            $header['Cache-Control'] = 'public';
            $header['Pragma'] = 'cache';
            $header['Expires'] = gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        }

        $controllername = $this->request->get('controllername');
        $lang = $this->request->get('lang');
        if (!$lang || !in_array($lang, config('allow_lang_list')) || !$controllername || !preg_match("/^[a-z0-9_\.]+$/i", $controllername)) {
            return jsonp(['errmsg' => '参数错误'], 200, [], ['json_encode_param' => JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE]);
        }

        $controllername = input("controllername");
        $className = Loader::parseClass($this->request->module(), 'controller', $controllername, false);

        //存在对应的类才加载
        if (class_exists($className)) {
            $this->loadlang($controllername);
        }

        //强制输出JSON Object
        return jsonp(Lang::get(), 200, $header, ['json_encode_param' => JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 生成后缀图标
     */
    public function icon()
    {
        $suffix = $this->request->request("suffix");
        $suffix = $suffix ? $suffix : "FILE";
        $data = build_suffix_image($suffix);
        $header = ['Content-Type' => 'image/svg+xml'];
        $offset = 30 * 60 * 60 * 24; // 缓存一个月
        $header['Cache-Control'] = 'public';
        $header['Pragma'] = 'cache';
        $header['Expires'] = gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        $response = Response::create($data, '', 200, $header);
        return $response;
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        return action('api/common/upload');
    }

}
