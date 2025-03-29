<?php

namespace app\common\library;

use app\common\exception\UploadException;
use app\common\model\Attachment;
use fast\Random;
use FilesystemIterator;
use think\Config;
use think\File;
use think\Hook;

/**
 * 文件上传类
 */
class Upload
{
    protected $merging = false;

    protected $chunkDir = null;

    protected $config = [];

    protected $error = '';

    /**
     * @var File
     */
    protected $file = null;
    protected $fileInfo = null;

    public function __construct($file = null)
    {
        $this->config = Config::get('upload');
        $this->chunkDir = RUNTIME_PATH . 'chunks';
        if ($file) {
            $this->setFile($file);
        }
    }

    /**
     * 设置分片目录
     * @param $dir
     */
    public function setChunkDir($dir)
    {
        $this->chunkDir = $dir;
    }

    /**
     * 获取文件
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * 设置文件
     * @param $file
     * @throws UploadException
     */
    public function setFile($file)
    {
        if (empty($file)) {
            throw new UploadException(__('No file upload or server upload limit exceeded'));
        }

        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix && preg_match("/^[a-zA-Z0-9]+$/", $suffix) ? $suffix : 'file';
        $fileInfo['suffix'] = $suffix;
        $fileInfo['imagewidth'] = 0;
        $fileInfo['imageheight'] = 0;

        $this->file = $file;
        $this->fileInfo = $fileInfo;
        $this->checkExecutable();
    }

    /**
     * 检测是否为可执行脚本
     * @return bool
     * @throws UploadException
     */
    protected function checkExecutable()
    {
        //禁止上传以.开头的文件
        if (substr($this->fileInfo['name'], 0, 1) === '.') {
            throw new UploadException(__('Uploaded file format is limited'));
        }

        //禁止上传PHP和HTML文件
        if (in_array($this->fileInfo['type'], ['text/x-php', 'text/html']) || in_array($this->fileInfo['suffix'], ['php', 'html', 'htm', 'phar', 'phtml']) || preg_match("/^php(.*)/i", $this->fileInfo['suffix'])) {
            throw new UploadException(__('Uploaded file format is limited'));
        }
        return true;
    }

    /**
     * 检测文件类型
     * @return bool
     * @throws UploadException
     */
    protected function checkMimetype()
    {
        $mimetypeArr = explode(',', strtolower($this->config['mimetype']));
        $typeArr = explode('/', $this->fileInfo['type']);
        //Mimetype值不正确
        if (stripos($this->fileInfo['type'], '/') === false) {
            throw new UploadException(__('Uploaded file format is limited'));
        }
        //验证文件后缀
        if (in_array($this->fileInfo['suffix'], $mimetypeArr) || in_array('.' . $this->fileInfo['suffix'], $mimetypeArr)
            || in_array($typeArr[0] . "/*", $mimetypeArr) || (in_array($this->fileInfo['type'], $mimetypeArr) && stripos($this->fileInfo['type'], '/') !== false)) {
            return true;
        }
        throw new UploadException(__('Uploaded file format is limited'));
    }

    /**
     * 检测是否图片
     * @param bool $force
     * @return bool
     * @throws UploadException
     */
    protected function checkImage($force = false)
    {
        //验证是否为图片文件
        if (in_array($this->fileInfo['type'], ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($this->fileInfo['suffix'], ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            $imgInfo = getimagesize($this->fileInfo['tmp_name']);
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                throw new UploadException(__('Uploaded file is not a valid image'));
            }
            $this->fileInfo['imagewidth'] = $imgInfo[0] ?? 0;
            $this->fileInfo['imageheight'] = $imgInfo[1] ?? 0;
            return true;
        } else {
            return !$force;
        }
    }

    /**
     * 检测文件大小
     * @throws UploadException
     */
    protected function checkSize()
    {
        preg_match('/([0-9\.]+)(\w+)/', $this->config['maxsize'], $matches);
        $size = $matches ? $matches[1] : $this->config['maxsize'];
        $type = $matches ? strtolower($matches[2]) : 'b';
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)($size * pow(1024, $typeDict[$type] ?? 0));
        if ($this->fileInfo['size'] > $size) {
            throw new UploadException(__(
                'File is too big (%sMiB), Max filesize: %sMiB.',
                round($this->fileInfo['size'] / pow(1024, 2), 2),
                round($size / pow(1024, 2), 2)
            ));
        }
    }

