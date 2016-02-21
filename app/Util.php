<?php
namespace SlimMVC;

/**
 * Utility class
 * Static method that can be used from anywhere in general are included.
 *
 * @package SlimMVC\Util
 * @author  Shinya Matsushita <simpraight@gmail.com>
 * @license MIT License.
 */
class Util
{

    public static $locale = APP_LOCALE;

    /**
     * translate
     *
     * @param string $str
     * @param string $ns available namespace
     * @return string
     */
    public static function translate($str, $ns = null)
    {
        if (is_null($ns) || !is_string($ns))
        {
            $ns = '';
        }

        static $dict = array();
        $k = md5($ns.DS.self::$locale);
        if (!isset($dict[$k]))
        {
            $f = empty($ns)
                ? APP_LOCALE_DIR.DS.self::$locale.'.php'
                : APP_LOCALE_DIR.DS.$ns.DS.self::$locale.'.php';

            if (!is_file($f))
            {
                $dict[$k] = array();
            }
            else
            {
                $dict[$k] = include $f;
            }
        }

        return isset($dict[$k][$str]) ? $dict[$k][$str] : $str;
    }

    /**
     * toYaml
     *   convert to YAML string from Array or Object variable
     *
     * @param mixed $array_or_object Array or Object
     * @return string YAML string
     * @see \Symfony\Component\Yaml\Yaml::dump
     */
    public static function toYaml($array_or_object)
    {
        return \Symfony\Component\Yaml\Yaml::dump($array_or_object, 2, 4, false, false);
    }

    /**
     * toJson
     *   convert to JSON string from mixed variable
     *
     * @param mixed $data
     * @return string JSON string
     */
    public static function toJson($data)
    {
        return json_encode($data);
    }

    /**
     * toArray
     *   convert to Array from mixed variable
     *   support ruby like hash string
     *
     *   Examlpes:
     *      toArray('1')      // array(1)
     *      toArray(true)     // array(true)
     *      toArray('1,2,3')  // array(1,2,3)
     *      roArray(':one => 1, :two => true')  // array('one' => 1, 'two' => true)
     *
     * @param mixed $data
     * @param bool $force_array
     * @return array
     */
    public static function toArray($data, $force_array  = true)
    {
        if (is_object($data)) { return self::toArray((array)$data, false); }
        if (is_array($data)) { return array_map(function($val){ return Util::toArray($val, false); }, $data); }
        if (!is_string($data)) { return $force_array ? array($data) : $data; }
        $_data = array_map('trim', explode(',', $data));
        $data = array();
        foreach ($_data as $d)
        {
            $k = false; $v = $d;
            if (preg_match('/^:(.+?)\s*\=\>\s*(.+)$/', $d, $m)) { $k = $m[1]; $v =$m[2]; }

            if (is_numeric($v)) { $v = intval($v); }
            else if (strtolower($v) == 'true') { $v = true; }
            else if (strtolower($v) == 'false') { $v = false; }
            else if (strtolower($v) == 'null') { $v = null; }

            ($k === false) ? array_push($data, $v) : $data[$k] = $v;
        }
        if (count($data) == 1) { return $force_array ? array($data[0]) : $data[0]; }
        return $data;
    }

    /**
     * fromYaml
     *   convert to Array from YAML string or file
     *
     * @param string $yaml_string_or_file YAML string or filename
     * @return array
     */
    public static function fromYaml($yaml_string_or_file)
    {
        try
        {
            $ret = \Symfony\Component\Yaml\Yaml::parse($yaml_string_or_file, false, false);
        }
        catch (\Exception $e)
        {
            $ret = null;
        }

        return $ret;
    }

    /**
     * fromJson
     *   convert to mixed value from JSON string or file
     *
     * @param mixed $json_string_or_file JSON string or file
     * @return mixed variable of converted JSON
     */
    public static function fromJson($json_string_or_file)
    {
        if (!is_string($json_string_or_file)) { return null; }
        if (strpos($json_string_or_file, "\n") !== false)
        {
            if (is_readable($json_string_or_file)) { $json_string_or_file = file_get_contents($json_string_or_file); }
            else { return null; }
        }
        return json_decode($json_string_or_file, true);
    }

