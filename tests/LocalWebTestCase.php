<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

use Monolog\Logger;
use Slim\Slim;
use There4\Slim\Test\WebTestCase;
use Xibo\Controller\Error;
use Xibo\Helper\SlimHelper;
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
        // Clear all Slim instances to prevent mishaps
        SlimHelper::clearInstances();

        // Create a logger
        $logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
            'name' => 'PHPUNIT',
            'handlers' => array(
                new \Xibo\Helper\DatabaseLogHandler(Logger::DEBUG)
            ),
            'processors' => array(
                new \Xibo\Helper\LogProcessor(),
                new \Monolog\Processor\UidProcessor(7)
            )
        ));

        $app = new Slim(array(
            'mode' => 'test',
            'debug' => false,
            'log.writer' => $logger
        ));
        $app->setName('test');

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