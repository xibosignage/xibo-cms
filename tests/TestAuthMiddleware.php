<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TestAuthMiddleware.php)
 */


namespace Xibo\tests;


use Slim\Middleware;

class TestAuthMiddleware extends Middleware
{
    public function call()
    {
        $app = $this->app;

        $this->app->hook('slim.before.dispatch', function() use ($app) {
            // Super User
            $app->user = (new \Xibo\Factory\UserFactory($app))->getById(1);
        });

        $this->next->call();
    }
}