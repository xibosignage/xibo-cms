<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TestAuthMiddleware.php)
 */


namespace Xibo\tests;


use Slim\Middleware;

/**
 * Class TestAuthMiddleware
 * @package Xibo\tests
 */
class TestAuthMiddleware extends Middleware
{
    public function call()
    {
        $app = $this->app;

        $this->app->hook('slim.before.dispatch', function() use ($app) {
            // Super User
            $app->user = $app->userFactory->getByName('phpunit');
        });

        $this->next->call();
    }
}