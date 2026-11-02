<?php

namespace fastaddons\addons;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use think\facade\Cache;
use think\facade\Db;
use Exception;
use think\file\UploadedFile;
use think\facade\Log;

/**
 * 
 * @package think\addons
 */
class Service
{
    /**
     * 
     */
    public static function addons($params = [])
    {
        $params['domain'] = request()->host(true);
        return self::sendRequest('/addon/index', $params, 'GET');
    }

    /**
     * 
     */
    public static function isBuy($name, $extend = [])
    {
        $params = array_merge(['name' => $name, 'domain' => request()->host(true)], $extend);
        return self::sendRequest('/addon/isbuy', $params, 'POST');
    }

    /**
     * 
     *
     * @param string $name   
     * @param string $domain 
     */
    public static function isAuthorization($name, $domain = '')
    {
        $config = self::config($name);
        $request = request();
        $domain = self::getRootDomain($domain ? $domain : $request->host(true));
        if (isset($config['domains']) && isset($config['validations']) && isset($config['licensecodes'])) {
            $index = array_search($domain, $config['domains']);
            if ((in_array($domain, $config['domains']) && in_array(md5(md5($domain) . ($config['licensecodes'][$index] ?? '')), $config['validations'])) || $request->isCli()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 
     *
     * @param string $name   
     * @param array  $extend 
     * @return  string
     */
    public static function download($name, $extend = [])
    {
        $addonsTempDir = self::getAddonsBackupDir();
        $tmpFile = $addonsTempDir . $name . ".zip";
        try {
            $client = self::getClient();
            $response = $client->get('/addon/download', ['query' => array_merge(['name' => $name], $extend)]);
            $body = $response->getBody();
            $content = $body->getContents();
            if (substr($content, 0, 1) === '{') {
                $json = (array)json_decode($content, true);
                //,
                if (isset($json['data']['url'])) {
                    $response = $client->get($json['data']['url']);
                    $body = $response->getBody();
                    $content = $body->getContents();
                } else {
                    //
                    throw new AddonException($json['msg'] ?? '', $json['code'] ?? 0, $json['data'] ?? []);
                }
            }
        } catch (TransferException $e) {
            throw new Exception("Addon package download failed");
        }

        if ($write = fopen($tmpFile, 'w')) {
            fwrite($write, $content);
            fclose($write);
            return $tmpFile;
        }
        throw new Exception("No permission to write temporary files");
    }

    /**
     * 
     *
     * @param string $name 
     * @param string $file 
     * @return  string
     * @throws  Exception
     */
    public static function unzip($name, $file = '')
    {
        if (!$name) {
            throw new Exception('Invalid parameters');
        }
        $addonsBackupDir = self::getAddonsBackupDir();
        $file = $file ?: $addonsBackupDir . $name . '.zip';

        // 
        $zip = self::zip();
        try {
            $zip->openFile($file);
        } catch (ZipException $e) {
            $zip->close();
            throw new Exception('Unable to open the zip file');
        }

        $dir = self::getAddonDir($name);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755);
        }

        // 
        try {
            $zip->extractTo($dir);
        } catch (ZipException $e) {
            throw new Exception('Unable to extract the file');
        } finally {
            $zip->close();
        }
        return $dir;
    }

    /**
     * 
     * @param string $file   
     * @param array  $extend 
     * @param string $force  
     */
    public static function local($file, $extend = [], $force = false)
    {
        $addonsTempDir = self::getAddonsBackupDir();
        if (!$file || !$file instanceof UploadedFile) {
            throw new Exception('No file upload or server upload limit exceeded');
        }
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, ['zip', 'fastaddon'])) {
            throw new Exception('Only zip/fastaddon files are supported');
        }
        if ($file->getSize() > 102400000) {
            throw new Exception('File size exceeds the limit');
        }
        $saveName = uniqid('', true) . '.' . $ext;
        try {
            $uploadFile = $file->move($addonsTempDir, $saveName);
        } catch (\Throwable $e) {
            throw new Exception($e->getMessage());
        }
        if (!$uploadFile) {
            throw new Exception('Upload failed');
        }
        $tmpFile = $uploadFile->getPathname();