    /**
     * toUnixtime
     *   convert to unixtime from formated datetime string
     *
     * @param mixed $time_string
     * @param bool $strict  use strict format check
     * @return int Unixtime
     */
    public static function toUnixtime($time_string, $strict = false)
    {
        if (!is_string($time_string) && !is_numeric($time_string))
        {
            return null;
        }

        if (is_numeric($time_string) && !preg_match('/^[1,2]\d{3}[0,1]\d[0-3]\d$/', $time_string) && (strtotime(date('Y/m/d H:i:s', $time_string)) === intval($time_string)))
        {
            return $time_string;
        }

        if ($strict)
        {
            $date_short_regex = '(19\d{2}|20\d{2})(1[0-2]|0[1-9])(3[0,1]|[0-2]\d)';
            $date_regex = '(19\d{2}|20\d{2})[\/\-](1[0-2]|0?[1-9])[\/\-](3[0,1]|[0-2]?\d)';
            $time_regex = '(2[0-4]|[0,1]?\d)\:([0-5]?\d)(?:\:([0-5]\d))';
        }
        else
        {
            $date_short_regex = '(19\d{2}|20\d{2})(\d{2})(\d{2})';
            $date_regex = '(19\d{2}|20\d{2})[\/\-](\d{1,2})[\/\-](\d{1,2})';
            $time_regex = '(\d{1,2})\:(\d{1,2})(?:\:(\d{1,2}))';
        }

        $time = false;
        $ymdhis = false;
        if (preg_match('/^'.$date_short_regex.'$/', $time_string, $m)
            || preg_match('/^'.$date_regex.'$/', $time_string, $m))
        {
            $time = mktime(0,0,0,$m[2],$m[3],$m[1]);
            $ymdhis = sprintf("%04d%02d%02d", $m[1], $m[2], $m[3]);
            if ($strict && (date('Ymd', $time) !== $ymdhis))  { $time = false; }
        }
        elseif (preg_match('/^' . $time_regex . '$/', $time_string, $m))
        {
            $time = mktime($m[1], $m[2], isset($m[3]) ? $m[3] : 0, date('n'), date('j'), date('Y'));
            $ymdhis = sprintf("%02d%02d%02d", $m[1], $m[2], isset($m[3])?$m[3]:0);
            if ($strict && (date('His', $time) !== $ymdhis))  { $time = false; }
        }
        elseif (preg_match('/^' . $date_regex . '\s+' . $time_regex . '$/', $time_string, $m))
        {
            $time = mktime($m[4], $m[5], isset($m[6]) ? $m[6] : 0, $m[2], $m[3], $m[1]);
            $ymdhis = sprintf("%04d%02d%02d%02d%02d%02d", $m[1], $m[2], $m[3], $m[4], $m[5], isset($m[6])?$m[6]:0);
            if ($strict && (date('YmdHis', $time) !== $ymdhis))  { $time = false; }
        }
        if ($time !== false) { return $time; }

        $try_strtotime = strtotime($time_string);
        if ($try_strtotime === false) { return null; }
        if ($ymdhis === false) { return $try_strtotime; }
        if (!$strict || $ymdhis === date('YmdHis', $try_strtotime)) { return $try_strtotime; }

        return null;
    }

    /**
     * encrypt
     *    string data encryption
     *
     * @param mixed $data
     * @param string $key
     * @return string encrypted text
     */
    public static function encrypt($data, $key = null)
    {
        if ($data === null || (!is_string($data) && !is_numeric($data))) { return null; }

        $key = (is_null($key) || !is_string($key)) ? APP_CRYPT_SECRET : $key;
        $time = microtime(true);
        $iv1 = hash_hmac('sha1', 'a' . $time . 'b', $key);
        $iv2 = hash_hmac('sha1', 'z' . $time . 'y', $key);
        $iv =  pack("h*", $iv1 . $iv2);

        $encrypted = \Slim\Http\Util::encrypt($data, $key, $iv);
        if ($encrypted == $data) { return null; }
        return "" . $time . "|" . base64_encode($encrypted);
    }

