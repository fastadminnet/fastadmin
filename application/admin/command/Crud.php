<?php

namespace app\admin\command;

use fast\Form;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\exception\ErrorException;
use think\exception\PDOException;
use think\Lang;
use think\Loader;

class Crud extends Command
{
    protected $stubList = [];

    protected $internalKeywords = [
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor'
    ];

    /**
     * 受保护的系统表, crud不会生效
     */
    protected $systemTables = [
        'admin',
        'admin_log',
        'auth_group',
        'auth_group_access',
        'auth_rule',
        'attachment',
        'config',
        'category',
        'ems',
        'sms',
        'user',
        'user_group',
        'user_rule',
        'user_score_log',
        'user_token',
    ];

    /**
     * Selectpage搜索字段关联
     */
    protected $fieldSelectpageMap = [
        'nickname' => ['user_id', 'user_ids', 'admin_id', 'admin_ids']
    ];

    /**
     * Enum类型识别为单选框的结尾字符,默认会识别为单选下拉列表
     */
    protected $enumRadioSuffix = ['data', 'state', 'status'];

    /**
     * Set类型识别为复选框的结尾字符,默认会识别为多选下拉列表
     */
    protected $setCheckboxSuffix = ['data', 'state', 'status'];

    /**
     * Int类型识别为日期时间的结尾字符,默认会识别为日期文本框
     */
    protected $intDateSuffix = ['time'];

    /**
     * 开关后缀
     */
    protected $switchSuffix = ['switch'];

    /**
     * 富文本后缀
     */
    protected $editorSuffix = ['content'];

    /**
     * 城市后缀
     */
    protected $citySuffix = ['city'];

    /**
     * 时间区间后缀
     */
    protected $rangeSuffix = ['range'];

    /**
     * JSON后缀
     */
    protected $jsonSuffix = ['json', 'array'];

    /**
     * 标签后缀
     */
    protected $tagSuffix = ['tag', 'tags'];

    /**
     * Selectpage对应的后缀
     */
    protected $selectpageSuffix = ['_id', '_ids'];

    /**
     * Selectpage多选对应的后缀
     */
    protected $selectpagesSuffix = ['_ids'];

    /**
     * 以指定字符结尾的字段格式化函数
     */
    protected $fieldFormatterSuffix = [
        'status' => ['type' => ['varchar', 'enum'], 'name' => 'status'],
        'icon'   => 'icon',
        'flag'   => 'flag',
        'url'    => 'url',
        'image'  => 'image',
        'images' => 'images',
        'file'   => 'file',
        'files'  => 'files',
        'avatar' => 'image',
        'switch' => 'toggle',
        'tag'    => 'flag',
        'tags'   => 'flag',
        'time'   => ['type' => ['int', 'bigint', 'timestamp'], 'name' => 'datetime'],
    ];

    /**
     * 识别为图片字段
     */
    protected $imageField = ['image', 'images', 'avatar', 'avatars'];

    /**
     * 识别为文件字段
     */
    protected $fileField = ['file', 'files'];

    /**
     * 保留字段
     */
    protected $reservedField = ['admin_id'];

    /**
     * 排除字段
     */
    protected $ignoreFields = [];

    /**
     * 排序字段
     */
    protected $sortField = 'weigh';

    /**
     * 筛选字段
     * @var string
     */
    protected $headingFilterField = 'status';

    /**
     * 添加时间字段
     * @var string
     */
    protected $createTimeField = 'createtime';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTimeField = 'updatetime';

    /**
     * 软删除时间字段
     * @var string
     */
    protected $deleteTimeField = 'deletetime';

    /**
     * 编辑器的Class
     */
    protected $editorClass = 'editor';

    /**
     * langList的key最长字节数
     */
    protected $fieldMaxLen = 0;

