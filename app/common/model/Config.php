<?php

namespace app\common\model;

use think\Model;

/**
 * 配置模型
 */
class Config extends Model
{

    // 表名,不含前缀
    protected $name = 'config';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;
    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    // 追加属性
    protected $append = [
        'extend_html',
    ];
    // setting 以 JSON 存取（对齐 reference 的 $type）
    protected $type = [
        'setting' => 'json',
    ];

    /**
     * 读取配置类型
     * @return array
     */
    public static function getTypeList()
    {
        $typeList = [
            'string'        => __('String'),
            'password'      => __('Password'),
            'text'          => __('Text'),
            'editor'        => __('Editor'),
            'number'        => __('Number'),
            'date'          => __('Date'),
            'time'          => __('Time'),
            'datetime'      => __('Datetime'),
            'datetimerange' => __('Datetimerange'),
            'select'        => __('Select'),
            'selects'       => __('Selects'),
            'image'         => __('Image'),
            'images'        => __('Images'),
            'file'          => __('File'),
            'files'         => __('Files'),
            'switch'        => __('Switch'),
            'checkbox'      => __('Checkbox'),
            'radio'         => __('Radio'),
            'city'          => __('City'),
            'selectpage'    => __('Selectpage'),
            'selectpages'   => __('Selectpages'),
            'array'         => __('Array'),
            'custom'        => __('Custom'),
        ];
        return $typeList;
    }

    public static function getRegexList()
    {
        $regexList = [
            'required' => __('Required'),
            'digits'   => __('Digits'),
            'letters'  => __('Letters'),
            'date'     => __('Date'),
            'time'     => __('Time'),
            'email'    => __('Email'),
            'url'      => __('Url'),
            'qq'       => __('QQ'),
            'IDcard'   => __('IDcard'),
            'tel'      => __('Tel'),
            'mobile'   => __('Mobile'),
            'zipcode'  => __('Zipcode'),
            'chinese'  => __('Chinese'),
            'username' => __('Username'),
            'password' => __('Password'),
        ];
        return $regexList;
    }

    public function getExtendHtmlAttr($value, $data)
    {
        $result = preg_replace_callback("/\{([a-zA-Z]+)\}/", function ($matches) use ($data) {
            if (isset($data[$matches[1]])) {
                return $data[$matches[1]];
            }
        }, $data['extend']);
        return $result;
    }

    /**
     * 读取分类分组列表
     * @return array
     */
    public static function getGroupList()
    {
        $groupList = config('site.configgroup');
        foreach ($groupList as $k => &$v) {
            $v = __($v);
        }
        return $groupList;
    }

    public static function getArrayData($data)
    {
        if (!isset($data['value'])) {
            $result = [];
            foreach ($data as $index => $datum) {
                $result['field'][$index] = $datum['key'];
                $result['value'][$index] = $datum['value'];
            }
            $data = $result;
        }
        $fieldarr = $valuearr = [];
        $field = $data['field'] ?? ($data['key'] ?? []);
        $value = $data['value'] ?? [];
        foreach ($field as $m => $n) {
            if ($n != '') {
                $fieldarr[] = $field[$m];
                $valuearr[] = $value[$m];
            }
        }
        return $fieldarr ? array_combine($fieldarr, $valuearr) : [];
    }

    /**
     * 将字符串解析成键值数组
     * @param string $text
     * @return array
     */
    public static function decode($text, $split = "\r\n")
    {
        $content = explode($split, $text);
        $arr = [];
        foreach ($content as $k => $v) {
            if (stripos($v, "|") !== false) {
                $item = explode('|', $v);
                $arr[$item[0]] = $item[1];
            }
        }
        return $arr;
    }

    /**
     * 将键值数组转换为字符串
     * @param array $array
     * @return string
     */
    public static function encode($array, $split = "\r\n")
    {
        $content = '';
        if ($array && is_array($array)) {
            $arr = [];
            foreach ($array as $k => $v) {
                $arr[] = "{$k}|{$v}";
            }
            $content = implode($split, $arr);
        }
        return $content;
    }

    public static function upload()
    {
        return [
            'mimetype' => config('upload.mimetype', 'jpg,png,bmp,jpeg,gif,webp,zip,rar,wav,mp4,mp3,webm'),
            'maxsize' => config('upload.maxsize', '10mb'),
            'savekey' => config('upload.savekey', '/uploads/{year}{mon}{day}/{filemd5}{.suffix}'),
            'driver' => 'local',
            'driver_config' => [],
        ];
    }

    /**
     * 刷新配置文件
     */
    public static function refreshFile()
    {
        //如果没有配置权限无法进行修改
        if (!\app\admin\library\Auth::instance()->check('general/config/edit')) {
            return false;
        }
        $config = [];
        $configList = self::select();
        foreach ($configList as $k => $v) {
            $value = $v->toArray();
            if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if ($value['type'] == 'array') {
                $value['value'] = (array)json_decode($value['value'], true);
            }
            $config[$value['name']] = $value['value'];
        }
        file_put_contents(
            config_path() . 'site.php',
            '<?php' . "\n\n// 本文件由后台“系统配置”自动生成，请勿手动修改。\n// 如需修改，请进入后台 -> 系统配置。\n\nreturn " . var_export($config, true) . ";\n"
        );
        return true;
    }

}
