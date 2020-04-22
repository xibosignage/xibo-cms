<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Tests;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Http\Factory\DecoratedResponseFactory;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\TwigMiddleware;
use Throwable;
use Xibo\Controller\Task;
use Xibo\Entity\Application;
use Xibo\Entity\User;
use Xibo\Factory\ContainerFactory;
use Xibo\Factory\TaskFactory;
use Xibo\Helper\Translate;
use Xibo\Middleware\State;
use Xibo\Middleware\Storage;
use Xibo\OAuth2\Client\Provider\XiboEntityProvider;
use Xibo\Service\DisplayNotifyService;
use Xibo\Storage\PdoStorageService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Tests\Helper\MockPlayerActionService;
use Xibo\Tests\Middleware\TestAuthMiddleware;
use Xibo\Tests\Xmds\XmdsWrapper;
use Xibo\XTR\ImageProcessingTask;
use Xibo\XTR\ReportScheduleTask;
use Xibo\XTR\TaskInterface;

/**
 * Class LocalWebTestCase
 * @package Xibo\Tests
 */
class LocalWebTestCase extends PHPUnit_TestCase
{
    /** @var  ContainerInterface */
    public static $container;

    /** @var LoggerInterface */
    public static $logger;

    /** @var TaskInterface */
    public static $taskService;

    /** @var  XiboEntityProvider */
    public static $entityProvider;
    
    /** @var  XmdsWrapper */
    public static $xmds;

