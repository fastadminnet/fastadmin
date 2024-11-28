<?php

namespace app\admin\command\Api\library;

use think\Config;

/**
 * @website https://github.com/calinrada/php-apidoc
 * @author  Calin Rada <rada.calin@gmail.com>
 * @author  Karson <karson@fastadmin.net>
 */
class Builder
{

    /**
     *
     * @var \think\View
     */
    public $view = null;

    /**
     * parse classes
     * @var array
     */
    protected $classes = [];

    /**
     *
     * @param array $classes
     */
    public function __construct($classes = [])
    {
        $this->classes = array_merge($this->classes, $classes);
        $this->view = new \think\View(Config::get('template'), Config::get('view_replace_str'));
    }

    protected function extractAnnotations()
    {
        foreach ($this->classes as $class) {
            $classAnnotation = Extractor::getClassAnnotations($class);
            // 如果忽略
            if (isset($classAnnotation['ApiInternal'])) {
                continue;
            }
            Extractor::getClassMethodAnnotations($class);
            //Extractor::getClassPropertyValues($class);
        }
        $allClassAnnotation = Extractor::getAllClassAnnotations();
        $allClassMethodAnnotation = Extractor::getAllClassMethodAnnotations();
        //$allClassPropertyValue = Extractor::getAllClassPropertyValues();

//        foreach ($allClassMethodAnnotation as $className => &$methods) {
//            foreach ($methods as &$method) {
//                //权重判断
//                if ($method && !isset($method['ApiWeigh']) && isset($allClassAnnotation[$className]['ApiWeigh'])) {
//                    $method['ApiWeigh'] = $allClassAnnotation[$className]['ApiWeigh'];
//                }
//            }
//        }
//        unset($methods);
        return [$allClassAnnotation, $allClassMethodAnnotation];
    }

    protected function generateHeadersTemplate($docs)
    {
        if (!isset($docs['ApiHeaders'])) {
            return [];
        }

        $headerslist = array();
        foreach ($docs['ApiHeaders'] as $params) {
            $tr = array(
                'name'        => $params['name'] ?? '',
                'type'        => $params['type'] ?? 'string',
                'sample'      => $params['sample'] ?? '',
                'required'    => $params['required'] ?? false,
                'description' => $params['description'] ?? '',
            );
            $headerslist[] = $tr;
        }

        return $headerslist;
    }

    protected function generateParamsTemplate($docs)
    {
        if (!isset($docs['ApiParams'])) {
            return [];
        }

        $typeArr = [
            'integer' => 'number',
            'file'    => 'file',
        ];
        $paramslist = array();
        foreach ($docs['ApiParams'] as $params) {
            $type = strtolower($params['type'] ?? 'string');
            $inputtype = $typeArr[$type] ?? ($params['name'] == 'password' ? 'password' : 'text');
            $tr = array(
                'name'        => $params['name'],
                'type'        => $type,
                'inputtype'   => $inputtype,
                'sample'      => $params['sample'] ?? '',
                'required'    => $params['required'] ?? true,
                'description' => $params['description'] ?? '',
            );
            $paramslist[] = $tr;
        }

        return $paramslist;
    }

    protected function generateReturnHeadersTemplate($docs)
    {
        if (!isset($docs['ApiReturnHeaders'])) {
            return [];
        }

        $headerslist = array();
        foreach ($docs['ApiReturnHeaders'] as $params) {
            $tr = array(
                'name'        => $params['name'] ?? '',
                'type'        => 'string',
                'sample'      => $params['sample'] ?? '',
                'required'    => isset($params['required']) && $params['required'] ? 'Yes' : 'No',
                'description' => $params['description'] ?? '',
            );
            $headerslist[] = $tr;
        }

        return $headerslist;
    }

    protected function generateReturnParamsTemplate($st_params)
    {
        if (!isset($st_params['ApiReturnParams'])) {
            return [];
        }

        $paramslist = array();
        foreach ($st_params['ApiReturnParams'] as $params) {
            $tr = array(
                'name'        => $params['name'] ?? '',
                'type'        => $params['type'] ?? 'string',
                'sample'      => $params['sample'] ?? '',
                'description' => $params['description'] ?? '',
            );
            $paramslist[] = $tr;
        }

        return $paramslist;
    }

