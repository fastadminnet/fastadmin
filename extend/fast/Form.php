<?php

namespace fast;

use ArrayAccess;

/**
 * 表单元素生成
 * @class   Form
 * @package fast
 * @method static string token() 生成Token
 * @method static string label(string $name, string $value = null, array $options = []) label标签
 * @method static string input($type, $name, string $value = null, array $options = []) 按类型生成文本框
 * @method static string text(string $name, string $value = null, array $options = []) 普通文本框
 * @method static string password(string $name, array $options = []) 密码文本框
 * @method static string hidden(string $name, string $value = null, array $options = []) 隐藏文本框
 * @method static string email(string $name, string $value = null, array $options = []) Email文本框
 * @method static string url(string $name, string $value = null, array $options = []) URL文本框
 * @method static string file(string $name, array $options = []) 文件上传组件
 * @method static string textarea(string $name, string $value = null, array $options = []) 多行文本框
 * @method static string editor(string $name, string $value = null, array $options = []) 富文本编辑器
 * @method static string select(string $name, array $list = [], string $selected = null, array $options = []) 下拉列表组件
 * @method static string selects(string $name, array $list = [], string $selected = null, array $options = []) 下拉列表组件(多选)
 * @method static string selectpicker(string $name, array $list = [], string $selected = null, array $options = []) 下拉列表组件(友好)
 * @method static string selectpickers(string $name, array $list = [], string $selected = null, array $options = []) 下拉列表组件(友好)(多选)
 * @method static string selectpage(string $name, string $value, string $url, string $field = null, string $primaryKey = null, array $options = []) 动态下拉列表组件
 * @method static string selectpages(string $name, string $value, string $url, string $field = null, string $primaryKey = null, array $options = []) 动态下拉列表组件(多选)
 * @method static string citypicker(string $name, string $value, array $options = []) 城市选择组件
 * @method static string switcher(string $name, string $value, array $options = []) 切换组件
 * @method static string datepicker(string $name, string $value, array $options = []) 日期选择组件
 * @method static string timepicker(string $name, string $value, array $options = []) 时间选择组件
 * @method static string datetimepicker(string $name, string $value, array $options = []) 日期时间选择组件
 * @method static string daterange(string $name, string $value, array $options = []) 日期区间组件
 * @method static string timerange(string $name, string $value, array $options = []) 时间区间组件
 * @method static string datetimerange(string $name, string $value, array $options = []) 日期时间区间组件
 * @method static string fieldlist(string $name, string $value, string $title = null, string $template = null, array $options = []) 字段列表组件
 * @method static string cxselect(string $url, array $names = [], array $values = [], array $options = []) 联动组件
 * @method static string selectRange(string $name, string $begin, string $end, string $selected = null, array $options = []) 选择数字区间
 * @method static string selectYear(string $name, string $begin, string $end, string $selected = null, array $options = []) 选择年
 * @method static string selectMonth(string $name, string $selected = null, array $options = [], string $format = '%m') 选择月
 * @method static string checkbox(string $name, string $value = '1', string $checked = null, array $options = []) 单个复选框
 * @method static string checkboxs(string $name, array $list = [], string $checked = null, array $options = []) 一组复选框
 * @method static string radio(string $name, string $value = null, string $checked = null, array $options = [])) 单个单选框
 * @method static string radios(string $name, array $list = [], string $checked = null, array $options = [])) 一组单选框
 * @method static string image(string $name = null, string $value = null, array $inputAttr = [], array $uploadAttr = [], array $chooseAttr = [], array $previewAttr = []) 上传图片组件
 * @method static string images(string $name = null, string $value = null, array $inputAttr = [], array $uploadAttr = [], array $chooseAttr = [], array $previewAttr = []) 上传图片组件(多图)）
 * @method static string upload(string $name = null, string $value = null, array $inputAttr = [], array $uploadAttr = [], array $chooseAttr = [], array $previewAttr = []) 上传文件组件
 * @method static string uploads(string $name = null, string $value = null, array $inputAttr = [], array $uploadAttr = [], array $chooseAttr = [], array $previewAttr = []) 上传文件组件(多文件)）
 * @method static string button(string $value = null, array $options = []) 表单button
 */
