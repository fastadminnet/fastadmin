<?php

namespace app\common\library;

use app\admin\model\AuthRule;
use fast\Tree;
use think\addons\Service;
use think\Db;
use think\Exception;
use think\exception\PDOException;

class Menu
{

    /**
     * 创建菜单
     * @param array $menu
     * @param mixed $parent 父类的name或pid
     */
    public static function create($menu = [], $parent = 0)
    {
        $old = [];
        self::menuUpdate($menu, $old, $parent);

        //菜单刷新处理
        $info = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        preg_match('/addons\\\\([a-z0-9]+)\\\\/i', $info['class'], $matches);
        if ($matches && isset($matches[1])) {
            Menu::refresh($matches[1], $menu);
        }
    }

    /**
     * 删除菜单
     * @param string $name 规则name
     * @return boolean
     */
    public static function delete($name)
    {
        $ids = self::getAuthRuleIdsByName($name);
        if (!$ids) {
            return false;
        }
        AuthRule::destroy($ids);
        return true;
    }

    /**
     * 启用菜单
     * @param string $name
     * @return boolean
     */
    public static function enable($name)
    {
        $ids = self::getAuthRuleIdsByName($name);
        if (!$ids) {
            return false;
        }
        AuthRule::where('id', 'in', $ids)->update(['status' => 'normal']);
        return true;
    }

    /**
     * 禁用菜单
     * @param string $name
     * @return boolean
     */
    public static function disable($name)
    {
        $ids = self::getAuthRuleIdsByName($name);
        if (!$ids) {
            return false;
        }
        AuthRule::where('id', 'in', $ids)->update(['status' => 'hidden']);
        return true;
    }

    /**
     * 升级菜单
     * @param string $name 插件名称
     * @param array  $menu 新菜单
     * @return bool
     */
    public static function upgrade($name, $menu)
    {
        $ids = self::getAuthRuleIdsByName($name);
        $old = AuthRule::where('id', 'in', $ids)->select();
        $old = $old->toArray();
        $old = array_column($old, null, 'name');

        Db::startTrans();
        try {
            self::menuUpdate($menu, $old);
            $ids = [];
            foreach ($old as $index => $item) {
                if (!isset($item['keep'])) {
                    $ids[] = $item['id'];
                }
            }
            if ($ids) {
                //旧版本的菜单需要做删除处理
                $config = Service::config($name);
                $menus = $config['menus'] ?? [];
                $__menuQuery = AuthRule::whereIn('id', $ids);
                if ($menus) {
                    //必须是旧版本中的菜单,可排除用户自主创建的菜单
                    $__menuQuery->whereIn('name', $menus);
                }
                $__menuQuery->delete();
            }

            Db::commit();
        } catch (PDOException $e) {
            Db::rollback();
            return false;
        }

        Menu::refresh($name, $menu);
        return true;
    }

    /**
     * 刷新插件菜单配置缓存
     * @param string $name
     * @param array  $menu
     */
    public static function refresh($name, $menu = [])
    {
        if (!$menu) {
            // $menu为空时表示首次安装，首次安装需刷新插件菜单标识缓存
            $menuIds = Menu::getAuthRuleIdsByName($name);
            $menus = Db::name("auth_rule")->where('id', 'in', $menuIds)->column('name');
        } else {
            // 刷新新的菜单缓存
            $getMenus = function ($menu) use (&$getMenus) {
                $result = [];
                foreach ($menu as $index => $item) {
                    $result[] = $item['name'];
                    $result = array_merge($result, isset($item['sublist']) && is_array($item['sublist']) ? $getMenus($item['sublist']) : []);
                }
                return $result;
            };
            $menus = $getMenus($menu);
        }

        //刷新新的插件核心菜单缓存
        Service::config($name, ['menus' => $menus]);
    }

    /**
     * 导出指定名称的菜单规则
     * @param string $name
     * @return array
     */
    public static function export($name)
    {
        $ids = self::getAuthRuleIdsByName($name);
        if (!$ids) {
            return [];
        }
        $menuList = [];
        $menu = AuthRule::getByName($name);
        if ($menu) {
            $ruleList = AuthRule::where('id', 'in', $ids)->select()->toArray();
            $menuList = Tree::instance()->init($ruleList)->getTreeArray($menu['id']);
        }
        return $menuList;
    }

    /**
     * 菜单升级
     * @param array $newMenu
     * @param array $oldMenu
     * @param int   $parent
     * @throws Exception
     */
    private static function menuUpdate($newMenu, &$oldMenu, $parent = 0)
    {
        if (!is_numeric($parent)) {
            $parentRule = AuthRule::getByName($parent);
            $pid = $parentRule ? $parentRule['id'] : 0;
        } else {
            $pid = $parent;
        }
        $allow = array_flip(['file', 'name', 'title', 'url', 'icon', 'condition', 'remark', 'ismenu', 'menutype', 'extend', 'weigh', 'status']);
        foreach ($newMenu as $k => $v) {
            $hasChild = isset($v['sublist']) && $v['sublist'];
            $data = array_intersect_key($v, $allow);
            $data['ismenu'] = $data['ismenu'] ?? ($hasChild ? 1 : 0);
            $data['icon'] = $data['icon'] ?? ($hasChild ? 'fa fa-list' : 'fa fa-circle-o');
            $data['pid'] = $pid;
            $data['status'] = $data['status'] ?? 'normal';
            if (!isset($oldMenu[$data['name']])) {
                $menu = AuthRule::create($data);
            } else {
                $menu = $oldMenu[$data['name']];
                //更新旧菜单
                AuthRule::update($data, ['id' => $menu['id']]);
                $oldMenu[$data['name']]['keep'] = true;
            }
            if ($hasChild) {
                self::menuUpdate($v['sublist'], $oldMenu, $menu['id']);
            }
        }
    }

    /**
     * 根据名称获取规则IDS
     * @param string $name
     * @return array
     */
    public static function getAuthRuleIdsByName($name)
    {
        $ids = [];
        $menu = AuthRule::getByName($name);
        if ($menu) {
            // 必须将结果集转换为数组
            $ruleList = AuthRule::order('weigh', 'desc')->field('id,pid,name')->select()->toArray();
            // 构造菜单数据
            $ids = Tree::instance()->init($ruleList)->getChildrenIds($menu['id'], true);
        }
        return $ids;
    }

}
