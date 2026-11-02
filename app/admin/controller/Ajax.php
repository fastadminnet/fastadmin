<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\exception\UploadException;
use app\common\library\Upload;
use fast\Random;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Lang;
use think\helper\Str;
use think\facade\Validate;
use think\facade\Event;

/**
 * Ajax异步请求接口
 * @internal
 */
class Ajax extends Backend
{
    protected $noNeedLogin = ['lang'];
    protected $noNeedRight = ['*'];
    protected $layout = '';

    public function _initialize(): void
    {
        parent::_initialize();
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);
    }

    /**
     * 加载语言包
     */
    public function lang()
    {
        $this->request->get(['callback' => 'define']);
        $header = ['Content-Type' => 'application/javascript'];
        if (!config('app.app_debug')) {
            $offset = 30 * 60 * 60 * 24;
            $header['Cache-Control'] = 'public';
            $header['Pragma'] = 'cache';
            $header['Expires'] = gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        }

        $controllername = $this->request->get('controllername');
        $lang = $this->request->get('lang');
        if (!$lang || !in_array($lang, config('allow_lang_list')) || !$controllername || !preg_match("/^[a-z0-9_\.]+$/i", $controllername)) {
            return jsonp(['errmsg' => '参数错误'], 200, [], ['json_encode_param' => JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE]);
        }

        $controllername = $this->request->get('controllername');
        $className = Str::snake($controllername);
                $className = 'app\\' . app('http')->getName() . '\\controller\\' . ucfirst($className);

        if (class_exists($className)) {
            $this->loadlang($controllername);
        }

        return jsonp(Lang::get(), 200, $header, ['json_encode_param' => JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        config('default_return_type', 'json');
        config('upload', config('upload'));

        $chunkid = $this->request->post("chunkid");
        if ($chunkid) {
            if (!config('upload.chunking')) {
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
                $this->success(__('Uploaded successful'), '', ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
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
            }

            $this->success(__('Uploaded successful'), '', ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
        }
    }

    /**
     * 通用排序
     */
    public function weigh()
    {
        $ids = $this->request->post("ids");
        $changeid = $this->request->post("changeid");
        $field = $this->request->post("field");
        $table = $this->request->post("table");
        if (!Validate::is($table, "alphaDash")) {
            $this->error();
        }
        $pk = $this->request->post("pk");
        $orderway = strtolower($this->request->post("orderway", ""));
        $orderway = $orderway == 'asc' ? 'ASC' : 'DESC';
        $sour = $weighdata = [];
        $ids = explode(',', $ids);
        $prikey = $pk && preg_match("/^[a-z0-9\-_]+$/i", $pk) ? $pk : (Db::name($table)->getPk() ?: 'id');
        $pid = $this->request->post("pid", "");
        $field = in_array($field, ['weigh']) ? $field : 'weigh';

        if ($pid !== '') {
            $hasids = [];
            $list = Db::name($table)->where($prikey, 'in', $ids)->where('pid', 'in', $pid)->field("{$prikey},pid")->select();
            foreach ($list as $k => $v) {
                $hasids[] = $v[$prikey];
            }
            $ids = array_values(array_intersect($ids, $hasids));
        }

        $list = Db::name($table)->field("$prikey,$field")->where($prikey, 'in', $ids)->order($field, $orderway)->select();
        foreach ($list as $k => $v) {
            $sour[] = $v[$prikey];
            $weighdata[$v[$prikey]] = $v[$field];
        }
        $position = array_search($changeid, $ids);
        $desc_id = $sour[$position] ?? end($sour);
        $sour_id = $changeid;
        $weighids = [];
        $temp = array_values(array_diff_assoc($ids, $sour));
        foreach ($temp as $m => $n) {
            if ($n == $sour_id) {
                $offset = $desc_id;
            } else {
                if ($sour_id == $temp[0]) {
                    $offset = $temp[$m + 1] ?? $sour_id;
                } else {
                    $offset = $temp[$m - 1] ?? $sour_id;
                }
            }
            if (!isset($weighdata[$offset])) {
                continue;
            }
            $weighids[$n] = $weighdata[$offset];
            Db::name($table)->where($prikey, $n)->update([$field => $weighdata[$offset]]);
        }
        $this->success();
    }

    /**
     * 清空系统缓存
     */
    public function wipecache()
    {
        try {
            $type = $this->request->request("type");
            switch ($type) {
                case 'all':
                case 'content':
                    rmdirs(runtime_path() . 'cache', false);
                    Cache::clear();
                    if ($type == 'content') {
                        break;
                    }
                case 'template':
                    rmdirs(runtime_path() . 'temp', false);
                    if ($type == 'template') {
                        break;
                    }
                case 'addons':
                    if ($type == 'addons') {
                        break;
                    }
                case 'browser':
                    if (!config('app.app_debug')) {
                        $version = config('site.version');
                        $newversion = preg_replace_callback("/(.*)\.([0-9]+)\$/", function ($match) {
                            return $match[1] . '.' . ($match[2] + 1);
                        }, $version);
                        if ($newversion && $newversion != $version) {
                            Db::startTrans();
                            try {
                                \app\common\model\Config::where('name', 'version')->update(['value' => $newversion]);
                                \app\common\model\Config::refreshFile();
                                Db::commit();
                            } catch (\Exception $e) {
                                Db::rollback();
                                $this->error($e->getMessage());
                            }
                        }
                    }
                    if ($type == 'browser') {
                        break;
                    }
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        Event::trigger("wipecache_after");
        $this->success();
    }

    /**
     * 读取分类数据,联动列表
     */
    public function category()
    {
        $type = $this->request->get('type', '');
        $pid = $this->request->get('pid', '');
        $where = ['status' => 'normal'];
        $categorylist = null;
        if ($pid || $pid === '0') {
            $where['pid'] = $pid;
        }
        if ($type) {
            $where['type'] = $type;
        }

        $categorylist = Db::name('category')->where($where)->field('id as value,name')->order('weigh desc,id desc')->select();

        $this->success('', '', $categorylist);
    }

    /**
     * 读取省市区数据,联动列表
     */
    public function area()
    {
        $params = $this->request->get("row/a");
        if (!empty($params)) {
            $province = isset($params['province']) ? $params['province'] : null;
            $city = isset($params['city']) ? $params['city'] : null;
        } else {
            $province = $this->request->get('province');
            $city = $this->request->get('city');
        }
        $where = ['pid' => 0, 'level' => 1];
        $provincelist = null;
        if ($province !== null) {
            $where['pid'] = $province;
            $where['level'] = 2;
            if ($city !== null) {
                $where['pid'] = $city;
                $where['level'] = 3;
            }
        }
        $provincelist = Db::name('area')->where($where)->field('id as value,name')->select();
        $this->success('', '', $provincelist);
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
        $offset = 30 * 60 * 60 * 24;
        $header['Cache-Control'] = 'public';
        $header['Pragma'] = 'cache';
        $header['Expires'] = gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        $response = response($data, 200, $header);
        return $response;
    }
}