class Form
{

    /**
     * @param $name
     * @param $arguments
     * @return FormBuilder
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([FormBuilder::instance(), $name], $arguments);
    }
}

/**
 *
 * 表单元素生成
 * @from https://github.com/illuminate/html
 * @package fast
 */
class FormBuilder
{

    /**
     * Token
     *
     * @var string
     */
    protected $csrfToken = array('name' => '__token__');

    /**
     * 已创建的标签名称
     *
     * @var array
     */
    protected $labels = [];

    /**
     * 跳过的填充value值的类型
     *
     * @var array
     */
    protected $skipValueTypes = array('file', 'password', 'checkbox', 'radio');

    /**
     * 转义HTML
     * @var boolean
     */
    protected $escapeHtml = true;
    protected static $instance;

    /**
     * 获取单例
     * @param array $options
     * @return static
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 设置是否转义
     * @param boolean $escape
     */
    public function setEscapeHtml($escape)
    {
        $this->escapeHtml = $escape;
    }

    /**
     * 获取转义编码后的值
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        if (!$this->escapeHtml) {
            return $value;
        }
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * 生成Token
     *
     * @param string $name
     * @param string $type
     * @return string
     */
    public function token($name = '__token__', $type = 'md5')
    {
        if (function_exists('token')) {
            return token($name, $type);
        }

        return '';
    }

    /**
     * 生成Label标签
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function label($name, $value = null, $options = [])
    {
        $this->labels[] = $name;

        $options = $this->attributes($options);
        $value = $this->escape($this->formatLabel($name, $value));

        return '<label for="' . $name . '"' . $options . '>' . $value . '</label>';
    }

    /**
     * Format the label value.
     *
     * @param string      $name
     * @param string|null $value
     * @return string
     */
    protected function formatLabel($name, $value)
    {
        return $value ?: ucwords(str_replace('_', ' ', $name));
    }

    /**
     * 生成文本框(按类型)
     *
     * @param string $type
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function input($type, $name, $value = null, $options = [])
    {
        if (!isset($options['name'])) {
            $options['name'] = $name;
        }

        $id = $this->getIdAttribute($name, $options);

        if (!in_array($type, $this->skipValueTypes)) {
            $value = $this->getValueAttribute($name, $value);
            $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'form-control') !== false ? '' : ' form-control') : 'form-control';
        }

        $merge = compact('type', 'value', 'id');
        $options = array_merge($options, $merge);

        return '<input' . $this->attributes($options) . '>';
    }

    /**
     * 生成普通文本框
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function text($name, $value = null, $options = [])
    {
        return $this->input('text', $name, $value, $options);
    }

    /**
     * 生成密码文本框
     *
     * @param string $name
     * @param array  $options
     * @return string
     */
    public function password($name, $options = [])
    {
        return $this->input('password', $name, '', $options);
    }

    /**
     * 生成隐藏文本框
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function hidden($name, $value = null, $options = [])
    {
        return $this->input('hidden', $name, $value, $options);
    }

    /**
     * 生成Email文本框
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function email($name, $value = null, $options = [])
    {
        return $this->input('email', $name, $value, $options);
    }

    /**
     * 生成URL文本框
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function url($name, $value = null, $options = [])
    {
        return $this->input('url', $name, $value, $options);
    }

    /**
     * 生成上传文件组件
     *
     * @param string $name
     * @param array  $options
     * @return string
     */
    public function file($name, $options = [])
    {
        return $this->input('file', $name, null, $options);
    }

