<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\exception\UploadException;
use app\common\library\Upload;
use app\common\model\Area;
use app\common\model\Version;
use fast\Random;
use think\captcha\Captcha;
use think\facade\Config;
use think\facade\Event;
use think\facade\Session;
use think\Response;

/**
 * 公共接口
 */
class Common extends Api
{
    protected $noNeedLogin = ['init', 'captcha'];
    protected $noNeedRight = '*';

    public function _initialize(): void
    {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header('Access-Control-Expose-Headers: __token__');
        }
        check_cors_request();

        if (!isset($_COOKIE['PHPSESSID'])) {
            $session = config('session');
            $session['id'] = $this->request->server("HTTP_SID");
            config('session', $session);
        }
        parent::_initialize();
    }

    /**
     * 加载初始化
     *
     * @ApiParams (name="version", type="string", required=true, description="版本号")
     */
    public function init()
    {
        if ($version = $this->request->request('version')) {
            $upload = Config::get('upload');

            $uploaddata = [];
            $uploaddata['cdnurl'] = $upload['cdnurl'] ?: cdnurl('', true);
            $uploaddata['uploadurl'] = (string)url('common/upload', [], false, true);

            $content = [
                'versiondata' => Version::check($version),
                'uploaddata'  => $uploaddata,
            ];
            $this->success('', $content);
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 上传文件
     *
     * @ApiMethod (POST)
     * @ApiParams (name="file", type="file", required=true, description="文件流")
     */
    public function upload()
    {
        Config::set(['default_return_type' => 'json']);
        $upload = config('upload');
        $upload['cdnurl'] = '';
        config('upload', $upload);
        $chunkid = $this->request->post("chunkid");
        if ($chunkid) {
            if (!Config::get('upload.chunking')) {
                $this->error(__('Chunk file disabled'));
            }
            $action = $this->request->post("action");
            $chunkindex = $this->request->post("chunkindex/d");
            $chunkcount = $this->request->post("chunkcount/d");
            $filename = $this->request->post("filename");
            $method = $this->request->method(true);
            if ($action == 'merge') {
                $attachment = null;
                try {
                    $upload = new Upload();
                    $attachment = $upload->merge($chunkid, $chunkcount, $filename);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
            } elseif ($method == 'clean') {
                try {
                    $upload = new Upload();
                    $upload->clean($chunkid);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            } else {
                $file = $this->request->file('file');
                try {
                    $upload = new Upload($file);
                    $upload->chunk($chunkid, $chunkindex, $chunkcount);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
        } else {
            $attachment = null;
            $file = $this->request->file('file');
            try {
                $upload = new Upload($file);
                $attachment = $upload->upload();
            } catch (UploadException $e) {
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }

            $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
        }
    }

    /**
     * 验证码
     *
     * @ApiParams (name="id", type="string", required=true, description="要生成验证码的标识")
     * @return \think\Response
     */
    public function captcha($id = "")
    {
        $captchaConfig = array_merge(config('captcha'), [
            'fontSize' => 44,
            'imageH'   => 150,
            'imageW'   => 350,
        ]);
        // 临时设置配置
        config(['captcha' => $captchaConfig]);
        $captcha = new Captcha($this->app->config, $this->app->session);
        return $captcha->create($id);
    }
}
