<?php

namespace app\admin\controller\auth;

use app\common\controller\Backend;
use fast\Tree;
use think\Cache;
use think\Db;

/**
 * 规则管理
 *
 * @icon   fa fa-list
 * @remark 规则通常对应一个控制器的方法,同时左侧的菜单栏数据也从规则中体现,通常建议通过控制台进行生成规则节点
 */
class Rule extends Backend
{

    /**
     * @var \app\admin\model\AuthRule
     */
    protected $model = null;
    protected $rulelist = [];
    protected $multiFields = 'ismenu,status';

    public function _initialize()
    {
        parent::_initialize();
        if (!$this->auth->isSuperAdmin()) {
            $this->error(__('Access is allowed only to the super management group'));
        }
        $this->model = model('AuthRule');
        $actionName = $this->request->action();

        $isAddEdit = in_array($actionName, ['add', 'edit']);

        // 优化加载
        if ($isAddEdit || ($actionName == 'index' && $this->request->isAjax())) {

            // 必须将结果集转换为数组
            $ruleList = Db::name("auth_rule")
                ->where(function ($query) use ($isAddEdit) {
                    if ($isAddEdit) {
                        $query->where('ismenu', 1);
                    }
                })->field('id,pid,name,title,icon,ismenu,status,weigh')
                ->order('weigh DESC,id ASC')
                ->select();

            array_walk($ruleList, function (&$v) {
                $v['title'] = __($v['title']);
            });

            // 读取规则菜单
            $this->rulelist = Tree::instance()->init($ruleList)->getTreeArrayList(0);

            // 只有编辑页才需要渲染
            if ($isAddEdit) {
                $ruledata = [0 => __('None')];
                foreach ($this->rulelist as $k => &$v) {
                    if (!$v['ismenu']) {
                        continue;
                    }
                    $ruledata[$v['id']] = str_repeat('&nbsp;', $v['level'] * 4) . $v['title'];
                }
                unset($v);

                $this->view->assign('ruledata', $ruledata);
            }
        }

        $this->view->assign("menutypeList", $this->model->getMenutypeList());
    }

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = $this->rulelist;
            $total = count($this->rulelist);
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a", [], 'strip_tags');
            if ($params) {
                if (!$params['ismenu'] && !$params['pid']) {
                    $this->error(__('The non-menu rule must have parent'));
                }
                $result = $this->model->validate()->save($params);
                if ($result === false) {
                    $this->error($this->model->getError());
                }
                $this->success();
            }
            $this->error();
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a", [], 'strip_tags');
            if ($params) {
                if (!$params['ismenu'] && !$params['pid']) {
                    $this->error(__('The non-menu rule must have parent'));
                }
                if ($params['pid'] == $row['id']) {
                    $this->error(__('Can not change the parent to self'));
                }
                if ($params['pid'] != $row['pid']) {
                    $tree = Tree::instance()->init(Db::name('auth_rule')->select());
                    $childrenIds = $tree->getChildrenIds($row['id']);
                    if (in_array($params['pid'], $childrenIds)) {
                        $this->error(__('Can not change the parent to child'));
                    }
                }
                //这里需要针对name做唯一验证
                $ruleValidate = \think\Loader::validate('AuthRule');
                $ruleValidate->rule([
                    'name' => 'require|unique:AuthRule,name,' . $row->id,
                ]);
                $result = $row->validate()->save($params);
                if ($result === false) {
                    $this->error($row->getError());
                }
                $this->success();
            }
            $this->error();
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post("ids");
        if ($ids) {
            $delIds = [];
            $tree = Tree::instance()->init(Db::name('auth_rule')->select());
            foreach (explode(',', $ids) as $k => $v) {
                $delIds = array_merge($delIds, $tree->getChildrenIds($v, true));
            }
            $delIds = array_unique($delIds);
            $count = $this->model->where('id', 'in', $delIds)->delete();
            if ($count) {
                Cache::rm('__menu__');
                $this->success();
            }
        }
        $this->error();
    }

    /**
     * 排序
     */
    public function dragsort()
    {

        // 注册Hook
        \think\Hook::add('admin_dragsort_after', function ($model) {
            Cache::rm('__menu__');
        });

        parent::dragsort();
    }
}