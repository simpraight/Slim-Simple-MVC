<?php

namespace SlimMVC\Controller;

/**
 * Base application WEBAPI controller
 *
 * @package Controller
 * @author  Shinya Matsushita <simpraight@gmail.com>
 * @license MIT License
 */
class Api extends \SlimMVC\Controller
{
    protected $template_name = 'api/result';
    protected $template_suffix = '.json';
    protected $content_type = 'application/json';

    public function notFound()
    {
        if (is_null($this->request)) { $this->request = $this->app->request(); }
        $this->assign('result', array('sucess' => false, 'error' => '404'));
        $this->app->render('api/result.json', $this->assigned_data, 404);
        $this->app->stop();
    }

    public function forbidden()
    {
        if (is_null($this->request)) { $this->request = $this->app->request(); }
        $this->assign('result', array('sucess' => false, 'error' => '403'));
        $this->app->render('api/result.json', $this->assigned_data, 403);
        $this->app->stop();
    }
}
