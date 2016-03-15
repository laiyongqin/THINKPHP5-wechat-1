<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

class View
{
    // 视图实例
    protected static $instance = null;
    // 模板引擎实例
    public $engine = null;
    // 模板主题名称
    protected $theme = '';
    // 模板变量
    protected $data = [];
    // 视图参数
    protected $config = [
        // 模板主题
        'theme_on'      => false,
        // 默认主题 开启模板主题有效
        'default_theme' => 'default',
        // 视图文件路径
        'view_path'     => '',
        // 视图文件后缀
        'view_suffix'   => '.html',
        // 视图文件分隔符
        'view_depr'     => DS,
        // 视图层目录名
        'view_layer'    => VIEW_LAYER,
        // 视图输出字符串替换
        'parse_str'     => [],
        // 视图驱动命名空间
        'namespace'     => '\\think\\view\\driver\\',
        // 模板引擎配置参数
        'template'      => [
            'type' => 'think',
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->config($config);
    }

    /**
     * 初始化视图
     * @access public
     * @param array $config  配置参数
     * @return object
     */
    public static function instance(array $config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 模板变量赋值
     * @access public
     * @param mixed $name  变量名
     * @param mixed $value 变量值
     * @return View
     */
    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->data = array_merge($this->data, $name);
            return $this;
        } else {
            $this->data[$name] = $value;
        }
        return $this;
    }

    /**
     * 设置视图参数
     * @access public
     * @param mixed $config 视图参数或者数组
     * @param string $value 值
     * @return View
     */
    public function config($config = '', $value = null)
    {
        if (is_array($config)) {
            foreach ($this->config as $key => $val) {
                if (isset($config[$key])) {
                    $this->config[$key] = $config[$key];
                }
            }
        } elseif (is_null($value)) {
            // 获取配置参数
            return $this->config[$config];
        } else {
            $this->config[$config] = $value;
        }
        return $this;
    }

    /**
     * 设置当前模板解析的引擎
     * @access public
     * @param string $engine 引擎名称
     * @param array $config 引擎参数
     * @return View
     */
    public function engine($engine, array $config = [])
    {
        if ('php' == $engine) {
            $this->engine = 'php';
        } else {
            $class = $this->config['namespace'] . ucfirst($engine);
            if (empty($this->config['view_path']) && defined('VIEW_PATH')) {
                $this->config['view_path'] = VIEW_PATH;
            }
            $config = array_merge($config, [
                'view_path'   => $this->config['view_path'],
                'view_suffix' => $this->config['view_suffix'],
                'view_depr'   => $this->config['view_depr'],
            ]);
            $this->engine = new $class($config);
        }
        return $this;
    }

    /**
     * 设置当前输出的模板主题
     * @access public
     * @param  mixed $theme 主题名称
     * @return View
     */
    public function theme($theme)
    {
        if (true === $theme) {
            // 启用主题
            $this->config['theme_on'] = true;
        } elseif (false === $theme) {
            // 关闭主题
            $this->config['theme_on'] = false;
        } else {
            // 指定主题
            $this->config['theme_on'] = true;
            $this->theme              = $theme;
        }
        return $this;
    }

    /**
     * 解析和获取模板内容 用于输出
     * @access public
     *
     * @param string $template 模板文件名或者内容
     * @param array  $vars     模板输出变量
     * @param array  $config     模板参数
     * @param bool   $renderContent 是否渲染内容
     *
     * @return string
     * @throws Exception
     */
    public function fetch($template = '', $vars = [], $config = [], $renderContent = false)
    {
        // 模板变量
        $vars = $vars ? $vars : $this->data;
        if (!$renderContent) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
            // 开启调试模式Win环境严格区分大小写
            // 模板不存在 抛出异常
            if (!is_file($template) || (APP_DEBUG && IS_WIN && realpath($template) != $template)) {
                throw new Exception('template file not exists:' . $template, 10700);
            }
            // 记录视图信息
            APP_DEBUG && Log::record('[ VIEW ] ' . $template . ' [ ' . var_export($vars, true) . ' ]', 'info');
        }
        if (is_null($this->engine)) {
            // 初始化模板引擎
            $this->engine($this->config['template']['type'], $this->config['template']);
        }
        // 页面缓存
        ob_start();
        ob_implicit_flush(0);
        if ('php' == $this->engine || empty($this->engine)) {
            // 原生PHP解析
            extract($vars, EXTR_OVERWRITE);
            is_file($template) ? include $template : eval('?>' . $template);
        } else {
            // 指定模板引擎
            $this->engine->fetch($template, $vars, $config);
        }
        // 获取并清空缓存
        $content = ob_get_clean();
        // 内容过滤标签
        APP_HOOK && Hook::listen('view_filter', $content);
        // 允许用户自定义模板的字符串替换
        if (!empty($this->config['parse_str'])) {
            $replace = $this->config['parse_str'];
            $content = str_replace(array_keys($replace), array_values($replace), $content);
        }
        if (!Config::get('response_auto_output')) {
            // 自动响应输出
            return Response::send($content, Response::type());
        }
        return $content;
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array  $vars    模板输出变量
     * @return mixed
     */
    public function show($content, $vars = [])
    {
        return $this->fetch($content, $vars, '', true);
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template)
    {
        if (is_file($template)) {
            return realpath($template);
        }
        if (empty($this->config['view_path']) && defined('VIEW_PATH')) {
            $this->config['view_path'] = VIEW_PATH;
        }
        // 获取当前主题
        $theme = $this->getTemplateTheme();
        $this->config['view_path'] .= $theme;

        $depr     = $this->config['view_depr'];
        $template = str_replace(['/', ':'], $depr, $template);
        if (strpos($template, '@')) {
            list($module, $template) = explode('@', $template);
            $path                    = APP_PATH . (APP_MULTI_MODULE ? $module . DS : '') . $this->config['view_layer'] . DS;
        } else {
            $path = $this->config['view_path'];
        }

        // 分析模板文件规则
        if (defined('CONTROLLER_NAME')) {
            if ('' == $template) {
                // 如果模板文件名为空 按照默认规则定位
                $template = str_replace('.', DS, CONTROLLER_NAME) . $depr . ACTION_NAME;
            } elseif (false === strpos($template, $depr)) {
                $template = str_replace('.', DS, CONTROLLER_NAME) . $depr . $template;
            }
        }
        return $path . $template . $this->config['view_suffix'];
    }

    /**
     * 获取当前的模板主题
     * @access private
     * @return string
     */
    private function getTemplateTheme()
    {
        if ($this->config['theme_on']) {
            if ($this->theme) {
                // 指定模板主题
                $theme = $this->theme;
            } else {
                $theme = $this->config['default_theme'];
            }
        }
        return isset($theme) ? $theme . DS : '';
    }

    /**
     * 模板变量赋值
     * @access public
     * @param string $name  变量名
     * @param mixed $value 变量值
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * 取得模板显示变量的值
     * @access protected
     * @param string $name 模板变量
     * @return mixed
     */
    public function __get($name)
    {
        return $this->data[$name];
    }

    /**
     * 检测模板变量是否设置
     * @access public
     * @param string $name 模板变量名
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
}
