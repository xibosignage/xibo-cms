<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

use Monolog\Logger;
use Slim\Environment;
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
        // Mock and Environment for use before the test is called
        Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO'      => '/',
            'SERVER_NAME'    => 'local.dev'
        ]);

        // Create a logger
        $logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
            'name' => 'PHPUNIT',
            'handlers' => array(
                new \Xibo\Helper\DatabaseLogHandler(Logger::ERROR)
            ),
            'processors' => array(
                new \Xibo\Helper\LogProcessor(),
                new \Monolog\Processor\UidProcessor(7)
            )
        ), false);

        $app = new Slim(array(
            'mode' => 'phpunit',
            'debug' => false,
            'log.writer' => $logger
        ));
        $app->setName('default');
        $app->setName('test');

        $app->add(new \Xibo\Middleware\State());
        $app->add(new \Xibo\Middleware\Storage());

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