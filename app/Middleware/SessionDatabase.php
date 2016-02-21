<?php

namespace SlimMVC\Middleware;

class SessionDatabase extends \Slim\Middleware
{
    protected $settings;

    public function __construct()
    {
        $this->settings = array();

        ini_set('session.use_cookies', 1);
        session_name(APP_SESSION_NAME);
        session_cache_limiter(false);
        session_set_save_handler(
                                 array($this, 'open'),
                                 array($this, 'close'),
                                 array($this, 'read'),
                                 array($this, 'write'),
                                 array($this, 'destroy'),
                                 array($this, 'gc')
                                );
    }

    public function call()
    {
        if (session_id() === '')
        {
            session_start();
        }
        $this->next->call();
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        if ($data = orm('Session')->select('value')->findOne($id))
        {
            return $data->value;
        }
        return '';
    }

    public function write($id, $value)
    {
        if (!($data = orm('Session')->findOne($id)))
        {
            $data = model('Session')->set(array('id' => $id,  'expire' => date('Y-m-d H:i:s', time())));
        }
        $data->set('value', $value);
        if ($data->is_dirty('value'))
        {
            $data->save();
        }
        return true;
    }

    public function destroy($id)
    {
        if ($data = orm('Session')->findOne($id))
        {
            $data->delete();
        }
        return true;
    }

    public function gc($maxlife)
    {
        return true;
    }
}
