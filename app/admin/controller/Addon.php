<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use fastaddons\addons\AddonException;
use fastaddons\addons\Service;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use Exception;

/**
 * Addon manager
 *
 * @icon   fa fa-cube
 */
class Addon extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['get_table_list'];

    public function _initialize(): void
    {
        parent::_initialize();
        if (!$this->auth->isSuperAdmin() && in_array($this->request->action(), ['install', 'uninstall', 'local', 'upgrade', 'authorization', 'testdata'])) {
            $this->error(__('Access is allowed only to the super management group'));
        }
    }

    /**
     * Addon list
     */
    public function index()
    {
        $addons = get_addon_list();
        foreach ($addons as $k => &$v) {
            $config = get_addon_config($v['name']);
            $v['config'] = $config ? 1 : 0;
            $v['url'] = str_replace($this->request->server('SCRIPT_NAME'), '', $v['url']);
        }
        $this->assignconfig(['addons' => $addons, 'api_url' => config('fastadmin.api_url'), 'faversion' => config('fastadmin.version'), 'domain' => request()->host(true)]);
        return $this->view->fetch();
    }

    /**
     * Addon config
     */
    public function config($name = null)
    {
        $name = $name ? $name : $this->request->get("name");
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(__('Addon name incorrect'));
        }
        $info = get_addon_info($name);
        $config = get_addon_fullconfig($name);
        if (!$info) {
            $this->error(__('Addon not exists'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a", [], 'trim');
            if ($params) {
                foreach ($config as $k => &$v) {
                    if (isset($params[$v['name']])) {
                        if ($v['type'] == 'array') {
                            $params[$v['name']] = is_array($params[$v['name']]) ? $params[$v['name']] : (array)json_decode($params[$v['name']], true);
                            $value = $params[$v['name']];
                        } else {
                            $value = is_array($params[$v['name']]) ? implode(',', $params[$v['name']]) : $params[$v['name']];
                        }
                        $v['value'] = $value;
                    }
                }
                try {
                    $addon = get_addon_instance($name);
                    // Plugin custom config handler
                    if (method_exists($addon, 'config')) {
                        $addon->config($name, $config);
                    } else {
                        // Update config file
                        set_addon_fullconfig($name, $config);
                        Service::refresh();
                    }
                } catch (Exception $e) {
                    $this->error(__($e->getMessage()));
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $tips = [];
        $groupList = [];
        $ungroupList = [];
        foreach ($config as $index => &$item) {
            // Grouped field
            if (isset($item['group']) && $item['group']) {
                if (!in_array($item['group'], $groupList)) {
                    $groupList["custom" . (count($groupList) + 1)] = $item['group'];
                }
            } elseif ($item['name'] != '__tips__') {
                $ungroupList[] = $item['name'];
            }
            if ($item['name'] == '__tips__') {
                $tips = $item;
                unset($config[$index]);
            }
        }
        if ($ungroupList) {
            $groupList['other'] = 'Other';
        }
        $this->view->assign("groupList", $groupList);
        $this->view->assign("addon", ['info' => $info, 'config' => $config, 'tips' => $tips]);
        $configFile = addons_path() . $name . DIRECTORY_SEPARATOR . 'config.html';
        $viewFile = is_file($configFile) ? $configFile : '';
        return $this->view->fetch($viewFile);
    }

    /**
     * Install addon
     */
    public function install()
    {
        $name = $this->request->post("name");
        $force = (int)$this->request->post("force");
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(__('Addon name incorrect'));
        }

        $info = [];
        try {
            $uid = $this->request->post("uid");
            $token = $this->request->post("token");
            $version = $this->request->post("version");
            $faversion = $this->request->post("faversion");
            $extend = [
                'uid'       => $uid,
                'token'     => $token,
                'version'   => $version,
                'faversion' => $faversion
            ];
            $info = Service::install($name, $force, $extend);
        } catch (AddonException $e) {
            $this->result($e->getData(), $e->getCode(), __($e->getMessage()));
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        $this->success(__('Install successful'), '', ['addon' => $info]);
    }

    /**
     * Uninstall addon
     */
    public function uninstall()
    {
        $name = $this->request->post("name");
        $force = (int)$this->request->post("force");
        $droptables = (int)$this->request->post("droptables");
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(__('Addon name incorrect'));
        }
        // Only super admin in debug mode can drop tables
        $tables = [];
        if ($droptables && Config::get("app.app_debug") && $this->auth->isSuperAdmin()) {
            $tables = get_addon_tables($name);
        }
        try {
            Service::uninstall($name, $force);
            if ($tables) {
                $prefix = Config::get('database.prefix');
                // Drop related tables
                foreach ($tables as $index => $table) {
                    // Skip tables not belonging to this addon
                    if (!preg_match("/^{$prefix}{$name}/", $table)) {
                        continue;
                    }
                    Db::execute("DROP TABLE IF EXISTS `{$table}`");
                }
            }
        } catch (AddonException $e) {
            $this->result($e->getData(), $e->getCode(), __($e->getMessage()));
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        $this->success(__('Uninstall successful'));
    }

    /**
     * Enable / disable addon
     */
    public function state()
    {
        $name = $this->request->post("name");
        $action = $this->request->post("action");
        $force = (int)$this->request->post("force");
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(__('Addon name incorrect'));
        }
        try {
            $action = $action == 'enable' ? $action : 'disable';
            Service::$action($name, $force);
            Cache::delete('__menu__');
        } catch (AddonException $e) {
            $this->result($e->getData(), $e->getCode(), __($e->getMessage()));
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        $this->success(__('Operate successful'));
    }

    /**
     * Local upload install
     */
    public function local()
    {
        Config::set(['default_return_type' => 'json'], 'app');

        $info = [];
        $file = $this->request->file('file');
        try {
            $uid = $this->request->post("uid");
            $token = $this->request->post("token");
            $faversion = $this->request->post("faversion");
            $force = $this->request->post("force");
            if (!$uid || !$token) {
                throw new Exception(__('Please login and try to install'));
            }
            $extend = [
                'uid'       => $uid,
                'token'     => $token,
                'faversion' => $faversion
            ];
            $info = Service::local($file, $extend, $force);
        } catch (AddonException $e) {
            $this->result($e->getData(), $e->getCode(), __($e->getMessage()));
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        $this->success(__('Offline installed tips'), '', ['addon' => $info]);
    }

    /**
     * Upgrade addon
     */
    public function upgrade()
    {
        $name = $this->request->post("name");
        $addonTmpDir = runtime_path() . 'addons' . DIRECTORY_SEPARATOR;
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(__('Addon name incorrect'));
        }
        if (!is_dir($addonTmpDir)) {
            @mkdir($addonTmpDir, 0755, true);
        }

        $info = [];
        try {
            $info = get_addon_info($name);
            $uid = $this->request->post("uid");
            $token = $this->request->post("token");
            $version = $this->request->post("version");
            $faversion = $this->request->post("faversion");
            $extend = [
                'uid'        => $uid,
                'token'      => $token,
                'version'    => $version,
                'oldversion' => $info['version'] ?? '',
                'faversion'  => $faversion
            ];
            $info = Service::upgrade($name, $extend);
            Cache::delete('__menu__');
        } catch (AddonException $e) {
            $this->result($e->getData(), $e->getCode(), __($e->getMessage()));
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        $this->success(__('Operate successful'), '', ['addon' => $info]);
    }

    /**
     * Import testdata
     */
    public function testdata()
    {
        $name = $this->request->post("name");
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(__('Addon name incorrect'));
        }

        try {
            Service::importsql($name, 'testdata.sql');
        } catch (AddonException $e) {
            $this->result($e->getData(), $e->getCode(), __($e->getMessage()));
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        $this->success(__('Import successful'), '');
    }

    /**
     * Installed addons
     */
    public function downloaded()
    {
        $offset = (int)$this->request->get("offset");
        $limit = (int)$this->request->get("limit");
        $filter = $this->request->get("filter", '');
        $search = $this->request->get("search", '', 'strip_tags,htmlspecialchars');
        $onlineaddons = $this->getAddonList();
        $filter = (array)json_decode($filter, true);
        $addons = get_addon_list();
        $list = [];
        foreach ($addons as $k => $v) {
            if ($search && stripos($v['name'], $search) === false && stripos($v['title'], $search) === false && stripos($v['intro'], $search) === false) {
                continue;
            }

            if (isset($onlineaddons[$v['name']])) {
                $v = array_merge($v, $onlineaddons[$v['name']]);
                $v['price'] = '-';
            } else {
                $v['category_id'] = 0;
                $v['flag'] = '';
                $v['banner'] = '';
                $v['image'] = '';
                $v['demourl'] = '';
                $v['price'] = __('None');
                $v['screenshots'] = [];
                $v['releaselist'] = [];
                $v['url'] = addon_url($v['name']);
                $v['url'] = str_replace($this->request->server('SCRIPT_NAME'), '', $v['url']);
            }
            $v['createtime'] = filemtime(addons_path() . $v['name']);
            if ($filter && isset($filter['category_id']) && is_numeric($filter['category_id']) && $filter['category_id'] != $v['category_id']) {
                continue;
            }
            $list[] = $v;
        }
        $total = count($list);
        if ($limit) {
            $list = array_slice($list, $offset, $limit);
        }
        $result = array("total" => $total, "rows" => $list);

        return json($result);
    }

    /**
     * Check purchase
     */
    public function isbuy()
    {
        $name = $this->request->post("name");
        $uid = $this->request->post("uid");
        $token = $this->request->post("token");
        $version = $this->request->post("version");
        $faversion = $this->request->post("faversion");
        $extend = [
            'uid'       => $uid,
            'token'     => $token,
            'version'   => $version,
            'faversion' => $faversion
        ];
        try {
            $result = Service::isBuy($name, $extend);
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        return json($result);
    }

    /**
     * Refresh authorization
     */
    public function authorization()
    {
        $params = [
            'uid'       => $this->request->post('uid'),
            'token'     => $this->request->post('token'),
            'faversion' => $this->request->post('faversion'),
        ];
        try {
            Service::authorization($params);
        } catch (Exception $e) {
            $this->error(__($e->getMessage()));
        }
        $this->success(__('Operate successful'));
    }

    /**
     * Get addon tables
     */
    public function get_table_list()
    {
        $name = $this->request->post("name");
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(__('Addon name incorrect'));
        }
        $tables = get_addon_tables($name);
        $prefix = Config::get('database.prefix');
        foreach ($tables as $index => $table) {
            // Skip tables not belonging to this addon
            if (!preg_match("/^{$prefix}{$name}/", $table)) {
                unset($tables[$index]);
            }
        }
        $tables = array_values($tables);
        $this->success('', null, ['tables' => $tables]);
    }

    protected function getAddonList()
    {
        $onlineaddons = Cache::get("onlineaddons");
        if (!is_array($onlineaddons) && config('fastadmin.api_url')) {
            $onlineaddons = [];
            $params = [
                'uid'       => $this->request->post('uid'),
                'token'     => $this->request->post('token'),
                'version'   => config('fastadmin.version'),
                'faversion' => config('fastadmin.version'),
            ];
            $json = [];
            try {
                $json = Service::addons($params);
            } catch (\Throwable $e) {

            }
            $rows = $json['rows'] ?? [];
            foreach ($rows as $index => $row) {
                if (!isset($row['name'])) {
                    continue;
                }
                $onlineaddons[$row['name']] = $row;
            }
            Cache::set("onlineaddons", $onlineaddons, 600);
        }
        return $onlineaddons;
    }
}