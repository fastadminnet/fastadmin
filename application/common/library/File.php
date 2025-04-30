<?php
namespace app\common\library;

class File extends \think\File implements \ArrayAccess
{

    public function __construct($filename, $mode = 'r')
    {

        parent::__construct($filename, $mode);
    }

    public function offsetExists($offset)
    {
        return isset($this->info[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->info[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->info[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->info[$offset]);
    }
}