    protected function configure()
    {
        $this
            ->setName('crud')
            ->addOption('table', 't', Option::VALUE_REQUIRED, 'table name without prefix', null)
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, 'controller name', null)
            ->addOption('model', 'm', Option::VALUE_OPTIONAL, 'model name', null)
            ->addOption('fields', 'i', Option::VALUE_OPTIONAL, 'model visible fields', null)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, 'force override or force delete,without tips', null)
            ->addOption('local', 'l', Option::VALUE_OPTIONAL, 'local model', 1)
            ->addOption('import', 'a', Option::VALUE_OPTIONAL, 'enable import function', 0)
            ->addOption('relation', 'r', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation table name without prefix', null)
            ->addOption('relationmodel', 'e', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation model name', null)
            ->addOption('relationforeignkey', 'k', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation foreign key', null)
            ->addOption('relationprimarykey', 'p', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation primary key', null)
            ->addOption('relationfields', 's', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation table fields', null)
            ->addOption('relationmode', 'o', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation table mode,hasone/belongsto/hasmany', null)
            ->addOption('relationcontroller', 'w', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation table controller,only work at hasmany mode', null)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, 'delete all files generated by CRUD', null)
            ->addOption('menu', 'u', Option::VALUE_OPTIONAL, 'create menu when CRUD completed', null)
            ->addOption('setcheckboxsuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate checkbox component with suffix', null)
            ->addOption('enumradiosuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate radio component with suffix', null)
            ->addOption('imagefield', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate image component with suffix', null)
            ->addOption('filefield', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate file component with suffix', null)
            ->addOption('intdatesuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate date component with suffix', null)
            ->addOption('switchsuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate switch component with suffix', null)
            ->addOption('citysuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate citypicker component with suffix', null)
            ->addOption('jsonsuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate fieldlist component with suffix', null)
            ->addOption('tagsuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate tag component with suffix', null)
            ->addOption('editorsuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate editor component with suffix', null)
            ->addOption('selectpagesuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate selectpage component with suffix', null)
            ->addOption('selectpagessuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate multiple selectpage component with suffix', null)
            ->addOption('ignorefields', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'ignore fields', null)
            ->addOption('sortfield', null, Option::VALUE_OPTIONAL, 'sort field', null)
            ->addOption('headingfilterfield', null, Option::VALUE_OPTIONAL, 'heading filter field', null)
            ->addOption('fixedcolumns', null, Option::VALUE_OPTIONAL, 'fixed columns', null)
            ->addOption('editorclass', null, Option::VALUE_OPTIONAL, 'automatically generate editor class', null)
            ->addOption('db', null, Option::VALUE_OPTIONAL, 'database config name', 'database')
            ->setDescription('Build CRUD controller and model from table');
    }

    protected function execute(Input $input, Output $output)
    {
        $adminPath = dirname(__DIR__) . DS;
        //数据库
        $db = $input->getOption('db');
        //表名
        $table = $input->getOption('table') ?: '';
        //自定义控制器
        $controller = $input->getOption('controller');
        //自定义模型
        $model = $input->getOption('model');
        $model = $model ? $model : $controller;
        //验证器类
        $validate = $model;
        //自定义显示字段
        $fields = $input->getOption('fields');
        //强制覆盖
        $force = $input->getOption('force');
        //是否为本地model,为0时表示为全局model将会把model放在app/common/model中
        $local = $input->getOption('local');
        //是否启用导入功能
        $import = $input->getOption('import');

        if (!$table) {
            throw new Exception('table name can\'t empty');
        }


        //是否生成菜单
        $menu = $input->getOption("menu");
        //关联表
        $relation = $input->getOption('relation');
        //自定义关联表模型
        $relationModels = $input->getOption('relationmodel');
        //模式
        $relationMode = $mode = $input->getOption('relationmode');
        //外键
        $relationForeignKey = $input->getOption('relationforeignkey');
        //主键
        $relationPrimaryKey = $input->getOption('relationprimarykey');
        //关联表显示字段
        $relationFields = $input->getOption('relationfields');
        //关联表显示字段
        $relationController = $input->getOption('relationcontroller');
        //复选框后缀
        $setcheckboxsuffix = $input->getOption('setcheckboxsuffix');
        //单选框后缀
        $enumradiosuffix = $input->getOption('enumradiosuffix');
        //图片后缀
        $imagefield = $input->getOption('imagefield');
        //文件后缀
        $filefield = $input->getOption('filefield');
        //标签后缀
        $tagsuffix = $input->getOption('tagsuffix');
        //日期后缀
        $intdatesuffix = $input->getOption('intdatesuffix');
        //开关后缀
        $switchsuffix = $input->getOption('switchsuffix');
        //富文本编辑器
        $editorsuffix = $input->getOption('editorsuffix');
        //城市后缀
        $citysuffix = $input->getOption('citysuffix');
        //JSON配置后缀
        $jsonsuffix = $input->getOption('jsonsuffix');
        //selectpage后缀
        $selectpagesuffix = $input->getOption('selectpagesuffix');
        //selectpage多选后缀
        $selectpagessuffix = $input->getOption('selectpagessuffix');
        //排除字段
        $ignoreFields = $input->getOption('ignorefields');
        //排序字段
        $sortfield = $input->getOption('sortfield');
        //顶部筛选过滤字段
        $headingfilterfield = $input->getOption('headingfilterfield');
        //固定列数量
        $fixedcolumns = $input->getOption('fixedcolumns');
        //编辑器Class
        $editorclass = $input->getOption('editorclass');
        if ($setcheckboxsuffix) {
            $this->setCheckboxSuffix = $setcheckboxsuffix;
        }
        if ($enumradiosuffix) {
            $this->enumRadioSuffix = $enumradiosuffix;
        }
        if ($imagefield) {
            $this->imageField = $imagefield;
        }
        if ($filefield) {
            $this->fileField = $filefield;
        }
        if ($tagsuffix) {
            $this->tagSuffix = $tagsuffix;
        }
        if ($intdatesuffix) {
            $this->intDateSuffix = $intdatesuffix;
        }
        if ($switchsuffix) {
            $this->switchSuffix = $switchsuffix;
        }
        if ($editorsuffix) {
            $this->editorSuffix = $editorsuffix;
        }
        if ($citysuffix) {
            $this->citySuffix = $citysuffix;
        }
        if ($jsonsuffix) {
            $this->jsonSuffix = $jsonsuffix;
        }
        if ($selectpagesuffix) {
            $this->selectpageSuffix = $selectpagesuffix;
        }
        if ($selectpagessuffix) {
            $this->selectpagesSuffix = $selectpagessuffix;
        }
        if ($ignoreFields) {
            $this->ignoreFields = $ignoreFields;
        }
        if ($editorclass) {
            $this->editorClass = $editorclass;
        }
        if ($sortfield) {
            $this->sortField = $sortfield;
        }
        if ($headingfilterfield) {
            $this->headingFilterField = $headingfilterfield;
        }

        $this->reservedField = array_merge($this->reservedField, [$this->createTimeField, $this->updateTimeField, $this->deleteTimeField]);

        $dbconnect = Db::connect($db);
        $dbname = Config::get($db . '.database');
        $prefix = Config::get($db . '.prefix');

        //系统表无法生成，防止后台错乱
        if (in_array(str_replace($prefix, "", $table), $this->systemTables)) {
            throw new Exception('system table can\'t be crud');
        }

        //模块
        $moduleName = 'admin';
        $modelModuleName = $local ? $moduleName : 'common';
        $validateModuleName = $local ? $moduleName : 'common';

        //检查主表
        $modelName = $table = stripos($table, $prefix) === 0 ? substr($table, strlen($prefix)) : $table;
        $modelTableType = 'table';
        $modelTableTypeName = $modelTableName = $modelName;
        $modelTableInfo = null;
        if (!$input->getOption('delete')) {
            $modelTableInfo = $dbconnect->query("SHOW TABLE STATUS LIKE '{$modelTableName}'", [], true);
            if (!$modelTableInfo) {
                $modelTableType = 'name';
                $modelTableName = $prefix . $modelName;
                $modelTableInfo = $dbconnect->query("SHOW TABLE STATUS LIKE '{$modelTableName}'", [], true);
                if (!$modelTableInfo) {
                    throw new Exception("table not found");
                }
            }
            $modelTableInfo = $modelTableInfo[0];
        }

        $relations = [];
        //检查关联表
        if ($relation) {
            $relationArr = $relation;
            $relations = [];

            foreach ($relationArr as $index => $relationTable) {
                $relationName = stripos($relationTable, $prefix) === 0 ? substr($relationTable, strlen($prefix)) : $relationTable;
                $relationTableType = 'table';
                $relationTableTypeName = $relationTableName = $relationName;
                $relationTableInfo = $dbconnect->query("SHOW TABLE STATUS LIKE '{$relationTableName}'", [], true);
                if (!$relationTableInfo) {
                    $relationTableType = 'name';
                    $relationTableName = $prefix . $relationName;
                    $relationTableInfo = $dbconnect->query("SHOW TABLE STATUS LIKE '{$relationTableName}'", [], true);
                    if (!$relationTableInfo) {
                        throw new Exception("relation table not found");
                    }
                }
                $relationTableInfo = $relationTableInfo[0];
                $relationModel = $relationModels[$index] ?? '';

                list($relationNamespace, $relationName, $relationFile) = $this->getModelData($modelModuleName, $relationModel, $relationName);

                $relations[] = [
                    //关联表基础名
                    'relationName'          => $relationName,
                    //关联表类命名空间
                    'relationNamespace'     => $relationNamespace,
                    //关联模型名
                    'relationModel'         => $relationModel,
                    //关联文件
                    'relationFile'          => $relationFile,
                    //关联表名称
                    'relationTableName'     => $relationTableName,
                    //关联表信息
                    'relationTableInfo'     => $relationTableInfo,
                    //关联模型表类型(name或table)
                    'relationTableType'     => $relationTableType,
                    //关联模型表类型名称
                    'relationTableTypeName' => $relationTableTypeName,
                    //关联模式
                    'relationFields'        => isset($relationFields[$index]) ? explode(',', $relationFields[$index]) : [],
                    //关联模式
                    'relationMode'          => isset($relationMode[$index]) ? $relationMode[$index] : 'belongsto',
                    //关联模型控制器
                    'relationController'    => isset($relationController[$index]) ? $relationController[$index] : '',
                    //关联表外键
                    'relationForeignKey'    => isset($relationForeignKey[$index]) ? $relationForeignKey[$index] : '',
                    //关联表主键
                    'relationPrimaryKey'    => isset($relationPrimaryKey[$index]) ? $relationPrimaryKey[$index] : '',
                ];
            }
        }

        //根据表名匹配对应的Fontawesome图标
        $iconPath = ROOT_PATH . str_replace('/', DS, '/public/assets/libs/font-awesome/less/variables.less');
        $iconName = is_file($iconPath) && stripos(file_get_contents($iconPath), '@fa-var-' . $table . ':') ? 'fa fa-' . $table : 'fa fa-circle-o';

        //控制器
        list($controllerNamespace, $controllerName, $controllerFile, $controllerArr) = $this->getControllerData($moduleName, $controller, $table);
        //模型
        list($modelNamespace, $modelName, $modelFile, $modelArr) = $this->getModelData($modelModuleName, $model, $table);
        //验证器
        list($validateNamespace, $validateName, $validateFile, $validateArr) = $this->getValidateData($validateModuleName, $validate, $table);

        //处理基础文件名，取消所有下划线并转换为小写
        $baseNameArr = $controllerArr;
        $baseFileName = Loader::parseName(array_pop($baseNameArr), 0);
        array_push($baseNameArr, $baseFileName);
        $controllerBaseName = strtolower(implode(DS, $baseNameArr));
        //$controllerUrl = strtolower(implode('/', $baseNameArr));
        $controllerUrl = $this->getControllerUrl($moduleName, $baseNameArr);

        //视图文件
        $viewArr = $controllerArr;
        $lastValue = array_pop($viewArr);
        $viewArr[] = Loader::parseName($lastValue, 0);
        array_unshift($viewArr, 'view');
        $viewDir = $adminPath . strtolower(implode(DS, $viewArr)) . DS;

        //最终将生成的文件路径
        $javascriptFile = ROOT_PATH . 'public' . DS . 'assets' . DS . 'js' . DS . 'backend' . DS . $controllerBaseName . '.js';
        $addFile = $viewDir . 'add.html';
        $editFile = $viewDir . 'edit.html';
        $indexFile = $viewDir . 'index.html';
        $recyclebinFile = $viewDir . 'recyclebin.html';
        $langFile = $adminPath . 'lang' . DS . Lang::detect() . DS . $controllerBaseName . '.php';

        //是否为删除模式
        $delete = $input->getOption('delete');
        if ($delete) {
            $readyFiles = [$controllerFile, $modelFile, $validateFile, $addFile, $editFile, $indexFile, $recyclebinFile, $langFile, $javascriptFile];
            foreach ($readyFiles as $k => $v) {
                $output->warning($v);
            }
            if (!$force) {
                $output->info("Are you sure you want to delete all those files?  Type 'yes' to continue: ");
                $line = fgets(defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
                if (trim($line) != 'yes') {
                    throw new Exception("Operation is aborted!");
                }
            }
            foreach ($readyFiles as $k => $v) {
                if (file_exists($v)) {
                    unlink($v);
                }
                //删除空文件夹
                switch ($v) {
                    case $modelFile:
                        $this->removeEmptyBaseDir($v, $modelArr);
                        break;
                    case $validateFile:
                        $this->removeEmptyBaseDir($v, $validateArr);
                        break;
                    case $addFile:
                    case $editFile:
                    case $indexFile:
                    case $recyclebinFile:
                        $this->removeEmptyBaseDir($v, $viewArr);
                        break;
                    default:
                        $this->removeEmptyBaseDir($v, $controllerArr);
                }
            }

            //继续删除菜单
            if ($menu) {
                exec("php think menu -c {$controllerUrl} -d 1 -f 1");
            }

            $output->info("Delete Successed");
            return;
        }

        //非覆盖模式时如果存在控制器文件则报错
        if (is_file($controllerFile) && !$force) {
            throw new Exception("controller already exists!\nIf you need to rebuild again, use the parameter --force=true ");
        }

        //非覆盖模式时如果存在模型文件则报错
        if (is_file($modelFile) && !$force) {
            throw new Exception("model already exists!\nIf you need to rebuild again, use the parameter --force=true ");
        }

        //非覆盖模式时如果存在验证文件则报错
        if (is_file($validateFile) && !$force) {
            throw new Exception("validate already exists!\nIf you need to rebuild again, use the parameter --force=true ");
        }

        require $adminPath . 'common.php';

        //从数据库中获取表字段信息
        $sql = "SELECT * FROM `information_schema`.`columns` "
            . "WHERE TABLE_SCHEMA = ? AND table_name = ? "
            . "ORDER BY ORDINAL_POSITION";
        //加载主表的列
        $columnList = $dbconnect->query($sql, [$dbname, $modelTableName]);
        $fieldArr = [];
        foreach ($columnList as $k => $v) {
            $fieldArr[] = $v['COLUMN_NAME'];
        }

        // 加载关联表的列
        foreach ($relations as $index => &$relation) {
            $relationColumnList = $dbconnect->query($sql, [$dbname, $relation['relationTableName']]);

            $relationFieldList = [];
            foreach ($relationColumnList as $k => $v) {
                $relationFieldList[] = $v['COLUMN_NAME'];
            }
            if (!$relation['relationPrimaryKey']) {
                foreach ($relationColumnList as $k => $v) {
                    if ($v['COLUMN_KEY'] == 'PRI') {
                        $relation['relationPrimaryKey'] = $v['COLUMN_NAME'];
                        break;
                    }
                }
            }
            // 如果主键为空
            if (!$relation['relationPrimaryKey']) {
                throw new Exception('Relation Primary key not found!');
            }
            // 如果主键不在表字段中
            if (!in_array($relation['relationPrimaryKey'], $relationFieldList)) {
                throw new Exception('Relation Primary key not found in table!');
            }
            $relation['relationColumnList'] = $relationColumnList;
            $relation['relationFieldList'] = $relationFieldList;
        }
        unset($relation);

        $addList = [];
        $editList = [];
        $javascriptList = [];
        $langList = [];
        $operateButtonList = [];
        $field = 'id';
        $order = 'id';
        $priDefined = false;
        $priKeyArr = [];
        $relationPrimaryKey = '';
        foreach ($columnList as $k => $v) {
            if ($v['COLUMN_KEY'] == 'PRI') {
                $priKeyArr[] = $v['COLUMN_NAME'];
            }
        }
        if (!$priKeyArr) {
            throw new Exception('Primary key not found!');
        }
        if (count($priKeyArr) > 1) {
            throw new Exception('Multiple primary key not support!');
        }
        $priKey = reset($priKeyArr);

        $order = $priKey;

        //如果是关联模型
        foreach ($relations as $index => &$relation) {
            if ($relation['relationMode'] == 'hasone') {
                $relationForeignKey = $relation['relationForeignKey'] ?: $table . "_id";
                $relationPrimaryKey = $relation['relationPrimaryKey'] ?: $priKey;

                if (!in_array($relationForeignKey, $relation['relationFieldList'])) {
                    throw new Exception('relation table [' . $relation['relationTableName'] . '] must be contain field [' . $relationForeignKey . ']');
                }
                if (!in_array($relationPrimaryKey, $fieldArr)) {
                    throw new Exception('table [' . $modelTableName . '] must be contain field [' . $relationPrimaryKey . ']');
                }
            } elseif ($relation['relationMode'] == 'belongsto') {
                $relationForeignKey = $relation['relationForeignKey'] ?: Loader::parseName($relation['relationName']) . "_id";
                $relationPrimaryKey = $relation['relationPrimaryKey'] ?: $relation['relationPriKey'];
                if (!in_array($relationForeignKey, $fieldArr)) {
                    throw new Exception('table [' . $modelTableName . '] must be contain field [' . $relationForeignKey . ']');
                }
                if (!in_array($relationPrimaryKey, $relation['relationFieldList'])) {
                    throw new Exception('relation table [' . $relation['relationTableName'] . '] must be contain field [' . $relationPrimaryKey . ']');
                }
            } elseif ($relation['relationMode'] == 'hasmany') {
                $relationForeignKey = $relation['relationForeignKey'] ?: $table . "_id";
                $relationPrimaryKey = $relation['relationPrimaryKey'] ?: $priKey;
                if (!in_array($relationForeignKey, $relation['relationFieldList'])) {
                    throw new Exception('relation table [' . $relation['relationTableName'] . '] must be contain field [' . $relationForeignKey . ']');
                }
                if (!in_array($relationPrimaryKey, $fieldArr)) {
                    throw new Exception('table [' . $modelTableName . '] must be contain field [' . $relationPrimaryKey . ']');
                }
                $relation['relationColumnList'] = [];
                $relation['relationFieldList'] = [];
            }
            $relation['relationForeignKey'] = $relationForeignKey;
            $relation['relationPrimaryKey'] = $relationPrimaryKey;
            $relation['relationClassName'] = $modelNamespace != $relation['relationNamespace'] ? $relation['relationNamespace'] . '\\' . $relation['relationName'] : $relation['relationName'];
        }
        unset($relation);

        try {
            Form::setEscapeHtml(false);
            $setAttrArr = [];
            $getAttrArr = [];
            $getEnumArr = [];
            $appendAttrList = [];
            $controllerAssignList = [];
            $headingHtml = '{:build_heading()}';
            $controllerImport = '';
            $importHtml = '';
            $multipleHtml = '';
            $recyclebinHtml = '';

            if ($import) {
                $controllerImport = $this->getReplacedStub('mixins/import', []);
                $importHtml = '<a href="javascript:;" class="btn btn-danger btn-import {:$auth->check(\'' . $controllerUrl . '/import\')?\'\':\'hide\'}" title="{:__(\'Import\')}" id="btn-import-file" data-url="ajax/upload" data-mimetype="csv,xls,xlsx" data-multiple="false"><i class="fa fa-upload"></i> {:__(\'Import\')}</a>';
            }

            //循环所有字段,开始构造视图的HTML和JS信息
            foreach ($columnList as $k => $v) {
                $field = $v['COLUMN_NAME'];
                $itemArr = [];
                // 这里构建Enum和Set类型的列表数据
                if (in_array($v['DATA_TYPE'], ['enum', 'set', 'tinyint']) || $this->headingFilterField == $field) {
                    if ($v['DATA_TYPE'] !== 'tinyint') {
                        $itemArr = substr($v['COLUMN_TYPE'], strlen($v['DATA_TYPE']) + 1, -1);
                        $itemArr = explode(',', str_replace("'", '', $itemArr));
                    }
                    $itemArr = $this->getItemArray($itemArr, $field, $v['COLUMN_COMMENT']);
                    //如果类型为tinyint且有使用备注数据
                    if ($itemArr && !in_array($v['DATA_TYPE'], ['enum', 'set'])) {
                        $v['DATA_TYPE'] = 'enum';
                    }
                }
                // 语言列表
                if ($v['COLUMN_COMMENT'] != '') {
                    $langList[] = $this->getLangItem($field, $v['COLUMN_COMMENT']);
                }
                $inputType = '';
                //保留字段不能修改和添加
                if ($v['COLUMN_KEY'] != 'PRI' && !in_array($field, $this->reservedField) && !in_array($field, $this->ignoreFields)) {
                    $inputType = $this->getFieldType($v);

                    // 如果是number类型时增加一个步长
                    $step = $inputType == 'number' && $v['NUMERIC_SCALE'] > 0 ? "0." . str_repeat(0, $v['NUMERIC_SCALE'] - 1) . "1" : 0;

                    $attrArr = ['id' => "c-{$field}"];
                    $cssClassArr = ['form-control'];
                    $fieldName = "row[{$field}]";
                    $defaultValue = $v['COLUMN_DEFAULT'];
                    $editValue = "{\$row.{$field}|htmlentities}";
                    // 如果默认值非null,则是一个必选项
                    if ($v['IS_NULLABLE'] == 'NO') {
                        $attrArr['data-rule'] = 'required';
                    }

                    //如果字段类型为无符号型，则设置<input min=0>
                    if (stripos($v['COLUMN_TYPE'], 'unsigned') !== false) {
                        $attrArr['min'] = 0;
                    }

                    if ($inputType == 'select') {
                        $cssClassArr[] = 'selectpicker';
                        $attrArr['class'] = implode(' ', $cssClassArr);
                        if ($v['DATA_TYPE'] == 'set') {
                            $attrArr['multiple'] = '';
                            $fieldName .= "[]";
                        }
                        $attrArr['name'] = $fieldName;

                        $this->getEnum($getEnumArr, $controllerAssignList, $field, $itemArr, $v['DATA_TYPE'] == 'set' ? 'multiple' : 'select');

                        $itemArr = $this->getLangArray($itemArr, false);
                        //添加一个获取器
                        $this->getAttr($getAttrArr, $field, $v['DATA_TYPE'] == 'set' ? 'multiple' : 'select');
                        if ($v['DATA_TYPE'] == 'set') {
                            $this->setAttr($setAttrArr, $field, $inputType);
                        }
                        $this->appendAttr($appendAttrList, $field);
                        $formAddElement = $this->getReplacedStub('html/select', ['field' => $field, 'fieldName' => $fieldName, 'fieldList' => $this->getFieldListName($field), 'attrStr' => Form::attributes($attrArr), 'selectedValue' => $defaultValue]);
                        $formEditElement = $this->getReplacedStub('html/select', ['field' => $field, 'fieldName' => $fieldName, 'fieldList' => $this->getFieldListName($field), 'attrStr' => Form::attributes($attrArr), 'selectedValue' => "\$row.{$field}"]);
                    } elseif ($inputType == 'datetime') {
                        $cssClassArr[] = 'datetimepicker';
                        $attrArr['class'] = implode(' ', $cssClassArr);
                        $format = "YYYY-MM-DD HH:mm:ss";
                        $phpFormat = "Y-m-d H:i:s";
                        $fieldFunc = '';
                        switch ($v['DATA_TYPE']) {
                            case 'year':
                                $format = "YYYY";
                                $phpFormat = 'Y';
                                break;
                            case 'date':
                                $format = "YYYY-MM-DD";
                                $phpFormat = 'Y-m-d';
                                break;
                            case 'time':
                                $format = "HH:mm:ss";
                                $phpFormat = 'H:i:s';
                                break;
                            case 'timestamp':
                                $fieldFunc = 'datetime';
                            // no break
                            case 'datetime':
                                $format = "YYYY-MM-DD HH:mm:ss";
                                $phpFormat = 'Y-m-d H:i:s';
                                break;
                            default:
                                $fieldFunc = 'datetime';
                                $this->getAttr($getAttrArr, $field, $inputType);
                                $this->setAttr($setAttrArr, $field, $inputType);
                                $this->appendAttr($appendAttrList, $field);
                                break;
                        }
                        $defaultDateTime = "{:date('{$phpFormat}')}";
                        $attrArr['data-date-format'] = $format;
                        $attrArr['data-use-current'] = "true";
                        $formAddElement = Form::text($fieldName, $defaultDateTime, $attrArr);
                        $formEditElement = Form::text($fieldName, ($fieldFunc ? "{:\$row.{$field}?{$fieldFunc}(\$row.{$field}):''}" : "{\$row.{$field}{$fieldFunc}}"), $attrArr);
                    } elseif ($inputType == 'datetimerange') {
                        $cssClassArr[] = 'datetimerange';
                        $attrArr['class'] = implode(' ', $cssClassArr);
                        $attrArr['data-locale'] = '{"format":"YYYY-MM-DD HH:mm:ss"}';
                        $fieldFunc = '';
                        $defaultDateTime = "";
                        $formAddElement = Form::text($fieldName, $defaultDateTime, $attrArr);
                        $formEditElement = Form::text($fieldName, $editValue, $attrArr);
                    } elseif ($inputType == 'checkbox' || $inputType == 'radio') {
                        unset($attrArr['data-rule']);
                        $fieldName = $inputType == 'checkbox' ? $fieldName .= "[]" : $fieldName;
                        $attrArr['name'] = "row[{$fieldName}]";

                        $this->getEnum($getEnumArr, $controllerAssignList, $field, $itemArr, $inputType);
                        $itemArr = $this->getLangArray($itemArr, false);
                        //添加一个获取器
                        $this->getAttr($getAttrArr, $field, $inputType);
                        if ($inputType == 'checkbox') {
                            $this->setAttr($setAttrArr, $field, $inputType);
                        }
                        $this->appendAttr($appendAttrList, $field);
                        $defaultValue = $inputType == 'radio' && !$defaultValue ? key($itemArr) : $defaultValue;

                        $formAddElement = $this->getReplacedStub('html/' . $inputType, ['field' => $field, 'fieldName' => $fieldName, 'fieldList' => $this->getFieldListName($field), 'attrStr' => Form::attributes($attrArr), 'selectedValue' => $defaultValue]);
                        $formEditElement = $this->getReplacedStub('html/' . $inputType, ['field' => $field, 'fieldName' => $fieldName, 'fieldList' => $this->getFieldListName($field), 'attrStr' => Form::attributes($attrArr), 'selectedValue' => "\$row.{$field}"]);
                    } elseif ($inputType == 'textarea' && !$this->isMatchSuffix($field, $this->selectpagesSuffix) && !$this->isMatchSuffix($field, $this->imageField)) {
                        $cssClassArr[] = $this->isMatchSuffix($field, $this->editorSuffix) ? $this->editorClass : '';
                        $attrArr['class'] = implode(' ', $cssClassArr);
                        $attrArr['rows'] = 5;
                        $formAddElement = Form::textarea($fieldName, $defaultValue, $attrArr);
                        $formEditElement = Form::textarea($fieldName, $editValue, $attrArr);
                    } elseif ($inputType == 'switch') {
                        unset($attrArr['data-rule']);
                        if ($defaultValue === '1' || $defaultValue === 'Y') {
                            $yes = $defaultValue;
                            $no = $defaultValue === '1' ? '0' : 'N';
                        } else {
                            $no = $defaultValue;
                            $yes = $defaultValue === '0' ? '1' : 'Y';
                        }
                        if (!$itemArr) {
                            $itemArr = [$yes => 'Yes', $no => 'No'];
                        }
                        $stateNoClass = 'fa-flip-horizontal text-gray';
                        $formAddElement = $this->getReplacedStub('html/' . $inputType, ['field' => $field, 'fieldName' => $fieldName, 'fieldYes' => $yes, 'fieldNo' => $no, 'attrStr' => Form::attributes($attrArr), 'fieldValue' => $defaultValue, 'fieldSwitchClass' => $defaultValue == $no ? $stateNoClass : '']);
                        $formEditElement = $this->getReplacedStub('html/' . $inputType, ['field' => $field, 'fieldName' => $fieldName, 'fieldYes' => $yes, 'fieldNo' => $no, 'attrStr' => Form::attributes($attrArr), 'fieldValue' => "{\$row.{$field}}", 'fieldSwitchClass' => "{eq name=\"\$row.{$field}\" value=\"{$no}\"}fa-flip-horizontal text-gray{/eq}"]);
                    } elseif ($inputType == 'citypicker') {
                        $attrArr['class'] = implode(' ', $cssClassArr);
                        $attrArr['data-toggle'] = "city-picker";
                        $formAddElement = sprintf("<div class='control-relative'>%s</div>", Form::input('text', $fieldName, $defaultValue, $attrArr));
                        $formEditElement = sprintf("<div class='control-relative'>%s</div>", Form::input('text', $fieldName, $editValue, $attrArr));
                    } elseif ($inputType == 'tagsinput') {
                        $attrArr['class'] = implode(' ', $cssClassArr);
                        $attrArr['data-role'] = "tagsinput";
                        $formAddElement = Form::input('text', $fieldName, $defaultValue, $attrArr);
                        $formEditElement = Form::input('text', $fieldName, $editValue, $attrArr);
                    } elseif ($inputType == 'fieldlist') {
                        $itemArr = $this->getItemArray($itemArr, $field, $v['COLUMN_COMMENT']);
                        $templateName = !isset($itemArr['key']) && count($itemArr) > 0 ? (isset($itemArr['value']) && count($itemArr) === 1 ? 'fieldlist-array' : 'fieldlist-template') : 'fieldlist';
                        $itemKey = isset($itemArr['key']) ? ucfirst($itemArr['key']) : 'Key';
                        $itemValue = isset($itemArr['value']) ? ucfirst($itemArr['value']) : 'Value';
                        $theadListArr = $tbodyListArr = [];
                        foreach ($itemArr as $index => $item) {
                            $theadListArr[] = "<td>{:__('" . $item . "')}</td>";
                            $tbodyListArr[] = '<td><input type="text" name="<%=name%>[<%=index%>][' . $index . ']" class="form-control" value="<%=row.' . $index . '%>"/></td>';
                        }
                        $colspan = count($theadListArr) + 1;
                        $commonFields = ['field' => $field, 'fieldName' => $fieldName, 'itemKey' => $itemKey, 'itemValue' => $itemValue, 'theadList' => implode("\n", $theadListArr), 'tbodyList' => implode("\n", $tbodyListArr), 'colspan' => $colspan];
                        $formAddElement = $this->getReplacedStub('html/' . $templateName, array_merge($commonFields, ['fieldValue' => $defaultValue]));
                        $formEditElement = $this->getReplacedStub('html/' . $templateName, array_merge($commonFields, ['fieldValue' => $editValue]));
                    } else {
                        $search = $replace = '';
                        //特殊字段为关联搜索
                        if ($this->isMatchSuffix($field, $this->selectpageSuffix)) {
                            $inputType = 'text';
                            $defaultValue = '';
                            $attrArr['data-rule'] = 'required';
                            $cssClassArr[] = 'selectpage';
                            $selectpageTable = substr($field, 0, strripos($field, '_'));
                            $selectpageField = '';
                            foreach ($relations as $index => $relation) {
                                if ($relation['relationForeignKey'] === $field) {
                                    $selectpageTable = substr($relation['relationTableName'], strlen($prefix));
                                    break;
                                }
                            }
                            $selectpageController = str_replace('_', '/', $selectpageTable);
                            $attrArr['data-source'] = $selectpageController . "/index";
                            //如果是类型表需要特殊处理下
                            if ($selectpageController == 'category') {
                                $attrArr['data-source'] = 'category/selectpage';
                                $attrArr['data-params'] = '##replacetext##';
                                $search = '"##replacetext##"';
                                $replace = '\'{"custom[type]":"' . $table . '"}\'';
                            } elseif ($selectpageController == 'admin') {
                                $attrArr['data-source'] = 'auth/admin/selectpage';
                            } elseif ($selectpageController == 'user') {
                                $attrArr['data-source'] = 'user/user/index';
                                $attrArr['data-field'] = 'nickname';
                            }
                            if ($this->isMatchSuffix($field, $this->selectpagesSuffix)) {
                                $attrArr['data-multiple'] = 'true';
                            }

                            $tableInfo = null;
                            try {
                                $tableInfo = \think\Db::name($selectpageTable)->getTableInfo();
                                if (isset($tableInfo['fields'])) {
                                    foreach ($tableInfo['fields'] as $m => $n) {
                                        if (in_array($n, ['nickname', 'title', 'name'])) {
                                            $selectpageField = $n;
                                            break;
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                            }
                            if (!$selectpageField) {
                                foreach ($this->fieldSelectpageMap as $m => $n) {
                                    if (in_array($field, $n)) {
                                        $attrArr['data-field'] = $m;
                                        break;
                                    }
                                }
                            }
                        }
                        //因为有自动完成可输入其它内容
                        $step = array_intersect($cssClassArr, ['selectpage']) ? 0 : $step;
                        $attrArr['class'] = implode(' ', $cssClassArr);
                        $isUpload = false;
                        if ($this->isMatchSuffix($field, array_merge($this->imageField, $this->fileField))) {
                            $isUpload = true;
                        }
                        //如果是步长则加上步长
                        if ($step) {
                            $attrArr['step'] = $step;
                        }
                        //如果是图片加上个size
                        if ($isUpload) {
                            $attrArr['size'] = 50;
                        }

                        //字段默认值判断
                        if ('NULL' == $defaultValue || "''" == $defaultValue) {
                            $defaultValue = '';
                        }

                        $formAddElement = Form::input($inputType, $fieldName, $defaultValue, $attrArr);
                        $formEditElement = Form::input($inputType, $fieldName, $editValue, $attrArr);
                        if ($search && $replace) {
                            $formAddElement = str_replace($search, $replace, $formAddElement);
                            $formEditElement = str_replace($search, $replace, $formEditElement);
                        }
                        //如果是图片或文件
                        if ($isUpload) {
                            $formAddElement = $this->getImageUpload($field, $formAddElement);
                            $formEditElement = $this->getImageUpload($field, $formEditElement);
                        }
                    }
                    //构造添加和编辑HTML信息
                    $addList[] = $this->getFormGroup($field, $formAddElement);
                    $editList[] = $this->getFormGroup($field, $formEditElement);
                }

                //过滤text类型字段
                if ($v['DATA_TYPE'] != 'text' && $inputType != 'fieldlist') {
                    //主键
                    if ($v['COLUMN_KEY'] == 'PRI' && !$priDefined) {
                        $priDefined = true;
                        $javascriptList[] = "{checkbox: true}";
                    }
                    if ($this->deleteTimeField == $field) {
                        $recyclebinHtml = $this->getReplacedStub('html/recyclebin-html', ['controllerUrl' => $controllerUrl]);
                        continue;
                    }
                    if (!$fields || in_array($field, explode(',', $fields))) {
                        //构造JS列信息
                        $javascriptList[] = $this->getJsColumn($field, $v['DATA_TYPE'], $inputType && in_array($inputType, ['select', 'checkbox', 'radio']) ? '_text' : '', $itemArr, $v);
                    }
                    if ($this->headingFilterField && $this->headingFilterField == $field && $itemArr) {
                        $headingHtml = $this->getReplacedStub('html/heading-html', ['field' => $field, 'fieldName' => Loader::parseName($field, 1, false)]);
                        $multipleHtml = $this->getReplacedStub('html/multiple-html', ['field' => $field, 'fieldName' => Loader::parseName($field, 1, false), 'controllerUrl' => $controllerUrl]);
                    }
                    //排序方式,如果有指定排序字段,否则按主键排序
                    $order = $field == $this->sortField ? $this->sortField : $order;
                }
            }

            //循环关联表,追加语言包和JS列
            foreach ($relations as $index => $relation) {
                if ($relation['relationMode'] == 'hasmany') {
                    $relationFieldText = ucfirst(strtolower($relation['relationName'])) . ' List';
                    // 语言列表
                    if ($relation['relationTableInfo']['Comment']) {
                        $langList[] = $this->getLangItem($relationFieldText, rtrim($relation['relationTableInfo']['Comment'], "表") . "列表");
                    }

                    $relationTableName = $relation['relationTableName'];
                    $relationTableName = stripos($relationTableName, $prefix) === 0 ? substr($relationTableName, strlen($prefix)) : $relationTableName;

                    list($realtionControllerNamespace, $realtionControllerName, $realtionControllerFile, $realtionControllerArr) = $this->getControllerData($moduleName, $relation['relationController'], $relationTableName);
                    $realtionControllerArr = array_map("strtolower", $realtionControllerArr);
                    if (count($realtionControllerArr) > 1) {
                        $realtionControllerArr = [implode('.', $realtionControllerArr)];
                    }
                    $realtionControllerArr[] = 'index';
                    $realtionControllerArr[] = $relation['relationForeignKey'] . '/{ids}';
                    $relationControllerUrl = implode('/', $realtionControllerArr);

                    //构造JS列信息
                    $operateButtonList[] = "{name: 'addtabs',title: __('{$relationFieldText}'),text: __('{$relationFieldText}'),classname: 'btn btn-xs btn-info btn-dialog',icon: 'fa fa-list',url: '" . $relationControllerUrl . "'}";
                    //echo "php think crud -t {$relation['relationTableName']} -c {$relation['relationController']} -m {$relation['relationModel']} -i " . implode(',', $relation['relationFields']);
                    //不存在关联表控制器的情况下才进行生成
                    if (!is_file($realtionControllerFile)) {
                        exec("php think crud -t {$relation['relationTableName']} -c {$relation['relationController']} -m {$relation['relationModel']} -i " . implode(',', $relation['relationFields']));
                    }
                }
                foreach ($relation['relationColumnList'] as $k => $v) {
                    // 不显示的字段直接过滤掉
                    if ($relation['relationFields'] && !in_array($v['COLUMN_NAME'], $relation['relationFields'])) {
                        continue;
                    }

                    $relationField = strtolower($relation['relationName']) . "." . $v['COLUMN_NAME'];
                    // 语言列表
                    if ($v['COLUMN_COMMENT'] != '') {
                        $langList[] = $this->getLangItem($relationField, $v['COLUMN_COMMENT']);
                    }

                    //过滤text类型字段
                    if ($v['DATA_TYPE'] != 'text') {
                        //构造JS列信息
                        $javascriptList[] = $this->getJsColumn($relationField, $v['DATA_TYPE'], '', [], $v);
                    }
                }
            }

            //JS最后一列加上操作列
            $javascriptList[] = str_repeat(" ", 24) . "{field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, " . ($operateButtonList ? "buttons: [" . implode(',', $operateButtonList) . "], " : "") . "formatter: Table.api.formatter.operate}";
            $addList = implode("\n", array_filter($addList));
            $editList = implode("\n", array_filter($editList));
            $javascriptList = implode(",\n", array_filter($javascriptList));
            $langList = implode(",\n", array_filter($langList));
            //数组等号对齐
            $langList = array_filter(explode(",\n", $langList . ",\n"));
            foreach ($langList as &$line) {
                if (preg_match("/^\s+'([^']+)'\s*=>\s*'([^']+)'\s*/is", $line, $matches)) {
                    $line = "    '{$matches[1]}'" . str_pad('=>', ($this->fieldMaxLen - strlen($matches[1]) + 3), ' ', STR_PAD_LEFT) . " '{$matches[2]}'";
                }
            }
            unset($line);
            $langList = implode(",\n", array_filter($langList));
            $fixedcolumns = count($columnList) >= 10 ? 1 : $fixedcolumns;

            $fixedColumnsJs = '';
            if (is_numeric($fixedcolumns) && $fixedcolumns) {
                $fixedColumnsJs = "\n" . str_repeat(" ", 16) . "fixedColumns: true,\n" . str_repeat(" ", 16) . ($fixedcolumns < 0 ? "fixedNumber" : "fixedRightNumber") . ": " . $fixedcolumns . ",";
            }

            //表注释
            $tableComment = $modelTableInfo ? $modelTableInfo['Comment'] : '';
            $tableComment = mb_substr($tableComment, -1) == '表' ? mb_substr($tableComment, 0, -1) . '管理' : $tableComment;

            $modelInit = '';
            if ($priKey != $order) {
                $modelInit = $this->getReplacedStub('mixins' . DS . 'modelinit', ['order' => $order]);
            }

            $data = [
                'modelConnection'         => $db == 'database' ? '' : "protected \$connection = '{$db}';",
                'controllerNamespace'     => $controllerNamespace,
                'modelNamespace'          => $modelNamespace,
                'validateNamespace'       => $validateNamespace,
                'controllerUrl'           => $controllerUrl,
                'controllerName'          => $controllerName,
                'controllerAssignList'    => implode("\n", $controllerAssignList),
                'modelName'               => $modelName,
                'modelTableName'          => $modelTableName,
                'modelTableType'          => $modelTableType,
                'modelTableTypeName'      => $modelTableTypeName,
                'validateName'            => $validateName,
                'tableComment'            => $tableComment,
                'iconName'                => $iconName,
                'pk'                      => $priKey,
                'order'                   => $order,
                'fixedColumnsJs'          => $fixedColumnsJs,
                'table'                   => $table,
                'tableName'               => $modelTableName,
                'addList'                 => $addList,
                'editList'                => $editList,
                'javascriptList'          => $javascriptList,
                'langList'                => $langList,
                'softDeleteClassPath'     => in_array($this->deleteTimeField, $fieldArr) ? "use traits\model\SoftDelete;" : '',
                'softDelete'              => in_array($this->deleteTimeField, $fieldArr) ? "use SoftDelete;" : '',
                'modelAutoWriteTimestamp' => in_array($this->createTimeField, $fieldArr) || in_array($this->updateTimeField, $fieldArr) ? "'integer'" : 'false',
                'createTime'              => in_array($this->createTimeField, $fieldArr) ? "'{$this->createTimeField}'" : 'false',
                'updateTime'              => in_array($this->updateTimeField, $fieldArr) ? "'{$this->updateTimeField}'" : 'false',
                'deleteTime'              => in_array($this->deleteTimeField, $fieldArr) ? "'{$this->deleteTimeField}'" : 'false',
                'relationSearch'          => $relations ? 'true' : 'false',
                'relationWithList'        => '',
                'relationMethodList'      => '',
                'controllerImport'        => $controllerImport,
                'controllerIndex'         => '',
                'recyclebinJs'            => '',
                'headingHtml'             => $headingHtml,
                'multipleHtml'            => $multipleHtml,
                'importHtml'              => $importHtml,
                'recyclebinHtml'          => $recyclebinHtml,
                'visibleFieldList'        => $fields ? "\$row->visible(['" . implode("','", array_filter(in_array($priKey, explode(',', $fields)) ? explode(',', $fields) : explode(',', $priKey . ',' . $fields))) . "']);" : '',
                'appendAttrList'          => implode(",\n", $appendAttrList),
                'getEnumList'             => implode("\n\n", $getEnumArr),
                'getAttrList'             => implode("\n\n", $getAttrArr),
                'setAttrList'             => implode("\n\n", $setAttrArr),
                'modelInit'               => $modelInit,
            ];

            //如果使用关联模型
            if ($relations) {
                $relationWithList = $relationMethodList = $relationVisibleFieldList = [];
                $relationKeyArr = ['hasone' => 'hasOne', 'belongsto' => 'belongsTo', 'hasmany' => 'hasMany'];
                foreach ($relations as $index => $relation) {
                    //需要构造关联的方法
                    $relation['relationMethod'] = strtolower($relation['relationName']);

                    //关联的模式
                    $relation['relationMode'] = strtolower($relation['relationMode']);
                    $relation['relationMode'] = array_key_exists($relation['relationMode'], $relationKeyArr) ? $relationKeyArr[$relation['relationMode']] : '';

                    //关联字段
                    $relation['relationPrimaryKey'] = $relation['relationPrimaryKey'] ? $relation['relationPrimaryKey'] : $priKey;

                    //构造关联模型的方法
                    $relationMethodList[] = $this->getReplacedStub('mixins' . DS . 'modelrelationmethod' . ($relation['relationMode'] == 'hasMany' ? '-hasmany' : ''), $relation);

                    if ($relation['relationMode'] == 'hasMany') {
                        continue;
                    }

                    //预载入的方法
                    $relationWithList[] = $relation['relationMethod'];

                    unset($relation['relationColumnList'], $relation['relationFieldList'], $relation['relationTableInfo']);

                    //如果设置了显示主表字段，则必须显式将关联表字段显示
                    if ($fields) {
                        $relationVisibleFieldList[] = "\$row->visible(['{$relation['relationMethod']}']);";
                    }

                    //显示的字段
                    if ($relation['relationFields']) {
                        $relationVisibleFieldList[] = "\$row->getRelation('" . $relation['relationMethod'] . "')->visible(['" . implode("','", $relation['relationFields']) . "']);";
                    }
                }

                $data['relationWithList'] = "->with(['" . implode("','", $relationWithList) . "'])";
                $data['relationMethodList'] = implode("\n\n", $relationMethodList);
                $data['relationVisibleFieldList'] = implode("\n\t\t\t\t", $relationVisibleFieldList);

                if ($relationWithList) {
                    //需要重写index方法
                    $data['controllerIndex'] = $this->getReplacedStub('controllerindex', $data);
                }
            } elseif ($fields) {
                $data = array_merge($data, ['relationWithList' => '', 'relationMethodList' => '', 'relationVisibleFieldList' => '']);
                //需要重写index方法
                $data['controllerIndex'] = $this->getReplacedStub('controllerindex', $data);
            }

            // 生成控制器文件
            $this->writeToFile('controller', $data, $controllerFile);
            // 生成模型文件
            $this->writeToFile('model', $data, $modelFile);

            if ($relations) {
                foreach ($relations as $i => $relation) {
                    $relation['modelNamespace'] = $relation['relationNamespace'];
                    if (!is_file($relation['relationFile'])) {
                        // 生成关联模型文件
                        $this->writeToFile('relationmodel', $relation, $relation['relationFile']);
                    }
                }
            }
            // 生成验证文件
            $this->writeToFile('validate', $data, $validateFile);
            // 生成视图文件
            $this->writeToFile('add', $data, $addFile);
            $this->writeToFile('edit', $data, $editFile);
            $this->writeToFile('index', $data, $indexFile);
            if ($recyclebinHtml) {
                $this->writeToFile('recyclebin', $data, $recyclebinFile);
                $recyclebinTitle = in_array('title', $fieldArr) ? 'title' : (in_array('name', $fieldArr) ? 'name' : '');
                $recyclebinTitleJs = $recyclebinTitle ? "\n                        {field: '{$recyclebinTitle}', title: __('" . (ucfirst($recyclebinTitle)) . "'), align: 'left'}," : '';
                $data['recyclebinJs'] = $this->getReplacedStub('mixins/recyclebinjs', ['deleteTimeField' => $this->deleteTimeField, 'recyclebinTitleJs' => $recyclebinTitleJs, 'controllerUrl' => $controllerUrl]);
            }
            // 生成JS文件
            $this->writeToFile('javascript', $data, $javascriptFile);
            // 生成语言文件
            $this->writeToFile('lang', $data, $langFile);
        } catch (ErrorException $e) {
            throw new Exception("Code: " . $e->getCode() . "\nLine: " . $e->getLine() . "\nMessage: " . $e->getMessage() . "\nFile: " . $e->getFile());
        }

        //继续生成菜单
        if ($menu) {
            exec("php think menu -c {$controllerUrl}");
        }

        $output->info("Build Successed");
    }

    protected function getEnum(&$getEnum, &$controllerAssignList, $field, $itemArr = '', $inputType = '')
    {
        if (!in_array($inputType, ['datetime', 'select', 'multiple', 'checkbox', 'radio'])) {
            return;
        }
        $fieldList = $this->getFieldListName($field);
        $methodName = 'get' . ucfirst($fieldList);
        foreach ($itemArr as $k => &$v) {
            $v = "__('" . mb_ucfirst($v) . "')";
        }
        unset($v);
        $itemString = $this->getArrayString($itemArr);
        $getEnum[] = <<<EOD
    public function {$methodName}()
    {
        return [{$itemString}];
    }
EOD;
        $controllerAssignList[] = <<<EOD
        \$this->view->assign("{$fieldList}", \$this->model->{$methodName}());
EOD;
    }

    protected function getAttr(&$getAttr, $field, $inputType = '')
    {
        if (!in_array($inputType, ['datetime', 'select', 'multiple', 'checkbox', 'radio'])) {
            return;
        }
        $attrField = ucfirst($this->getCamelizeName($field));
        $getAttr[] = $this->getReplacedStub("mixins" . DS . $inputType, ['field' => $field, 'methodName' => "get{$attrField}TextAttr", 'listMethodName' => "get{$attrField}List"]);
    }

    protected function setAttr(&$setAttr, $field, $inputType = '')
    {
        if (!in_array($inputType, ['datetime', 'checkbox', 'select'])) {
            return;
        }
        $attrField = ucfirst($this->getCamelizeName($field));
        if ($inputType == 'datetime') {
            $return = <<<EOD
return \$value === '' ? null : (\$value && !is_numeric(\$value) ? strtotime(\$value) : \$value);
EOD;
        } elseif (in_array($inputType, ['checkbox', 'select'])) {
            $return = <<<EOD
return is_array(\$value) ? implode(',', \$value) : \$value;
EOD;
        }
        $setAttr[] = <<<EOD
    protected function set{$attrField}Attr(\$value)
    {
        $return
    }
EOD;
    }

    protected function appendAttr(&$appendAttrList, $field)
    {
        $appendAttrList[] = <<<EOD
        '{$field}_text'
EOD;
    }

    /**
     * 移除相对的空目录
     * @param $parseFile
     * @param $parseArr
     * @return bool
     */
    protected function removeEmptyBaseDir($parseFile, $parseArr)
    {
        if (count($parseArr) > 1) {
            $parentDir = dirname($parseFile);
            for ($i = 0; $i < count($parseArr); $i++) {
                try {
                    $iterator = new \FilesystemIterator($parentDir);
                    $isDirEmpty = !$iterator->valid();
                    if ($isDirEmpty) {
                        rmdir($parentDir);
                        $parentDir = dirname($parentDir);
                    } else {
                        return true;
                    }
                } catch (\UnexpectedValueException $e) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 获取控制器URL
     * @param string $moduleName
     * @param array  $baseNameArr
     * @return string
     */
    protected function getControllerUrl($moduleName, $baseNameArr)
    {
        for ($i = 0; $i < count($baseNameArr) - 1; $i++) {
            $temp = array_slice($baseNameArr, 0, $i + 1);
            $temp[$i] = ucfirst($temp[$i]);
            $controllerFile = APP_PATH . $moduleName . DS . 'controller' . DS . implode(DS, $temp) . '.php';
            //检测父级目录同名控制器是否存在，存在则变更URL格式
            if (is_file($controllerFile)) {
                $baseNameArr = [implode('.', $baseNameArr)];
                break;
            }
        }
        $controllerUrl = strtolower(implode('/', $baseNameArr));
        return $controllerUrl;
    }

    /**
     * 获取控制器相关信息
     * @param $module
     * @param $controller
     * @param $table
     * @return array
     */
    protected function getControllerData($module, $controller, $table)
    {
        return $this->getParseNameData($module, $controller, $table, 'controller');
    }

    /**
     * 获取模型相关信息
     * @param $module
     * @param $model
     * @param $table
     * @return array
     */
    protected function getModelData($module, $model, $table)
    {
        return $this->getParseNameData($module, $model, $table, 'model');
    }

    /**
     * 获取验证器相关信息
     * @param $module
     * @param $validate
     * @param $table
     * @return array
     */
    protected function getValidateData($module, $validate, $table)
    {
        return $this->getParseNameData($module, $validate, $table, 'validate');
    }

    /**
     * 获取已解析相关信息
     * @param string $module 模块名称
     * @param string $name   自定义名称
     * @param string $table  数据表名
     * @param string $type   解析类型，本例中为controller、model、validate
     * @return array
     */
    protected function getParseNameData($module, $name, $table, $type)
    {
        $arr = [];
        if (!$name) {
            $parseName = Loader::parseName($table, 1);
            $name = str_replace('_', '/', $table);
        }

        $name = str_replace(['.', '/', '\\'], '/', $name);
        $arr = explode('/', $name);
        $parseName = ucfirst(array_pop($arr));
        $parseArr = $arr;
        array_push($parseArr, $parseName);
        //类名不能为内部关键字
        if (in_array(strtolower($parseName), $this->internalKeywords)) {
            throw new Exception('Unable to use internal variable:' . $parseName);
        }
        $appNamespace = Config::get('app_namespace');
        $parseNamespace = "{$appNamespace}\\{$module}\\{$type}" . ($arr ? "\\" . implode("\\", $arr) : "");
        $moduleDir = APP_PATH . $module . DS;
        $parseFile = $moduleDir . $type . DS . ($arr ? implode(DS, $arr) . DS : '') . $parseName . '.php';
        return [$parseNamespace, $parseName, $parseFile, $parseArr];
    }

    /**
     * 写入到文件
     * @param string $name
     * @param array  $data
     * @param string $pathname
     * @return mixed
     */
    protected function writeToFile($name, $data, $pathname)
    {
        foreach ($data as $index => &$datum) {
            $datum = is_array($datum) ? '' : $datum;
        }
        unset($datum);
        $content = $this->getReplacedStub($name, $data);

        if (!is_dir(dirname($pathname))) {
            mkdir(dirname($pathname), 0755, true);
        }
        return file_put_contents($pathname, $content);
    }

    /**
     * 获取替换后的数据
     * @param string $name
     * @param array  $data
     * @return string
     */
    protected function getReplacedStub($name, $data)
    {
        foreach ($data as $index => &$datum) {
            $datum = is_array($datum) ? '' : $datum;
        }
        unset($datum);
        $search = $replace = [];
        foreach ($data as $k => $v) {
            $search[] = "{%{$k}%}";
            $replace[] = $v;
        }
        $stubname = $this->getStub($name);
        if (isset($this->stubList[$stubname])) {
            $stub = $this->stubList[$stubname];
        } else {
            $this->stubList[$stubname] = $stub = file_get_contents($stubname);
        }
        $content = str_replace($search, $replace, $stub);
        return $content;
    }

    /**
     * 获取基础模板
     * @param string $name
     * @return string
     */
    protected function getStub($name)
    {
        return __DIR__ . DS . 'Crud' . DS . 'stubs' . DS . $name . '.stub';
    }

    protected function getLangItem($field, $content)
    {
        if ($content || !Lang::has($field)) {
            $this->fieldMaxLen = strlen($field) > $this->fieldMaxLen ? strlen($field) : $this->fieldMaxLen;
            $content = str_replace('，', ',', $content);
            if (stripos($content, ':') !== false && stripos($content, '=') !== false) {
                list($fieldLang, $item) = explode(':', $content);
                $itemArr = [$field => $fieldLang];
                foreach (explode(',', $item) as $k => $v) {
                    $valArr = explode('=', $v);
                    if (count($valArr) == 2) {
                        list($key, $value) = $valArr;
                        $itemArr[$field . ' ' . $key] = $value;
                        if ($this->headingFilterField == $field) {
                            $itemArr['Set ' . $field . ' to ' . $key] = '设为' . $value;
                        }
                        $this->fieldMaxLen = strlen($field . ' ' . $key) > $this->fieldMaxLen ? strlen($field . ' ' . $key) : $this->fieldMaxLen;
                    }
                }
            } else {
                $itemArr = [$field => $content];
            }
            $resultArr = [];
            foreach ($itemArr as $k => $v) {
                $resultArr[] = "    '" . mb_ucfirst($k) . "' => '{$v}'";
            }
            return implode(",\n", $resultArr);
        } else {
            return '';
        }
    }

    /**
     * 读取数据和语言数组列表
     * @param array   $arr
     * @param boolean $withTpl
     * @return array
     */
    protected function getLangArray($arr, $withTpl = true)
    {
        $langArr = [];
        foreach ($arr as $k => $v) {
            $langArr[$k] = is_numeric($k) ? ($withTpl ? "{:" : "") . "__('" . mb_ucfirst($v) . "')" . ($withTpl ? "}" : "") : $v;
        }
        return $langArr;
    }

    /**
     * 将数据转换成带字符串
     * @param array $arr
     * @return string
     */
    protected function getArrayString($arr)
    {
        if (!is_array($arr)) {
            return $arr;
        }
        $stringArr = [];
        foreach ($arr as $k => $v) {
            $is_var = in_array(substr($v, 0, 1), ['$', '_']);
            if (!$is_var) {
                $v = str_replace("'", "\'", $v);
                $k = str_replace("'", "\'", $k);
            }
            $stringArr[] = "'" . $k . "' => " . ($is_var ? $v : "'{$v}'");
        }
        return implode(", ", $stringArr);
    }

    protected function getItemArray($item, $field, $comment)
    {
        $itemArr = [];
        $comment = str_replace('，', ',', $comment);
        if (stripos($comment, ':') !== false && stripos($comment, '=') !== false) {
            list($fieldLang, $item) = explode(':', $comment);
            $itemArr = [];
            foreach (explode(',', $item) as $k => $v) {
                $valArr = explode('=', $v);
                if (count($valArr) == 2) {
                    list($key, $value) = $valArr;
                    $itemArr[$key] = $field . ' ' . $key;
                }
            }
        } else {
            foreach ($item as $k => $v) {
                $itemArr[$v] = is_numeric($v) ? $field . ' ' . $v : $v;
            }
        }
        return $itemArr;
    }

    protected function getFieldType(&$v)
    {
        $inputType = 'text';
        switch ($v['DATA_TYPE']) {
            case 'bigint':
            case 'int':
            case 'mediumint':
            case 'smallint':
            case 'tinyint':
                $inputType = 'number';
                break;
            case 'enum':
            case 'set':
                $inputType = 'select';
                break;
            case 'decimal':
            case 'double':
            case 'float':
                $inputType = 'number';
                break;
            case 'longtext':
            case 'text':
            case 'mediumtext':
            case 'smalltext':
            case 'tinytext':
                $inputType = 'textarea';
                break;
            case 'year':
            case 'date':
            case 'time':
            case 'datetime':
            case 'timestamp':
                $inputType = 'datetime';
                break;
            default:
                break;
        }
        $fieldsName = $v['COLUMN_NAME'];
        // 指定后缀说明也是个时间字段
        if ($this->isMatchSuffix($fieldsName, $this->intDateSuffix)) {
            $inputType = 'datetime';
        }
        // 指定后缀结尾且类型为enum,说明是个单选框
        if ($this->isMatchSuffix($fieldsName, $this->enumRadioSuffix) && $v['DATA_TYPE'] == 'enum') {
            $inputType = "radio";
        }
        // 指定后缀结尾且类型为set,说明是个复选框
        if ($this->isMatchSuffix($fieldsName, $this->setCheckboxSuffix) && $v['DATA_TYPE'] == 'set') {
            $inputType = "checkbox";
        }
        // 指定后缀结尾且类型为char或tinyint且长度为1,说明是个Switch复选框
        if ($this->isMatchSuffix($fieldsName, $this->switchSuffix) && ($v['COLUMN_TYPE'] == 'tinyint(1)' || $v['COLUMN_TYPE'] == 'char(1)') && $v['COLUMN_DEFAULT'] !== '' && $v['COLUMN_DEFAULT'] !== null) {
            $inputType = "switch";
        }
        // 指定后缀结尾城市选择框
        if ($this->isMatchSuffix($fieldsName, $this->citySuffix) && ($v['DATA_TYPE'] == 'varchar' || $v['DATA_TYPE'] == 'char')) {
            $inputType = "citypicker";
        }
        // 指定后缀结尾城市选择框
        if ($this->isMatchSuffix($fieldsName, $this->rangeSuffix) && ($v['DATA_TYPE'] == 'varchar' || $v['DATA_TYPE'] == 'char')) {
            $inputType = "datetimerange";
        }
        // 指定后缀结尾JSON配置
        if ($this->isMatchSuffix($fieldsName, $this->jsonSuffix) && ($v['DATA_TYPE'] == 'varchar' || $v['DATA_TYPE'] == 'text')) {
            $inputType = "fieldlist";
        }
        // 指定后缀结尾标签配置
        if ($this->isMatchSuffix($fieldsName, $this->tagSuffix) && ($v['DATA_TYPE'] == 'varchar' || $v['DATA_TYPE'] == 'text')) {
            $inputType = "tagsinput";
        }
        return $inputType;
    }

    /**
     * 判断是否符合指定后缀
     * @param string $field     字段名称
     * @param mixed  $suffixArr 后缀
     * @return boolean
     */
    protected function isMatchSuffix($field, $suffixArr)
    {
        $suffixArr = is_array($suffixArr) ? $suffixArr : explode(',', $suffixArr);
        foreach ($suffixArr as $k => $v) {
            if (preg_match("/{$v}$/i", $field)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取表单分组数据
     * @param string $field
     * @param string $content
     * @return string
     */
    protected function getFormGroup($field, $content)
    {
        $langField = mb_ucfirst($field);
        return <<<EOD
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2">{:__('{$langField}')}:</label>
        <div class="col-xs-12 col-sm-8">
            {$content}
        </div>
    </div>
EOD;
    }

    /**
     * 获取图片模板数据
     * @param string $field
     * @param string $content
     * @return string
     */
    protected function getImageUpload($field, $content)
    {
        $uploadfilter = $selectfilter = '';
        if ($this->isMatchSuffix($field, $this->imageField)) {
            $uploadfilter = ' data-mimetype="image/gif,image/jpeg,image/png,image/jpg,image/bmp,image/webp"';
            $selectfilter = ' data-mimetype="image/*"';
        }
        $multiple = substr($field, -1) == 's' ? ' data-multiple="true"' : ' data-multiple="false"';
        $preview = ' data-preview-id="p-' . $field . '"';
        $previewcontainer = $preview ? '<ul class="row list-inline faupload-preview" id="p-' . $field . '"></ul>' : '';
        return <<<EOD
<div class="input-group">
                {$content}
                <div class="input-group-addon no-border no-padding">
                    <span><button type="button" id="faupload-{$field}" class="btn btn-danger faupload" data-input-id="c-{$field}"{$uploadfilter}{$multiple}{$preview}><i class="fa fa-upload"></i> {:__('Upload')}</button></span>
                    <span><button type="button" id="fachoose-{$field}" class="btn btn-primary fachoose" data-input-id="c-{$field}"{$selectfilter}{$multiple}><i class="fa fa-list"></i> {:__('Choose')}</button></span>
                </div>
                <span class="msg-box n-right" for="c-{$field}"></span>
            </div>
            {$previewcontainer}
EOD;
    }

    /**
     * 获取JS列数据
     * @param string $field
     * @param string $datatype
     * @param string $extend
     * @param array  $itemArr
     * @param array  $fieldConfig
     * @return string
     */
    protected function getJsColumn($field, $datatype = '', $extend = '', $itemArr = [], $fieldConfig = [])
    {
        $lang = mb_ucfirst($field);
        $formatter = '';
        foreach ($this->fieldFormatterSuffix as $k => $v) {
            if (preg_match("/{$k}$/i", $field)) {
                if (is_array($v)) {
                    if (in_array($datatype, $v['type'])) {
                        $formatter = $v['name'];
                        break;
                    }
                } else {
                    $formatter = $v;
                    break;
                }
            }
        }
        $html = str_repeat(" ", 24) . "{field: '{$field}', title: __('{$lang}')";

        if ($datatype == 'set') {
            $formatter = 'label';
        }
        foreach ($itemArr as $k => &$v) {
            if (substr($v, 0, 3) !== '__(') {
                $v = "__('" . mb_ucfirst($v) . "')";
            }
        }
        unset($v);
        $searchList = json_encode($itemArr, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
        $searchList = str_replace(['":"', '"}', ')","'], ['":', '}', '),"'], $searchList);
        if ($itemArr) {
            $html .= ", searchList: " . $searchList;
        }

        // 文件、图片、权重等字段默认不加入搜索栏，字符串类型默认LIKE
        $noSearchFiles = ['file$', 'files$', 'image$', 'images$', '^weigh$'];
        if (preg_match("/" . implode('|', $noSearchFiles) . "/i", $field)) {
            $html .= ", operate: false";
        } elseif (in_array($datatype, ['varchar'])) {
            $html .= ", operate: 'LIKE'";
        }

        if (in_array($datatype, ['date', 'datetime']) || $formatter === 'datetime') {
            $html .= ", operate:'RANGE', addclass:'datetimerange', autocomplete:false";
        } elseif (in_array($datatype, ['float', 'double', 'decimal'])) {
            $html .= ", operate:'BETWEEN'";
        }
        if (in_array($datatype, ['set'])) {
            $html .= ", operate:'FIND_IN_SET'";
        }
        if (isset($fieldConfig['CHARACTER_MAXIMUM_LENGTH']) && $fieldConfig['CHARACTER_MAXIMUM_LENGTH'] >= 255 && in_array($datatype, ['varchar']) && !$formatter) {
            $formatter = 'content';
            $html .= ", table: table, class: 'autocontent'";
        }
        if (in_array($formatter, ['image', 'images'])) {
            $html .= ", events: Table.api.events.image";
        }
        if (in_array($formatter, ['toggle'])) {
            $html .= ", table: table";
        }
        if ($itemArr && !$formatter) {
            $formatter = 'normal';
        }
        if ($formatter) {
            $html .= ", formatter: Table.api.formatter." . $formatter . "}";
        } else {
            $html .= "}";
        }
        return $html;
    }

    protected function getCamelizeName($uncamelized_words, $separator = '_')
    {
        $uncamelized_words = $separator . str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator);
    }

    protected function getFieldListName($field)
    {
        return $this->getCamelizeName($field) . 'List';
    }
}