    /** @var App */
    protected $app;

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
     * @return App
     * @throws \Exception
     */
    public function getSlimInstance()
    {
        //$this->getLogger()->debug('Getting Slim Instance');

        // this function sets $_SERVER parameters for this test environment.
        $this->setEnvironment();

        // Create the container for dependency injection.
        try {
            $container = ContainerFactory::create();
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        // Create a Slim application
        $app = \DI\Bridge\Slim\Bridge::create($container);
        $twigMiddleware = TwigMiddleware::createFromContainer($app);

        // Create a logger
        $handlers = [];
        if (isset($_SERVER['PHPUNIT_LOG_TO_FILE']) && $_SERVER['PHPUNIT_LOG_TO_FILE']) {
            $handlers[] = new StreamHandler(PROJECT_ROOT . '/library/log.txt', Logger::DEBUG);
        } else {
            $handlers[] = new NullHandler();
        }

        $container->set('logger', function (ContainerInterface $container) use ($handlers) {
            $logger = new Logger('PHPUNIT');

            $uidProcessor = new UidProcessor();
            $logger->pushProcessor($uidProcessor);
            foreach ($handlers as $handler) {
                $logger->pushHandler($handler);
            }

            return $logger;
        });

        $container->set('name', 'test');

        //$this->getLogger()->debug('Loading Config');

        // Config
        $app->config = $container->get('configService');
        $app->router = $app->getRouteCollector()->getRouteParser();
        $request = $this->createRequest('GET', '/');

        \Xibo\Middleware\State::setState($app, $request);

       // $this->getLogger()->debug('Setting Middleware');
        $app->add(new Storage($app));
        $app->add(new TestAuthMiddleware($app));
        $app->add(new State($app));
        $app->add($twigMiddleware);
        $app->add(new Middleware\TestXmr($app));
        $app->addRoutingMiddleware();

        $customErrorHandler = function (Request $request, Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails) use ($app) {
            $nyholmFactory = new Psr17Factory();
            $decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
            /** @var Response $response */
            $response = $decoratedResponseFactory->createResponse($exception->getCode());

            return $response->withJson([
                'success' => false,
                'error' => $exception->getMessage(),
                'httpStatus' => $exception->getCode(),
                'data' => []
            ]);
        };

        // Add Error Middleware
        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler($customErrorHandler);

        // Store our container
        self::$container = $container;
        //$this->getLogger()->debug('Including Routes');

        // All routes
        require PROJECT_ROOT . '/lib/routes-web.php';
        require PROJECT_ROOT . '/lib/routes.php';

        // Add the route for running a task manually
        $app->get('/tasks/{id}', ['\Xibo\Controller\Task','run']);

        return $app;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $headers
     * @param string $requestAttrVal
     * @param bool|false $ajaxHeader
     * @param null $body
     * @return ResponseInterface
     */
    protected function sendRequest(string $method, string $path, $body = null,  array $headers = ['HTTP_ACCEPT'=>'application/json'], $requestAttrVal = 'test', $ajaxHeader = false ): ResponseInterface
    {
        // Create a request for tests
        $request = new Request(new ServerRequest($method, $path, $headers));
        $request = $request->withAttribute('name', $requestAttrVal);

        // If we are using POST or PUT method then we expect to have Body provided, add it to the request
        if (in_array($method, ['POST', 'PUT']) && $body != null) {

            $request = $request->withParsedBody($body);

            // in case we forgot to set Content-Type header for PUT requests
            if ($method === 'PUT') {
                $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
        }

        if ($ajaxHeader === true) {
            $request = $request->withHeader('X-Requested-With', 'XMLHttpRequest');
        }

        if ($method == 'GET' && $body != null) {
            $request = $request->withQueryParams($body);
        }

        // send the request and return the response
        $response = $this->app->handle($request);

        return $response;
    }

    /**
     * @param string $method
     * @param string $path
     * @param null $body
     * @param array $headers
     * @param array $serverParams
     * @return Request
     */
    protected function createRequest(string $method, string $path, $body = null,  array $headers = ['HTTP_ACCEPT'=>'application/json'], $serverParams = []): Request
    {
        // Create a request for tests
        $request = new Request(new ServerRequest($method, $path, $headers, $body, '', $serverParams));
        $request = $request->withAttribute('name', 'test');

        return $request;
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
        //  - an API application owned by phpunit with client_credentials grant type
        $container = ContainerFactory::create();

        // Create a logger
        $handlers = [];
        if (isset($_SERVER['PHPUNIT_LOG_TO_FILE']) && $_SERVER['PHPUNIT_LOG_TO_FILE']) {
            $handlers[] = new StreamHandler(PROJECT_ROOT . '/library/log.txt', Logger::INFO);
        } else {
            $handlers[] = new NullHandler();
        }

        $container->set('logger', function (ContainerInterface $container) use ($handlers) {
            $logger = new Logger('PHPUNIT');

            $uidProcessor = new UidProcessor();
            $logger->pushProcessor($uidProcessor);
            foreach ($handlers as $handler) {
                $logger->pushHandler($handler);
            }

            return $logger;
        });

        //config
        $container->get('configService');

        //translations
        $container->get('configService')->setDependencies($container->get('store'), '/');
        Translate::InitLocale($container->get('configService'));

        $container->set('name', 'test');

        // Find the PHPUnit user and if we don't create it
        try {
            /** @var User $user */
            $user = $container->get('userFactory')->getByName('phpunit');
            // Pass the page factory into the user object, so that it can check its page permissions
            $user->setChildAclDependencies($container->get('userGroupFactory'), $container->get('pageFactory'));
            // Load the user
            $user->load(false);


            $container->set('user', $user);
        } catch (NotFoundException $e) {
            // Create the phpunit user with a random password
            /** @var \Xibo\Entity\User $user */
            $user = $container->get('userFactory')->create();
            $user->setChildAclDependencies($container->get('userGroupFactory'), $container->get('pageFactory'));
            $user->userTypeId = 1;
            $user->userName = 'phpunit';
            $user->libraryQuota = 0;
            $user->homePageId = $container->get('pageFactory')->getByName('statusdashboard')->pageId;
            $user->isSystemNotification = 1;
            $user->setNewPassword(\Xibo\Helper\Random::generateString());
            $user->save();
            $container->get('store')->commitIfNecessary();

            $container->set('user', $user);
        }

        // Find the phpunit user and if we don't, complain
        try {
            /** @var User $admin */
            $admin = $container->get('userFactory')->getByName('phpunit');

        } catch (NotFoundException $e) {
            die ('Cant proceed without the phpunit user');
        }

        // Check to see if there is an API application we can use
        try {
            /** @var Application $application */
            $application = $container->get('applicationFactory')->getByName('phpunit');
        } catch (NotFoundException $e) {
            // Add it
            $application = $container->get('applicationFactory')->create();
            $application->name = ('phpunit');
            $application->authCode = 0;
            $application->clientCredentials = 1;
            $application->userId = $admin->userId;
            $application->assignScope($container->get('applicationScopeFactory')->getById('all'));
            $application->save();

            /** @var PdoStorageService $store */
            $store = $container->get('store');
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
        $store = $container->get('store');
        $key = $store->select('SELECT value FROM `setting` WHERE `setting` = \'SERVER_KEY\'', [])[0]['value'];
        $store->commitIfNecessary();
        $store->close();

        // Create an XMDS wrapper for the tests to use
        $xmds = new XmdsWrapper('http://localhost/xmds.php', $key);

        // Store our entityProvider
        self::$entityProvider = new XiboEntityProvider($provider);

        // Store our container
        self::$container = $container;

        // Store our XmdsWrapper
        self::$xmds = $xmds;
    }

    public static function tearDownAfterClass()
    {
        // Remove the DB
        self::$container->get('store')->close();

        parent::tearDownAfterClass();
    }

    public function tearDown()
    {
        // Remove the DB
        self::$container->get('store')->close();

        parent::tearDown();
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
        return self::$container->get('store');
    }

    /**
     * Set required service to instantiate a task
     */
    public function setService()
    {
        $c = self::$container;

        // Player Action Helper
        $c->set('playerActionService', function(ContainerInterface $c) {
            return new MockPlayerActionService(
                $c->get('configService'),
                $c->get('logService'),
                false
            );
        });

        // Register the display notify service
        $c->set('displayNotifyService', function(ContainerInterface $c) {
            return new DisplayNotifyService(
                $c->get('configService'),
                $c->get('logService'),
                $c->get('store'),
                $c->get('pool'),
                $c->get('playerActionService'),
                $c->get('scheduleFactory'),
                $c->get('dayPartFactory')
            );
        });
    }

    /**
     * Get a task object
     * @param string $task The path of the task class
     * @return TaskInterface
     * @throws NotFoundException
     */
    public function getTask($task)
    {

        $c = self::$container;

        // Set required service to instantiate the task
        $this->setService();

        /** @var TaskFactory $taskFactory */
        $taskFactory = $c->get('taskFactory');
        $task = $taskFactory->getByClass($task);

        /** @var TaskInterface $taskClass */
        $taskClass = new $task->class();

        return $taskClass
            ->setSanitizer($c->get('sanitizerService'))
            ->setUser($c->get('user'))
            ->setConfig($c->get('configService'))
            ->setLogger($c->get('logService'))
            ->setPool($c->get('pool'))
            ->setStore($c->get('store'))
            ->setTimeSeriesStore($c->get('timeSeriesStore'))
            ->setFactories($c)
            ->setTask($task);
    }

    /**
     * @return LoggerInterface
     * @throws \Exception
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
     * This function is using MockPlayerActionService, which returns an array of displayId on processQueue
     *
     * @return int[]
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getPlayerActionQueue()
    {
        /** @var MockPlayerActionService $service */
        $service = self::$container->get('playerActionService');

        if ($service === null)
            $this->fail('Test hasnt used the client and therefore cannot determine XMR activity');

        return $service->processQueue();
    }

    protected static function installModuleIfNecessary($name, $class)
    {
        // Make sure the HLS widget is installed
        $res = self::$container->get('store')->select('SELECT * FROM `module` WHERE `module` = :module', ['module' => $name]);

        if (count($res) <= 0) {
            // Install the module
            self::$container->get('store')->insert('
              INSERT INTO `module` (`Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`,
                 `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`, `viewPath`, `class`, `defaultDuration`, `installName`)
              VALUES (:module, :name, :enabled, :region_specific, :description,
                 :schema_version, :valid_extensions, :preview_enabled, :assignable, :render_as, :settings, :viewPath, :class, :defaultDuration, :installName)
            ', [
                'module' => $name,
                'name' => $name,
                'enabled' => 1,
                'region_specific' => 1,
                'description' => $name,
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
            self::$container->get('store')->commitIfNecessary();
        }
    }

    // Run for each unit test to setup our slim app environment
    public function setup()
    {
        // Establish a local reference to the Slim app object
        $this->app = $this->getSlimInstance();
    }

    public function setEnvironment($userSettings = [])
    {
        $defaults = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/',
            'QUERY_STRING' => '',
            'SERVER_NAME' => 'local.dev',
            'SERVER_PORT' => 80,
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'USER_AGENT' => 'Slim Framework',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $environmentSettings = array_merge($userSettings, $defaults);

        foreach ($environmentSettings as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }
}
