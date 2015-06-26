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
use Xibo\Middleware\ApiView;

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
            'mode' => 'testing',
            'debug' => false
        ));
        $app->setName('test');

        // Set the App name
        \Xibo\Helper\ApplicationState::$appName = $app->getName();

        $app->runNo = \Xibo\Helper\Random::generateString(10);
        $app->add(new \Xibo\Middleware\Storage());
        $app->add(new \Xibo\Middleware\State());

        $app->view(new ApiView());

        // Configure the Slim error handler
        $app->error(function (\Exception $e) use ($app) {
            $controller = new Error();
            $controller->handler($e);
        });

        // Super User
        $app->user = \Xibo\Factory\UserFactory::getById(1);

        // All routes
        require PROJECT_ROOT . '/lib/routes.php';
        require PROJECT_ROOT . '/lib/routes-web.php';

        return $app;
    }
}