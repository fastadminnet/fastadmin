<?php

namespace app\admin\library;

use app\admin\model\Admin;
use fast\Random;
use fast\Tree;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Request;
use think\Session;

class Auth extends \fast\Auth
{
    protected $_error = '';
    protected $requestUri = '';
    protected $breadcrumb = [];
    protected $logined = false; //登录状态

    public function __construct()
    {
        parent::__construct();
    }

    public function __get($name)
    {
        return Session::get('admin.' . $name);
    }

    /**
     * 管理员登录
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param int    $keeptime 有效时长
     * @return  boolean
     */
    public function login($username, $password, $keeptime = 0)
    {
        $admin = Admin::get(['username' => $username]);
        if (!$admin) {
            $this->setError('Username is incorrect');
            return false;
        }
        if ($admin['status'] == 'hidden') {
            $this->setError('Admin is forbidden');
            return false;
        }
        if (Config::get('fastadmin.login_failure_retry') && $admin->loginfailure >= 10 && time() - $admin->updatetime < 86400) {
            $this->setError('Please try again after 1 day');
            return false;
        }
        if ($admin->password != $this->getEncryptPassword($password, $admin->salt)) {
            $admin->loginfailure++;
            $admin->save();
            $this->setError('Password is incorrect');
            return false;
        }
        $admin->loginfailure = 0;
        $admin->logintime = time();
        $admin->loginip = request()->ip();
        $admin->token = Random::uuid();
        $admin->save();
        Session::set("admin", $admin->toArray());
        Session::set("admin.safecode", $this->getEncryptSafecode($admin));
        $this->keeplogin($admin, $keeptime);
        return true;
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $admin = Admin::get(intval($this->id));
        if ($admin) {
            $admin->token = '';
            $admin->save();
        }
        $this->logined = false; //重置登录状态
        Session::delete("admin");
        Cookie::delete("keeplogin");
        setcookie('fastadmin_userinfo', '', $_SERVER['REQUEST_TIME'] - 3600, rtrim(url("/" . request()->module(), '', false), '/'));
        return true;
    }

