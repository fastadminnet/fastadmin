<?php

namespace app\admin\command;

use fast\Random;
use PDO;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Lang;
use think\facade\View;

class Install extends Command
{
    /**
     * Minimum PHP version.
     * @var string
     */
    protected $minPhpVersion = '8.1.0';

    protected function configure()
    {
        $config = $this->getDefaultDbConfig();
        $this
            ->setName('install')
            ->addOption('hostname', 'a', Option::VALUE_OPTIONAL, 'mysql hostname', $config['hostname'] ?? '127.0.0.1')
            ->addOption('hostport', 'o', Option::VALUE_OPTIONAL, 'mysql hostport', $config['hostport'] ?? '3306')
            ->addOption('database', 'd', Option::VALUE_OPTIONAL, 'mysql database', $config['database'] ?? '')
            ->addOption('prefix', 'r', Option::VALUE_OPTIONAL, 'table prefix', $config['prefix'] ?? 'fa_')
            ->addOption('username', 'u', Option::VALUE_OPTIONAL, 'mysql username', $config['username'] ?? 'root')
            ->addOption('password', 'p', Option::VALUE_OPTIONAL, 'mysql password', $config['password'] ?? '')
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, 'force override', false)
            ->setDescription('New installation of FastAdmin');
    }

    /**
     * Get the default database connection config (TP8 nested structure aware).
     * @return array
     */
    protected function getDefaultDbConfig()
    {
        $database = Config::get('database', []);
        if (is_array($database) && isset($database['connections'])) {
            $name = $database['default'] ?? 'mysql';
            return $database['connections'][$name] ?? [];
        }
        return is_array($database) ? $database : [];
    }

    protected function execute(Input $input, Output $output)
    {
        define('INSTALL_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR);

        $force = $input->getOption('force');
        $hostname = $input->getOption('hostname');
        $hostport = $input->getOption('hostport');
        $database = $input->getOption('database');
        $prefix = $input->getOption('prefix');
        $username = $input->getOption('username');
        $password = $input->getOption('password');

        $installLockFile = INSTALL_PATH . 'install.lock';
        if (is_file($installLockFile) && !$force) {
            throw new Exception("\nFastAdmin already installed!\nIf you need to reinstall again, use the parameter --force=true ");
        }

        $adminUsername = 'admin';
        $adminPassword = Random::alnum(10);
        $adminEmail = 'admin@admin.com';
        $siteName = 'My Website';

        $adminName = $this->installation($hostname, $hostport, $database, $username, $password, $prefix, $adminUsername, $adminPassword, $adminEmail, $siteName);
        if ($adminName) {
            $output->highlight("Admin url:https://www.example.com/{$adminName}");
        }

        $output->highlight("Admin username:{$adminUsername}");
        $output->highlight("Admin password:{$adminPassword}");

        Cache::delete('__menu__');

        $output->info('Install Successed!');
    }

    /**
     * Web installer entry.
     */
    public function index()
    {
        define('INSTALL_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR);

        $lang = Lang::getLangSet() ?: 'zh-cn';
        if (in_array($lang, ['zh-cn', 'zh-hans-cn'])) {
            Lang::load(INSTALL_PATH . 'zh-cn.php');
        }

        $installLockFile = INSTALL_PATH . 'install.lock';
        if (is_file($installLockFile)) {
            echo __('The system has been installed. If you need to reinstall, please remove %s first', 'install.lock');
            exit;
        }

        $output = function ($code, $msg, $url = null, $data = null) {
            return json(['code' => $code, 'msg' => $msg, 'url' => $url, 'data' => $data]);
        };

        $request = request();
        if ($request->isPost()) {
            $mysqlHostname = $request->post('mysqlHostname', '127.0.0.1');
            $mysqlHostport = $request->post('mysqlHostport', '3306');
            $hostArr = explode(':', $mysqlHostname);
            if (count($hostArr) > 1) {
                $mysqlHostname = $hostArr[0];
                $mysqlHostport = $hostArr[1];
            }
            $mysqlUsername = $request->post('mysqlUsername', 'root');
            $mysqlPassword = $request->post('mysqlPassword', '');
            $mysqlDatabase = $request->post('mysqlDatabase', '');
            $mysqlPrefix = $request->post('mysqlPrefix', 'fa_');
            $adminUsername = $request->post('adminUsername', 'admin');
            $adminPassword = $request->post('adminPassword', '');
            $adminPasswordConfirmation = $request->post('adminPasswordConfirmation', '');
            $adminEmail = $request->post('adminEmail', 'admin@admin.com');
            $siteName = $request->post('siteName', 'My Website');

            if ($adminPassword !== $adminPasswordConfirmation) {
                return $output(0, __('The two passwords you entered did not match'));
            }

            try {
                $adminName = $this->installation($mysqlHostname, $mysqlHostport, $mysqlDatabase, $mysqlUsername, $mysqlPassword, $mysqlPrefix, $adminUsername, $adminPassword, $adminEmail, $siteName);
            } catch (\PDOException $e) {
                return $output(0, $e->getMessage());
            } catch (\Exception $e) {
                return $output(0, $e->getMessage());
            }
            return $output(1, __('Install Successed'), null, ['adminName' => $adminName]);
        }

        $errInfo = '';
        try {
            $this->checkenv();
        } catch (\Exception $e) {
            $errInfo = $e->getMessage();
        }
        $envData = $this->getEnvDatabaseConfig();
        $envInfo = $this->getEnvironmentInfo();
        return View::fetch(INSTALL_PATH . 'install.html', ['errInfo' => $errInfo, 'envData' => $envData, 'envInfo' => $envInfo]);
    }

    /**
     * 读取根目录 .env 的数据库配置，作为安装表单默认值
     * @return array
     */
    protected function getEnvDatabaseConfig(): array
    {
        $defaults = [
            'hostname' => '127.0.0.1',
            'hostport' => '3306',
            'database' => '',
            'username' => 'root',
            'password' => '',
            'prefix'   => 'fa_',
        ];
        $envFile = root_path() . '.env';
        if (is_file($envFile)) {
            $content = (string)@file_get_contents($envFile);
            $map = [
                'HOSTNAME' => 'hostname',
                'HOSTPORT' => 'hostport',
                'DATABASE' => 'database',
                'USERNAME' => 'username',
                'PASSWORD' => 'password',
                'PREFIX'   => 'prefix',
            ];
            foreach ($map as $key => $field) {
                if (preg_match('/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*([^\r\n]*)$/mi', $content, $matches)) {
                    $defaults[$field] = trim($matches[1]);
                }
            }
        }
        return $defaults;
    }

    /**
     * 安装环境检测信息
     * @return array
     */
    protected function getEnvironmentInfo(): array
    {
        $extensionList = [
            ['name' => 'pdo', 'label' => 'PDO', 'required' => true],
            ['name' => 'pdo_mysql', 'label' => 'PDO MySQL', 'required' => true],
            ['name' => 'mbstring', 'label' => 'Mbstring', 'required' => true],
            ['name' => 'fileinfo', 'label' => 'Fileinfo', 'required' => true],
            ['name' => 'openssl', 'label' => 'OpenSSL', 'required' => true],
            ['name' => 'curl', 'label' => 'cURL', 'required' => true],
            ['name' => 'gd', 'label' => 'GD', 'required' => false],
            ['name' => 'xml', 'label' => 'XML', 'required' => false],
            ['name' => 'redis', 'label' => 'Redis', 'required' => false],
            ['name' => 'intl', 'label' => 'Intl', 'required' => false],
            ['name' => 'zip', 'label' => 'Zip', 'required' => false],
            ['name' => 'bcmath', 'label' => 'BCMath', 'required' => false],
        ];
        $extensions = [];
        $allRequiredOk = true;
        foreach ($extensionList as $item) {
            $item['loaded'] = extension_loaded($item['name']);
            if ($item['required'] && !$item['loaded']) {
                $allRequiredOk = false;
            }
            $extensions[] = $item;
        }
        return [
            'php_version' => PHP_VERSION,
            'tp_version'  => app()->version(),
            'os'          => PHP_OS_FAMILY,
            'extensions'  => $extensions,
            'required_ok' => $allRequiredOk,
        ];
    }

    /**
     * Run the installation.
     */
    protected function installation($mysqlHostname, $mysqlHostport, $mysqlDatabase, $mysqlUsername, $mysqlPassword, $mysqlPrefix, $adminUsername, $adminPassword, $adminEmail = null, $siteName = null)
    {
        $this->checkenv();

        if ($mysqlDatabase == '') {
            throw new Exception(__('Please input correct database'));
        }
        if (!preg_match("/^\w{3,12}$/", $adminUsername)) {
            throw new Exception(__('Please input correct username'));
        }
        if (!preg_match("/^[\S]{6,16}$/", $adminPassword)) {
            throw new Exception(__('Please input correct password'));
        }
        $weakPasswordArr = ['123456', '12345678', '123456789', '654321', '111111', '000000', 'password', 'qwerty', 'abc123', '1qaz2wsx'];
        if (in_array($adminPassword, $weakPasswordArr)) {
            throw new Exception(__('Password is too weak'));
        }
        if ($siteName == '' || preg_match("/fast" . "admin/i", $siteName)) {
            throw new Exception(__('Please input correct website'));
        }

        $sql = file_get_contents(INSTALL_PATH . 'fastadmin.sql');
        $sql = str_replace('`fa_', '`' . $mysqlPrefix, $sql);

        $dbConfig = $this->getDefaultDbConfig();
        $type = $dbConfig['type'] ?? 'mysql';

        try {
            $pdo = new PDO("{$type}:host={$mysqlHostname}" . ($mysqlHostport ? ";port={$mysqlHostport}" : ''), $mysqlUsername, $mysqlPassword);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->query("CREATE DATABASE IF NOT EXISTS `{$mysqlDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");

            // Connect to the database specified for install.
            $instance = Db::connect([
                'type'     => $type,
                'hostname' => $mysqlHostname,
                'hostport' => $mysqlHostport,
                'database' => $mysqlDatabase,
                'username' => $mysqlUsername,
                'password' => $mysqlPassword,
                'prefix'   => $mysqlPrefix,
            ]);

            $instance->execute('SELECT 1');
            $instance->getPdo()->exec($sql);
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }

        // Update environment (database) config.
        $envFile = root_path() . '.env';
        $envSampleFile = root_path() . '.env.sample';
        if (!file_exists($envFile)) {
            if (!is_file($envSampleFile) || !copy($envSampleFile, $envFile)) {
                throw new Exception(__('Failed to copy %s to %s', '.env.sample', '.env'));
            }
        }

        $envText = @file_get_contents($envFile);
        $envText = $this->updateEnvDatabase($envText, [
            'HOSTNAME' => $mysqlHostname,
            'HOSTPORT' => $mysqlHostport,
            'DATABASE' => $mysqlDatabase,
            'USERNAME' => $mysqlUsername,
            'PASSWORD' => $mysqlPassword,
            'PREFIX'   => $mysqlPrefix,
        ]);
        if (@file_put_contents($envFile, $envText) === false) {
            throw new Exception(__('The current permissions are insufficient to write the file %s', '.env'));
        }

        // Regenerate token key.
        $tokenConfigFile = config_path() . 'token.php';
        if (is_file($tokenConfigFile)) {
            $tokenConfig = include $tokenConfigFile;
            if (is_array($tokenConfig)) {
                $tokenConfig['key'] = Random::alnum(32);
                file_put_contents($tokenConfigFile, "<?php\n\n// 本文件由安装程序生成，请勿手动修改。\n\nreturn " . var_export_short($tokenConfig) . ";\n");
            }
        }

        $avatar = '/assets/img/avatar.png';
        $adminPassword = $adminPassword ?: Random::alnum(8);
        $adminEmail = $adminEmail ?: 'admin@admin.com';
        $newSalt = substr(md5(uniqid('', true)), 0, 6);
        $newPassword = md5(md5($adminPassword) . $newSalt);
        $data = ['username' => $adminUsername, 'email' => $adminEmail, 'avatar' => $avatar, 'password' => $newPassword, 'salt' => $newSalt];
        $instance->name('admin')->where('username', 'admin')->update($data);

        // Change the default frontend user password to a random one.
        $newSalt = substr(md5(uniqid('', true)), 0, 6);
        $newPassword = md5(md5(Random::alnum(8)) . $newSalt);
        $instance->name('user')->where('username', 'admin')->update(['avatar' => $avatar, 'password' => $newPassword, 'salt' => $newSalt]);

        // Rename admin entry for security.
        $adminName = '';
        $adminFile = root_path() . 'public' . DIRECTORY_SEPARATOR . 'admin.php';
        if (is_file($adminFile)) {
            $adminName = Random::alpha(10) . '.php';
            rename($adminFile, root_path() . 'public' . DIRECTORY_SEPARATOR . $adminName);
        }

        // Update site name.
        $siteConfigFile = config_path() . 'site.php';
        if ($siteName != config('site.name') && is_file($siteConfigFile)) {
            $instance->name('config')->where('name', 'name')->update(['value' => $siteName]);
            $siteConfig = include $siteConfigFile;
            $configList = $instance->name('config')->select();
            foreach ($configList as $value) {
                if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                    $value['value'] = is_array($value['value']) ? $value['value'] : explode(',', $value['value']);
                }
                if ($value['type'] == 'array') {
                    $value['value'] = (array)json_decode($value['value'], true);
                }
                $siteConfig[$value['name']] = $value['value'];
            }
            $siteConfig['name'] = $siteName;
            file_put_contents($siteConfigFile, "<?php\n\n// 本文件由后台“系统配置”自动生成，请勿手动修改。\n// 如需修改，请进入后台 -> 系统配置。\n\nreturn " . var_export_short($siteConfig) . ";\n");
        }

        $installLockFile = INSTALL_PATH . 'install.lock';
        if (@file_put_contents($installLockFile, 1) === false) {
            throw new Exception(__('The current permissions are insufficient to write the file %s', 'app/admin/command/Install/install.lock'));
        }

        try {
            @unlink(root_path() . 'public' . DIRECTORY_SEPARATOR . 'install.php');
        } catch (\Exception $e) {
        }

        return $adminName;
    }

    /**
     * Update database values inside the .env file.
     */
    protected function updateEnvDatabase($content, array $values)
    {
        foreach ($values as $key => $value) {
            $value = (string)$value;
            $pattern = '/^(\s*' . preg_quote($key, '/') . '\s*=\s*).*$/mi';
            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, function ($matches) use ($value) {
                    return $matches[1] . $value;
                }, $content, 1);
            } else {
                $content = rtrim($content) . "\n" . $key . ' = ' . $value . "\n";
            }
        }
        return $content;
    }

    /**
     * Check install environment.
     */
    protected function checkenv()
    {
        $checkDirs = [
            'vendor',
            'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'libs',
        ];

        $dbConfigFile = config_path() . 'database.php';

        if (version_compare(PHP_VERSION, $this->minPhpVersion, '<')) {
            throw new Exception(__('The current PHP %s is too low, please use PHP %s or higher', PHP_VERSION, $this->minPhpVersion));
        }
        if (!extension_loaded('PDO')) {
            throw new Exception(__('PDO is not currently installed and cannot be installed'));
        }
        if (!is_really_writable(root_path() . '.env')) {
            throw new Exception(__('The current permissions are insufficient to write the file .env'));
        }
        foreach ($checkDirs as $v) {
            if (!is_dir(root_path() . $v)) {
                throw new Exception(__('Please go to the official website to download the full package or resource package and try to install'));
            }
        }
        return true;
    }
}
