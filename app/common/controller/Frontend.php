<?php

namespace app\common\controller;

use app\common\library\Auth;
use think\facade\Config;
use think\facade\Event;
use think\facade\Lang;
use think\facade\Request;
use think\helper\Str;
use think\facade\Validate;

/**
 * 前台控制器基类
 */
class Frontend extends CompatController
{

    /**
     * 布局模板
     * @var string
     */
    protected $layout = '';

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array|string
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array|string
     */
    protected $noNeedRight = [];

    /**
     * 权限Auth
     * @var Auth|null
     */
    protected ?Auth $auth = null;

    public function _initialize()
    {
        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');
        $modulename = app()->http->getName();
        $controllername = Str::snake($this->request->controller());
        $actionname = strtolower($this->request->action());

        // 检测IP是否允许
        check_ip_allowed();

        // 如果有使用模板布局
        if ($this->layout) {
            $this->view->engine()->layout('layout/' . $this->layout);
        }
        $this->auth = Auth::instance();

        // token
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', cookie('token')) ?? '');

        $path = str_replace('.', '/', $controllername) . '/' . $actionname;
        // 设置当前请求的URI
        $this->auth->setRequestUri($path);
        // 检测是否需要验证登录
        if (!$this->auth->match($this->noNeedLogin)) {
            //初始化
            $this->auth->init($token);
            //检测是否登录
            if (!$this->auth->isLogin()) {
                $this->error(__('Please login first'), 'index/user/login');
            }
            // 判断是否需要验证权限
            if (!$this->auth->match($this->noNeedRight)) {
                // 判断控制器和方法判断是否有对应权限
                if (!$this->auth->check($path)) {
                    $this->error(__('You have no permission'));
                }
            }
        } else {
            // 如果有传递token才验证是否登录状态
            if ($token) {
                $this->auth->init($token);
            }
        }

        $this->view->assign('user', $this->auth->getUser());

        // 语言检测
        $lang = config('lang.default_lang', 'zh-cn');

        $site = config('site');

        $upload = \app\common\model\Config::upload();

        // 上传信息配置后
        Event::trigger("upload_config_init", $upload);

        // 配置信息
        $config = [
            'site'           => array_intersect_key($site, array_flip(['name', 'cdnurl', 'version', 'timezone', 'languages'])),
            'upload'         => $upload,
            'modulename'     => $modulename,
            'controllername' => $controllername,
            'actionname'     => $actionname,
            'jsname'         => 'frontend/' . str_replace('.', '/', $controllername),
            'moduleurl'      => rtrim(url("/{$modulename}", [], false), '/'),
            'language'       => $lang
        ];
        $config = array_merge($config, config('view_replace_str'));

        config('upload', array_merge(config('upload'), $upload));

        // 配置信息后
        Event::trigger("config_init", $config);
        // 加载当前控制器语言包
        $this->loadlang($controllername);
        $this->assign('site', $site);
        $this->assign('config', $config);
    }

    /**
     * 加载语言文件
     * @param string $name
     */
    protected function loadlang($name): void
    {
        $name = Str::snake($name);
        $name = preg_match("/^([a-zA-Z0-9_\.\/]+)\$/i", $name) ? $name : 'index';
        $lang = config('lang.default_lang', 'zh-cn');
        Lang::load(app_path() . app()->http->getName() . '/lang/' . $lang . '/' . str_replace('.', '/', $name) . '.php');
    }

    /**
     * 渲染配置信息
     * @param mixed $name  键名或数组
     * @param mixed $value 值
     */
    protected function assignconfig($name, $value = '')
    {
        $this->view->config = array_merge($this->view->config ? $this->view->config : [], is_array($name) ? $name : [$name => $value]);
    }

    /**
     * 刷新Token
     */
    protected function token(): void
    {
        $token = $this->request->param('__token__');

        //验证Token
        if (!Validate::rule(['__token__' => 'require|token'])->check(['__token__' => $token])) {
            $this->error(__('Token verification error'), '', ['__token__' => token()]);
        }

        //刷新Token
        token();
    }
}
