<?php

namespace app\common\library;

use fast\Tree;
use think\Db;
use think\Exception;
use think\Model;

/**
 * SelectPage 查询构建器
 */
class SelectPage
{
    /**
     * 模型实例
     * @var Model
     */
    protected $model;

    /**
     * 允许显示的字段
     * @var array|string
     */
    protected $selectpageFields = '*';

    /**
     * 数据限制模式
     * @var bool|string
     */
    protected $dataLimit = false;

    /**
     * 数据限制字段
     * @var string
     */
    protected $dataLimitField = 'admin_id';

    /**
     * 允许的表字段列表
     * @var array
     */
    protected $allowedFields = [];

    /**
     * 允许的操作符（ThinkPHP Builder::$exp 的键和值(不包含exp)，去重后保留小写）
     * @var array
     */
    protected static $allowedOperators = [
        'eq', 'neq', 'gt', 'egt', 'lt', 'elt',
        '=', '<>', '>', '>=', '<', '<=',
        'like', 'not like', 'notlike',
        'in', 'not in', 'notin',
        'between', 'not between', 'notbetween',
        'null', 'not null', 'notnull',
        'exists', 'not exists', 'notexists',
        '> time', '< time', '>= time', '<= time',
        'between time', 'not between time', 'notbetween time',
    ];

    /**
     * 允许排序的字段
     * @var array
     */
    protected $orderFields = [];

    /**
     * @param Model  $model  模型实例
     * @param string $fields SelectPage可显示的字段
     */
    public function __construct(Model $model, $fields = '*')
    {
        $this->model = $model;
        $this->selectpageFields = $fields;
        $this->allowedFields = array_map('strtolower', $model->getTableFields());
        $this->orderFields = $this->allowedFields;
    }

    /**
     * 数据限制的ID集合
     * @var array
     */
    protected $dataLimitIds = [];

    /**
     * 设置数据限制
     * @param bool|string $dataLimit      auth/personal/false
     * @param string      $dataLimitField 限制字段
     * @param array       $dataLimitIds   允许的ID列表
     * @return $this
     */
    public function setDataLimit($dataLimit, $dataLimitField = 'admin_id', array $dataLimitIds = [])
    {
        $this->dataLimit = $dataLimit;
        $this->dataLimitField = $dataLimitField;
        $this->dataLimitIds = $dataLimitIds;

        return $this;
    }

    /**
     * 应用数据限制条件（每次构建新查询链前调用）
     * ThinkPHP 的 count()/select() 执行后会清空 model options，
     * 所以需要在每次查询前重新注入 dataLimit 条件。
     * @return $this
     */
    protected function applyDataLimit()
    {
        if ($this->dataLimit) {
            $this->model->where($this->dataLimitField, 'in', $this->dataLimitIds);
        }
        return $this;
    }

