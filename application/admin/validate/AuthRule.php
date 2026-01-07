<?php

namespace app\admin\validate;

use think\Validate;

class AuthRule extends Validate
{

    /**
     * 正则
     */
    protected $regex = ['format' => '[a-z0-9_\/]+'];

    /**
     * 验证规则
     */
    protected $rule = [
        'name'  => 'require|regex:format|unique:AuthRule',
        'title' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'name.regex' => 'URL规则只能是小写字母、数字、下划线和/组成'
    ];

    /**
     * 字段描述
     */
    protected $field = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'name'  => __('Name'),
            'title' => __('Title'),
        ];
        $this->message['name.regex'] = __('Name only supports letters, numbers, underscore and slash');
        parent::__construct($rules, $message, $field);
    }

}
