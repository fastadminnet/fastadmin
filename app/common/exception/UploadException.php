<?php

namespace app\common\exception;

use think\Exception;
use Throwable;

class UploadException extends Exception
{
    public function __construct($message = "", $code = 0, $data = [])
    {
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;
    }

}