        $info = [];
        $zip = self::zip();
        try {

            // 
            try {
                $zip->openFile($tmpFile);
            } catch (ZipException $e) {
                @unlink($tmpFile);
                throw new Exception('Unable to open the zip file');
            }

            $config = self::getInfoIni($zip);

            // 
            $name = $config['name'] ?? '';
            if (!$name) {
                throw new Exception('Addon info file data incorrect');
            }

            // 
            if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
                throw new Exception('Addon name incorrect');
            }

            // 
            $newAddonDir = self::getAddonDir($name);
            if (!$force && is_dir($newAddonDir)) {
                throw new AddonException('Addon already exists', -1, ['name' => $name, 'title' => $config['title'] ?? '']);
            }

            // 
            $oldversion = '';
            if (is_dir($newAddonDir)) {
                $oldConfig = parse_ini_file($newAddonDir . 'info.ini');
                $oldversion = $oldConfig['version'] ?? '';
            }

            $extend['oldversion'] = $oldversion;
            $extend['version'] = $config['version'] ?? '';

            // MD5Data
            $extend['md5'] = md5_file($tmpFile);
            $extend['data'] = $zip->getArchiveComment();
            $extend['unknownsources'] = config('app.app_debug') && config('fastadmin.unknownsources');
            $extend['faversion'] = config('fastadmin.version');
            $extend['phpversion'] = PHP_VERSION;
            $mysqlVersion = Db::query('SELECT version() AS version');
            $extend['mysqlversion'] = $mysqlVersion[0]['version'] ?? '';

            $params = array_merge($config, $extend);

            // 
            Service::valid($params);

