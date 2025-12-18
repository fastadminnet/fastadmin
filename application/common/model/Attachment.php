<?php

namespace app\common\model;

use think\Model;

class Attachment extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 定义字段类型
    protected $type = [
    ];
    protected $append = [
        'thumb_style'
    ];

    protected static function init()
    {
        // 如果已经上传该资源，则不再记录
        self::beforeInsert(function ($model) {
            if (self::where('url', '=', $model['url'])->where('storage', $model['storage'])->find()) {
                return false;
            }
        });
        self::beforeWrite(function ($row) {
            if (isset($row['category']) && $row['category'] == 'unclassed') {
                $row['category'] = '';
            }
        });
    }

    public function setUploadtimeAttr($value)
    {
        return is_numeric($value) ? $value : strtotime($value);
    }

    public function getCategoryAttr($value)
    {
        return $value == '' ? 'unclassed' : $value;
    }

    public function setCategoryAttr($value)
    {
        return $value == 'unclassed' ? '' : $value;
    }

    /**
     * 获取云储存的缩略图样式字符
     */
    public function getThumbStyleAttr($value, $data)
    {
        if (!isset($data['storage']) || $data['storage'] == 'local') {
            return '';
        } else {
            $config = get_addon_config($data['storage']);
            if ($config && isset($config['thumbstyle'])) {
                return $config['thumbstyle'];
            }
        }
        return '';
    }

    /**
     * 获取Mimetype列表
     * @return array
     */
    public static function getMimetypeList()
    {
        $data = [
            "image/*"        => __("Image"),
            "audio/*"        => __("Audio"),
            "video/*"        => __("Video"),
            "text/*"         => __("Text"),
            "application/*"  => __("Application"),
            "zip,rar,7z,tar" => __("Zip"),
        ];
        return $data;
    }

    /**
     * 获取定义的附件类别列表
     * @return array
     */
    public static function getCategoryList()
    {
        $data = config('site.attachmentcategory') ?? [];
        foreach ($data as $index => &$datum) {
            $datum = __($datum);
        }
        $data['unclassed'] = __('Unclassed');
        return $data;
    }

    /**
     * 返回上传文件的相关信息
     */
    public function getBaseData()
    {
        $data = [
            'url'      => $this->url,
            'fullurl'  => cdnurl($this->url, true),
            'category' => $this->category,
            'filename' => $this->filename,
            'filesize' => $this->filesize,
            'mimetype' => $this->mimetype,
        ];
        return $data;
    }
}
