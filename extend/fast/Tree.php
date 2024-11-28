<?php

namespace fast;

use think\Config;

/**
 * 通用的树型类
 * @author XiaoYao <476552238li@gmail.com>
 */
class Tree
{
    protected static $instance;
    //默认配置
    protected $config = [];
    public $options = [];

    /**
     * 生成树型结构所需要的2维数组
     * @var array
     */
    public $arr = [];

    /**
     * 生成树型结构所需修饰符号，可以换成图片
     * @var array
     */
    public $icon = array('│', '├', '└');
    public $nbsp = "&nbsp;";
    public $pidname = 'pid';

    public function __construct($options = [])
    {
        if ($config = Config::get('tree')) {
            $this->options = array_merge($this->config, $config);
        }
        $this->options = array_merge($this->config, $options);
    }

    /**
     * 初始化
     * @access public
     * @param array $options 参数
     * @return Tree
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 初始化方法
     * @param array  $arr     2维数组，例如：
     *      array(
     *      1 => array('id'=>'1','pid'=>0,'name'=>'一级栏目一'),
     *      2 => array('id'=>'2','pid'=>0,'name'=>'一级栏目二'),
     *      3 => array('id'=>'3','pid'=>1,'name'=>'二级栏目一'),
     *      4 => array('id'=>'4','pid'=>1,'name'=>'二级栏目二'),
     *      5 => array('id'=>'5','pid'=>2,'name'=>'二级栏目三'),
     *      6 => array('id'=>'6','pid'=>3,'name'=>'三级栏目一'),
     *      7 => array('id'=>'7','pid'=>3,'name'=>'三级栏目二')
     *      )
     * @param string $pidname 父字段名称
     * @param string $nbsp    空格占位符
     * @return Tree
     */
    public function init($arr = [], $pidname = null, $nbsp = null)
    {
        $this->arr = $arr;
        if (!is_null($pidname)) {
            $this->pidname = $pidname;
        }
        if (!is_null($nbsp)) {
            $this->nbsp = $nbsp;
        }
        return $this;
    }

    /**
     * 得到子级数组
     * @param int
     * @return array
     */
    public function getChild($myid)
    {
        $newarr = [];
        foreach ($this->arr as $value) {
            if (!isset($value['id'])) {
                continue;
            }
            if ($value[$this->pidname] == $myid) {
                $newarr[$value['id']] = $value;
            }
        }
        return $newarr;
    }

    /**
     * 读取指定节点的所有孩子节点
     * @param int     $myid     节点ID
     * @param boolean $withself 是否包含自身
     * @return array
     */
    public function getChildren($myid, $withself = false)
    {
        $newarr = [];
        foreach ($this->arr as $value) {
            if (!isset($value['id'])) {
                continue;
            }
            if ((string)$value[$this->pidname] == (string)$myid) {
                $newarr[] = $value;
                $newarr = array_merge($newarr, $this->getChildren($value['id']));
            } elseif ($withself && (string)$value['id'] == (string)$myid) {
                $newarr[] = $value;
            }
        }
        return $newarr;
    }

    /**
     * 读取指定节点的所有孩子节点ID
     * @param int     $myid     节点ID
     * @param boolean $withself 是否包含自身
     * @return array
     */
    public function getChildrenIds($myid, $withself = false)
    {
        $childrenlist = $this->getChildren($myid, $withself);
        $childrenids = [];
        foreach ($childrenlist as $k => $v) {
            $childrenids[] = $v['id'];
        }
        return $childrenids;
    }

    /**
     * 得到当前位置父辈数组
     * @param int
     * @return array
     */
    public function getParent($myid)
    {
        $pid = 0;
        $newarr = [];
        foreach ($this->arr as $value) {
            if (!isset($value['id'])) {
                continue;
            }
            if ($value['id'] == $myid) {
                $pid = $value[$this->pidname];
                break;
            }
        }
        if ($pid) {
            foreach ($this->arr as $value) {
                if ($value['id'] == $pid) {
                    $newarr[] = $value;
                    break;
                }
            }
        }
        return $newarr;
    }

    /**
     * 得到当前位置所有父辈数组
     * @param int
     * @param bool $withself 是否包含自己
     * @return array
     */
    public function getParents($myid, $withself = false)
    {
        $pid = 0;
        $newarr = [];
        foreach ($this->arr as $value) {
            if (!isset($value['id'])) {
                continue;
            }
            if ($value['id'] == $myid) {
                if ($withself) {
                    $newarr[] = $value;
                }
                $pid = $value[$this->pidname];
                break;
            }
        }
        if ($pid) {
            $arr = $this->getParents($pid, true);
            $newarr = array_merge($arr, $newarr);
        }
        return $newarr;
    }

