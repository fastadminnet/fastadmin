<?php

namespace app\admin\command;

use app\admin\command\Api\library\Builder;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;

class Api extends Command
{
    protected function configure()
    {
        $site = Config::get('site');
        $this
            ->setName('api')
            ->addOption('url', 'u', Option::VALUE_OPTIONAL, 'default api url', '')
            ->addOption('cdnurl', 'd', Option::VALUE_OPTIONAL, 'default cdn url', '')
            ->addOption('module', 'm', Option::VALUE_OPTIONAL, 'module name(index/api)', 'api')
            ->addOption('output', 'o', Option::VALUE_OPTIONAL, 'output index file name', '')
            ->addOption('template', 'e', Option::VALUE_OPTIONAL, '', 'index.html')
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, 'force override general file', false)
            ->addOption('title', 't', Option::VALUE_OPTIONAL, 'document title', $site['name'] ?? '')
            ->addOption('class', 'c', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'extend class', null)
            ->addOption('language', 'l', Option::VALUE_OPTIONAL, 'language', 'zh-cn')
            ->addOption('addon', 'a', Option::VALUE_OPTIONAL, 'addon name', null)
            ->addOption('controller', 'r', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, 'controller name', null)
            ->setDescription('Build Api document from controller');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiDir = __DIR__ . DS . 'Api' . DS;

        $force = $input->getOption('force');
        $url = $input->getOption('url');
        $cdnurl = $input->getOption('cdnurl');
        $language = $input->getOption('language');
        $template = $input->getOption('template');
        if (!preg_match("/^([a-z0-9]+)\.html\$/i", $template)) {
            throw new Exception('template file not correct');
        }
        $language = $language ?: 'zh-cn';
        $langFile = $apiDir . 'lang' . DS . $language . '.php';
        if (!is_file($langFile)) {
            throw new Exception('language file not found');
        }
        $lang = include_once $langFile;

        // 目标目录
        $outputDir = ROOT_PATH . 'runtime' . DS . 'docs' . DS;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $outputFilename = $input->getOption('output') ?: 'doc_' . date('Ymd_') . strtolower(\fast\Random::alnum(6)) . '.html';
        if ($outputFilename === 'api.html') {
            throw new Exception('api.html cannot be used as the output file name');
        }
        $outputFile = $outputDir . $outputFilename;
        if (is_file($outputFile) && !$force) {
            throw new Exception("api index file already exists!\nIf you need to rebuild again, use the parameter --force=true ");
        }
        // 模板文件
        $templateDir = $apiDir . 'template' . DS;
        $templateFile = $templateDir . $template;
        if (!is_file($templateFile)) {
            throw new Exception('template file not found');
        }
        // 额外的类
        $classes = $input->getOption('class');
        // 标题
        $title = $input->getOption('title');
        // 模块
        $module = $input->getOption('module');
        // 插件
        $addon = $input->getOption('addon');

        if ($addon) {
            $addonInfo = get_addon_info($addon);
            if (!$addonInfo) {
                throw new Exception('addon not found');
            }
            $moduleDir = ADDON_PATH . $addon . DS;
        } else {
            $moduleDir = APP_PATH . $module . DS;
        }
        if (!is_dir($moduleDir)) {
            throw new Exception('module not found');
        }
        if (in_array($module, ['admin', 'common'])) {
            throw new Exception('module not allowed');
        }

        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception("Requires PHP version 7.4 or newer");
        }

        //控制器名
        $controller = $input->getOption('controller') ?: [];
        if (!$controller) {
            $controllerDir = $moduleDir . Config::get('url_controller_layer') . DS;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($controllerDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir() && $file->getExtension() == 'php') {
                    $filePath = $file->getRealPath();
                    $className = $this->getClassFromFile($filePath);
                    if ($className) {
                        $classes[] = $className;
                    }
                }
            }
        } else {
            foreach ($controller as $index => $item) {
                $filePath = $moduleDir . Config::get('url_controller_layer') . DS . $item . '.php';
                $className = $this->getClassFromFile($filePath);
                if ($className) {
                    $classes[] = $className;
                }
            }
        }

        $classes = array_unique(array_filter($classes));

        $cdnurl = $cdnurl ?: Config::get('site.cdnurl');

        $config = [
            'sitename'    => config('site.name'),
            'title'       => $title,
            'author'      => config('site.name'),
            'description' => '',
            'apiurl'      => $url,
            'cdnurl'      => $cdnurl,
            'language'    => $language,
        ];

        Config::set('view_replace_str.__CDN__', $cdnurl);
        $builder = new Builder($classes);
        $content = $builder->render($templateFile, ['config' => $config, 'lang' => $lang]);

        if (!file_put_contents($outputFile, $content)) {
            throw new Exception('Cannot save the content to ' . $outputFile);
        }
        $output->info("Build Successed!");
        $output->info("Docs Location:" . $outputFile);
    }

    /**
     * 从文件获取命名空间和类名
     *
     * @param string $filename
     * @return string
     */
    protected function getClassFromFile($filename)
    {
        $getNext = null;
        $isNamespace = false;
        $skipNext = false;
        $namespace = '';
        $class = '';
        foreach (\PhpToken::tokenize(file_get_contents($filename)) as $token) {
            if (!$token->isIgnorable()) {
                $name = $token->getTokenName();
                switch ($name) {
                    case 'T_NAMESPACE':
                        $isNamespace = true;
                        break;
                    case 'T_EXTENDS':
                    case 'T_USE':
                    case 'T_IMPLEMENTS':
                        $skipNext = true;
                        break;
                    case 'T_CLASS':
                        if ($skipNext) {
                            $skipNext = false;
                        } else {
                            $getNext = strtolower(substr($name, 2));
                        }
                        break;
                    case 'T_NAME_QUALIFIED':
                    case 'T_NS_SEPARATOR':
                    case 'T_STRING':
                    case ';':
                        if ($isNamespace) {
                            if ($name == ';') {
                                $isNamespace = false;
                            } else {
                                $namespace .= $token->text;
                            }
                        } elseif ($skipNext) {
                            $skipNext = false;
                        } elseif ($getNext == 'class') {
                            $class = $token->text;
                            $getNext = null;
                            break 2;
                        }
                        break;
                    default:
                        $getNext = null;
                }
            }
        }
        $className = $namespace . '\\' . $class;
        return preg_match('/([a-z0-9_\\]+)([a-z0-9_]+)$/i', $className) ? $className : '';
    }
}