    /**
     * 获取后缀
     * @return string
     */
    public function getSuffix()
    {
        return $this->fileInfo['suffix'] ?: 'file';
    }

    /**
     * 获取存储的文件名
     * @param string $savekey  保存路径
     * @param string $filename 文件名
     * @param string $md5      文件MD5
     * @param string $category 分类
     * @return mixed|null
     */
    public function getSavekey($savekey = null, $filename = null, $md5 = null, $category = null)
    {
        if ($filename) {
            $suffix = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        } else {
            $suffix = $this->fileInfo['suffix'] ?? '';
        }
        $suffix = $suffix && preg_match("/^[a-zA-Z0-9]+$/", $suffix) ? $suffix : 'file';
        $filename = $filename ? $filename : ($this->fileInfo['name'] ?? 'unknown');
        $filename = xss_clean(strip_tags(htmlspecialchars($filename)));
        $fileprefix = substr($filename, 0, strripos($filename, '.'));
        $md5 = $md5 ? $md5 : (isset($this->fileInfo['tmp_name']) ? md5_file($this->fileInfo['tmp_name']) : '');
        $category = $category ? $category : request()->post('category');
        $category = $category ? xss_clean($category) : 'all';
        $replaceArr = [
            '{year}'       => date("Y"),
            '{mon}'        => date("m"),
            '{day}'        => date("d"),
            '{hour}'       => date("H"),
            '{min}'        => date("i"),
            '{sec}'        => date("s"),
            '{random}'     => Random::alnum(16),
            '{random32}'   => Random::alnum(32),
            '{category}'   => $category ? $category : '',
            '{filename}'   => substr($filename, 0, 100),
            '{fileprefix}' => substr($fileprefix, 0, 100),
            '{suffix}'     => $suffix,
            '{.suffix}'    => $suffix ? '.' . $suffix : '',
            '{filemd5}'    => $md5,
        ];
        $savekey = $savekey ? $savekey : $this->config['savekey'];
        $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        return $savekey;
    }

    /**
     * 清理分片文件
     * @param $chunkid
     */
    public function clean($chunkid)
    {
        if (!preg_match('/^[a-z0-9\-]{36}$/', $chunkid)) {
            throw new UploadException(__('Invalid parameters'));
        }
        $iterator = new \GlobIterator($this->chunkDir . DS . $chunkid . '-*', FilesystemIterator::KEY_AS_FILENAME);
        $array = iterator_to_array($iterator);
        foreach ($array as $index => &$item) {
            $sourceFile = $item->getRealPath() ?: $item->getPathname();
            $item = null;
            @unlink($sourceFile);
        }
    }

    /**
     * 合并分片文件
     * @param string $chunkid
     * @param int    $chunkcount
     * @param string $filename
     * @return attachment|\think\Model
     * @throws UploadException
     */
    public function merge($chunkid, $chunkcount, $filename)
    {
        if (!preg_match('/^[a-z0-9\-]{36}$/', $chunkid)) {
            throw new UploadException(__('Invalid parameters'));
        }

        $filePath = $this->chunkDir . DS . $chunkid;

        $completed = true;
        //检查所有分片是否都存在
        for ($i = 0; $i < $chunkcount; $i++) {
            if (!file_exists("{$filePath}-{$i}.part")) {
                $completed = false;
                break;
            }
        }
        if (!$completed) {
            $this->clean($chunkid);
            throw new UploadException(__('Chunk file info error'));
        }

        //如果所有文件分片都上传完毕，开始合并
        $uploadPath = $filePath;

        if (!$destFile = @fopen($uploadPath, "wb")) {
            $this->clean($chunkid);
            throw new UploadException(__('Chunk file merge error'));
        }
        if (flock($destFile, LOCK_EX)) { // 进行排他型锁定
            for ($i = 0; $i < $chunkcount; $i++) {
                $partFile = "{$filePath}-{$i}.part";
                if (!$handle = @fopen($partFile, "rb")) {
                    break;
                }
                while ($buff = fread($handle, filesize($partFile))) {
                    fwrite($destFile, $buff);
                }
                @fclose($handle);
                @unlink($partFile); //删除分片
            }

            flock($destFile, LOCK_UN);
        }
        @fclose($destFile);

        $attachment = null;
        try {
            $file = new File($uploadPath);
            $info = [
                'name'     => $filename,
                'type'     => $file->getMime(),
                'tmp_name' => $uploadPath,
                'error'    => 0,
                'size'     => $file->getSize()
            ];
            $file->setSaveName($filename)->setUploadInfo($info);
            $file->isTest(true);

            //重新设置文件
            $this->setFile($file);

            unset($file);
            $this->merging = true;

            //允许大文件
            $this->config['maxsize'] = "1024G";

            $attachment = $this->upload();
        } catch (\Exception $e) {
            @unlink($uploadPath);
            throw new UploadException($e->getMessage());
        }
        return $attachment;
    }