    /**
     * 读取指定节点所有父类节点ID
     * @param int     $myid
     * @param boolean $withself
     * @return array
     */
    public function getParentsIds($myid, $withself = false)
    {
        $parentlist = $this->getParents($myid, $withself);
        $parentsids = [];
        foreach ($parentlist as $k => $v) {
            $parentsids[] = $v['id'];
        }
        return $parentsids;
    }

    /**
     * 树型结构Option
     * @param int    $myid        表示获得这个ID下的所有子级
     * @param string $itemtpl     条目模板 如："<option value=@id @selected @disabled>@spacer@name</option>"
     * @param mixed  $selectedids 被选中的ID，比如在做树型下拉框的时候需要用到
     * @param mixed  $disabledids 被禁用的ID，比如在做树型下拉框的时候需要用到
     * @param string $itemprefix  每一项前缀
     * @param string $toptpl      顶级栏目的模板
     * @return string
     */
    public function getTree($myid, $itemtpl = "<option value=@id @selected @disabled>@spacer@name</option>", $selectedids = '', $disabledids = '', $itemprefix = '', $toptpl = '')
    {
        $ret = '';
        $number = 1;
        $childs = $this->getChild($myid);
        if ($childs) {
            $total = count($childs);
            foreach ($childs as $value) {
                $id = $value['id'];
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $spacer = $itemprefix ? $itemprefix . $j : '';
                $selected = $selectedids && in_array($id, (is_array($selectedids) ? $selectedids : explode(',', $selectedids))) ? 'selected' : '';
                $disabled = $disabledids && in_array($id, (is_array($disabledids) ? $disabledids : explode(',', $disabledids))) ? 'disabled' : '';
                $value = array_merge($value, array('selected' => $selected, 'disabled' => $disabled, 'spacer' => $spacer));
                $value = array_combine(array_map(function ($k) {
                    return '@' . $k;
                }, array_keys($value)), $value);
                $nstr = strtr((($value["@{$this->pidname}"] == 0 || $this->getChild($id)) && $toptpl ? $toptpl : $itemtpl), $value);
                $ret .= $nstr;
                $ret .= $this->getTree($id, $itemtpl, $selectedids, $disabledids, $itemprefix . $k . $this->nbsp, $toptpl);
                $number++;
            }
        }
        return $ret;
    }

    /**
     * 树型结构UL
     * @param int    $myid        表示获得这个ID下的所有子级
     * @param string $itemtpl     条目模板 如："<li value=@id @selected @disabled>@name @childlist</li>"
     * @param string $selectedids 选中的ID
     * @param string $disabledids 禁用的ID
     * @param string $wraptag     子列表包裹标签
     * @param string $wrapattr    子列表包裹属性
     * @return string
     */
    public function getTreeUl($myid, $itemtpl, $selectedids = '', $disabledids = '', $wraptag = 'ul', $wrapattr = '')
    {
        $str = '';
        $childs = $this->getChild($myid);
        if ($childs) {
            foreach ($childs as $value) {
                $id = $value['id'];
                unset($value['child']);
                $selected = $selectedids && in_array($id, (is_array($selectedids) ? $selectedids : explode(',', $selectedids))) ? 'selected' : '';
                $disabled = $disabledids && in_array($id, (is_array($disabledids) ? $disabledids : explode(',', $disabledids))) ? 'disabled' : '';
                $value = array_merge($value, array('selected' => $selected, 'disabled' => $disabled));
                $value = array_combine(array_map(function ($k) {
                    return '@' . $k;
                }, array_keys($value)), $value);
                $nstr = strtr($itemtpl, $value);
                $childdata = $this->getTreeUl($id, $itemtpl, $selectedids, $disabledids, $wraptag, $wrapattr);
                $childlist = $childdata ? "<{$wraptag} {$wrapattr}>" . $childdata . "</{$wraptag}>" : "";
                $str .= strtr($nstr, array('@childlist' => $childlist));
            }
        }
        return $str;
    }

