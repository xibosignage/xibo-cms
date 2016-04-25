<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

use Monolog\Handler\PHPConsoleHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Slim\Environment;
use Slim\Helper\Set;
use Slim\Log;
use Slim\Slim;
use There4\Slim\Test\WebTestCase;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\AccessibleMonologWriter;
use Xibo\Helper\Random;
use Xibo\Helper\Translate;
use Xibo\Middleware\ApiView;
use Xibo\Middleware\State;
use Xibo\Middleware\Storage;
use Xibo\Service\ConfigService;
use Xibo\Service\SanitizeService;

/**
 * Class LocalWebTestCase
 * @package Xibo\Tests
 */
class LocalWebTestCase extends WebTestCase
{
    /**
     * @var Set
     */
    protected $container;

    /**
     * Get App
     * @return Slim
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Get non-app container
     * @return Set
     */
    protected function getContainer()
    {
        return $this->container;
    }

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
        $logger = new AccessibleMonologWriter(array(
            'name' => 'PHPUNIT',
            'handlers' => array(
                new StreamHandler('test.log')
            ),
            'processors' => array(
                new \Xibo\Helper\LogProcessor(),
                new \Monolog\Processor\UidProcessor(7)
            )
        ), false);

        $app = new \RKA\Slim(array(
            'mode' => 'phpunit',
            'debug' => false,
            'log.writer' => $logger
        ));
        $app->setName('default');
        $app->setName('test');

        // Config
        $app->configService = ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

        $app->add(new TestAuthMiddleware());
        $app->add(new \Xibo\Middleware\State());
        $app->add(new \Xibo\Middleware\Storage());
        $app->add(new \Xibo\Middleware\Xmr());

        $app->view(new ApiView());

        // Configure the Slim error handler
        $app->error(function (\Exception $e) use ($app) {
            $app->getLog()->emergency($e->getMessage());
            throw $e;
        });

        // All routes
        require PROJECT_ROOT . '/lib/routes.php';
        require PROJECT_ROOT . '/lib/routes-web.php';

        // Create a container for non-app calls to Factories
        $this->container = new Set();

        // Create a logger for this container
        $this->container->singleton('log', function ($c) use ($logger) {
            $log = new \Slim\Log($logger);
            $log->setEnabled(true);
            $log->setLevel(Log::DEBUG);
            $env = $c['environment'];
            $env['slim.log'] = $log;

            return $log;
        });

        // Provide the same config
        $this->container->configService = ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

        Storage::setStorage($this->container);

        $this->container->configService->setDependencies($this->container->store, '/');

        // Define versions, etc.
        $this->container->configService->Version();

        // Register the sanitizer
        $this->container->singleton('sanitizerService', function($container) {
            return new SanitizeService($container);
        });

        // Register the factory service
        State::registerFactoriesWithDi($this->container);

        // Register the translations
        Translate::InitLocale($this->container->configService, 'en-GB');

        // Find the PHPUnit user
        try {
            $this->container->user = $this->container->userFactory->getByName('phpunit');
        } catch (NotFoundException $e) {
            // Create the phpunit user with a random password
            /** @var User $user */
            $user = $this->container->userFactory->create();
            $user->setChildAclDependencies($this->container->userGroupFactory, $this->container->pageFactory);
            $user->userTypeId = 1;
            $user->userName = 'phpunit';
            $user->libraryQuota = 0;
            $user->homePageId = $this->container->pageFactory->getByName('statusdashboard')->pageId;
            $user->isSystemNotification = 1;
            $user->setNewPassword(Random::generateString());
            $user->save();
            $this->container->store->commitIfNecessary();

            $this->container->user = $this->container->userFactory->getByName('phpunit');
        }

        return $app;
    }
}
