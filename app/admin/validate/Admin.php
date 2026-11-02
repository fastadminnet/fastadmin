<?php

namespace app\admin\validate;

use think\Validate;

class Admin extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'username' => 'require|regex:\w{3,30}|unique:admin',
        'nickname' => 'require',
        'password' => 'require|regex:\S{32}',
        'email'    => 'require|email|unique:admin,email',
        'mobile'   => 'regex:1[3-9]\d{9}|unique:admin,mobile',
    ];

    /**
     * 提示消息
     */
    protected $message = [
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
        'add'  => ['username', 'email', 'nickname', 'password', 'mobile'],
        'edit' => ['username', 'email', 'nickname', 'password', 'mobile'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'username' => __('Username'),
            'nickname' => __('Nickname'),
            'password' => __('Password'),
            'email'    => __('Email'),
            'mobile'   => __('Mobile'),
        ];
        $this->message = array_merge($this->message, [
            'username.regex' => __('Please input correct username'),
            'password.regex' => __('Please input correct password')
        ]);
        parent::__construct($rules, $message, $field);
    }

}
