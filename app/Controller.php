<?php

namespace SlimMVC;

/**
 * Base application controller
 *
 * @package Controller
 * @author  Shinya Matsushita <simpraight@gmail.com>
 * @license MIT License
 */
class Controller
{
    /**
     * assigned values for view-template.
     *
     * @var array
     */
    protected $assigned_data = array();
    /**
     * layout file name for view-template.
     *
     * @var string
     */
    private $_layout = "/layout/application.html";

    /**
     * current controller URL routing patterns.
     *
     * @var array
     */
    protected $routes = array();
    /**
     * autoloading compornents
     *
     * @var array
     * @see \SlimMVC\Component
     */
    protected $components = array();

    /**
     * Slim Application object
     *
     * @var \Slim\Slim
     */
    public $app = null;
    /**
     * Slim Request object
     *
     * @var \Slim\Http\Request
     */
    public $request = null;
    /**
     * current controller name
     *
     * @var string
     */
    public $controller_name = null;
    /**
     * current action name
     *
     * @var string
     */
    public $action_name = null;
    /**
     * template name
     *
     * @var string
     */
    protected $template_name = null;
    /**
     * suffix of template
     *
     * @var string
     */
    protected $template_suffix = '.html';
    /**
     * content type
     *
     * @var string
     */
    protected $content_type = 'text/html; charset=utf-8';

    /**
     * - assign member variables.
     * - mapping routes to application.
     *
     * @param string $basepath
     * @param string $name
     * @param \Slim\Slim $app
     * @return void
     */
    public function __construct($basepath, $name, &$app)
    {
        $this->controller_name = $name;
        $this->app =& $app;
        $self =& $this;

        $this->app->notFound(function() use ($self) { $self->notFound(); });
        $this->app->error(function(\Exception $e) use ($self) { $self->onError($e); });

        $helper_class = '\\SlimMVC\\Helper\\' . join('\\', array_map('ucfirst', explode('/', strtolower($name))));
        if (class_exists($helper_class))
        {
            $this->app->view()->parserExtensions[] = new $helper_class();
        }

        foreach ($this->routes as $route => $map)
        {
            if (is_string($map))
            {
                $path = rtrim($basepath, '/') . rtrim($route, '/');
                $app->map($path ? $path : '/', function() use ($self, $map) {
                    $self->action_name = $map;
                    $self->invokeAction(func_get_args());
                })->via('GET','POST');
            }
            elseif (is_array($map))
            {
                foreach ($map as $method => $action)
                {
                    if (!in_array($method, array('get','post','put','delete'))) continue;
                    if (!is_string($action)) continue;

                    $path = rtrim($basepath, '/') . rtrim($route, '/');
                    $app->{$method}($path ? $path : '/', function() use ($self, $action) {
                        $self->action_name = $action;
                        $self->invokeAction(func_get_args());
                    });
                }
            }
        }
    }

    /**
     * Return URL parsed pattern and parameters.
     *
     *   Special params are:
     *
     *     - 'inherit' (default=false)  inherit current uri parameters.
     *     - 'only_path' (default=true) return absolute url, if set false.
     *
     * @param string $pattern  URL routing pattern
     * @param array $params   Parameters of path or queries
     * @return string URL
     */
    final public static function urlFor($pattern = null, $params = array())
    {
        $app = \Slim\Slim::getInstance();
        $internal = true;
        $replace = array();
        $query = array();
        $prefix = $app->request->getRootUri();

        if ($pattern === null)
        {
            $pattern = $app->router()->getCurrentRoute()->getPattern();
            $env = $app->environment();
            mb_parse_str($env['QUERY_STRING'], $query);
        }
        else if (strpos($pattern, 'http') === 0)
        {
            $prefix = '';
        }
        else
        {
            $pattern = '/' . ltrim($pattern, '/');
        }
        if (!empty($params) && isset($params['inherit']) && $params['inherit'])
        {
            $_params = $app->router()->getCurrentRoute()->getParams();
            $params = array_merge($_params, $params);
            unset($params['inherit']);
        }

        if (!empty($params) && isset($params['only_path']) && !$params['only_path'])
        {
            $prefix = $app->request->getUrl() . $prefix;
            unset($params['only_path']);
        }

        foreach ($params as $k => $v)
        {
            $reg = '#:' . preg_quote($k, '#') . '\+?(?!\w)#';
            if (!preg_match($reg, $pattern)) $query[$k] = $v;
            else $replace[$reg] = $v;
        }

        $url = (0 < count($replace)) ? preg_replace(array_keys($replace), array_values($replace), $pattern) : $pattern;
        $q = (0 < count($query) && ($query = http_build_query($query))) ? '?'.$query : '';
        return $prefix . preg_replace('#\(?/:.+\)|\(|\)|\\\\#', '', $url) . $q;
    }

