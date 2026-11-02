<?php

// API路由
return [
    // API 根路由
    '' => 'api/index/index',

    // Common 接口
    'common/init' => 'api/common/init',
    'common/upload' => 'api/common/upload',
    'common/captcha' => 'api/common/captcha',

    // User 接口
    'user/login' => 'api/user/login',
    'user/register' => 'api/user/register',
    'user/logout' => 'api/user/logout',
    'user/profile' => 'api/user/profile',
    'user/changepwd' => 'api/user/changepwd',
    'user/changemobile' => 'api/user/changemobile',
    'user/changeemail' => 'api/user/changeemail',
    'user/resetpwd' => 'api/user/resetpwd',
    'user/third' => 'api/user/third',
    'user/index' => 'api/user/index',

    // Validate 接口
    'validate/check_email_available' => 'api/validate/check_email_available',
    'validate/check_username_available' => 'api/validate/check_username_available',
    'validate/check_nickname_available' => 'api/validate/check_nickname_available',
    'validate/check_mobile_available' => 'api/validate/check_mobile_available',
    'validate/check_mobile_exist' => 'api/validate/check_mobile_exist',
    'validate/check_email_exist' => 'api/validate/check_email_exist',
    'validate/check_sms_correct' => 'api/validate/check_sms_correct',
    'validate/check_ems_correct' => 'api/validate/check_ems_correct',

    // Token 接口
    'token/check' => 'api/token/check',
    'token/refresh' => 'api/token/refresh',

    // Sms 接口
    'sms/send' => 'api/sms/send',
    'sms/check' => 'api/sms/check',

    // Ems 接口
    'ems/send' => 'api/ems/send',
    'ems/check' => 'api/ems/check',

    // Demo 接口
    'demo/test' => 'api/demo/test',
    'demo/test1' => 'api/demo/test1',
    'demo/test2' => 'api/demo/test2',
];
