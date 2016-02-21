<?php
mb_internal_encoding('UTF-8');
/**
 * Application Core Constants
 *    Usually, you should not change these settings.
 */
define('DS', DIRECTORY_SEPARATOR);
define('APP_CONFIG_DIR', dirname(__FILE__));
define('APP_BASE_DIR', realpath(APP_CONFIG_DIR . DS . '..' . DS));
define('APP_DIR', APP_BASE_DIR . DS . 'app');
define('APP_VENDOR_DIR', APP_BASE_DIR . DS . 'vendor');
define('APP_PUBLIC_DIR', APP_BASE_DIR . DS . 'public');
define('APP_VIEW_DIR', APP_DIR . DS . 'View');
define('APP_CONTROLLER_DIR', APP_DIR . DS . 'Controller');
define('APP_MODEL_DIR', APP_DIR . DS . 'Model');
define('APP_PLUGIN_DIR', APP_DIR . DS . 'Plugin');
define('APP_LOCALE_DIR', APP_CONFIG_DIR.DS.'locales');
define('APP_LOGLEVEL_DEBUG', 31);
define('APP_LOGLEVEL_INFO', 15);
define('APP_LOGLEVEL_WARN', 7);
define('APP_LOGLEVEL_ERROR', 3);
define('APP_LOGLEVEL_FATAL', 1);

/**
 * Autoloading
 *   The file genarated automatically by "composer.phar"
 */
require_once APP_VENDOR_DIR . DS . 'autoload.php';

/**
 * Load user configuration file
 */
require_once APP_CONFIG_DIR . DS . 'config.php';

/**
 * More Constants
 *    The following constants are used as default values,
 *      if there is no description in the "config.php".
 */
!defined('APP_ENVIRONMENT') ? define('APP_ENVIRONMENT', 'development') : null;
!defined('APP_DEGUG') ? define('APP_DEBUG', APP_ENVIRONMENT!=='production') : null;
!defined('APP_LOCALE') ? define('APP_LOCALE', 'en_US') : null;
!defined('APP_TIMEZONE') ? define('APP_TIMEZONE', 'Asia/Tokyo') : null;
!defined('APP_LOG_LEVEL') ? define('APP_LOG_LEVEL', APP_ENVIRONMENT!=='production' ? \Slim\Log::DEBUG : \Slim\Log::WARN) : null;
!defined('APP_LOG_DIR') ? define('APP_LOG_DIR', APP_BASE_DIR . DS . 'logs') : null;
!defined('APP_TMP_DIR') ? define('APP_TMP_DIR', APP_BASE_DIR . DS . 'tmp') : null;
!defined('APP_CACHE_DIR') ? define('APP_CACHE_DIR', APP_TMP_DIR . DS . 'cache') : null;
!defined('APP_UPLOAD_DIR') ? define('APP_UPLOAD_DIR', APP_BASE_DIR . DS .'uploads') : null;
!defined('APP_USERDATA_DIR') ? define('APP_USERDATA_DIR', APP_BASE_DIR . DS . 'userdata') : null;
!defined('APP_BACKUP_DIR') ? define('APP_BACKUP_DIR', APP_BASE_DIR . DS . 'backup') : null;
!defined('APP_COOKIE_CRYPT') ? define('APP_COOKIE_CRYPT', true) : null;
!defined('APP_CRYPT_SECRET') ? define('APP_CRYPT_SECRET', 'YOU_SHOULD_CHANGE_THIS_DEFAULT_SECRET_KEY') : null;
!defined('APP_SMTP') ? define('APP_SMTP', true) : null;
!defined('APP_SMTP_SERVER') ? define('APP_SMTP_SERVER', 'localhost') : null;
!defined('APP_SMTP_USER') ? define('AK_SMTP_USER', '') : null;
!defined('APP_SMTP_PASSWORD') ? define('AK_SMTP_PASSWORD', '') : null;


