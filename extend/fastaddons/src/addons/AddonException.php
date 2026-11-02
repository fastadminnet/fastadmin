<?php

namespace fastaddons\addons;

use Exception;

/**
 * 插件异常类
 * Class AddonException
 * @package fastaddons\addons
 */
class AddonException extends Exception
{
    protected $data = [];

    public function __construct($message, $code = 0, $data = [], $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}