    /**
     * checkOperate
     *
     * @param string $operate
     * @return bool
     */
    protected function checkOperate($operate)
    {
        if (!is_string($operate))
        {
            return false;
        }
        if (!($op = base64_decode($this->request->params('operate', ''))))
        {
            return false;
        }
        if (!preg_match('/^(\d+)\/([0-9a-f]{40})$/i', $op, $m))
        {
            return false;
        }
        if ($m[1] + 7200 < time())
        {
            return false;
        }
        return ($m[2] === sha1($m[1].APP_CRYPT_SECRET.session_id().$operate));
    }

    /**
     * invoke action
     *
     * @param array $params
     * @return void
     */
    final public function invokeAction($params)
    {
        if (!method_exists($this, $this->action_name))
        {
            throw new \Exception(sprintf('Action method "%s" not exists', $this->action_name));
        }

        $this->request = $this->app->request();
        $this->loadComponents();

        if (!$this->beforeAction())
        {
            throw new \Exception('Callback error on beforeAction');
        }

        if (!($template = call_user_func_array(array($this, $this->action_name), $params)))
        {
            $template = $this->action_name;
        }
        if ($this->template_name == null)
        {
            $template = $this->controller_name . DS . $template;
        }
        else
        {
            $template = $this->template_name;
        }

        $this->template_name = $template;

        $this->autoAssign();
        $this->app->contentType($this->content_type);
        $this->app->render($template . $this->template_suffix, $this->assigned_data);
    }

    /**
     * assign as variable of view-template.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    final public function assign($key, $value)
    {
        $this->assigned_data[$key] = $value;
    }

    /**
     * assign the following variables.
     *   - layout:  Layout file name.
     *   - request: Request object (\Slim\Http\Request)
     *   - [components]: loaded components.
     *
     * @return void
     */
    final private function autoAssign()
    {
        $this->assigned_data = array_merge($this->assigned_data, array(
                                                                       'controller_name' => $this->controller_name,
                                                                       'action_name' => $this->action_name,
                                                                       'template_name' => $this->template_name,
                                                                       'layout' => $this->_layout,
                                                                       'request' => $this->request,
                                                                      ));
        foreach ($this->components as $component)
        {
            $this->assigned_data[$component] =& $this->app->{$component};
        }
    }

    /**
     * Render NotFound (404) page
     *
     * @return void
     */
    public function notFound()
    {
        if (is_null($this->request)) { $this->request = $this->app->request(); }
        $this->loadComponents();
        $this->autoAssign();
        $this->app->render('error/404.html', $this->assigned_data, 404);
        $this->app->stop();
    }

    /**
     * Render Forbidden (403) page
     *
     * @return void
     */
    public function forbidden()
    {
        if (is_null($this->request)) { $this->request = $this->app->request(); }
        $this->loadComponents();
        $this->autoAssign();
        $this->app->render('error/403.html', $this->assigned_data, 403);
        $this->app->stop();
    }

    /**
     * Render Nothing
     *
     * @param $status
     * @return void
     */
    public function nothing($status = 200)
    {
        if (!is_numeric($status)) { $status = 200; }
        $this->app->response->setStatus($status);
        $this->app->response->setBody('');
        $this->app->stop();
    }

    /**
     * Render Error page
     *
     * @return void
     */
    final public function onError(\Exception $e = null)
    {
        if (is_null($this->request)) { $this->request = $this->app->request(); }
        if (!is_null($e))
        {
            $error_info = array(
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            );
            $this->assign('error_info', $error_info);
        }
        $this->loadComponents();
        $this->autoAssign();
        $this->app->render('error/500.html', $this->assigned_data, 500);
        $this->app->stop();
    }

    /**
     * Load Components
     *
     * @return void
     * @see \SlimMVC\Component
     * @see self::$components
     */
    final private function loadComponents()
    {
        static $loaded = false;
        if ($loaded) return;
        foreach ($this->components as $component)
        {
            $className = '\\SlimMVC\\Component\\' . $component;
            $this->app->container->singleton($component, function() use ($className) { return new $className(); });
        }
        $loaded = true;
    }

    /**
     *  will called before invoking action method.
     *
     *  @return bool  if it return false, invoking action will cancel.
     */
    protected function beforeAction() { return true; }
}