    /**
     * decrypt
     *    decrypt encrypted text
     *
     * @param string $data encrypted text
     * @param string $key
     * @return mixed
     */
    public static function decrypt($data, $key = null)
    {
        if ($data === null || (!is_string($data))) { return null; }
        $data = explode('|', $data);
        if (count($data) !== 2) { return null; }

        $key = (is_null($key) || !is_string($key)) ? APP_CRYPT_SECRET : $key;
        $time = $data[0];
        $iv1 = hash_hmac('sha1', 'a' . $time . 'b', $key);
        $iv2 = hash_hmac('sha1', 'z' . $time . 'y', $key);
        $iv =  pack("h*", $iv1 . $iv2);

        $decrypted = \Slim\Http\Util::decrypt(base64_decode($data[1]), $key, $iv);
        return $decrypted;
    }

    /**
     * capitalize
     *   convert to Capitalize string
     *
     * @param string $str
     * @return string
     */
    public static function capitalize($str)
    {
        if ($str === null || !is_string($str)) { return null; }
        $str = preg_replace('/[A-Z]/', '_$0', $str);
        $str_array = preg_split('/[\s_-]+/', $str);
        return join('', array_map('ucfirst', array_map('strtolower', $str_array)));
    }

    /**
     * underscore
     *   convert to under_score style string
     *
     * @param string $str
     * @return string
     */
    public static function underscore($str)
    {
        if ($str === null || !is_string($str)) { return null; }
        $str = strtolower(preg_replace('/([A-Z])/', '_$1', $str));
        return trim(preg_replace('/[\s_-]+/', '_', $str), '_');
    }

    /**
     * collect
     *   collect values from array
     *
     * @param array $source
     * @param string $key keyname of collect
     * @return array
     */
    public static function collect($source, $key)
    {
        if (!is_array($source) || (!is_string($key) && !is_array($key))) return array();
        $ret = array();
        foreach ($source as $arr)
        {
            if (is_string($key))
            {
                if (is_object($arr) && isset($arr->{$key})) { $ret[] = $arr->{$key}; }
                if (is_array($arr) && isset($arr[$key])) { $ret[] = $arr[$key]; }
            }
            if (is_array($key))
            {
                $_ret = array();
                foreach ($key as $k)
                {
                    if (is_object($arr) && isset($arr->{$k})) { $_ret[$k] = $arr->{$k}; }
                    if (is_array($arr) && isset($arr[$k])) { $_ret[$k] = $arr[$k]; }
                }
                if (!empty($_ret)) { $ret[] = $_ret; }
            }
        }
        return $ret;
    }

    /**
     * serialized
     *   is string serialized value.
     *
     * @param string $value
     * @return bool
     */
    public static function serialized($value)
    {
        if (!is_string($value)) { return false; }
        if ($value == serialize(false)) { return true; }
        return @unserialize($value) !== false;
    }

    /**
     * mkdir
     *
     * @param string $dirname absolute directory path of creating.
     * @return bool
     */
    public static function mkdir($dirname)
    {
        if (!is_string($dirname)) { return false; }

        if (DS == '/' && strpos($dirname, DS) !== 0) { return false; }
        if (DS == '\\' && strpos($dirname, ':'.DS) !== 1) { return false; }

        if (is_dir($dirname)) { return true; }

        return @mkdir($dirname, 0755, true);
    }

    /**
     * pager
     *   Create and returns Pager object
     *
     * @param int $currentPage
     * @param int $countTotal
     * @param int $countPerPage
     * @return \SlimMVC\Util\Pager
     */
    public static function pager($currentPage, $countTotal, $countPerPage)
    {
        return new Util\Pager($currentPage, $countTotal, $countPerPage);
    }

    /**
     * zip
     *
     * @param string $filename it is only accepts absolute file path.
     * @param array $sources
     * @return bool
     */
    public static function zip($filename, $sources)
    {
        if (!is_string($filename) || !is_array($sources))
        {
            return false;
        }
        if (!self::mkdir(dirname($filename)) || !is_writable(dirname($filename)))
        {
            return false;
        }

        $zip = new \ZipArchive;
        if ($zip->open($filename, \ZipArchive::OVERWRITE) !== true)
        {
            return false;
        }

        $no_problem = true;
        $names = array();
        foreach ($sources as $src => $name)
        {
            if (is_string($name))
            {
                $i = 1;
                $_name = $name;
                while (in_array($name, $names))
                {
                    $tmp = explode('.', $_name);
                    $c = count($tmp);
                    if ($c == 1) { $tmp[0] .= sprintf('(%d)', $i); }
                    else { $tmp[$c-2] .= sprintf('(%d)', $i); }
                    $name = join('.', $tmp);
                    $i++;
                }
                $names[] = $name;
            }
            $no_problem = $no_problem && is_string($name) && is_file($src) && $zip->addFile($src, $name);
        }

        $no_problem = $no_problem && (0 < $zip->numFiles);

        $zip->close();

        if (!$no_problem)
        {
            @unlink($filename);
        }
        return $no_problem;
    }

