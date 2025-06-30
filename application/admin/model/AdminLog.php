<?php

namespace app\admin\model;

use app\admin\library\Auth;
use think\Model;
use think\Loader;

class AdminLog extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';
    //自定义日志标题
    protected static $title = '';
    //自定义日志内容
    protected static $content = '';
    //忽略的链接正则列表
    protected static $ignoreRegex = [
        '/^(.*)\/(selectpage|index|get_table_list|get_fields_list)$/i',
        '/^auth\/group\/roletree$/i',
    ];

    public static function setTitle($title)
    {
        self::$title = $title;
    }

    public static function setContent($content)
    {
        self::$content = $content;
    }

    public static function setIgnoreRegex($regex = [])
    {
        $regex = is_array($regex) ? $regex : [$regex];
        self::$ignoreRegex = array_merge(self::$ignoreRegex, $regex);
    }

    /**
     * 记录日志
     * @param string $title   日志标题
     * @param string $content 日志内容
     */
    public static function record($title = '', $content = '')
    {
        $auth = Auth::instance();
        $admin_id = $auth->isLogin() ? $auth->id : 0;
        $username = $auth->isLogin() ? $auth->username : __('Unknown');

        // 设置过滤函数
        request()->filter('trim,strip_tags,htmlspecialchars');

        $controllername = Loader::parseName(request()->controller());
        $actionname = strtolower(request()->action());
        $path = str_replace('.', '/', $controllername) . '/' . $actionname;
        if (self::$ignoreRegex) {
            foreach (self::$ignoreRegex as $index => $item) {
                if (preg_match($item, $path)) {
                    return;
                }
            }
        }
        $content = $content ?: self::$content;
        if (!$content) {
            $content = request()->param('') ?: file_get_contents("php://input");
            $contentLength = request()->server('CONTENT_LENGTH');
            if (is_string($content) && $contentLength && strlen($content) < $contentLength) {
                $content = '[Request Data Truncated]';
            }
            $content = self::getPureContent($content);
        }
        $title = $title ?: self::$title;
        $title = $title ?: implode(' / ', array_column(Auth::instance()->getBreadcrumb($path), 'title'));
        self::create([
            'title'     => $title,
            'content'   => !is_scalar($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content,
            'url'       => substr(xss_clean(strip_tags(request()->url())), 0, 1500),
            'admin_id'  => $admin_id,
            'username'  => $username,
            'useragent' => substr(request()->server('HTTP_USER_AGENT'), 0, 255),
            'ip'        => xss_clean(strip_tags(request()->ip()))
        ]);
    }

    /**
     * 获取已屏蔽关键信息的数据
     * @param $content
     * @return array
     */
    protected static function getPureContent($content)
    {
        if (!is_array($content)) {
            return $content;
        }
        foreach ($content as $index => &$item) {
            if (preg_match("/(password|salt|token)/i", $index)) {
                $item = "***";
            } else {
                if (is_array($item)) {
                    $item = self::getPureContent($item);
                }
            }
        }
        return $content;
    }

    public function admin()
    {
        return $this->belongsTo('Admin', 'admin_id')->setEagerlyType(0);
    }
}