    protected function generateBadgeForMethod($data)
    {
        $method = strtoupper(is_array($data['ApiMethod'][0]) ? $data['ApiMethod'][0]['data'] : $data['ApiMethod'][0]);
        $labes = array(
            'POST'    => 'label-primary',
            'GET'     => 'label-success',
            'PUT'     => 'label-warning',
            'DELETE'  => 'label-danger',
            'PATCH'   => 'label-default',
            'OPTIONS' => 'label-info'
        );

        return $labes[$method] ?? $labes['GET'];
    }

    public function parse()
    {
        list($allClassAnnotations, $allClassMethodAnnotations) = $this->extractAnnotations();

        $sectorArr = [];
        foreach ($allClassAnnotations as $index => &$allClassAnnotation) {
            // 如果设置隐藏，则不显示在文档
            if (isset($allClassAnnotation['ApiInternal'])) {
                continue;
            }
            $sector = isset($allClassAnnotation['ApiSector']) ? $allClassAnnotation['ApiSector'][0] : $allClassAnnotation['ApiTitle'][0];
            $sectorArr[$sector] = isset($allClassAnnotation['ApiWeigh']) ? $allClassAnnotation['ApiWeigh'][0] : 0;
        }
        unset($allClassAnnotation);

        arsort($sectorArr);
        $routes = include_once CONF_PATH . 'route.php';
        $subdomain = false;
        if (config('url_domain_deploy') && isset($routes['__domain__']) && isset($routes['__domain__']['api']) && $routes['__domain__']['api']) {
            $subdomain = true;
        }
        $counter = 0;
        $section = null;
        $weigh = 0;
        $docsList = [];
        foreach ($allClassMethodAnnotations as $class => $methods) {
            foreach ($methods as $name => $docs) {
                if (isset($docs['ApiSector'][0])) {
                    $section = is_array($docs['ApiSector'][0]) ? $docs['ApiSector'][0]['data'] : $docs['ApiSector'][0];
                } else {
                    $section = $class;
                }
                if (0 === count($docs)) {
                    continue;
                }
                $route = is_array($docs['ApiRoute'][0]) ? $docs['ApiRoute'][0]['data'] : $docs['ApiRoute'][0];
                if ($subdomain) {
                    $route = substr($route, 4);
                }
                $docsList[$section][$name] = [
                    'id'                => $counter,
                    'method'            => is_array($docs['ApiMethod'][0]) ? $docs['ApiMethod'][0]['data'] : $docs['ApiMethod'][0],
                    'methodLabel'       => $this->generateBadgeForMethod($docs),
                    'section'           => $section,
                    'route'             => $route,
                    'title'             => is_array($docs['ApiTitle'][0]) ? $docs['ApiTitle'][0]['data'] : $docs['ApiTitle'][0],
                    'summary'           => is_array($docs['ApiSummary'][0]) ? $docs['ApiSummary'][0]['data'] : $docs['ApiSummary'][0],
                    'body'              => isset($docs['ApiBody'][0]) ? (is_array($docs['ApiBody'][0]) ? $docs['ApiBody'][0]['data'] : $docs['ApiBody'][0]) : '',
                    'headersList'       => $this->generateHeadersTemplate($docs),
                    'paramsList'        => $this->generateParamsTemplate($docs),
                    'returnHeadersList' => $this->generateReturnHeadersTemplate($docs),
                    'returnParamsList'  => $this->generateReturnParamsTemplate($docs),
                    'weigh'             => is_array($docs['ApiWeigh'][0]) ? $docs['ApiWeigh'][0]['data'] : $docs['ApiWeigh'][0],
                    'return'            => isset($docs['ApiReturn']) ? (is_array($docs['ApiReturn'][0]) ? $docs['ApiReturn'][0]['data'] : $docs['ApiReturn'][0]) : '',
                    'needLogin'         => $docs['ApiPermissionLogin'][0],
                    'needRight'         => $docs['ApiPermissionRight'][0],
                ];
                $counter++;
            }
        }

        //重建排序
        foreach ($docsList as $index => &$methods) {
            $methodSectorArr = [];
            foreach ($methods as $name => $method) {
                $methodSectorArr[$name] = $method['weigh'] ?? 0;
            }
            arsort($methodSectorArr);
            $methods = array_merge(array_flip(array_keys($methodSectorArr)), $methods);
        }
        $docsList = array_merge(array_flip(array_keys($sectorArr)), $docsList);
        return $docsList;
    }

    public function getView()
    {
        return $this->view;
    }

    /**
     * 渲染
     * @param string $template
     * @param array  $vars
     * @return string
     */
    public function render($template, $vars = [])
    {
        $docsList = $this->parse();
        return $this->view->display(file_get_contents($template), array_merge($vars, ['docsList' => $docsList]));
    }
}
