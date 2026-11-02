<?php

namespace app\admin\command;

use app\admin\command\Api\library\Builder;
use think\facade\Config;
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
        $apiDir = __DIR__ . DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR;

        $force = $input->getOption('force');
        $url = $input->getOption('url');
        $cdnurl = $input->getOption('cdnurl');
        $language = $input->getOption('language');
        $template = $input->getOption('template');
        if (!preg_match("/^([a-z0-9]+)\.html\$/i", $template)) {
            throw new Exception('template file not correct');
        }
        $language = $language ?: 'zh-cn';
        $langFile = $apiDir . 'lang' . DIRECTORY_SEPARATOR . $language . '.php';
        if (!is_file($langFile)) {
            throw new Exception('language file not found');
        }
        $lang = include_once $langFile;

        // 鐩爣鐩綍
        $outputDir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR;
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
        // 妯℃澘鏂囦欢
        $templateDir = $apiDir . 'template' . DIRECTORY_SEPARATOR;
        $templateFile = $templateDir . $template;
        if (!is_file($templateFile)) {
            throw new Exception('template file not found');
        }
        // 棰濆鐨勭被
        $classes = $input->getOption('class');
        // 鏍囬
        $title = $input->getOption('title');
        // 妯″潡
        $module = $input->getOption('module');
        // 鎻掍欢
        $addon = $input->getOption('addon');

        if ($addon) {
            $addonInfo = get_addon_info($addon);
            if (!$addonInfo) {
                throw new Exception('addon not found');
            }
            $moduleDir = addons_path() . $addon . DIRECTORY_SEPARATOR;
        } else {
            $moduleDir = app_path() . $module . DIRECTORY_SEPARATOR;
        }
        if (!is_dir($moduleDir)) {
            throw new Exception('module not found');
        }
        if (in_array($module, ['admin', 'common'])) {
            throw new Exception('module not allowed');
        }

        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            throw new Exception("Requires PHP version 8.1 or newer");
        }

        //鎺у埗鍣ㄥ悕
        $controller = $input->getOption('controller') ?: [];
        if (!$controller) {
            $controllerDir = $moduleDir . Config::get('app.url_controller_layer', 'controller') . DIRECTORY_SEPARATOR;
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
                $filePath = $moduleDir . Config::get('app.url_controller_layer', 'controller') . DIRECTORY_SEPARATOR . $item . '.php';
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

        $viewConfig = (array)Config::get('view', []);
        $viewConfig['tpl_replace_string'] = array_merge((array)($viewConfig['tpl_replace_string'] ?? []), ['__CDN__' => $cdnurl]);
        Config::set($viewConfig, 'view');
        $builder = new Builder($classes);
        $content = $builder->render($templateFile, ['config' => $config, 'lang' => $lang]);

        if (!file_put_contents($outputFile, $content)) {
            throw new Exception('Cannot save the content to ' . $outputFile);
        }
        $output->info("Build Successed!");
        $output->info("Docs Location:" . $outputFile);
    }

    /**
     * 浠庢枃浠惰幏鍙栧懡鍚嶇┖闂村拰绫诲悕
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