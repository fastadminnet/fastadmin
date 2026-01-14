<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $rows = $list->items();
            addtion($rows, [['field' => 'group_id', 'display' => 'group_name', 'model' => \app\admin\model\UserGroup::class]]);
            $result = array("total" => $list->total(), "rows" => $rows);

            return json($result);
        }
        $this->assignconfig('multi_delete_user', config('fastadmin.multi_delete_user'));
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
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
        $ids = array_filter(explode(',', $ids));
        if (!(config('fastadmin.multi_delete_user') ?: false)) {
            if (count($ids) > 1) {
                $this->error(__('Multi delete is not allowed'));
            }
            $row = $this->model->get($ids);
            $this->modelValidate = true;
            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $result = Auth::instance()->delete($row['id']);
            $count = $result ? 1 : 0;
            if ($count) {
                $this->success();
            }
        } else {
            //允许批量删除
            $list = $this->model->where('id', 'in', $ids)->select();
            $count = 0;
            foreach ($list as $item) {
                $result = Auth::instance()->delete($item['id']);
                if ($result) {
                    $count++;
                }
            }
        }
        if ($count) {
            $this->success(__('%s rows deleted succeeded, %s rows deleted failed', $count, count($ids) - $count));
        } else {
            $this->error(__('No rows were deleted'));
        }
    }

}
