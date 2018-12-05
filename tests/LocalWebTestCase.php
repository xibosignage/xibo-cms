<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Environment;
use Slim\Helper\Set;
use Slim\Log;
use Slim\Slim;
use There4\Slim\Test\WebTestCase;
use Xibo\Entity\Application;
use Xibo\Entity\User;
use Xibo\Helper\AccessibleMonologWriter;
use Xibo\Helper\Translate;
use Xibo\Middleware\ApiView;
use Xibo\Middleware\State;
use Xibo\Middleware\Storage;
use Xibo\OAuth2\Client\Provider\XiboEntityProvider;
use Xibo\Service\ConfigService;
use Xibo\Service\SanitizeService;
use Xibo\Storage\PdoStorageService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Tests\Middleware\TestAuthMiddleware;
use Xibo\Tests\Xmds\XmdsWrapper;

/**
 * Class LocalWebTestCase
 * @package Xibo\Tests
 */
class LocalWebTestCase extends WebTestCase
{
    /** @var  Set */
    public static $container;

    /** @var LoggerInterface */
    public static $logger;

    /** @var  XiboEntityProvider */
    public static $entityProvider;
    
    /** @var  XmdsWrapper */
    public static $xmds;

    /**
     * Get Entity Provider
     * @return XiboEntityProvider
     */
    public function getEntityProvider()
    {
        return self::$entityProvider;
    }
    
    /**
     * Get Xmds Wrapper
     * @return XmdsWrapper
     */
    public function getXmdsWrapper()
    {
        return self::$xmds;
    }