    /**
     * unzip
     *
     * @param string $filename
     * @param string $destination it is only accepts absolute directory path.
     * @return bool
     */
    public static function unzip($filename, $destination)
    {
        if (!is_string($filename) || !is_file($filename) || !is_string($destination))
        {
            return false;
        }
        if (!self::mkdir($destination) || !is_writable($destination))
        {
            return false;
        }
        if (!class_exists('\\ZipArchive'))
        {
            return false;
        }

        $zip = new \ZipArchive;
        if ($zip->open($filename) !== true)
        {
            return false;
        }

        $ret = $zip->extractTo($destination);
        $zip->close();
        return $ret;
    }

    /**
     * copy file
     *
     * @param string $source only accepts absolute path.
     * @param string $dest only accepts absolute path.
     * @return bool
     */
    public static function copy($source, $dest)
    {
        if (!is_string($source) || !is_string($dest) || !is_file($source))
        {
            return false;
        }

        $destdir = dirname($dest);

        if (!self::mkdir($destdir) || !is_writable($destdir))
        {
            return false;
        }

        return copy($source, $dest);
    }

    /**
     * browser
     *
     * @return array {type: ie|edge|firefox|chrome|safari|unknown, version: numeric(-1=unknown)}
     */
    public static function browser()
    {
        $ret = array('type' => 'unknown', 'version' => -1);

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : false;

        if ($ua)
        {
            $ua = strtolower($ua);
            if (preg_match('/msie\s(\d+)\./', $ua, $m)) { $ret['type'] = 'ie'; $ret['version'] = intval($m[1]); }
            elseif (strpos($ua, 'trident/7.') !== false) { $ret['type'] = 'ie'; $ret['version'] = 11; }
            elseif (preg_match('/chrome\/(\d+)\./', $ua, $m)) { $ret['type'] = 'chrome'; $ret['version'] = intval($m[1]); }
            elseif (preg_match('/firefox\/(\d+)\./', $ua, $m)) { $ret['type'] = 'firefox'; $ret['version'] = intval($m[1]); }
            elseif (preg_match('/safari\/(\d+)\./', $ua, $m)) { $ret['type'] = 'safari'; $ret['version'] = intval($m[1]); }
            elseif (strpos($ua, 'edge/12.') !== false) { $ret['type'] = 'edge'; $ret['version'] = 12; }
        }

        return $ret;
    }

    /**
     * Output headers for download
     *
     * @param array $options
     * @return void
     */
    public static function downloadHeaders($options = null)
    {
        if (!is_array($options)) { $options = array(); }
        $default = array(
            'name' => 'download',
            'mimetype' => 'application/octet-stream',
            'size' => -1,
            'cache' => false,
        );

        $options = array_merge($default, $options);

        $contentType = sprintf('Content-Type: %s', $options['mimetype']);
        $disposition = sprintf('Content-Disposition: attachment;filename="%s"', $options['name']);

        $browser = Util::browser();

        switch($browser['type'])
        {
            case 'ie':
                $disposition = mb_convert_encoding($disposition, 'SJIS-win', 'UTF-8');
                break;
            case 'edge':
                $disposition = sprintf("Content-Disposition: attachment; filename*=UTF-8''%s", rawurlencode($options['name']));
                break;
            default:
                $contentType = $contentType . '; Charset=UTF-8';
                break;
        }

        header($contentType);
        header($disposition);

        if (0 < $options['size'])
        {
            header(sprintf('Content-Length: %d', $options['size']));
        }
        if ($options['cache'])
        {
            header("Cache-Control: public");
            header("Pragma: public");
        }
        else
        {
            header("Pragma: no-cache");
            header("Expires: Thu, 01 Dec 1994 16:00:00 GMT");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        }
    }
}
