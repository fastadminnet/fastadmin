<?php

namespace fast;

use think\facade\Db;
use think\facade\Request;
use think\facade\Session;

/**
 * 权限认证类
 */
class Auth
{

    /**
     * @var object 对象实例
     */
    protected static $instance;
    protected $rules = [];

    /**
     * 当前请求实例
     * @var \think\Request
     */
    protected $request;
    //默认配置
    protected $config = [
        'auth_on'           => 1,
        'auth_type'         => 1,
        'auth_group'        => 'auth_group',
        'auth_group_access' => 'auth_group_access',
        'auth_rule'         => 'auth_rule',
        'auth_user'         => 'user',
    ];

    public function __construct()
    {
        if ($auth = config('auth')) {
            $this->config = array_merge($this->config, $auth);
        }
        $this->request = request();
    }

    /**
     * 初始化
     * @access public
     * @param array $options 参数
     * @return Auth
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 检查权限
     * @param string|array $name     需要验证的规则列表
     * @param int          $uid      认证用户的id
     * @param string       $relation 如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
     * @param string       $mode     执行验证的模式,可分为 url,normal
     * @return bool 通过验证返回true;失败返回false
     */
    public function check($name, $uid, $relation = 'or', $mode = 'url')
    {
        if (!$this->config['auth_on']) {
            return true;
        }
        // 获取用户需要验证的所有有效规则列表
        $rulelist = $this->getRuleList($uid);
        if (in_array('*', $rulelist)) {
            return true;
        }

        if (is_string($name)) {
            $name = strtolower($name);
            if (strpos($name, ',') !== false) {
                $name = explode(',', $name);
            } else {
                $name = [$name];
            }
        }
        $list = []; //保存验证通过的规则名
        if ('url' == $mode) {
            $REQUEST = unserialize(strtolower(serialize($this->request->param())));
        }
        foreach ($rulelist as $rule) {
            $query = preg_replace('/^.+\?/U', '', $rule);
            if ('url' == $mode && $query != $rule) {
                parse_str($query, $param); //解析规则中的param
                $intersect = array_intersect_assoc($REQUEST, $param);
                $rule = preg_replace('/\?.*$/U', '', $rule);
                if (in_array($rule, $name) && $intersect == $param) {
                    //如果节点相符且url参数满足
                    $list[] = $rule;
                }
            } else {
                if (in_array($rule, $name)) {
                    $list[] = $rule;
                }
            }
        }
        if ('or' == $relation && !empty($list)) {
            return true;
        }
        $diff = array_diff($name, $list);
        if ('and' == $relation && empty($diff)) {
            return true;
        }

        return false;
    }

    /**
     * 根据用户id获取用户组,返回值为数组
     * @param int $uid  用户id
     * @return array       用户所属的用户组
     */
    public function getGroups($uid)
    {
        static $groups = [];
        if (isset($groups[$uid])) {
            return $groups[$uid];
        }

        // 获取表前缀
        $prefix = config('database.connections.mysql.prefix', 'fa_');
        $authGroupAccessTable = $prefix . $this->config['auth_group_access'];
        $authGroupTable = $prefix . $this->config['auth_group'];

        // 执行查询
        $user_groups = Db::table($authGroupAccessTable)
            ->alias('aga')
            ->join($authGroupTable . ' ag', 'aga.group_id = ag.id', 'LEFT')
            ->field('aga.uid,aga.group_id,ag.id,ag.pid,ag.name,ag.rules')
            ->where("aga.uid='{$uid}' and ag.status='normal'")
            ->select()->toArray();
        $groups[$uid] = $user_groups ?: [];
        return $groups[$uid];
    }

    /**
     * 获得权限规则列表
     * @param int $uid 用户id
     * @return array
     */
    public function getRuleList($uid)
    {
        static $_rulelist = []; //保存用户验证通过的权限列表
        if (isset($_rulelist[$uid])) {
            return $_rulelist[$uid];
        }
        if (2 == $this->config['auth_type'] && Session::has('_rule_list_' . $uid)) {
            return Session::get('_rule_list_' . $uid);
        }

        // 读取用户规则节点
        $ids = $this->getRuleIds($uid);
        $ids = array_values(array_filter($ids, function ($v) {
            return $v !== '' && $v !== null;
        }));
        if (empty($ids)) {
            $_rulelist[$uid] = [];
            return [];
        }

        // 筛选条件
        $where = [
            'status' => 'normal'
        ];
        if (!in_array('*', $ids)) {
            $where['id'] = ['in', $ids];
        }
        //读取用户组所有权限规则
        $prefix = config('database.connections.mysql.prefix', 'fa_');
        $authRuleTable = $prefix . $this->config['auth_rule'];
        $__ruleQuery = Db::table($authRuleTable)->where('status', 'normal');
        if (!in_array('*', $ids)) {
            $__ruleQuery->whereIn('id', $ids);
        }
        $this->rules = $__ruleQuery->field('id,pid,condition,icon,name,title,ismenu')->select();

        //循环规则,判断结果
        $rulelist = []; //
        if (in_array('*', $ids)) {
            $rulelist[] = "*";
        }
        foreach ($this->rules as $rule) {
            //超级管理员无需验证condition
            if (!empty($rule['condition']) && !in_array('*', $ids)) {
                //根据condition进行验证
                $user = $this->getUserInfo($uid); //获取用户信息,一维数组
                $nums = 0;
                $condition = str_replace(['&&', '||'], "\r\n", $rule['condition']);
                $condition = preg_replace('/\{(\w*?)\}/', '\\1', $condition);
                $conditionArr = explode("\r\n", $condition);
                foreach ($conditionArr as $index => $item) {
                    preg_match("/^(\w+)\s?([\>\<\=]+)\s?(.*)$/", trim($item), $matches);
                    if ($matches && isset($user[$matches[1]]) && version_compare($user[$matches[1]], $matches[3], $matches[2])) {
                        $nums++;
                    }
                }
                if ($conditionArr && ((stripos($rule['condition'], "||") !== false && $nums > 0) || count($conditionArr) == $nums)) {
                    $rulelist[$rule['id']] = strtolower($rule['name']);
                }
            } else {
                //只要存在就记录
                $rulelist[$rule['id']] = strtolower($rule['name']);
            }
        }
        $_rulelist[$uid] = $rulelist;
        //登录验证则需要保存规则列表
        if (2 == $this->config['auth_type']) {
            //规则列表结果保存到session
            Session::set('_rule_list_' . $uid, $rulelist);
        }
        return array_unique($rulelist);
    }

    public function getRuleIds($uid)
    {
        //读取用户所属用户组
        $groups = $this->getGroups($uid);
        $ids = []; //保存用户所属用户组设置的所有权限规则id
        foreach ($groups as $g) {
            $ids = array_merge($ids, explode(',', trim($g['rules'], ',')));
        }
        $ids = array_unique($ids);
        return $ids;
    }

    /**
     * 获得用户资料
     * @param int $uid 用户id
     * @return mixed
     */
    protected function getUserInfo($uid)
    {
        static $user_info = [];

        $prefix = config('database.connections.mysql.prefix', 'fa_');
        $authUserTable = $prefix . $this->config['auth_user'];
        $user = Db::table($authUserTable);
        // 获取用户表主键
        $_pk = is_string($user->getPk()) ? $user->getPk() : 'uid';
        if (!isset($user_info[$uid])) {
            $user_info[$uid] = $user->where($_pk, $uid)->find();
        }

        return $user_info[$uid];
    }
}