/**
 * Regex patterns
 */
define('APP_REGEXP_EMAIL', '/^(?:[\w\!#\$%\&\'\*\+\/\=\?\^_\{\}\\\|~\.-]+?)@(?:[\w][\w-]*\.)+[\w][\w-]*$/');

/**
 * Session Settings
 *
 *   if APP_SESSION_TYPE is "file":
 *      "APP_SESSION_STORAGE" means "Session saving directory name".
 *      ex.)  APP_TMP_DIR
 *
 *  if APP_SESSION_TYPE is "mecached":
 *      "APP_SESSION_STORAGE" means "Memcached server addresses".
 *      ex.)  "127.0.0.11211,192.168.1.100:11212"
 *
 *  if APP_SESSION_TYPE is "database":
 *      "APP_SESSION_STORAGE" means "Database table name".
 *      ex.)  "sessions"
 */
!defined('APP_SESSION_NAME') ? define('APP_SESSION_NAME', 'APP_SESSID') : null;
!defined('APP_SESSION_TYPE') ? define('APP_SESSION_TYPE', 'database') : null;
!defined('APP_SESSION_STORAGE') ? define('APP_SESSION_STORAGE', '') : null;
!defined('APP_SESSION_EXPIRE') ? define('APP_SESSION_EXPIRE', 0) : null;

/**
 * Default Database Settings
 *
 *   It is ignored if setting is described in the "database.json" file.
 */
!defined('APP_DATABASE_TYPE') ? define('APP_DATABASE_TYPE', 'mysql') : null;
!defined('APP_DATABASE_NAME') ? define('APP_DATABASE_NAME', 'dbname') : null;
!defined('APP_DATABASE_HOST') ? define('APP_DATABASE_HOST', 'localhost') : null;
!defined('APP_DATABASE_PORT') ? define('APP_DATABASE_PORT', '3306') : null;
!defined('APP_DATABASE_USER') ? define('APP_DATABASE_USER', 'root') : null;
!defined('APP_DATABASE_PASS') ? define('APP_DATABASE_PASS', '') : null;

/**
 * Set models class name prefix.
 * @see \Model
 */
\Model::$auto_prefix_models = '\\SlimMVC\\Model\\';

// **********************************************************************************
//   Global Core Functions
// **********************************************************************************

/**
 * bootstrap
 *   Initialize and Configure application.
 *
 * @param \Slim\Slim $app
 * @access public
 * @return void
 */
function bootstrap(&$app)
{
    //
    // App general settings
    //
    $app->config('debug', APP_DEBUG);
    $app->config('mode', APP_ENVIRONMENT);
    $app->config('templates.path', APP_VIEW_DIR);
    $app->config('view', new Slim\Views\Twig());
    $app->config('cookies.encrypt', true);
    $app->config('cookies.secret_key', APP_CRYPT_SECRET);
    //$app->config('cookies.cipher', MCRYPT_RIJNDAEL_256);
    //$app->config('cookies.cipher_mode', MCRYPT_MODE_CBC);

    //
    // Add session middleware
    //
    switch (APP_SESSION_TYPE)
    {
        case 'cookie':
            $app->add(new Slim\Middleware\SessionCookie(array(
                'expires' => APP_SESSION_EXPIRE . ' minutes',
                'domain' => null,
                'name' => APP_SESSION_NAME,
            )));
            break;
        case 'database':
            $app->add(new SlimMVC\Middleware\SessionDatabase());
            break;
        case 'memcached':
            $app->add(new SlimMVC\Middleware\SessionMemcached());
            break;
    }

    //
    // View settings
    //
    $app->view()->parserOptions = array(
        'debug' => APP_DEBUG,
        'cache' => APP_CACHE_DIR . DS . 'views',
    );
    $app->view()->parserExtensions = array(
        new \Slim\Views\TwigExtension(),
        new \SlimMVC\Helper\Application(),
    );

    //
    // Route settings
    //
    $routes = json_decode(file_get_contents(APP_CONFIG_DIR . DS . 'routes.json'), true);
    if ($routes !== null)
    {
        $app->hook('slim.before.router', function() use ($app, $routes) {
            $path = $app->request()->getPathInfo();
            foreach ($routes as $pathGroup => $controller)
            {
                if (strpos($path, $pathGroup) === 0) { controller($pathGroup, $controller); break; }
            }
        });
    }
}