    /**
     * 菜单数据
     * @param int    $myid
     * @param string $itemtpl
     * @param mixed  $selectedids
     * @param mixed  $disabledids
     * @param string $wraptag
     * @param string $wrapattr
     * @param int    $deeplevel
     * @return string
     */
    public function getTreeMenu($myid, $itemtpl, $selectedids = '', $disabledids = '', $wraptag = 'ul', $wrapattr = '', $deeplevel = 0)
    {
        $str = '';
        $childs = $this->getChild($myid);
        if ($childs) {
            foreach ($childs as $value) {
                $id = $value['id'];
                unset($value['child']);
                $selected = in_array($id, (is_array($selectedids) ? $selectedids : explode(',', $selectedids))) ? 'selected' : '';
                $disabled = in_array($id, (is_array($disabledids) ? $disabledids : explode(',', $disabledids))) ? 'disabled' : '';
                $value = array_merge($value, array('selected' => $selected, 'disabled' => $disabled));
                $value = array_combine(array_map(function ($k) {
                    return '@' . $k;
                }, array_keys($value)), $value);
                $bakvalue = array_intersect_key($value, array_flip(['@url', '@caret', '@class']));
                $value = array_diff_key($value, $bakvalue);
                $nstr = strtr($itemtpl, $value);
                $value = array_merge($value, $bakvalue);
                $childdata = $this->getTreeMenu($id, $itemtpl, $selectedids, $disabledids, $wraptag, $wrapattr, $deeplevel + 1);
                $childlist = $childdata ? "<{$wraptag} {$wrapattr}>" . $childdata . "</{$wraptag}>" : "";
                $childlist = strtr($childlist, array('@class' => $childdata ? 'last' : ''));
                $value = array(
                    '@childlist' => $childlist,
                    '@url'       => $childdata || !isset($value['@url']) ? "javascript:;" : $value['@url'],
                    '@addtabs'   => $childdata || !isset($value['@url']) ? "" : (stripos($value['@url'], "?") !== false ? "&" : "?") . "ref=addtabs",
                    '@caret'     => ($childdata && (!isset($value['@badge']) || !$value['@badge']) ? '<i class="fa fa-angle-left"></i>' : ''),
                    '@badge'     => $value['@badge'] ?? '',
                    '@class'     => ($selected ? ' active' : '') . ($disabled ? ' disabled' : '') . ($childdata ? ' treeview' . (config('fastadmin.show_submenu') ? ' treeview-open' : '') : ''),
                );
                $str .= strtr($nstr, $value);
            }
        }
        return $str;
    }

    /**
     * 特殊
     * @param integer $myid        要查询的ID
     * @param string  $itemtpl1    第一种HTML代码方式
     * @param string  $itemtpl2    第二种HTML代码方式
     * @param mixed   $selectedids 默认选中
     * @param mixed   $disabledids 禁用
     * @param string  $itemprefix  前缀
     * @return string
     */
    public function getTreeSpecial($myid, $itemtpl1, $itemtpl2, $selectedids = 0, $disabledids = 0, $itemprefix = '')
    {
        $ret = '';
        $number = 1;
        $childs = $this->getChild($myid);
        if ($childs) {
            $total = count($childs);
            foreach ($childs as $id => $value) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $spacer = $itemprefix ? $itemprefix . $j : '';
                $selected = $selectedids && in_array($id, (is_array($selectedids) ? $selectedids : explode(',', $selectedids))) ? 'selected' : '';
                $disabled = $disabledids && in_array($id, (is_array($disabledids) ? $disabledids : explode(',', $disabledids))) ? 'disabled' : '';
                $value = array_merge($value, array('selected' => $selected, 'disabled' => $disabled, 'spacer' => $spacer));
                $value = array_combine(array_map(function ($k) {
                    return '@' . $k;
                }, array_keys($value)), $value);
                $nstr = strtr(!isset($value['@disabled']) || !$value['@disabled'] ? $itemtpl1 : $itemtpl2, $value);

                $ret .= $nstr;
                $ret .= $this->getTreeSpecial($id, $itemtpl1, $itemtpl2, $selectedids, $disabledids, $itemprefix . $k . $this->nbsp);
                $number++;
            }
        }
        return $ret;
    }

    /**
     *
     * 获取树状数组
     * @param string $myid       要查询的ID
     * @param string $itemprefix 前缀
     * @return array
     */
    public function getTreeArray($myid, $itemprefix = '')
    {
        $childs = $this->getChild($myid);
        $n = 0;
        $data = [];
        $number = 1;
        if ($childs) {
            $total = count($childs);
            foreach ($childs as $id => $value) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $spacer = $itemprefix ? $itemprefix . $j : '';
                $value['spacer'] = $spacer;
                $data[$n] = $value;
                $data[$n]['childlist'] = $this->getTreeArray($id, $itemprefix . $k . $this->nbsp);
                $n++;
                $number++;
            }
        }
        return $data;
    }

    /**
     * 将getTreeArray的结果返回为二维数组
     * @param array  $data
     * @param string $field
     * @return array
     */
    public function getTreeList($data = [], $field = 'name')
    {
        $arr = [];
        foreach ($data as $k => $v) {
            $childlist = $v['childlist'] ?? [];
            unset($v['childlist']);
            $v[$field] = $v['spacer'] . ' ' . $v[$field];
            $v['haschild'] = $childlist ? 1 : 0;
            if ($v['id']) {
                $arr[] = $v;
            }
            if ($childlist) {
                $arr = array_merge($arr, $this->getTreeList($childlist, $field));
            }
        }
        return $arr;
    }
}