            if (!$oldversion) {
                // 
                $info = Service::install($name, $force, $extend, $tmpFile);
            } else {
                // 
                $info = Service::upgrade($name, $extend, $tmpFile);
            }

        } catch (AddonException $e) {
            throw new AddonException($e->getMessage(), $e->getCode(), $e->getData());
        } catch (Exception $e) {
            throw new Exception(__($e->getMessage()));
        } finally {
            $zip->close();
            unset($uploadFile);
            is_file($tmpFile) && unlink($tmpFile);
        }

        $info['config'] = get_addon_config($name) ? 1 : 0;
        $info['bootstrap'] = is_file(Service::getBootstrapFile($name));
        $info['testdata'] = is_file(Service::getTestdataFile($name));
        return $info;
    }

    /**
     * 
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public static function valid($params = [])
    {
        $json = self::sendRequest('/addon/valid', $params, 'POST');
        if ($json && isset($json['code'])) {
            if ($json['code']) {
                return true;
            } else {
                throw new Exception($json['msg'] ?? "Invalid addon package");
            }
        } else {
            throw new Exception("Unknown data format");
        }
    }

    /**
     * 
     * @param string $name 
     * @return bool
     * @throws Exception
     */
    public static function backup($name)
    {
        $addonsBackupDir = self::getAddonsBackupDir();
        $file = $addonsBackupDir . $name . '-backup-' . date("YmdHis") . '.zip';
        $zipFile = self::zip();
        try {
            $zipFile
                ->addDirRecursive(self::getAddonDir($name))
                ->saveAsFile($file)
                ->close();
        } catch (ZipException $e) {

        } finally {
            $zipFile->close();
        }

        return true;
    }

    /**
     * 
     *
     * @param string $name 
     * @return  boolean
     * @throws  Exception
     */
    public static function check($name)
    {
        if (!$name || !is_dir(addons_path() . $name)) {
            throw new Exception('Addon not exists');
        }
        $addonClass = get_addon_class($name);
        if (!$addonClass) {
            throw new Exception("The addon file does not exist");
        }
        $addon = new $addonClass();
        if (!$addon->checkInfo()) {
            throw new Exception("The configuration file content is incorrect");
        }
        // 
        if (method_exists($addon, 'checkEnv')) {
            $addon->checkEnv();
        }
        return true;
    }

    /**
     * 
     *
     * @param string $name 
     * @return  boolean
     * @throws  AddonException
     */
    public static function noconflict($name)
    {
        // 
        $list = self::getGlobalFiles($name, true);
        if ($list) {
            //
            throw new AddonException(__("Conflicting file found"), -3, ['conflictlist' => $list]);
        }
        return true;
    }

    /**
     * SQL
     *
     * @param string $name     
     * @param string $fileName SQL
     * @return  boolean
     */
    public static function importsql($name, $fileName = null)
    {
        $fileName = is_null($fileName) ? 'install.sql' : $fileName;
        $sqlFile = self::getAddonDir($name) . $fileName;
        if (is_file($sqlFile)) {
            $lines = file($sqlFile);
            $templine = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*') {
                    continue;
                }

                $templine .= $line;
                if (substr(trim($line), -1, 1) == ';') {
                    $prefix = (string)config('database.prefix');
                    $templine = str_ireplace('__PREFIX__', $prefix, $templine);
                    $templine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $templine);
                    try {
                        Db::getPdo()->exec($templine);
                    } catch (\PDOException $e) {
                        \think\facade\Log::error('Addon SQL execution failed: ' . $e->getMessage());
                    }
                    $templine = '';
                }
            }
        }
        return true;
    }

    /**
     * 
     *
     * @return  boolean
     * @throws  Exception
     */
    public static function refresh()
    {
        //addons.js
        $addons = get_addon_list();
        $bootstrapArr = [];
        foreach ($addons as $name => $addon) {
            $bootstrapFile = self::getBootstrapFile($name);
            if ($addon['state'] && is_file($bootstrapFile)) {
                $bootstrapArr[] = file_get_contents($bootstrapFile);
            }
        }
        $addonsFile = root_path() . str_replace("/", DIRECTORY_SEPARATOR, "public/assets/js/addons.js");
        if ($handle = fopen($addonsFile, 'w')) {
            $tpl = <<<EOD
define([], function () {
    {__JS__}
});
EOD;
            fwrite($handle, str_replace("{__JS__}", implode("\n", $bootstrapArr), $tpl));
            fclose($handle);
        } else {
            throw new Exception(__("Unable to open file '%s' for writing", "addons.js"));
        }

        Cache::delete("addons");
        Cache::delete("hooks");

        $file = self::getExtraAddonsFile();

        $config = get_addon_autoload_config(true);
        if ($config['autoload']) {
            return;
        }

        if (!is_really_writable($file)) {
            throw new Exception(__("Unable to open file '%s' for writing", "addons.php"));
        }

        file_put_contents($file, "<?php\n\n// 本文件由插件管理自动生成，请勿手动修改。\n\n" . "return " . var_export_short($config, true) . ";\n", LOCK_EX);
        return true;
    }

    /**
     * 
     *
     * @param string  $name    
     * @param boolean $force   
     * @param array   $extend  
     * @param array   $tmpFile 
     * @return  boolean
     * @throws  Exception
     * @throws  AddonException
     */
    public static function install($name, $force = false, $extend = [], $tmpFile = '')
    {
        if (!$name || (is_dir(addons_path() . $name) && !$force)) {
            throw new Exception('Addon already exists');
        }

        $extend['domain'] = request()->host(true);
        $extend['phpversion'] = PHP_VERSION;
        $mysqlVersion = Db::query('SELECT version() AS version');
        $extend['mysqlversion'] = $mysqlVersion[0]['version'] ?? '';

        // 
        $tmpFile = $tmpFile ?: Service::download($name, $extend);

        $addonDir = self::getAddonDir($name);

        try {
            // 
            Service::unzip($name, $tmpFile);

            // 
            Service::check($name);

            if (!$force) {
                Service::noconflict($name);
            }
        } catch (AddonException $e) {
            @rmdirs($addonDir);
            throw new AddonException($e->getMessage(), $e->getCode(), $e->getData());
        } catch (Exception $e) {
            @rmdirs($addonDir);
            throw new Exception($e->getMessage());
        } finally {
            // 
            @unlink($tmpFile);
        }

        // 
        $info = get_addon_info($name);

        Db::startTrans();
        try {
            if (!$info['state']) {
                $info['state'] = 1;
                set_addon_info($name, $info);
            }

            // 
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();
                $addon->install();
            }
            Db::commit();
        } catch (Exception $e) {
            @rmdirs($addonDir);
            Db::rollback();
            throw new Exception($e->getMessage());
        }

        // 
        Service::importsql($name);

        // 
        Service::enable($name, true);

        $info['config'] = get_addon_config($name) ? 1 : 0;
        $info['bootstrap'] = is_file(Service::getBootstrapFile($name));
        $info['testdata'] = is_file(Service::getTestdataFile($name));
        return $info;
    }

    /**
     * 
     *
     * @param string  $name
     * @param boolean $force 
     * @return  boolean
     * @throws  Exception
     */
    public static function uninstall($name, $force = false)
    {
        if (!$name || !is_dir(addons_path() . $name)) {
            throw new Exception('Addon not exists');
        }

        if (!$force) {
            Service::noconflict($name);
        }

        // 删除插件安装到全局的文件（含 application → app 映射）
        // getGlobalFiles 只扫描插件目录，迁移后 app/ 源文件可能已不在插件目录，
        // 因此必须合并 .addonrc 的 files 清单，否则会残留 app/ 与 public/ 文件。
        $list = Service::getGlobalFiles($name);
        $__addonConfig = (array)Service::config($name);
        foreach (($__addonConfig['files'] ?? []) as $v) {
            $list[] = $v;
        }
        $list = array_values(array_unique(array_filter($list)));
        foreach ($list as $v) {
            $relative = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, self::mapTp8Path((string)$v)), DIRECTORY_SEPARATOR);
            $file = root_path() . $relative;
            if (is_file($file)) {
                @unlink($file);
            }
            if (is_dir(dirname($file))) {
                remove_empty_folder(dirname($file));
            }
        }
        // 删除插件资源目录（public/assets/addons/<name>）
        $assetDir = self::getDestAssetsDir($name);
        if (is_dir($assetDir)) {
            @rmdirs($assetDir);
        }

        // 
        try {
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();
                $addon->uninstall();
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // 
        rmdirs(addons_path() . $name);

        // 
        Service::refresh();
        return true;
    }

    /**
     * 
     * @param string  $name  
     * @param boolean $force 
     * @return  boolean
     */
    public static function enable($name, $force = false)
    {
        if (!$name || !is_dir(addons_path() . $name)) {
            throw new Exception('Addon not exists');
        }

        if (!$force) {
            Service::noconflict($name);
        }

        //
        if (config('fastadmin.backup_global_files')) {
            $conflictFiles = self::getGlobalFiles($name, true);
            if ($conflictFiles) {
                $zip = self::zip();
                try {
                    foreach ($conflictFiles as $k => $v) {
                        $zip->addFile(root_path() . $v, $v);
                    }
                    $addonsBackupDir = self::getAddonsBackupDir();
                    $zip->saveAsFile($addonsBackupDir . $name . "-conflict-enable-" . date("YmdHis") . ".zip");
                } catch (Exception $e) {

                } finally {
                    $zip->close();
                }
            }
        }

        $addonDir = self::getAddonDir($name);
        $sourceAssetsDir = self::getSourceAssetsDir($name);
        $destAssetsDir = self::getDestAssetsDir($name);

        $files = self::getGlobalFiles($name);
        if ($files) {
            //
            Service::config($name, ['files' => $files]);
        }

        // 
        if (is_dir($sourceAssetsDir)) {
            copydirs($sourceAssetsDir, $destAssetsDir);
        }

        // applicationpublic
        foreach (self::getCheckDirs() as $k => $dir) {
            if (is_dir($addonDir . $dir)) {
                $targetDir = $dir === 'application' ? 'app' : $dir;
                copydirs($addonDir . $dir, root_path() . $targetDir);
            }
        }

        //applicationpublicassets
        if (config('fastadmin.addon_pure_mode')) {
            // 
            @rmdirs($sourceAssetsDir);
            foreach (self::getCheckDirs() as $k => $dir) {
                @rmdirs($addonDir . $dir);
            }
        }

        //
        try {
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();
                if (method_exists($class, "enable")) {
                    $addon->enable();
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $info = get_addon_info($name);
        $info['state'] = 1;
        unset($info['url']);

        set_addon_info($name, $info);

        // 
        Service::refresh();
        return true;
    }

    /**
     * 
     *
     * @param string  $name  
     * @param boolean $force 
     * @return  boolean
     * @throws  Exception
     */
    public static function disable($name, $force = false)
    {
        if (!$name || !is_dir(addons_path() . $name)) {
            throw new Exception('Addon not exists');
        }

        $file = self::getExtraAddonsFile();
        if (!is_really_writable($file)) {
            throw new Exception(__("Unable to open file '%s' for writing", "addons.php"));
        }

        if (!$force) {
            Service::noconflict($name);
        }

        if (config('fastadmin.backup_global_files')) {
            //
            $conflictFiles = Service::getGlobalFiles($name, true);
            if ($conflictFiles) {
                $zip = self::zip();
                try {
                    foreach ($conflictFiles as $k => $v) {
                        $zip->addFile(root_path() . $v, $v);
                    }
                    $addonsBackupDir = self::getAddonsBackupDir();
                    $zip->saveAsFile($addonsBackupDir . $name . "-conflict-disable-" . date("YmdHis") . ".zip");
                } catch (Exception $e) {

                } finally {
                    $zip->close();
                }
            }
        }

        $config = Service::config($name);

        $addonDir = self::getAddonDir($name);
        //
        $destAssetsDir = self::getDestAssetsDir($name);

        // 
        $list = Service::getGlobalFiles($name);

        //
        //
        if (config('fastadmin.addon_pure_mode') || !$list) {
            if ($config && isset($config['files']) && is_array($config['files'])) {
                foreach ($config['files'] as $index => $item) {
                    //
                    $item = self::mapTp8Path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $item));
                    //
                    if (stripos($item, str_replace(root_path(), '', $destAssetsDir)) === 0) {
                        continue;
                    }
                    //
                    $itemBaseDir = dirname($addonDir . $item);
                    if (!is_dir($itemBaseDir)) {
                        @mkdir($itemBaseDir, 0755, true);
                    }
                    if (is_file(root_path() . $item)) {
                        @copy(root_path() . $item, $addonDir . $item);
                    }
                }
                $list = $config['files'];
            }
            //
            if (is_dir($destAssetsDir)) {
                @copydirs($destAssetsDir, $addonDir . 'assets' . DIRECTORY_SEPARATOR);
            }
        }

        $dirs = [];
        foreach ($list as $k => $v) {
            $file = root_path() . self::mapTp8Path($v);
            $dirs[] = dirname($file);
            @unlink($file);
        }

        // 
        $dirs = array_filter(array_unique($dirs));
        foreach ($dirs as $k => $v) {
            remove_empty_folder($v);
        }

        $info = get_addon_info($name);
        $info['state'] = 0;
        unset($info['url']);

        set_addon_info($name, $info);

        // 
        try {
            $class = get_addon_class($name);
            if (class_exists($class)) {
                $addon = new $class();

                if (method_exists($class, "disable")) {
                    $addon->disable();
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // 
        Service::refresh();
        return true;
    }

    /**
     * 
     *
     * @param string $name   
     * @param array  $extend 
     */
    public static function upgrade($name, $extend = [], $tmpFile = false)
    {
        $info = get_addon_info($name);
        if ($info['state']) {
            throw new Exception(__('Please disable addon first'));
        }
        $config = get_addon_config($name);
        if ($config) {
            //
        }

        // ()
        $tmpFile = $tmpFile ? $tmpFile : Service::download($name, $extend);

        // 
        Service::backup($name);

        $addonDir = self::getAddonDir($name);

        // applicationpublic
        $files = self::getCheckDirs();
        foreach ($files as $index => $file) {
            @rmdirs($addonDir . $file);
        }

        try {
            // 
            Service::unzip($name, $tmpFile);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        } finally {
            // 
            @unlink($tmpFile);
        }

        if ($config) {
            $configFile = addons_path() . $name . DIRECTORY_SEPARATOR . 'config.php';
            $bakFile = addons_path() . $name . DIRECTORY_SEPARATOR . 'config_tmp.php';
            @copy($configFile, $bakFile);
            $fullConfig = include($bakFile);
            @unlink($bakFile);
            foreach ($fullConfig as $index => &$item) {
                if (isset($config[$item['name']])) {
                    $item['value'] = $config[$item['name']];
                }
            }
            // 
            set_addon_fullconfig($name, $fullConfig);
        }

        // 
        Service::importsql($name);

        // 
        try {
            $addonName = ucfirst($name);
            //
            $sourceFile = $addonDir . $addonName . ".php";
            $destFile = $addonDir . $addonName . "Upgrade.php";

            $classContent = str_replace("class {$addonName} extends", "class {$addonName}Upgrade extends", file_get_contents($sourceFile));

            //
            file_put_contents($destFile, $classContent);

            $className = "\\addons\\" . $name . "\\" . $addonName . "Upgrade";
            $addon = new $className($name);

            //
            if (method_exists($addon, "upgrade")) {
                $addon->upgrade();
            }

            //
            @unlink($destFile);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // 
        Service::refresh();

        //
        $info['version'] = $extend['version'] ?? $info['version'];

        $info['config'] = get_addon_config($name) ? 1 : 0;
        $info['bootstrap'] = is_file(Service::getBootstrapFile($name));
        return $info;
    }

    /**
     * 
     * @param string $name
     * @param array  $changed
     * @return array
     */
    public static function config($name, $changed = [])
    {
        $addonDir = self::getAddonDir($name);
        $addonConfigFile = $addonDir . '.addonrc';
        $config = [];
        if (is_file($addonConfigFile)) {
            $config = (array)json_decode(file_get_contents($addonConfigFile), true);
        }
        $config = array_merge($config, $changed);
        if ($changed) {
            file_put_contents($addonConfigFile, json_encode($config, JSON_UNESCAPED_UNICODE));
        }
        return $config;
    }

    /**
     * 
     *
     * @param string  $name         
     * @param boolean $onlyconflict 
     * @return  array
     */
    public static function getGlobalFiles($name, $onlyconflict = false)
    {
        $list = [];
        $addonDir = self::getAddonDir($name);
        $checkDirList = self::getCheckDirs();
        $checkDirList = array_merge($checkDirList, ['assets']);

        $assetDir = self::getDestAssetsDir($name);

        // 
        foreach ($checkDirList as $k => $dirName) {
            //
            if (!is_dir($addonDir . $dirName)) {
                continue;
            }
            //
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($addonDir . $dirName, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                if ($fileinfo->isFile()) {
                    $filePath = $fileinfo->getPathName();
                    //assets
                    if ($dirName === 'assets') {
                        $path = str_replace(root_path(), '', $assetDir) . str_replace($addonDir . $dirName . DIRECTORY_SEPARATOR, '', $filePath);
                    } else {
                        $path = self::mapTp8Path(str_replace($addonDir, '', $filePath));
                    }
                    if ($onlyconflict) {
                        $destPath = root_path() . $path;
                        if (is_file($destPath)) {
                            if (filesize($filePath) != filesize($destPath) || md5_file($filePath) != md5_file($destPath)) {
                                $list[] = $path;
                            }
                        }
                    } else {
                        $list[] = $path;
                    }
                }
            }
        }
        $list = array_filter(array_unique($list));
        return $list;
    }

    /**
     * 
     */
    public static function authorization($params = [])
    {
        $addonList = get_addon_list();
        $result = [];
        $domain = request()->host(true);
        $addons = [];
        foreach ($addonList as $name => $item) {
            $config = self::config($name);
            $addons[] = ['name' => $name, 'domains' => $config['domains'] ?? [], 'licensecodes' => $config['licensecodes'] ?? [], 'validations' => $config['validations'] ?? []];
        }
        $params = array_merge($params, [
            'faversion' => config('fastadmin.version'),
            'domain'    => $domain,
            'addons'    => $addons
        ]);
        $result = self::sendRequest('/addon/authorization', $params, 'POST');
        if (isset($result['code']) && $result['code'] == 1) {
            $json = $result['data']['addons'] ?? [];
            foreach ($addonList as $name => $item) {
                self::config($name, ['domains' => $json[$name]['domains'] ?? [], 'licensecodes' => $json[$name]['licensecodes'] ?? [], 'validations' => $json[$name]['validations'] ?? []]);
            }
            return true;
        } else {
            throw new Exception($result['msg'] ?? __('Network error'));
        }
    }

    /**
     * 
     * @param $name
     * @return bool
     */
    public static function checkAddonAuthorization($name)
    {
        $request = request();
        $config = self::config($name);
        $domain = self::getRootDomain($request->host(true));
        //
        if (isset($config['domains']) && isset($config['validations']) && isset($config['licensecodes'])) {
            $index = array_search($domain, $config['domains']);
            if ((in_array($domain, $config['domains']) && in_array(md5(md5($domain) . ($config['licensecodes'][$index] ?? '')), $config['validations'])) || $request->isCli()) {
                app()->instance('authorized', $domain ?: 'cli');
                return true;
            } elseif ($config['domains']) {
                foreach ($config['domains'] as $index => $item) {
                    if (substr_compare($domain, "." . $item, -strlen("." . $item)) === 0 && in_array(md5(md5($item) . ($config['licensecodes'][$index] ?? '')), $config['validations'])) {
                        app()->instance('authorized', $domain);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 
     * @param $domain
     * @return string
     */
    public static function getRootDomain($domain)
    {
        $host = strtolower(trim($domain));
        $hostArr = explode('.', $host);
        $hostCount = count($hostArr);
        $cnRegex = '/\w+\.(gov|org|ac|mil|net|edu|com|bj|tj|sh|cq|he|sx|nm|ln|jl|hl|js|zj|ah|fj|jx|sd|ha|hb|hn|gd|gx|hi|sc|gz|yn|xz|sn|gs|qh|nx|xj|tw|hk|mo)\.cn$/i';
        $countryRegex = '/\w+\.(\w{2}|com|net)\.\w{2}$/i';
        if ($hostCount > 2 && (preg_match($cnRegex, $host) || preg_match($countryRegex, $host))) {
            $host = implode('.', array_slice($hostArr, -3, 3, true));
        } else {
            $host = implode('.', array_slice($hostArr, -2, 2, true));
        }
        return $host;
    }

    /**
     * 
     * @return string
     */
    public static function getExtraAddonsFile()
    {
        return config_path() . 'addons.php';
    }

    /**
     * bootstrap.js
     * @return string
     */
    public static function getBootstrapFile($name)
    {
        return addons_path() . $name . DIRECTORY_SEPARATOR . 'bootstrap.js';
    }

    /**
     * testdata.sql
     * @return string
     */
    public static function getTestdataFile($name)
    {
        return addons_path() . $name . DIRECTORY_SEPARATOR . 'testdata.sql';
    }

    /**
     * 
     */
    public static function getAddonDir($name)
    {
        $dir = addons_path() . $name . DIRECTORY_SEPARATOR;
        return $dir;
    }

    /**
     * 
     */
    public static function getAddonsBackupDir()
    {
        $dir = runtime_path() . 'addons' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * 
     * @param string $name 
     * @return  string
     */
    protected static function getSourceAssetsDir($name)
    {
        return addons_path() . $name . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
    }

    /**
     * 
     * @param string $name 
     * @return  string
     */
    protected static function getDestAssetsDir($name)
    {
        $assetsDir = root_path() . str_replace("/", DIRECTORY_SEPARATOR, "public/assets/addons/{$name}/");
        return $assetsDir;
    }

    /**
     * 
     * @return  string
     */
    protected static function getServerUrl()
    {
        return config('fastadmin.api_url');
    }

    /**
     * 
     * @return  array
     */
    protected static function getCheckDirs()
    {
        return [
            'app',
            'application',
            'public'
        ];
    }

    /**
     * TP5 插件文件位于 application/，TP8 应用目录为 app/
     */
    protected static function mapTp8Path(string $path): string
    {
        return preg_replace('#^application([\\\\/])#', 'app$1', $path);
    }

    /**
     * Get zip instance
     * @return ZipFile
     * @throws Exception
     */
    protected static function zip()
    {
        if (!class_exists(ZipFile::class)) {
            throw new Exception('The nelexa/zip package is required for addon zip operations');
        }
        return new ZipFile();
    }
    /**
     * 
     * @return Client
     */
    public static function getClient()
    {
        if (!class_exists(Client::class)) {
            throw new Exception('The guzzlehttp/guzzle package is required for addon market operations');
        }
        $options = [
            'base_uri'        => self::getServerUrl(),
            'timeout'         => 30,
            'connect_timeout' => 30,
            'verify'          => false,
            'http_errors'     => false,
            'headers'         => [
                'X-REQUESTED-WITH' => 'XMLHttpRequest',
                'Referer'          => dirname(request()->root(true)),
                'User-Agent'       => 'FastAddon',
            ]
        ];
        static $client;
        if (empty($client)) {
            $client = new Client($options);
        }
        return $client;
    }

    /**
     * 
     * @return array
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function sendRequest($url, $params = [], $method = 'POST')
    {
        $json = [];
        try {
            $client = self::getClient();
            $options = strtoupper($method) == 'POST' ? ['form_params' => $params] : ['query' => $params];
            $response = $client->request($method, $url, $options);
            $body = $response->getBody();
            $content = $body->getContents();
            $json = (array)json_decode($content, true);
        } catch (TransferException $e) {
            throw new Exception(__('Network error'));
        } catch (\Throwable $e) {
            throw new Exception(__('Unknown data format'));
        }
        return $json;
    }

    /**
     * info
     * @param ZipFile $zip
     * @return array|false
     * @throws Exception
     */
    protected static function getInfoIni($zip)
    {
        $config = [];
        // 
        try {
            $info = $zip->getEntryContents('info.ini');
            $config = parse_ini_string($info);
        } catch (ZipException $e) {
            throw new Exception('Unable to extract the file');
        }
        return $config;
    }

}
