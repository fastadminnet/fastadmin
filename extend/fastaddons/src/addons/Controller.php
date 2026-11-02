<?php

namespace fastaddons\addons;

use app\common\controller\Frontend;
use think\App;
use think\facade\Config;
use think\facade\Event;
use think\facade\Lang;

/**
 * 插件基类控制器
 * @package fastaddons\addons
 */
class Controller extends Frontend
{
    // 当前插件操作
    protected $addon = null;
    protected $controller = null;
    protected $action = null;
    // 当前template
    protected $template;

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = ['*'];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = ['*'];

    /**
     * 布局模板
     * @var string
     */
    protected $layout = '';

    /**
     * 架构函数
     * @access public
     */
    public function __construct(App $app = null)
    {
        $app = $app ?: app();
        $request = $app->request;

        // 是否自动转换控制器和操作名
        $convert = Config::get('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';

        $addon = (string)$request->route('addon', '');
        $controller = (string)$request->route('controller', '');
        $action = (string)$request->route('action', '');

        $this->addon = $addon ? call_user_func($filter, $addon) : '';
        $this->controller = $controller ? call_user_func($filter, $controller) : 'index';
        $this->action = $action ? call_user_func($filter, $action) : 'index';

        parent::__construct($app);
    }

    public function _initialize()
    {
        // 设置插件视图目录（必须在父类应用 layout 之前完成，否则 layout 会按默认视图目录解析）
        $viewConfig = [
            'view_path' => addons_path() . $this->addon . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR,
        ];

        // 设置替换字符串
        $cdnurl = Config::get('site.cdnurl');
        $replace = array_merge((array)Config::get('view.tpl_replace_string', []), [
            '__ADDON__' => $cdnurl . '/assets/addons/' . $this->addon,
        ]);
        $viewConfig['tpl_replace_string'] = $replace;

        Config::set($viewConfig, 'view');
        $this->view->engine()->config($viewConfig);

        // 渲染配置到视图中
        $config = get_addon_config($this->addon);
        $this->assign('config', $config);

        $lang = config('lang.default_lang', 'zh-cn');

        // 加载插件语言包
        $langFile = addons_path() . $this->addon . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $lang . '.php';
        if (is_file($langFile)) {
            Lang::load($langFile);
        }

        $site = Config::get('site');
        $upload = \app\common\model\Config::upload();

        // 上传信息配置后
        Event::trigger("upload_config_init", $upload);
        Config::set(['upload' => array_merge((array)Config::get('upload'), $upload)], 'upload');

        $this->assign('site', $site);

        // 父类会进行登录/权限校验，失败时会用上面的视图配置渲染提示页
        parent::_initialize();
    }

    /**
     * 加载模板输出
     * @access protected
     * @param string $template 模板文件名
     * @param array  $vars     模板输出变量
     * @param array  $replace  模板替换
     * @param array  $config   模板参数
     * @return mixed
     */
    protected function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        return $this->view->fetch($template, $vars);
    }
}