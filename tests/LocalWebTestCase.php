<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

use Slim\Slim;
use There4\Slim\Test\WebTestCase;
use Xibo\Controller\Error;

class LocalWebTestCase extends WebTestCase
{
    /**
     * Gets the Slim instance configured
     * @return Slim
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSlimInstance()
    {
        $app = new Slim(array(
            'mode' => 'testing'
        ));
        $app->setName('test');
        $app->runNo = \Xibo\Helper\Random::generateString(10);
        $app->add(new \Xibo\Middleware\Storage());
        $app->add(new \Xibo\Middleware\State());

        $app->add(new \JsonApiMiddleware());
        $app->view(new \JsonApiView());

        // Configure the Slim error handler
        $app->error(function (\Exception $e) use ($app) {
            $controller = new Error();
            $controller->handler($e);
        });

        // Super User
        $app->user = \Xibo\Factory\UserFactory::getById(1);

        // All routes
        require PROJECT_ROOT . '/lib/routes.php';

        return $app;
    }
}