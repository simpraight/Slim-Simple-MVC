<?php

namespace SlimMVC\Controller;

use \SlimMVC\Util;

class Page extends \SlimMVC\Controller
{
    protected $routes = array(
                              '(/)' => 'index',
                             );

    public function index ()
    {
        $this->notFound();
    }

}