    /**
     * 生成多行文本框
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function textarea($name, $value = null, $options = [])
    {
        if (!isset($options['name'])) {
            $options['name'] = $name;
        }

        $options = $this->setTextAreaSize($options);
        $options['id'] = $this->getIdAttribute($name, $options);
        $value = (string)$this->getValueAttribute($name, $value);

        unset($options['size']);

        $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'form-control') !== false ? '' : ' form-control') : 'form-control';
        $options = $this->attributes($options);

        return '<textarea' . $options . '>' . $this->escape($value) . '</textarea>';
    }

    /**
     * 生成富文本编辑器
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function editor($name, $value = null, $options = [])
    {
        $options['class'] = isset($options['class']) ? $options['class'] . ' editor' : 'editor';
        return $this->textarea($name, $value, $options);
    }

    /**
     * 设置默认的文本框行列数
     *
     * @param array $options
     * @return array
     */
    protected function setTextAreaSize($options)
    {
        if (isset($options['size'])) {
            return $this->setQuickTextAreaSize($options);
        }

        $cols = array_get($options, 'cols', 50);
        $rows = array_get($options, 'rows', 5);

        return array_merge($options, compact('cols', 'rows'));
    }

    /**
     * 根据size设置行数和列数
     *
     * @param array $options
     * @return array
     */
    protected function setQuickTextAreaSize($options)
    {
        $segments = explode('x', $options['size']);
        return array_merge($options, array('cols' => $segments[0], 'rows' => $segments[1]));
    }

    /**
     * 生成滑块
     *
     * @param string $name
     * @param string $min
     * @param string $max
     * @param string $step
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function slider($name, $min, $max, $step, $value = null, $options = [])
    {
        $options = array_merge($options, ['data-slider-min' => $min, 'data-slider-max' => $max, 'data-slider-step' => $step, 'data-slider-value' => $value ? $value : '']);
        $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'form-control') !== false ? '' : ' slider form-control') : 'slider form-control';
        return $this->input('text', $name, $value, $options);
    }

    /**
     * 生成下拉列表框
     *
     * @param string $name
     * @param array  $list
     * @param mixed  $selected
     * @param array  $options
     * @return string
     */
    public function select($name, $list = [], $selected = null, $options = [])
    {
        $selected = $this->getValueAttribute($name, $selected);

        $options['id'] = $this->getIdAttribute($name, $options);

        if (!isset($options['name'])) {
            $options['name'] = $name;
        }

        $html = [];
        foreach ($list as $value => $display) {
            $html[] = $this->getSelectOption($display, $value, $selected);
        }
        $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'form-control') !== false ? '' : ' form-control') : 'form-control';

        $options = $this->attributes($options);
        $list = implode('', $html);

