<?php

namespace SlimMVC\Helper;

use \SlimMVC\Util;

class Application extends \SlimMVC\Helper
{
    protected $_name = 'application';
    protected $_functions = array(
                                'def'    => 'def',
                                'otk'    => 'otk',
                                'urlFor' => 'urlFor',
                                'hasError' => 'hasError',
                                'getErrorMessageOn' => 'getErrorMessageOn',
                               );
    protected $_filters = array(
                                't' => 't',
                                'size_format' => 'size_format',
                                'repeat' => 'repeat',
                                'truncate' => 'truncate',
                               );

    /**
     * Function: def
     *
     * @param string $name name of constant
     * @param mixed $default default value if constant is undefined.
     * @return mixed
     */
    public static function def($name, $default = null)
    {
        if (!is_string($name)) { return $default; }
        return !defined($name) ? $default : constant($name);
    }

    /**
     * Function: generate One-Time-Key
     *
     * @param string $key
     * @return void
     */
    public static function otk($key)
    {
        $t = time();
        return base64_encode($t.'/'.sha1($t.APP_CRYPT_SECRET.session_id().$key));
    }

    /**
     * Filter: t
     *
     *   i18n
     *
     * @param string $str
     * @param string $ns available namespace
     * @return string
     * @see \SlimMVC\Util::translate
     */
    public static function t($str, $ns = null)
    {
        return Util::translate($str, $ns);
    }

    public static function truncate($str, $count, $suffix = '...')
    {
        if (!is_string($str)) { return $str; }
        if (!is_numeric($count) || $count < 1) { return $str; }
        if (!is_string($suffix)) { $suffix = '...'; }
        if ($count < mb_strlen($str))
        {
            $str = mb_substr($str, 0, $count) . $suffix;
        }
        return $str;
    }

    /**
     * Function: urlFor
     *
     *   "$pattern" means URL routing pattern.
     *   if this parameter is null, it inherit current route pattern.
     *
     * @param string $pattern  URL routing pattern
     * @param array $params    Parameters of path and  query string.
     * @return string  URL with query string.
     * @see \SlimMVC\Controller::urlFor()
     */
    public static function urlFor($pattern, $params = array())
    {
        return \SlimMVC\Controller::urlFor($pattern, $params);
    }

    /**
     * Filter: size_format
     *
     *   {{ 1024|size_format }} = 1 KB
     *   {{ 1234567890|size_format }} = 1.14 GB
     *
     * @param int $num
     * @param string $unit GB|MB|KB|byte - default auto detect
     * @return string
     */
    public static function size_format($num, $unit = null)
    {
        if (!is_numeric($num) || $num <= 0) { return '0 byte'; }
        $num = intval($num);
        if (!is_string($unit)) { $unit = ""; }

        switch ($unit)
        {
            case 'GB':
                return sprintf('%s GB', number_format(round($num/1024/1024/1024, 2), 2));
            case 'MB':
                return sprintf('%s MB', number_format(round($num/1024/1024, 2), 2));
            case 'KB':
                return sprintf('%s KB', number_format(round($num/1024, 2), 2));
            case 'byte':
                return sprintf('%s byte', number_format($num, 2));
            default:
                if ($num >= 768*1024*1024) { return self::size_format($num, 'GB'); }
                if ($num >= 768*1024) { return self::size_format($num, 'MB'); }
                if ($num >= 768) { return self::size_format($num, 'KB'); }
                return self::size_format($num, 'byte');
        }
    }

    /**
     * Filter: repeat the string
     *
     * @param string $str
     * @param int $length
     * @return string
     */
    public static function repeat($str, $length)
    {
        if (!is_numeric($length) || $length < 1) { return ""; }
        if (!is_string($str)) { $str = ""; }
        $ret = "";
        for ($i=0;$i<$length;$i++) { $ret .= $str; }
        return $ret;
    }

    /**
     * Function: returns bool variable if has errors.
     *
     * @param \SlimMVC\Model $model Model instance
     * @param string $key target key
     * @return bool
     */
    public static function hasError($model, $key = null)
    {
        if (!($model instanceof \SlimMVC\Model)) return false;
        return $model->hasError($key);
    }

    /**
     * Function: returns error message if has errors.
     *
     * @param \SlimMVC\Model $model Model instance
     * @param string $key target key
     * @return string
     */
    public static function getErrorMessageOn($model, $key = null)
    {
        if (!($model instanceof \SlimMVC\Model)) return "";
        return $model->getErrorMessageOn($key);
    }
}