    /**
     * Gets the Slim instance configured
     * @return Slim
     */
    public function getSlimInstance()
    {
        //$this->getLogger()->debug('Getting Slim Instance');

        // Mock and Environment for use before the test is called
        Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO'      => '/',
            'SERVER_NAME'    => 'local.dev'
        ]);

        // Create a logger
        $handlers = [];
        if (isset($_SERVER['PHPUNIT_LOG_TO_FILE']) && $_SERVER['PHPUNIT_LOG_TO_FILE']) {
            $handlers[] = new StreamHandler(PROJECT_ROOT . '/library/log.txt', Logger::DEBUG);
        } else {
            $handlers[] = new NullHandler();
        }

        $logger = new AccessibleMonologWriter(array(
            'name' => 'PHPUNIT',
            'handlers' => $handlers,
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

        //$this->getLogger()->debug('Loading Config');

        // Config
        $app->configService = ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

        //$this->getLogger()->debug('Setting Middleware');

        $app->add(new TestAuthMiddleware());
        $app->add(new \Xibo\Middleware\State());
        $app->add(new \Xibo\Middleware\Storage());
        $app->add(new \Xibo\Tests\Middleware\TestXmr());

        $app->view(new ApiView());

        // Configure the Slim error handler
        $app->error(function (\Exception $e) use ($app) {
            $app->container->get('\Xibo\Controller\Error')->handler($e);
        });

        //$this->getLogger()->debug('Including Routes');

        // All routes
        require PROJECT_ROOT . '/lib/routes.php';
        require PROJECT_ROOT . '/lib/routes-web.php';

        // Add the route for running a task manually
        $app->get('/tasks/:id', '\Xibo\Controller\Task:run');

        return $app;
    }

    /**
     * Create container
     * @return Set
     * @throws \Exception
     */
    public static function createContainer()
    {
        // Create a container for non-app calls to Factories
        $container = new Set();

        // Create a logger
        $logger = new AccessibleMonologWriter(array(
            'name' => 'PHPUNIT',
            'handlers' => array(
                new \Monolog\Handler\StreamHandler(PROJECT_ROOT . '/library/log-container.txt', Logger::DEBUG)
            ),
            'processors' => array(
                new \Monolog\Processor\UidProcessor(7)
            )
        ), false);

        // Create a logger for this container
        $container->singleton('log', function ($c) use ($logger) {
            $log = new \Slim\Log($logger);
            $log->setEnabled(true);
            $log->setLevel(Log::ERROR);
            $env = $c['environment'];
            $env['slim.log'] = $log;

            return $log;
        });

        // Provide the same config
        $container->configService = ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

        Storage::setStorage($container);

        $container->configService->setDependencies($container->store, '/');

        // Register the sanitizer
        $container->singleton('sanitizerService', function($container) {
            return new SanitizeService($container);
        });

        // Register the factory service
        State::registerFactoriesWithDi($container);

        // Register the translations
        Translate::InitLocale($container->configService, 'en-GB');

        return $container;
    }

    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        // Configure global test state
        // We want to ensure there is a
        //  - global DB object
        //  - phpunit user who executes the tests through Slim
        //  - an API application owned by xibo_admin with client_credentials grant type
        $container = \Xibo\Tests\LocalWebTestCase::createContainer();

        // Find the PHPUnit user and if we don't create it
        try {
            $container->user = $container->userFactory->getByName('phpunit');

        } catch (\Xibo\Exception\NotFoundException $e) {
            // Create the phpunit user with a random password
            /** @var \Xibo\Entity\User $user */
            $user = $container->userFactory->create();
            $user->setChildAclDependencies($container->userGroupFactory, $container->pageFactory);
            $user->userTypeId = 1;
            $user->userName = 'phpunit';
            $user->libraryQuota = 0;
            $user->homePageId = $container->pageFactory->getByName('statusdashboard')->pageId;
            $user->isSystemNotification = 1;
            $user->setNewPassword(\Xibo\Helper\Random::generateString());
            $user->save();
            $container->store->commitIfNecessary();

            $container->user = $container->userFactory->getByName('phpunit');
        }

        // Find the xibo_admin user and if we don't, complain
        try {
            /** @var User $admin */
            $admin = $container->userFactory->getByName('phpunit');

        } catch (\Xibo\Exception\NotFoundException $e) {
            die ('Cant proceed without the xibo_admin user');
        }

        // Check to see if there is an API application we can use
        /** @var Application $application */
        try {
            $application = $container->applicationFactory->getByName('phpunit');
        } catch (\Xibo\Exception\NotFoundException $e) {
            // Add it
            $application = $container->applicationFactory->create();
            $application->name = ('phpunit');
            $application->authCode = 0;
            $application->clientCredentials = 1;
            $application->userId = $admin->userId;
            $application->assignScope($container->applicationScopeFactory->getById('all'));
            $application->save();

            /** @var PdoStorageService $store */
            $store = $container->store;
            $store->commitIfNecessary();
        }

        // Register a provider and entity provider to act as our API wrapper
        $provider = new \Xibo\OAuth2\Client\Provider\Xibo([
            'clientId' => $application->key,
            'clientSecret' => $application->secret,
            'redirectUri' => '',
            'baseUrl' => 'http://localhost'
        ]);
        
        // Discover the CMS key for XMDS
        /** @var PdoStorageService $store */
        $store = $container->store;
        $key = $store->select('SELECT value FROM `setting` WHERE `setting` = \'SERVER_KEY\'', [])[0]['value'];
        $store->commitIfNecessary();
        
        // Create an XMDS wrapper for the tests to use
        $xmds = new \Xibo\Tests\Xmds\XmdsWrapper('http://localhost/xmds.php', $key);

        // Store our entityProvider
        self::$entityProvider = new \Xibo\OAuth2\Client\Provider\XiboEntityProvider($provider);

        // Store our container
        self::$container = $container;
        
        // Store our XmdsWrapper
        self::$xmds = $xmds;
    }

    /**
     * Convenience function to skip a test with a reason and close output buffers nicely.
     * @param string $reason
     */
    public function skipTest($reason)
    {
        $this->markTestSkipped($reason);
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    public function getStore()
    {
        return self::$container->store;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        // Create if necessary
        if (self::$logger === null) {
            if (isset($_SERVER['PHPUNIT_LOG_TO_CONSOLE']) && $_SERVER['PHPUNIT_LOG_TO_CONSOLE']) {
                self::$logger = new Logger('TESTS', [new \Monolog\Handler\StreamHandler(STDERR, Logger::DEBUG)]);
            } else {
                self::$logger = new NullLogger();
            }
        }

        return self::$logger;
    }

    /**
     * @return int[]
     */
    public function getPlayerActionQueue()
    {
        $service = $this->app->container->get('playerActionService');

        if ($service === null)
            $this->fail('Test hasnt used the client and therefore cannot determine XMR activity');

        return $service->processQueue();
    }

    protected static function installModuleIfNecessary($name, $class)
    {
        // Make sure the HLS widget is installed
        $res = self::$container->store->select('SELECT * FROM `module` WHERE `module` = :module', ['module' => $name]);

        if (count($res) <= 0) {
            // Install the module
            self::$container->store->insert('
              INSERT INTO `module` (`Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`,
                `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`, `viewPath`, `class`, `defaultDuration`, `installName`)
              VALUES (:module, :name, :enabled, :region_specific, :description,
                :image_uri, :schema_version, :valid_extensions, :preview_enabled, :assignable, :render_as, :settings, :viewPath, :class, :defaultDuration, :installName)
            ', [
                'module' => $name,
                'name' => $name,
                'enabled' => 1,
                'region_specific' => 1,
                'description' => $name,
                'image_uri' => null,
                'schema_version' => 1,
                'valid_extensions' => null,
                'preview_enabled' => 1,
                'assignable' => 1,
                'render_as' => 'html',
                'settings' => json_encode([]),
                'viewPath' => '../modules',
                'class' => $class,
                'defaultDuration' => 10,
                'installName' => $name
            ]);
            self::$container->store->commitIfNecessary();
        }
    }
}