    /**
     * 执行查询
     * @param array $params 请求参数
     * @return array ['list' => [...], 'total' => int]
     */
    public function execute(array $params)
    {
        $keywordWords = $this->getArrayParam($params, 'q_word');
        $page = $params['pageNumber'] ?? 1;
        $pageSize = $params['pageSize'] ?? 10;
        $andor = strtoupper($params['andOr'] ?? 'AND');
        $orderBy = $this->getArrayParam($params, 'orderBy');
        $showField = $params['showField'] ?? 'name';
        $keyField = $params['keyField'] ?? '';
        $keyValue = $params['keyValue'] ?? null;
        $searchField = $this->getArrayParam($params, 'searchField');
        $custom = $this->getArrayParam($params, 'custom');
        $isTree = (bool)($params['isTree'] ?? 0);
        $isHtml = (bool)($params['isHtml'] ?? 0);

        // 树形模式强制参数
        if ($isTree) {
            $keywordWords = [];
            $pageSize = 999999;
        }

        // 验证字段
        $this->validateField($showField);
        $this->validateField($keyField);

        // 验证搜索字段
        foreach ($searchField as $f) {
            $this->validateField($f);
        }

        // 验证自定义条件的字段和操作符
        $this->validateCustomConditions($custom);

        // 构建排序
        $order = $this->buildOrder($orderBy);

        // 构建查询条件
        $where = $this->buildWhere(
            $keywordWords,
            $andor,
            $showField,
            $searchField,
            $custom,
            $keyField,
            $keyValue
        );

        // 执行总数统计
        $total = $this->applyDataLimit()
            ->model->where($where)
            ->count();

        if ($total <= 0) {
            return ['list' => [], 'total' => 0];
        }

        // 排序处理
        if ($keyValue !== null && $keyField) {
            $this->applyPrimaryKeyOrder($keyField, $keyValue);
        } else {
            $this->model->order($order);
        }

        // 执行查询（count()会清空options，需重新应用dataLimit）
        $dataList = $this->applyDataLimit()
            ->model->where($where)
            ->page($page, $pageSize)
            ->select();

        // 构建结果集
        $list = $this->buildResultList($dataList, $showField, $keyField);

        // 树形结构处理
        if ($isTree && !$keyValue) {
            $list = $this->buildTreeList($list, $showField, $isHtml);
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 标准化字段为数组（支持逗号分隔字符串）
     */
    protected function normalizeField($field): array
    {
        if (is_array($field)) {
            return $field;
        }
        if (is_string($field) && strpos($field, ',') !== false) {
            return array_map('trim', explode(',', $field));
        }
        return $field !== '' ? [$field] : [];
    }

    /**
     * 获取数组参数
     */
    protected function getArrayParam(array $params, string $key): array
    {
        $value = $params[$key] ?? [];
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && strpos($value, ',') !== false) {
            return array_map('trim', explode(',', $value));
        }
        if ($value === '' || $value === null) {
            return [];
        }
        return [$value];
    }

    /**
     * 验证字段名是否在允许列表中
     */
    protected function validateField(string $field)
    {
        $field = strtolower($field);
        if (!in_array($field, $this->allowedFields, true)) {
            throw new Exception('Invalid parameters');
        }
    }

    /**
     * 验证自定义搜索条件
     */
    protected function validateCustomConditions(array $custom)
    {
        foreach ($custom as $k => $v) {
            $field = strtolower($k);
            if (!in_array($field, $this->allowedFields, true)) {
                throw new Exception('Invalid parameters');
            }
            // 如果操作符是数组形式传入，校验操作符合法性
            if (is_array($v) && count($v) >= 2) {
                $operator = strtolower(trim($v[0]));
                if (!in_array($operator, self::$allowedOperators, true)) {
                    throw new Exception('Invalid parameters');
                }
            }
        }
    }

    /**
     * 构建排序
     */
    protected function buildOrder(array $orderBy): array
    {
        $order = [];
        foreach ($orderBy as $v) {
            if (!isset($v[0], $v[1])) {
                continue;
            }
            $field = strtolower($v[0]);
            $direction = strtoupper($v[1]) === 'ASC' ? 'ASC' : 'DESC';
            if (in_array($field, $this->orderFields, true)) {
                $order[$field] = $direction;
            }
        }
        return $order;
    }

    /**
     * 构建查询条件
     */
    protected function buildWhere(
        array  $keywordWords,
        string $andor,
        string $showField,
        array  $searchField,
        array  $custom,
        string $keyField,
               $keyValue
    )
    {
        // 如果有 keyValue，按主键值精确查询
        if ($keyValue !== null && $keyField) {
            return [$keyField => ['in', is_array($keyValue) ? $keyValue : explode(',', (string)$keyValue)]];
        }

        return function ($query) use ($keywordWords, $andor, $showField, $searchField, $custom) {
            // 关键词搜索
            $searchFields = $this->resolveSearchFields($searchField, $showField, $andor);
            $words = array_filter(array_unique($keywordWords));
            if (!empty($words)) {
                if (count($words) === 1) {
                    $query->where($searchFields, 'like', '%' . reset($words) . '%');
                } else {
                    $query->where(function ($query) use ($words, $searchFields) {
                        foreach ($words as $word) {
                            $query->whereOr($searchFields, 'like', '%' . $word . '%');
                        }
                    });
                }
            }

            // 自定义条件
            foreach ($custom as $k => $v) {
                if (is_array($v) && count($v) >= 2) {
                    $operator = strtolower(trim($v[0]));
                    $value = $v[1];
                    $query->where(strtolower($k), $operator, $value);
                } else {
                    $query->where(strtolower($k), '=', $v);
                }
            }
        };
    }

    /**
     * 解析搜索字段
     */
    protected function resolveSearchFields(array $searchField, string $showField, string $andor): string
    {
        // 过滤掉不在允许列表中的字段
        $validFields = [];
        $inputFields = array_filter(array_map('trim', $searchField));

        foreach ($inputFields as $field) {
            $lowerField = strtolower($field);
            if (in_array($lowerField, $this->allowedFields, true)) {
                $validFields[] = $lowerField;
            }
        }

        if (empty($validFields)) {
            $lowerShow = strtolower($showField);
            if (in_array($lowerShow, $this->allowedFields, true)) {
                return $lowerShow;
            }
            return 'id';
        }

        $logic = $andor === 'AND' ? '&' : '|';
        return implode($logic, $validFields);
    }

    /**
     * 应用主键排序
     */
    protected function applyPrimaryKeyOrder(string $keyField, $keyValue)
    {
        $values = is_array($keyValue) ? $keyValue : explode(',', (string)$keyValue);
        $values = array_unique(array_filter(array_map(function ($v) {
            return trim((string)$v);
        }, $values)));

        if (empty($values)) {
            return;
        }

        $quotedValues = implode(',', array_map(function ($v) {
            return Db::quote($v);
        }, $values));

        $this->model->orderRaw("FIELD(`{$keyField}`, {$quotedValues})");
    }

    /**
     * 构建结果列表
     */
    protected function buildResultList($dataList, string $showField, string $keyField): array
    {
        $list = [];
        $fields = $this->resolveSelectpageFields();

        foreach ($dataList as $item) {
            $row = $item instanceof Model ? $item->toArray() : (array)$item;

            // 移除敏感字段
            unset($row['password'], $row['salt']);

            if ($this->selectpageFields === '*') {
                $result = [
                    $keyField  => $row[$keyField] ?? '',
                    $showField => $row[$showField] ?? '',
                ];
            } else {
                $result = array_intersect_key($row, array_flip($fields));
            }

            // 添加父级ID
            $result['pid'] = $row['pid'] ?? ($row['parent_id'] ?? 0);

            // HTML 转义
            $result = array_map(function ($value) {
                return $value === null ? '' : htmlentities((string)$value, ENT_QUOTES, 'UTF-8');
            }, $result);

            $list[] = $result;
        }

        return $list;
    }

    /**
     * 构建树形列表
     */
    protected function buildTreeList(array $list, string $showField, bool $isHtml): array
    {
        $tree = Tree::instance();
        $tree->init($list, 'pid');
        $result = $tree->getTreeList($tree->getTreeArray(0), $showField);

        if (!$isHtml) {
            foreach ($result as &$item) {
                $item = str_replace('&nbsp;', ' ', $item);
            }
            unset($item);
        }

        return $result;
    }

    /**
     * 解析 SelectPage 显示字段
     */
    protected function resolveSelectpageFields(): array
    {
        if (is_array($this->selectpageFields)) {
            return $this->selectpageFields;
        }
        if ($this->selectpageFields && $this->selectpageFields !== '*') {
            return explode(',', $this->selectpageFields);
        }
        return [];
    }
}