/**
 * database
 *   Configure and Connect database.
 *
 * @param string $name namespace of connection. default value is \ORM::DEFAULT_CONNECTION (="default")
 * @access public
 * @return void
 * @see database.json
 */
function database($connection_name = \ORM::DEFAULT_CONNECTION)
{
  static $db_conf = null;
  static $configured = array();

  if (is_null($db_conf))
  {
    if (!($conf = @file_get_contents(APP_CONFIG_DIR . DS . 'database.json')))
    {
      throw new \Exception('Could not read database config file');
    }
    $db_conf = json_decode($conf);
  }
  if (!isset($configured[$connection_name]))
  {
    if (!isset($db_conf->{APP_ENVIRONMENT}, $db_conf->{APP_ENVIRONMENT}->{$connection_name}))
    {
      $db_conf->{APP_ENVIRONMENT}->{$connection_name} = array(
        'type' => APP_DATABASE_TYPE, 'host' => APP_DATABASE_HOST, 'port' => APP_DATABASE_PORT,
        'name' => APP_DATABASE_NAME, 'user' => APP_DATABASE_USER, 'pass' => APP_DATABASE_PASS,
        'logging' => (APP_ENVIRONMENT != 'production')
      );
    }
    $db = $db_conf->{APP_ENVIRONMENT}->{$connection_name};
    if ($db->type === 'mysql')
    {
      $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s', $db->host, $db->port, $db->name);
      ORM::configure(array(
        'connection_string' => $dsn,
        'username' => $db->user,
        'password' => $db->pass,
        'driver_options' => array(
          PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        ),
        'logging' => $db->logging,
        'caching' => true,
        'caching_auto_clear' => true,
      ), null, $connection_name);
    }
    else if ($db->type === 'sqlite')
    {
      ORM::configure(sprintf('sqlite:%s', $db->name), null, $connection_name);
    }
    $configured[$connection_name] = true;
  }
}

/**
 * controller
 *   Load application controller.
 *
 * @param string $path
 * @param string $name
 * @param string $action
 * @access public
 * @return void
 */
function controller($path, $name, $action = null)
{
    $class_array = explode('/', strtolower($name));
    $class_name = 'SlimMVC\\Controller\\' . join('\\', array_map('ucfirst', $class_array));
    if (!class_exists($class_name))
    {
        throw new Exception(sprintf('Controller class "%s" not exists', $class_name));
    }
    new $class_name($path, $name, \Slim\Slim::getInstance());
}

/**
 * model
 *   return new Model instance.
 *
 *   Example:
 *
 *       model('User');
 *       model('User')->set('account', 'fuga')->save();
 *
 * @param string $name  model class name.
 * @param string $connectionName  name of connection. default value is null.
 * @access public
 * @return Object (instance of \SlimMVC\Model)
 */
function model($name, $connection_name = null)
{
    return orm($name, $connection_name)->create();
}

/**
 * orm
 *   return new ORMWapper instance.
 *
 *   Example:
 *
 *       orm('User')->find_many();
 *       orm('User')->where('account', 'hoge')->findOne()->set('account', 'fuga')->save();
 *
 * @param string $name  model class name.
 * @param string $connectionName  name of connection. default value is null.
 * @access public
 * @return ORMWapper
 */
function orm($name, $connection_name = null)
{
    $modelClass = \SlimMVC\Util::capitalize($name);
    return \SlimMVC\Model::factory($modelClass, $connection_name);
}
