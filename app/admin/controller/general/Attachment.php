<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\model\Attachment as AttachmentModel;

/**
 * 附件管理
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于管理上传到服务器或第三方存储的数据
 */
class Attachment extends Backend
{

    /**
     * @var AttachmentModel
     */
    protected $model = null;

    protected $searchFields = 'id,filename,url';
    protected $noNeedRight = ['classify'];

    public function _initialize(): void
    {
        parent::_initialize();
        $this->model = model('app\common\model\Attachment');
        $this->view->assign("mimetypeList", AttachmentModel::getMimetypeList());
        $this->view->assign("categoryList", AttachmentModel::getCategoryList());
        $this->assignconfig("categoryList", AttachmentModel::getCategoryList());
    }

    /**
     * 查看
     *
     * @return void
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $mimetypeQuery = [];
            $filter = $this->request->request('filter', '');
            $filterArr = (array)json_decode($filter, true);
            if (isset($filterArr['category']) && $filterArr['category'] == 'unclassed') {
                $filterArr['category'] = ',unclassed';
                $this->request->get(['filter' => json_encode(array_diff_key($filterArr, ['category' => '']))]);
            }
            if (isset($filterArr['mimetype']) && preg_match("/(\/|\,|\*)/", $filterArr['mimetype'])) {
                $mimetype = $filterArr['mimetype'];
                $filterArr = array_diff_key($filterArr, ['mimetype' => '']);
                $mimetypeQuery = function ($query) use ($mimetype) {
                    $mimetypeArr = array_filter(explode(',', $mimetype));
                    foreach ($mimetypeArr as $index => $item) {
                        $query->whereOr('mimetype', 'like', '%' . str_replace("/*", "/", $item) . '%');
                    }
                };
            }
            $this->request->get(['filter' => json_encode($filterArr)]);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($mimetypeQuery)
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            $cdnurl = preg_replace("/\/(\w+)\.php$/i", '', $this->request->root());
            foreach ($list as $k => &$v) {
                $v['fullurl'] = ($v['storage'] == 'local' ? $cdnurl : $this->view->config['upload']['cdnurl']) . $v['url'];
            }
            unset($v);
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 选择附件
     *
     * @return void
     */
    public function select()
    {
        if ($this->request->isAjax()) {
            return $this->index();
        }
        $mimetype = $this->request->get('mimetype', '');
        $mimetype = substr($mimetype, -1) === '/' ? $mimetype . '*' : $mimetype;
        $this->view->assign('mimetype', $mimetype);
        return $this->view->fetch();
    }

    /**
     * 添加
     *
     * @return void
     */
    public function add()
    {
        if ($this->request->isAjax()) {
            $this->error();
        }
        return $this->view->fetch();
    }

    /**
     * 删除附件
     * 
     * @param array $ids
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $attachmentlist = $this->model->where('id', 'in', $ids)->select();
            foreach ($attachmentlist as $attachment) {
                if ($attachment->storage == 'local') {
                    $attachmentFile = root_path() . 'public' . $attachment->url;
                    if (is_file($attachmentFile)) {
                        @unlink($attachmentFile);
                    }
                }
                $attachment->delete();
            }
            $this->success();
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 归类
     *
     * @return void
     */
    public function classify()
    {
        if (!$this->auth->check('general/attachment/edit')) {
            $this->error(__('You have no permission'), '');
        }
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $category = $this->request->post('category', '');
        $ids = $this->request->post('ids');
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $categoryList = AttachmentModel::getCategoryList();
        if ($category && !isset($categoryList[$category])) {
            $this->error(__('Category not found'));
        }
        $category = $category == 'unclassed' ? '' : $category;
        AttachmentModel::where('id', 'in', $ids)->update(['category' => $category]);
        $this->success();
    }

}