        return "<select{$options}>{$list}</select>";
    }

    /**
     * 下拉列表(多选)
     *
     * @param string $name
     * @param array  $list
     * @param mixed  $selected
     * @param array  $options
     * @return string
     */
    public function selects($name, $list = [], $selected = null, $options = [])
    {
        $options[] = 'multiple';
        return $this->select($name, $list, $selected, $options);
    }

    /**
     * 下拉列表(友好)
     *
     * @param string $name
     * @param array  $list
     * @param mixed  $selected
     * @param array  $options
     * @return string
     */
    public function selectpicker($name, $list = [], $selected = null, $options = [])
    {
        $options['class'] = isset($options['class']) ? $options['class'] . ' selectpicker' : 'selectpicker';
        return $this->select($name, $list, $selected, $options);
    }

    /**
     * 下拉列表(友好)(多选)
     *
     * @param string $name
     * @param array  $list
     * @param mixed  $selected
     * @param array  $options
     * @return string
     */
    public function selectpickers($name, $list = [], $selected = null, $options = [])
    {
        $options[] = 'multiple';
        return $this->selectpicker($name, $list, $selected, $options);
    }

    /**
     * 生成动态下拉列表
     *
     * @param string $name       名称
     * @param mixed  $value
     * @param string $url        数据源地址
     * @param string $field      显示的字段名称,默认为name
     * @param string $primaryKey 主键,数据库中保存的值,默认为id
     * @param array  $options
     * @return string
     */
    public function selectpage($name, $value, $url, $field = null, $primaryKey = null, $options = [])
    {
        $options = array_merge($options, ['data-source' => $url, 'data-field' => $field ? $field : 'name', 'data-primary-key' => $primaryKey ? $primaryKey : 'id']);
        $options['class'] = isset($options['class']) ? $options['class'] . ' selectpage' : 'selectpage';
        return $this->text($name, $value, $options);
    }


    /**
     * 生成动态下拉列表(复选)
     *
     * @param string $name       名称
     * @param mixed  $value
     * @param string $url        数据源地址
     * @param string $field      显示的字段名称,默认为name
     * @param string $primaryKey 主键,数据库中保存的值,默认为id
     * @param array  $options
     * @return string
     */
    public function selectpages($name, $value, $url, $field = null, $primaryKey = null, $options = [])
    {
        $options['data-multiple'] = "true";
        return $this->selectpage($name, $value, $url, $field, $primaryKey, $options);
    }

    /**
     * 生成城市选择框
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $options
     * @return string
     */
    public function citypicker($name, $value, $options = [])
    {
        $options['data-toggle'] = 'city-picker';
        return "<div class='control-relative'>" . $this->text($name, $value, $options) . "</div>";
    }

    /**
     * 生成switch组件
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $options
     * @return string
     */
    public function switcher($name, $value, $options = [])
    {
        $domname = str_replace(['[', ']', '.'], '', $name);
        $btn = $this->hidden($name, $value, ['id' => "c-{$domname}"]);
        $yes = 1;
        $no = 0;
        if (isset($options['yes']) && isset($options['no'])) {
            $yes = $options['yes'];
            $no = $options['no'];
        }
        $selected = $no == $value ? "fa-flip-horizontal text-gray" : "";
        $disabled = (isset($options['disabled']) && $options['disabled']) || in_array('disabled', $options) ? "disabled" : '';
        $color = isset($options['color']) ? $options['color'] : 'success';
        unset($options['yes'], $options['no'], $options['color'], $options['disabled']);
        $attr = $this->attributes($options);
        $html = <<<EOD
{$btn}
<a href="javascript:;" data-toggle="switcher" class="btn-switcher {$disabled}" data-input-id="c-{$domname}" data-yes="{$yes}" data-no="{$no}" {$attr}><i class="fa fa-toggle-on text-{$color} {$selected} fa-2x"></i></a>
EOD;
        return $html;
    }

    /**
     * 日期选择器
     * @param string $name
     * @param mixed  $value
     * @param array  $options
     * @return string
     */
    public function datepicker($name, $value, $options = [])
    {
        $defaults = [
            'data-date-format' => "YYYY-MM-DD",
        ];
        $options = array_merge($defaults, $options);
        $value = is_numeric($value) ? date("Y-m-d", $value) : $value;
        return $this->datetimepicker($name, $value, $options);
    }

    /**
     * 时间选择器
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $options
     * @return string
     */
    public function timepicker($name, $value, $options = [])
    {
        $defaults = [
            'data-date-format' => "HH:mm:ss",
        ];
        $options = array_merge($defaults, $options);
        $value = is_numeric($value) ? date("H:i:s", $value) : $value;
        return $this->datetimepicker($name, $value, $options);
    }

    /**
     * 日期时间选择器
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $options
     * @return string
     */
    public function datetimepicker($name, $value, $options = [])
    {
        $defaults = [
            'data-date-format' => "YYYY-MM-DD HH:mm:ss",
            // 兼容旧的用法
            'data-use-current' => "true",
            'data-date-use-current' => "true",
        ];
        $value = is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
        $options = array_merge($defaults, $options);
        $options['class'] = isset($options['class']) ? $options['class'] . ' datetimepicker' : 'datetimepicker';
        return $this->text($name, $value, $options);
    }

    /**
     * 日期区间
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function daterange($name, $value, $options = [])
    {
        $defaults = [
            'data-locale' => [
                'format' => 'YYYY-MM-DD'
            ]
        ];
        $options = array_merge($defaults, $options);
        return $this->datetimerange($name, $value, $options);
    }

    /**
     * 时间区间
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function timerange($name, $value, $options = [])
    {
        $defaults = [
            'data-locale'                  => [
                'format' => 'HH:mm:ss'
            ],
            'data-ranges'                  => [],
            'data-show-custom-range-label' => "false",
            'data-time-picker'             => "true",
        ];
        $options = array_merge($defaults, $options);
        return $this->datetimerange($name, $value, $options);
    }

    /**
     * 日期时间区间
     *
     * @param string $name
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function datetimerange($name, $value, $options = [])
    {
        $defaults = [
            'data-locale' => [
                'format' => 'YYYY-MM-DD HH:mm:ss'
            ]
        ];
        $options = array_merge($defaults, $options);
        $options['class'] = isset($options['class']) ? $options['class'] . ' datetimerange' : 'datetimerange';
        return $this->text($name, $value, $options);
    }

    /**
     * 生成字段列表组件
     *
     * @param string $name
     * @param mixed  $value
     * @param array  $title
     * @param string $template
     * @param array  $options
     * @return string
     */
    public function fieldlist($name, $value, $title = null, $template = null, $options = [])
    {
        $append = __('Append');
        $template = $template ? 'data-template="' . $template . '"' : '';
        $attributes = $this->attributes($options);
        if (is_null($title)) {
            $title = [__('Key'), __('Value')];
        }
        $ins = implode("\n", array_map(function ($value) {
            return "<ins>{$value}</ins>";
        }, $title));
        $value = is_array($value) ? json_encode($value) : $value;
        $html = <<<EOD
<dl class="fieldlist" data-name="{$name}" {$template} {$attributes}>
    <dd>
        {$ins}
    </dd>
    <dd><a href="javascript:;" class="btn btn-sm btn-success btn-append"><i class="fa fa-plus"></i> {$append}</a></dd>
    <textarea name="{$name}" class="form-control hide" cols="30" rows="5">{$value}</textarea>
</dl>
EOD;
        return $html;
    }

    /**
     * 生成联动下拉列表
     *
     * @param string $url     联动获取数据源的URL地址
     * @param array  $names   联动字段名称
     * @param array  $values  联动字段默认选中的值
     * @param array  $options 扩展属性
     * @return string
     */
    public function cxselect($url, $names = [], $values = [], $options = [])
    {
        $classes = [];
        $cxselect = [];
        $attributes = $this->attributes($options);
        foreach ($names as $index => $value) {
            $level = $index + 1;
            $class = "cxselect-{$level}";
            $classes[] = $class;
            $selectValue = isset($values[$value]) ? $values[$value] : (isset($values[$index]) ? $values[$index] : '');

            $cxselect[] = <<<EOD
<select class="{$class} form-control" name="{$value}" data-value="{$selectValue}" data-url="{$url}?level={$level}&name={$value}" {$attributes}></select>
EOD;
        }
        $cxselect = implode("\n", $cxselect);
        $selects = implode(',', $classes);
        $html = <<<EOD
<div class="form-inline" data-toggle="cxselect" data-selects="{$selects}">
{$cxselect}
</div>
EOD;
        return $html;
    }

    /**
     * 创建一个下拉列表选择区间组件
     *
     * @param string $name
     * @param string $begin
     * @param string $end
     * @param string $selected
     * @param array  $options
     * @return string
     */
    public function selectRange($name, $begin, $end, $selected = null, $options = [])
    {
        $range = array_combine($range = range($begin, $end), $range);
        return $this->select($name, $range, $selected, $options);
    }

    /**
     * 生成选择年组件
     *
     * @param string $name
     * @param string $begin
     * @param string $end
     * @param string $selected
     * @param array  $options
     * @return string
     */
    public function selectYear($name, $begin, $end, $selected, $options)
    {
        return call_user_func_array(array($this, 'selectRange'), func_get_args());
    }

    /**
     * 生成选择月组件
     *
     * @param string $name
     * @param string $selected
     * @param array  $options
     * @param string $format
     * @return string
     */
    public function selectMonth($name, $selected = null, $options = [], $format = '%m')
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = strftime($format, mktime(0, 0, 0, $month, 1));
        }

        return $this->select($name, $months, $selected, $options);
    }

    /**
     * 根据传递的值生成option
     *
     * @param string $display
     * @param string $value
     * @param string $selected
     * @return string
     */
    public function getSelectOption($display, $value, $selected)
    {
        if (is_array($display)) {
            return $this->optionGroup($display, $value, $selected);
        }

        return $this->option($display, $value, $selected);
    }

    /**
     * 生成optionGroup
     *
     * @param array  $list
     * @param string $label
     * @param string $selected
     * @return string
     */
    protected function optionGroup($list, $label, $selected)
    {
        $html = [];

        foreach ($list as $value => $display) {
            $html[] = $this->option($display, $value, $selected);
        }

        return '<optgroup label="' . $this->escape($label) . '">' . implode('', $html) . '</optgroup>';
    }

    /**
     * 生成option选项
     *
     * @param string $display
     * @param string $value
     * @param string $selected
     * @return string
     */
    protected function option($display, $value, $selected)
    {
        $selected = $this->getSelectedValue($value, $selected);

        $options = array('value' => $this->escape($value), 'selected' => $selected);

        return '<option' . $this->attributes($options) . '>' . $this->escape($display) . '</option>';
    }

    /**
     * 检测value是否选中
     *
     * @param string $value
     * @param string $selected
     * @return string
     */
    protected function getSelectedValue($value, $selected)
    {
        if (is_array($selected)) {
            return in_array($value, $selected) ? 'selected' : null;
        }

        return ((string)$value == (string)$selected) ? 'selected' : null;
    }

    /**
     * 生成复选按钮
     *
     * @param string $name
     * @param mixed  $value
     * @param bool   $checked
     * @param array  $options
     * @return string
     */
    public function checkbox($name, $value = 1, $checked = null, $options = [])
    {
        if ($checked) {
            $options['checked'] = 'checked';
        }

        return $this->input('checkbox', $name, $value, $options);
    }

    /**
     * 生成一组筛选框
     *
     * @param string $name
     * @param array  $list
     * @param mixed  $checked
     * @param array  $options
     * @return string
     */
    public function checkboxs($name, $list, $checked, $options = [])
    {
        $html = [];
        $checked = is_null($checked) ? [] : $checked;
        $checked = is_array($checked) ? $checked : explode(',', $checked);
        foreach ($list as $k => $v) {
            $options['id'] = "{$name}-{$k}";
            $html[] = sprintf(Form::label("{$name}-{$k}", "%s " . str_replace('%', '%%', $v)), Form::checkbox("{$name}[{$k}]", $k, in_array($k, $checked), $options));
        }
        return '<div class="checkbox">' . implode(' ', $html) . '</div>';
    }

    /**
     * 生成单选按钮
     *
     * @param string $name
     * @param mixed  $value
     * @param bool   $checked
     * @param array  $options
     * @return string
     */
    public function radio($name, $value = null, $checked = null, $options = [])
    {
        if (is_null($value)) {
            $value = $name;
        }

        if ($checked) {
            $options['checked'] = 'checked';
        }

        return $this->input('radio', $name, $value, $options);
    }

    /**
     * 生成一组单选框
     *
     * @param string $name
     * @param array  $list
     * @param mixed  $checked
     * @param array  $options
     * @return string
     */
    public function radios($name, $list, $checked = null, $options = [])
    {
        $html = [];
        $checked = is_null($checked) ? key($list) : $checked;
        $checked = is_array($checked) ? $checked : explode(',', $checked);
        foreach ($list as $k => $v) {
            $options['id'] = "{$name}-{$k}";
            $html[] = sprintf(Form::label("{$name}-{$k}", "%s " . str_replace('%', '%%', $v)), Form::radio($name, $k, in_array($k, $checked), $options));
        }
        return '<div class="radio">' . implode(' ', $html) . '</div>';
    }

    /**
     * 生成上传图片组件(单图)
     *
     * @param string $name
     * @param string $value
     * @param array  $inputAttr
     * @param array  $uploadAttr
     * @param array  $chooseAttr
     * @param array  $previewAttr
     * @return string
     */
    public function image($name = null, $value = null, $inputAttr = [], $uploadAttr = [], $chooseAttr = [], $previewAttr = [])
    {
        $default = [
            'data-mimetype' => 'image/gif,image/jpeg,image/png,image/jpg,image/bmp'
        ];
        $uploadAttr = is_array($uploadAttr) ? array_merge($default, $uploadAttr) : $uploadAttr;
        $chooseAttr = is_array($chooseAttr) ? array_merge($default, $chooseAttr) : $chooseAttr;
        return $this->uploader($name, $value, $inputAttr, $uploadAttr, $chooseAttr, $previewAttr);
    }

    /**
     * 生成上传图片组件(多图)
     *
     * @param string $name
     * @param string $value
     * @param array  $inputAttr
     * @param array  $uploadAttr
     * @param array  $chooseAttr
     * @param array  $previewAttr
     * @return string
     */
    public function images($name = null, $value = null, $inputAttr = [], $uploadAttr = [], $chooseAttr = [], $previewAttr = [])
    {
        $default = [
            'data-multiple' => 'true',
            'data-mimetype' => 'image/gif,image/jpeg,image/png,image/jpg,image/bmp'
        ];
        $uploadAttr = is_array($uploadAttr) ? array_merge($default, $uploadAttr) : $uploadAttr;
        $chooseAttr = is_array($chooseAttr) ? array_merge($default, $chooseAttr) : $chooseAttr;
        return $this->uploader($name, $value, $inputAttr, $uploadAttr, $chooseAttr, $previewAttr);
    }

    /**
     * 生成上传文件组件(单文件)
     *
     * @param string $name
     * @param string $value
     * @param array  $inputAttr
     * @param array  $uploadAttr
     * @param array  $chooseAttr
     * @param array  $previewAttr
     * @return string
     */
    public function upload($name = null, $value = null, $inputAttr = [], $uploadAttr = [], $chooseAttr = [], $previewAttr = [])
    {
        return $this->uploader($name, $value, $inputAttr, $uploadAttr, $chooseAttr, $previewAttr);
    }

    /**
     * 生成上传文件组件(多文件)
     *
     * @param string $name
     * @param string $value
     * @param array  $inputAttr
     * @param array  $uploadAttr
     * @param array  $chooseAttr
     * @param array  $previewAttr
     * @return string
     */
    public function uploads($name = null, $value = null, $inputAttr = [], $uploadAttr = [], $chooseAttr = [], $previewAttr = [])
    {
        $default = [
            'data-multiple' => 'true',
        ];
        $uploadAttr = is_array($uploadAttr) ? array_merge($default, $uploadAttr) : $uploadAttr;
        $chooseAttr = is_array($chooseAttr) ? array_merge($default, $chooseAttr) : $chooseAttr;
        return $this->uploader($name, $value, $inputAttr, $uploadAttr, $chooseAttr, $previewAttr);
    }

    protected function uploader($name = null, $value = null, $inputAttr = [], $uploadAttr = [], $chooseAttr = [], $previewAttr = [])
    {
        $domname = str_replace(['[', ']', '.'], '', $name);
        $options = [
            'id'            => "faupload-{$domname}",
            'class'         => "btn btn-danger faupload",
            'data-input-id' => "c-{$domname}",
        ];
        $upload = $uploadAttr === false ? false : true;
        $choose = $chooseAttr === false ? false : true;
        $preview = $previewAttr === false ? false : true;
        if ($preview) {
            $options['data-preview-id'] = "p-{$domname}";
        }
        $uploadBtn = $upload ? $this->button('<i class="fa fa-upload"></i> ' . __('Upload'), array_merge($options, $uploadAttr)) : '';
        $options = [
            'id'            => "fachoose-{$domname}",
            'class'         => "btn btn-danger fachoose",
            'data-input-id' => "c-{$domname}",
        ];
        if ($preview) {
            $options['data-preview-id'] = "p-{$domname}";
        }
        $chooseBtn = $choose ? $this->button('<i class="fa fa-list"></i> ' . __('Choose'), array_merge($options, $chooseAttr)) : '';
        $previewAttrHtml = $this->attributes($previewAttr);
        $previewArea = $preview ? '<ul class="row list-inline faupload-preview" id="p-' . $domname . '" ' . $previewAttrHtml . '></ul>' : '';
        $input = $this->text($name, $value, array_merge(['size' => 50, 'id' => "c-{$domname}"], $inputAttr));
        $html = <<<EOD
<div class="input-group">
                {$input}
                <div class="input-group-addon no-border no-padding">
                    <span>{$uploadBtn}</span>                  
                    <span>{$chooseBtn}</span>
                </div>
                <span class="msg-box n-right" for="c-{$domname}"></span>
            </div>
            {$previewArea}
EOD;
        return $html;
    }

    /**
     * 生成一个按钮
     *
     * @param string $value
     * @param array  $options
     * @return string
     */
    public function button($value = null, $options = [])
    {
        if (!array_key_exists('type', $options)) {
            $options['type'] = 'button';
        }

        return '<button' . $this->attributes($options) . '>' . $value . '</button>';
    }

    /**
     * 获取ID属性值
     *
     * @param string $name
     * @param array  $attributes
     * @return string
     */
    public function getIdAttribute($name, $attributes)
    {
        if (array_key_exists('id', $attributes)) {
            return $attributes['id'];
        }

        if (in_array($name, $this->labels)) {
            return $name;
        }
    }

    /**
     * 获取Value属性值
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    public function getValueAttribute($name, $value = null)
    {
        if (is_null($name)) {
            return $value;
        }

        if (!is_null($value)) {
            return $value;
        }
    }

    /**
     * 数组转换成一个HTML属性字符串。
     *
     * @param array $attributes
     * @return string
     */
    public function attributes($attributes)
    {
        $html = [];
        // 假设我们的keys 和 value 是相同的,
        // 拿HTML“required”属性来说,假设是['required']数组,
        // 会已 required="required" 拼接起来,而不是用数字keys去拼接
        foreach ((array)$attributes as $key => $value) {
            $element = $this->attributeElement($key, $value);
            if (!is_null($element)) {
                $html[] = $element;
            }
        }
        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }

    /**
     * 拼接成一个属性。
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    protected function attributeElement($key, $value)
    {
        if (is_numeric($key)) {
            $key = $value;
        }
        if (!is_null($value)) {
            if (is_array($value) || stripos($value, '"') !== false) {
                $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                return $key . "='" . $value . "'";
            } else {
                return $key . '="' . $value . '"';
            }
        }
    }
}

class Arr
{

    /**
     * Determine whether the given value is array accessible.
     *
     * @param mixed $value
     * @return bool
     */
    public static function accessible($value)
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param \ArrayAccess|array $array
     * @param string|int         $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        return array_key_exists($key, $array);
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string             $key
     * @param mixed              $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        if (!static::accessible($array)) {
            return $default;
        }
        if (is_null($key)) {
            return $array;
        }
        if (static::exists($array, $key)) {
            return $array[$key];
        }
        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }
        return $array;
    }

    /**
     * Get all of the given array except for a specified array of items.
     *
     * @param array        $array
     * @param array|string $keys
     * @return array
     */
    public static function except($array, $keys)
    {
        static::forget($array, $keys);
        return $array;
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param array        $array
     * @param array|string $keys
     * @return void
     */
    public static function forget(&$array, $keys)
    {
        $original = &$array;
        $keys = (array)$keys;
        if (count($keys) === 0) {
            return;
        }
        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (static::exists($array, $key)) {
                unset($array[$key]);
                continue;
            }
            $parts = explode('.', $key);
            // clean up before each pass
            $array = &$original;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }
            unset($array[array_shift($parts)]);
        }
    }
}

if (!function_exists('array_get')) {

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string             $key
     * @param mixed              $default
     * @return mixed
     */
    function array_get($array, $key, $default = null)
    {
        return Arr::get($array, $key, $default);
    }
}
if (!function_exists('e')) {

    /**
     * Escape HTML special characters in a string.
     *
     *
     * @return string
     */
    function e($value)
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }
}
if (!function_exists('array_except')) {

    /**
     * Get all of the given array except for a specified array of items.
     *
     * @param array        $array
     * @param array|string $keys
     * @return array
     */
    function array_except($array, $keys)
    {
        return Arr::except($array, $keys);
    }
}
