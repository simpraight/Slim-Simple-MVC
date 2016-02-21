<?php
require '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$app = new \Slim\Slim();
bootstrap($app);

$app->run();
