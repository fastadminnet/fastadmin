<?php

namespace app\admin\controller\auth;

use app\admin\model\AuthGroup;
use app\admin\model\AuthGroupAccess;
use app\common\controller\Backend;
use fast\Random;
use fast\Tree;
use think\facade\Db;

/**
 * 管理员管理
 *
 * @icon   fa fa-users
 * @remark 一个管理员可以有多个角色组,左侧的菜单根据管理员所拥有的权限进行生成
 */
class Admin extends Backend
{

    /**
     * @var \app\admin\model\Admin
     */
    protected $model = null;
    protected $selectpageFields = 'id,username,nickname,avatar';
    protected $searchFields = 'id,username,nickname';
    protected $childrenGroupIds = [];
    protected $childrenAdminIds = [];

    public function _initialize(): void
    {
        parent::_initialize();
        $this->model = model('app\admin\model\Admin');

        $this->childrenAdminIds = $this->auth->getChildrenAdminIds($this->auth->isSuperAdmin());
        $this->childrenGroupIds = $this->auth->getChildrenGroupIds($this->auth->isSuperAdmin());

        $groupList = AuthGroup::where('id', 'in', $this->childrenGroupIds)->select()->toArray();

        Tree::instance()->init($groupList);
        $groupdata = [];
        if ($this->auth->isSuperAdmin()) {
            $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
            foreach ($result as $k => $v) {
                $groupdata[$v['id']] = $v['name'];
            }
        } else {
            $result = [];
            $groups = $this->auth->getGroups();
            foreach ($groups as $m => $n) {
                $childlist = Tree::instance()->getTreeList(Tree::instance()->getTreeArray($n['id']));
                $temp = [];
                foreach ($childlist as $k => $v) {
                    $temp[$v['id']] = $v['name'];
                }
                $result[__($n['name'])] = $temp;
            }
            $groupdata = $result;
        }

        $this->view->assign('groupdata', $groupdata);
        $this->assignconfig("admin", ['id' => $this->auth->id]);
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $childrenGroupIds = $this->childrenGroupIds;
            $groupName = AuthGroup::where('id', 'in', $childrenGroupIds)->column('id,name');
            $authGroupList = AuthGroupAccess::where('group_id', 'in', $childrenGroupIds)
                ->field('uid,group_id')
                ->select();

            $adminGroupName = [];
            foreach ($authGroupList as $k => $v) {
                if (isset($groupName[$v['group_id']])) {
                    $adminGroupName[$v['uid']][$v['group_id']] = $groupName[$v['group_id']];
                }
            }
            $groups = $this->auth->getGroups();
            foreach ($groups as $m => $n) {
                $adminGroupName[$this->auth->id][$n['id']] = $n['name'];
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->where('id', 'in', $this->childrenAdminIds)
                ->withoutField('password,salt,token')
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $k => &$v) {
                $groups = isset($adminGroupName[$v['id']]) ? $adminGroupName[$v['id']] : [];
                $v['groups'] = implode(',', array_keys($groups));
                $v['groups_text'] = implode(',', array_values($groups));
            }
            unset($v);
            $result = ['total' => $list->total(), 'rows' => $list->items()];

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
            $params = $this->request->post("row/a");
            if ($params) {
                Db::startTrans();
                try {
                    if (!preg_match('/^\S{6,30}$/', $params['password'] ?? '')) {
                        throw new \Exception(__("Please input correct password"));
                    }
                    if (empty($params['username']) || !preg_match('/^\w{3,30}$/', $params['username'])) {
                        throw new \Exception(__("Please input correct username"));
                    }
                    if (empty($params['nickname'])) {
                        throw new \Exception(__('Nickname can not be empty'));
                    }
                    if (empty($params['email']) || !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new \Exception(__('Please input correct email'));
                    }
                    if ($this->model->where('username', $params['username'])->find()) {
                        throw new \Exception(__('Username already exists'));
                    }
                    if ($this->model->where('email', $params['email'])->find()) {
                        throw new \Exception(__('Email already exists'));
                    }
                    $params['salt'] = Random::alnum();
                    $params['password'] = $this->auth->getEncryptPassword($params['password'], $params['salt']);
                    $params['avatar'] = '/assets/img/avatar.png';
                    if ($this->model->save($params) === false) {
                        throw new \Exception(__('Save failed'));
                    }
                    $group = $this->request->post("group/a", []);
                    $group = array_intersect($this->childrenGroupIds, $group ?: []);
                    if (!$group) {
                        throw new \Exception(__('The parent group exceeds permission limit'));
                    }

                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $this->model->id, 'group_id' => $value];
                    }
                    \think\facade\Db::name('auth_group_access')->insertAll($dataset);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!in_array($row->id, $this->childrenAdminIds)) {
            $this->error(__('You have no permission'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludePrimaryKey($params);
                Db::startTrans();
                try {
                    if (empty($params['username']) || !preg_match('/^\w{3,30}$/', $params['username'])) {
                        throw new \Exception(__("Please input correct username"));
                    }
                    if (empty($params['nickname'])) {
                        throw new \Exception(__('Nickname can not be empty'));
                    }
                    if (empty($params['email']) || !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new \Exception(__('Please input correct email'));
                    }
                    if ($this->model->where('username', $params['username'])->where('id', '<>', $row->id)->find()) {
                        throw new \Exception(__('Username already exists'));
                    }
                    if ($this->model->where('email', $params['email'])->where('id', '<>', $row->id)->find()) {
                        throw new \Exception(__('Email already exists'));
                    }
                    if (!empty($params['password'])) {
                        if (!preg_match('/^\S{6,30}$/', $params['password'])) {
                            throw new \Exception(__("Please input correct password"));
                        }
                        $params['salt'] = Random::alnum();
                        $params['password'] = $this->auth->getEncryptPassword($params['password'], $params['salt']);
                    } else {
                        unset($params['password'], $params['salt']);
                    }
                    if ($row->save($params) === false) {
                        throw new \Exception(__('Save failed'));
                    }

                    AuthGroupAccess::where('uid', $row->id)->delete();

                    $group = $this->request->post("group/a", []);
                    $group = array_intersect($this->childrenGroupIds, $group ?: []);
                    if (!$group) {
                        throw new \Exception(__('The parent group exceeds permission limit'));
                    }

                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $row->id, 'group_id' => $value];
                    }
                    \think\facade\Db::name('auth_group_access')->insertAll($dataset);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $grouplist = $this->auth->getGroups($row['id']);
        $groupids = [];
        foreach ($grouplist as $k => $v) {
            $groupids[] = $v['id'];
        }
        $this->view->assign("row", $row);
        $this->view->assign("groupids", $groupids);
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
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $ids = array_intersect($this->childrenAdminIds, array_filter(explode(',', $ids)));
            $childrenGroupIds = $this->childrenGroupIds;
            $adminList = $this->model->where('id', 'in', $ids)->where('id', 'in', function ($query) use ($childrenGroupIds) {
                $query->name('auth_group_access')->where('group_id', 'in', $childrenGroupIds)->field('uid');
            })->select();
            if ($adminList) {
                $deleteIds = [];
                foreach ($adminList as $k => $v) {
                    $deleteIds[] = $v->id;
                }
                $deleteIds = array_values(array_diff($deleteIds, [$this->auth->id]));
                if ($deleteIds) {
                    Db::startTrans();
                    try {
                        \app\admin\model\Admin::destroy($deleteIds);
                        AuthGroupAccess::where('uid', 'in', $deleteIds)->delete();
                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success();
                }
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('You have no permission'));
    }

    /**
     * 批量更新
     * @internal
     */
    public function multi($ids = "")
    {
        $this->error();
    }

    /**
     * 下拉搜索
     */
    public function selectpage()
    {
        $this->dataLimit = 'auth';
        $this->dataLimitField = 'id';
        return parent::selectpage();
    }
}