    /**
     * 自动登录
     * @return boolean
     */
    public function autologin()
    {
        $keeplogin = Cookie::get('keeplogin');
        if (!$keeplogin) {
            return false;
        }
        list($id, $keeptime, $expiretime, $key) = explode('|', $keeplogin);
        if ($id && $keeptime && $expiretime && $key && $expiretime > time()) {
            $admin = Admin::get($id);
            if (!$admin || !$admin->token) {
                return false;
            }
            //token有变更
            if ($key != $this->getKeeploginKey($admin, $keeptime, $expiretime)) {
                return false;
            }
            $ip = request()->ip();
            //IP有变动
            if ($admin->loginip != $ip) {
                return false;
            }
            Session::set("admin", $admin->toArray());
            Session::set("admin.safecode", $this->getEncryptSafecode($admin));
            //刷新自动登录的时效
            $this->keeplogin($admin, $keeptime);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 刷新保持登录的Cookie
     *
     * @param int $keeptime
     * @return  boolean
     */
    protected function keeplogin($admin, $keeptime = 0)
    {
        if ($keeptime) {
            $expiretime = time() + $keeptime;
            $key = $this->getKeeploginKey($admin, $keeptime, $expiretime);
            Cookie::set('keeplogin', implode('|', [$admin['id'], $keeptime, $expiretime, $key]), $keeptime);
            return true;
        }
        return false;
    }

    /**
     * 获取密码加密后的字符串
     * @param string $password 密码
     * @param string $salt     密码盐
     * @return string
     */
    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password) . $salt);
    }

    /**
     * 获取密码加密后的自动登录码
     * @param string $password 密码
     * @param string $salt     密码盐
     * @return string
     */
    public function getEncryptKeeplogin($params, $keeptime)
    {
        $expiretime = time() + $keeptime;
        $key = md5(md5($params['id']) . md5($keeptime) . md5($expiretime) . $params['token'] . config('token.key'));
        return implode('|', [$this->id, $keeptime, $expiretime, $key]);
    }

    /**
     * 获取自动登录Key
     * @param $params
     * @param $keeptime
     * @param $expiretime
     * @return string
     */
    public function getKeeploginKey($params, $keeptime, $expiretime)
    {
        $key = md5(md5($params['id']) . md5($keeptime) . md5($expiretime) . $params['token'] . config('token.key'));
        return $key;
    }

    /**
     * 获取加密后的安全码
     * @param $params
     * @return string
     */
    public function getEncryptSafecode($params)
    {
        return md5(md5($params['username']) . md5(substr($params['password'], 0, 6)) . config('token.key'));
    }

    public function check($name, $uid = '', $relation = 'or', $mode = 'url')
    {
        $uid = $uid ? $uid : $this->id;
        return parent::check($name, $uid, $relation, $mode);
    }

    /**
     * 检测当前控制器和方法是否匹配传递的数组
     *
     * @param array $arr 需要验证权限的数组
     * @return bool
     */
    public function match($arr = [])
    {
        $request = Request::instance();
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (!$arr) {
            return false;
        }

        $arr = array_map('strtolower', $arr);
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr)) {
            return true;
        }

        // 没找到匹配
        return false;
    }

    /**
     * 检测是否登录
     *
     * @return boolean
     */
    public function isLogin()
    {
        if ($this->logined) {
            return true;
        }
        $admin = Session::get('admin');
        if (!$admin) {
            return false;
        }
        $my = Admin::get($admin['id']);
        if (!$my) {
            return false;
        }
        //校验安全码，可用于判断关键信息发生了变更需要重新登录
        if (!isset($admin['safecode']) || $this->getEncryptSafecode($my) !== $admin['safecode']) {
            $this->logout();
            return false;
        }
        //判断是否同一时间同一账号只能在一个地方登录
        if (Config::get('fastadmin.login_unique')) {
            if ($my['token'] != $admin['token']) {
                $this->logout();
                return false;
            }
        }
        //判断管理员IP是否变动
        if (Config::get('fastadmin.loginip_check')) {
            if (!isset($admin['loginip']) || $admin['loginip'] != request()->ip()) {
                $this->logout();
                return false;
            }
        }
        $this->logined = true;
        return true;
    }

    /**
     * 获取当前请求的URI
     * @return string
     */
    public function getRequestUri()
    {
        return $this->requestUri;
    }

    /**
     * 设置当前请求的URI
     * @param string $uri
     */
    public function setRequestUri($uri)
    {
        $this->requestUri = $uri;
    }

    public function getGroups($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;
        return parent::getGroups($uid);
    }

    public function getRuleList($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;
        return parent::getRuleList($uid);
    }

    public function getUserInfo($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;

        return $uid != $this->id ? Admin::get(intval($uid)) : Session::get('admin');
    }

    public function getRuleIds($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;
        return parent::getRuleIds($uid);
    }

    public function isSuperAdmin()
    {
        return in_array('*', $this->getRuleIds()) ? true : false;
    }

    /**
     * 获取管理员所属于的分组ID
     * @param int $uid
     * @return array
     */
    public function getGroupIds($uid = null)
    {
        $groups = $this->getGroups($uid);
        $groupIds = [];
        foreach ($groups as $K => $v) {
            $groupIds[] = (int)$v['group_id'];
        }
        return $groupIds;
    }

    /**
     * 取出当前管理员所拥有权限的分组
     * @param boolean $withself 是否包含当前所在的分组
     * @return array
     */
    public function getChildrenGroupIds($withself = false)
    {
        //取出当前管理员所有的分组
        $groups = $this->getGroups();
        $groupIds = array_column($groups, 'id');
        $originGroupIds = $groupIds;
        foreach ($groups as $k => $v) {
            if (in_array($v['pid'], $originGroupIds)) {
                $groupIds = array_diff($groupIds, [$v['id']]);
                unset($groups[$k]);
            }
        }
        // 取出所有分组
        $groupList = \app\admin\model\AuthGroup::where($this->isSuperAdmin() ? '1=1' : ['status' => 'normal'])->select();
        $objList = [];
        foreach ($groups as $k => $v) {
            if ($v['rules'] === '*') {
                $objList = $groupList;
                break;
            }
            // 取出包含自己的所有子节点
            $childrenList = Tree::instance()->init($groupList, 'pid')->getChildren($v['id'], true);
            $obj = Tree::instance()->init($childrenList, 'pid')->getTreeArray($v['pid']);
            $objList = array_merge($objList, Tree::instance()->getTreeList($obj));
        }
        $childrenGroupIds = [];
        foreach ($objList as $k => $v) {
            $childrenGroupIds[] = $v['id'];
        }
        if (!$withself) {
            $childrenGroupIds = array_diff($childrenGroupIds, $groupIds);
        }
        return $childrenGroupIds;
    }

    /**
     * 取出当前管理员所拥有权限的管理员
     * @param boolean $withself 是否包含自身
     * @return array
     */
    public function getChildrenAdminIds($withself = false)
    {
        $childrenAdminIds = [];
        if (!$this->isSuperAdmin()) {
            $groupIds = $this->getChildrenGroupIds(false);
            $childrenAdminIds = \app\admin\model\AuthGroupAccess::where('group_id', 'in', $groupIds)
                ->column('uid');
        } else {
            //超级管理员拥有所有人的权限
            $childrenAdminIds = Admin::column('id');
        }
        if ($withself) {
            if (!in_array($this->id, $childrenAdminIds)) {
                $childrenAdminIds[] = $this->id;
            }
        } else {
            $childrenAdminIds = array_diff($childrenAdminIds, [$this->id]);
        }
        return $childrenAdminIds;
    }

    /**
     * 获得面包屑导航
     * @param string $path
     * @return array
     */
    public function getBreadCrumb($path = '')
    {
        if ($this->breadcrumb || !$path) {
            return $this->breadcrumb;
        }
        $titleArr = [];
        $menuArr = [];
        $urlArr = explode('/', $path);
        foreach ($urlArr as $index => $item) {
            $pathArr[implode('/', array_slice($urlArr, 0, $index + 1))] = $index;
        }
        if (!$this->rules && $this->id) {
            $this->getRuleList();
        }
        foreach ($this->rules as $rule) {
            if (isset($pathArr[$rule['name']])) {
                $rule['title'] = __($rule['title']);
                $rule['url'] = url($rule['name']);
                $titleArr[$pathArr[$rule['name']]] = $rule['title'];
                $menuArr[$pathArr[$rule['name']]] = $rule;
            }
        }
        ksort($menuArr);
        $this->breadcrumb = $menuArr;
        return $this->breadcrumb;
    }

    /**
     * 获取左侧和顶部菜单栏
     *
     * @param array  $params    URL对应的badge数据
     * @param string $fixedPage 默认页
     * @return array
     */
    public function getSidebar($params = [], $fixedPage = 'dashboard')
    {
        // 边栏开始
        Hook::listen("admin_sidebar_begin", $params);
        $colorArr = ['red', 'green', 'yellow', 'blue', 'teal', 'orange', 'purple'];
        $colorNums = count($colorArr);
        $badgeList = [];
        $module = request()->module();
        // 生成菜单的badge
        foreach ($params as $k => $v) {
            $url = $k;
            if (is_array($v)) {
                $nums = $v[0] ?? 0;
                $color = $v[1] ?? $colorArr[(is_numeric($nums) ? $nums : strlen($nums)) % $colorNums];
                $class = $v[2] ?? 'label';
            } else {
                $nums = $v;
                $color = $colorArr[(is_numeric($nums) ? $nums : strlen($nums)) % $colorNums];
                $class = 'label';
            }
            //必须nums大于0才显示
            if ($nums) {
                $badgeList[$url] = '<small class="' . $class . ' pull-right bg-' . $color . '">' . $nums . '</small>';
            }
        }

        // 读取管理员当前拥有的权限节点
        $userRule = $this->getRuleList();
        $selected = $referer = [];
        $refererUrl = Session::get('referer');
        // 必须将结果集转换为数组
        $ruleList = collection(\app\admin\model\AuthRule::where('status', 'normal')
            ->where('ismenu', 1)
            ->order('weigh', 'desc')
            ->cache("__menu__")
            ->select())->toArray();
        $indexRuleList = \app\admin\model\AuthRule::where('status', 'normal')
            ->where('ismenu', 0)
            ->where('name', 'like', '%/index')
            ->column('name,pid');
        $pidArr = array_unique(array_filter(array_column($ruleList, 'pid')));
        foreach ($ruleList as $k => &$v) {
            if (!in_array(strtolower($v['name']), $userRule)) {
                unset($ruleList[$k]);
                continue;
            }
            $indexRuleName = $v['name'] . '/index';
            if (isset($indexRuleList[$indexRuleName]) && !in_array($indexRuleName, $userRule)) {
                unset($ruleList[$k]);
                continue;
            }
            $v['icon'] = $v['icon'] . ' fa-fw';
            $v['url'] = isset($v['url']) && $v['url'] ? $v['url'] : '/' . $module . '/' . $v['name'];
            $v['badge'] = $badgeList[$v['name']] ?? '';
            $v['title'] = __($v['title']);
            $v['url'] = preg_match("/^((?:[a-z]+:)?\/\/|data:image\/)(.*)/i", $v['url']) ? $v['url'] : url($v['url']);
            $v['menuclass'] = in_array($v['menutype'], ['dialog', 'ajax']) ? 'btn-' . $v['menutype'] : '';
            $v['menutabs'] = !$v['menutype'] || in_array($v['menutype'], ['default', 'addtabs']) ? 'addtabs="' . $v['id'] . '"' : '';
            $selected = $v['name'] == $fixedPage ? $v : $selected;
            $referer = $v['url'] == $refererUrl ? $v : $referer;
        }
        $lastArr = array_unique(array_filter(array_column($ruleList, 'pid')));
        $pidDiffArr = array_diff($pidArr, $lastArr);
        foreach ($ruleList as $index => $item) {
            if (in_array($item['id'], $pidDiffArr)) {
                unset($ruleList[$index]);
            }
        }
        if ($selected == $referer) {
            $referer = [];
        }

        $select_id = $referer ? $referer['id'] : ($selected ? $selected['id'] : 0);
        $menu = $nav = '';
        $showSubmenu = config('fastadmin.show_submenu');
        if (Config::get('fastadmin.multiplenav')) {
            $topList = [];
            foreach ($ruleList as $index => $item) {
                if (!$item['pid']) {
                    $topList[] = $item;
                }
            }
            $selectParentIds = [];
            $tree = Tree::instance();
            $tree->init($ruleList);
            if ($select_id) {
                $selectParentIds = $tree->getParentsIds($select_id, true);
            }
            foreach ($topList as $index => $item) {
                $childList = Tree::instance()->getTreeMenu(
                    $item['id'],
                    '<li class="@class" pid="@pid"><a @extend href="@url@addtabs" addtabs="@id" class="@menuclass" url="@url" py="@py" pinyin="@pinyin"><i class="@icon"></i> <span>@title</span> <span class="pull-right-container">@caret @badge</span></a> @childlist</li>',
                    $select_id,
                    '',
                    'ul',
                    'class="treeview-menu' . ($showSubmenu ? ' menu-open' : '') . '"'
                );
                $current = in_array($item['id'], $selectParentIds);
                $url = $childList ? 'javascript:;' : $item['url'];
                $addtabs = $childList || !$url ? "" : (stripos($url, "?") !== false ? "&" : "?") . "ref=" . ($item['menutype'] ? $item['menutype'] : 'addtabs');
                $childList = str_replace(
                    '" pid="' . $item['id'] . '"',
                    ' ' . ($current ? '' : 'hidden') . '" pid="' . $item['id'] . '"',
                    $childList
                );
                $nav .= '<li class="' . ($current ? 'active' : '') . '"><a ' . $item['extend'] . ' href="' . $url . $addtabs . '" ' . $item['menutabs'] . ' class="' . $item['menuclass'] . '" url="' . $url . '" title="' . $item['title'] . '"><i class="' . $item['icon'] . '"></i> <span>' . $item['title'] . '</span> <span class="pull-right-container"> </span></a> </li>';
                $menu .= $childList;
            }
        } else {
            // 构造菜单数据
            Tree::instance()->init($ruleList);
            $menu = Tree::instance()->getTreeMenu(
                0,
                '<li class="@class"><a @extend href="@url@addtabs" @menutabs class="@menuclass" url="@url" py="@py" pinyin="@pinyin"><i class="@icon"></i> <span>@title</span> <span class="pull-right-container">@caret @badge</span></a> @childlist</li>',
                $select_id,
                '',
                'ul',
                'class="treeview-menu' . ($showSubmenu ? ' menu-open' : '') . '"'
            );
            if ($selected) {
                $nav .= '<li role="presentation" id="tab_' . $selected['id'] . '" class="' . ($referer ? '' : 'active') . '"><a href="#con_' . $selected['id'] . '" node-id="' . $selected['id'] . '" aria-controls="' . $selected['id'] . '" role="tab" data-toggle="tab"><i class="' . $selected['icon'] . ' fa-fw"></i> <span>' . $selected['title'] . '</span> </a></li>';
            }
            if ($referer) {
                $nav .= '<li role="presentation" id="tab_' . $referer['id'] . '" class="active"><a href="#con_' . $referer['id'] . '" node-id="' . $referer['id'] . '" aria-controls="' . $referer['id'] . '" role="tab" data-toggle="tab"><i class="' . $referer['icon'] . ' fa-fw"></i> <span>' . $referer['title'] . '</span> </a> <i class="close-tab fa fa-remove"></i></li>';
            }
        }

        return [$menu, $nav, $selected, $referer];
    }

    /**
     * 设置错误信息
     *
     * @param string $error 错误信息
     * @return Auth
     */
    public function setError($error)
    {
        $this->_error = $error;
        return $this;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError()
    {
        return $this->_error ? __($this->_error) : '';
    }
}