    /**
     * 分片上传
     * @throws UploadException
     */
    public function chunk($chunkid, $chunkindex, $chunkcount, $chunkfilesize = null, $chunkfilename = null, $direct = false)
    {
        if ($this->fileInfo['type'] != 'application/octet-stream') {
            throw new UploadException(__('Uploaded file format is limited'));
        }

        if (!preg_match('/^[a-z0-9\-]{36}$/', $chunkid)) {
            throw new UploadException(__('Invalid parameters'));
        }

        $destDir = RUNTIME_PATH . 'chunks';
        $fileName = $chunkid . "-" . $chunkindex . '.part';
        $destFile = $destDir . DS . $fileName;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        if (!move_uploaded_file($this->file->getPathname(), $destFile)) {
            throw new UploadException(__('Chunk file write error'));
        }
        $file = new File($destFile);
        $info = [
            'name'     => $fileName,
            'type'     => $file->getMime(),
            'tmp_name' => $destFile,
            'error'    => 0,
            'size'     => $file->getSize()
        ];
        $file->setSaveName($fileName)->setUploadInfo($info);
        $this->setFile($file);
        return $file;
    }

    /**
     * 普通上传
     * @return \app\common\model\attachment|\think\Model
     * @throws UploadException
     */
    public function upload($savekey = null)
    {
        if (empty($this->file)) {
            throw new UploadException(__('No file upload or server upload limit exceeded'));
        }

        $this->checkSize();
        $this->checkExecutable();
        $this->checkMimetype();
        $this->checkImage();

        $savekey = $savekey ? $savekey : $this->getSavekey();
        $savekey = '/' . ltrim($savekey, '/');
        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);

        $destDir = ROOT_PATH . 'public' . str_replace('/', DS, $uploadDir);

        $sha1 = $this->file->hash();

        //如果是合并文件
        if ($this->merging) {
            if (!$this->file->check()) {
                throw new UploadException($this->file->getError());
            }
            $destFile = $destDir . $fileName;
            $sourceFile = $this->file->getRealPath() ?: $this->file->getPathname();
            $info = $this->file->getInfo();
            $this->file = null;
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            rename($sourceFile, $destFile);
            $file = new File($destFile);
            $file->setSaveName($fileName)->setUploadInfo($info);
        } else {
            $file = $this->file->move($destDir, $fileName);
            if (!$file) {
                // 上传失败获取错误信息
                throw new UploadException($this->file->getError());
            }
        }
        $this->file = $file;
        $category = request()->post('category');
        $category = array_key_exists($category, config('site.attachmentcategory') ?? []) ? $category : '';
        $auth = Auth::instance();
        $params = array(
            'admin_id'    => (int)session('admin.id'),
            'user_id'     => (int)$auth->id,
            'filename'    => mb_substr(htmlspecialchars(strip_tags($this->fileInfo['name'])), 0, 100),
            'category'    => $category,
            'filesize'    => $this->fileInfo['size'],
            'imagewidth'  => $this->fileInfo['imagewidth'],
            'imageheight' => $this->fileInfo['imageheight'],
            'imagetype'   => $this->fileInfo['suffix'],
            'imageframes' => 0,
            'mimetype'    => $this->fileInfo['type'],
            'url'         => $uploadDir . $file->getSaveName(),
            'uploadtime'  => time(),
            'storage'     => 'local',
            'sha1'        => $sha1,
            'extparam'    => '',
        );
        $attachment = new Attachment();
        $attachment->data(array_filter($params));
        $attachment->save();

        \think\Hook::listen("upload_after", $attachment);
        return $attachment;
    }

    /**
     * 设置错误信息
     * @param $msg
     */
    public function setError($msg)
    {
        $this->error = $msg;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }
}
