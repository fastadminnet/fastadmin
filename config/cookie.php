<?php

/**
 * Cookie 配置
 */
return [
    // 过期时间（秒，0 表示浏览器关闭即失效）
    'expire'   => 0,
    // 生效路径
    'path'     => '/',
    // 生效域名（留空表示当前域名）
    'domain'   => '',
    // 是否仅通过 HTTPS 发送
    'secure'   => false,
    // 是否禁止 JavaScript 读取（HttpOnly）
    'httponly' => true,
    // SameSite 策略（可选 Lax/Strict/None，留空表示不设置）
    'samesite' => '',
];
