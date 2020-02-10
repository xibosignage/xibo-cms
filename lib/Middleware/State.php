<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

namespace Xibo\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Stash\Driver\Composite;
use Stash\Pool;
use Xibo\Exception\InstanceSuspendedException;
use Xibo\Exception\UpgradePendingException;
use Xibo\Helper\Environment;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\ReportService;
use Xibo\Twig\TwigMessages;

/**
 * Class State
 * @package Xibo\Middleware
 */
class State implements Middleware
{

    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws InstanceSuspendedException
     * @throws UpgradePendingException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;
        $container = $app->getContainer();

        // Set state
        State::setState($app, $request);
        $response = $handler->handle($request);
        // Attach a hook to log the route


            // Do we need SSL/STS?
            // If we are behind a load balancer we should look at HTTP_X_FORWARDED_PROTO
            // if a whitelist of IP address is provided, we should check it, otherwise trust
            $whiteListLoadBalancers = $container->get('configService')->getSetting('WHITELIST_LOAD_BALANCERS');
            $originIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $forwardedProtoHttps = (
                strtolower($request->getHeaderLine('HTTP_X_FORWARDED_PROTO')) === 'https'
                && $originIp != ''
                && (
                    $whiteListLoadBalancers === '' || in_array($originIp, explode(',', $whiteListLoadBalancers))
                )
            );

            if ($request->getUri()->getScheme() == 'https' || $forwardedProtoHttps) {
                if ($container->get('configService')->getSetting('ISSUE_STS', 0) == 1) {
                    $response->withHeader('strict-transport-security', 'max-age=' . $container->get('configService')->getSetting('STS_TTL', 600));
                }
            } else {
                // Get the current route pattern
                $routeContext = RouteContext::fromRequest($request);
                $route = $routeContext->getRoute();
                $resource = $route->getPattern();

                // Allow non-https access to the clock page, otherwise force https
                if ($resource !== '/clock' && $container->get('configService')->getSetting('FORCE_HTTPS', 0) == 1) {
                    $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    $response
                        ->withHeader('Location', $redirect)
                        ->withStatus(302);
                }
            }

            // Check to see if the instance has been suspended, if so call the special route
            if ($container->get('configService')->getSetting('INSTANCE_SUSPENDED') == 1)
                throw new InstanceSuspendedException();

            // Get to see if upgrade is pending, we don't want to throw this when we are on error page, causes redirect problems with error handler.
            if (Environment::migrationPending() && $request->getUri()->getPath() != '/error') {
                throw new UpgradePendingException();
            }

            // Reset the ETAGs for GZIP
            $requestEtag = $request->getHeader('IF_NONE_MATCH');
            if ($requestEtag) {
                $app->request()->headers->set('IF_NONE_MATCH', str_replace('-gzip', '', $requestEtag));
            }

            // Handle correctly outputting cache headers for AJAX requests
            // IE cache busting
        // TODO $app->getName() === 'web' && we should be able to use getAttribute (we need to set the names in auths)
            if ($this->isAjax($request) && $request->getMethod() == 'GET') {
                $response->withHeader('Cache-control', 'no-cache');
                $response->withHeader('Cache-control', 'no-store');
                $response->withHeader('Pragma', 'no-cache');
                $response->withHeader('Expires', '0');
            }

/*
        // Attach a hook to be called after the route has been dispatched
        $this->app->hook('slim.after.dispatch', function() use ($app) {
            // On our way back out the onion, we want to render the controller.
            if ($app->controller != null)
                $app->controller->render();
        });
*/
        // Next middleware
        return $response;
    }

    /**
     * @param App $app
     * @param \Psr\Http\Message\ServerRequestInterface $request
     */
    public static function setState(App $app, Request $request)
    {
        $container = $app->getContainer();

        // Set the root Uri
        State::setRootUri($app);

        // Set the config dependencies
        $container->get('configService')->setDependencies($container->get('store'), $app->rootUri);

        // Register the report service
        $container->set('reportService', function(ContainerInterface $container) {
            return new ReportService(
                $container,
                $container->get('state'),
                $container->get('store'),
                $container->get('timeSeriesStore'),
                $container->get('logService'),
                $container->get('configService'),
                $container->get('dateService'),
                $container->get('sanitizerService'),
                $container->get('savedReportFactory')
            );
        });



        // Set some public routes
        $app->publicRoutes = [
            '/login', '/login/forgotten', '/clock', '/about', '/login/ping',
            '/rss/:psk',
            '/sssp_config.xml',
            '/sssp_dl.wgt',
            '/playersoftware/:nonce/sssp_dl.wgt',
            '/playersoftware/:nonce/sssp_config.xml',
            '/tfa',
            '/error',
            '/notFound'
        ];

        // Setup the translations for gettext
        Translate::InitLocale($container->get('configService'));

        // Default timezone
        date_default_timezone_set($container->get('configService')->getSetting("defaultTimezone"));

        // Configure the cache TODO remove, configured in containerFactory
     //   self::configureCache($app->getContainer(), $app->configService, $app->getContainer()->get('logService')->getWriter());
/*
        // Register the help service
        $app->container->singleton('helpService', function($container) use ($app) {
            return new HelpService(
                $container->store,
                $container->configService,
                $container->pool,
                ($app->router()->getCurrentRoute() !== null) ? $app->router()->getCurrentRoute()->getPattern() : null
            );
        });

        // Create a session
        $app->container->singleton('session', function() use ($app) {
            if ($app->getName() == 'web' || $app->getName() == 'auth')
                return new Session($app->logService);
            else
                return new NullSession();
        });
*/
        // We use Slim Flash Messages so we must immediately start a session (boo)
        $container->get('session')->set('init', '1');
        /** @var Twig $view */
        $view = $container->get('view');
        // TODO some sort of conflict with web and api access :(
        $view->addExtension(new TwigMessages(new \Slim\Flash\Messages()));

        // App Mode
        $mode = $container->get('configService')->getSetting('SERVER_MODE');
        $app->getContainer()->get('logService')->setMode($mode);

        // Configure logging
        if (Environment::isForceDebugging() || strtolower($mode) == 'test') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
         //   $container->get('logger')->setLevel(Logger::DEBUG);
        }
        else {

            // Log level
            $level = \Xibo\Service\LogService::resolveLogLevel($container->get('configService')->getSetting('audit'));
            $restingLevel = \Xibo\Service\LogService::resolveLogLevel($container->get('configService')->getSetting('RESTING_LOG_LEVEL'));

            if ($level > $restingLevel) {
                // Do we allow the log level to be this high
                $elevateUntil = $container->get('configService')->getSetting('ELEVATE_LOG_UNTIL');

                if (intval($elevateUntil) < time()) {
                    // Elevation has expired, revert log level
                    $container->get('configService')->changeSetting('audit', $container->get('configService')->getSetting('RESTING_LOG_LEVEL'));

                    $level = $restingLevel;
                }
            }

            //$app->getLog()->setLevel($level);
        }

        // TODO  Configure any extra log handlers
        /*
        if ($container->get('configService')->logHandlers != null && is_array($container->get('configService')->logHandlers)) {
            $container->get('logService')->debug('Configuring %d additional log handlers from Config', count($container->get('configService')->logHandlers));
            foreach ($container->get('configService')->logHandlers as $handler) {
                $app->logWriter->addHandler($handler);
            }
        }

        // Configure any extra log processors
        if ($app->configService->logProcessors != null && is_array($app->configService->logProcessors)) {
            $app->logService->debug('Configuring %d additional log processors from Config', count($app->configService->logProcessors));
            foreach ($app->configService->logProcessors as $processor) {
                $app->logWriter->addProcessor($processor);
            }
        }
        */
    }

    /**
     * Set additional middleware
     * @param App $app
     */
    public static function setMiddleWare($app)
    {
        // Handle additional Middleware
        if (isset($app->configService->middleware) && is_array($app->getContainer()->get('configService')->middleware)) {
            foreach ($app->getContainer()->get('configService')->middleware as $object) {
                $app->add($object);
            }
        }
    }

    /**
     * Set the Root URI
     * @param App $app
     */
    public static function setRootUri(App $app)
    {
        // Set the root Uri
        $basePath = self::determineBasePath();

        // Static source, so remove index.php from the path
        // this should only happen if rewrite is disabled
        $basePath = str_replace('/index.php', '', $basePath);

        // Replace out all of the entrypoints to get back to the root
        $basePath = str_replace('/api/authorize', '', $basePath);
        $basePath = str_replace('/api', '', $basePath);
        $basePath = str_replace('/maintenance', '', $basePath);
        $basePath = str_replace('/install', '', $basePath);

        // Handle an empty (we always have our root with reference to `/`
        if ($basePath == null) {
            $basePath = '/';
        }

        $app->rootUri = $basePath;
    }

    /**
     * Determine the Base Path
     * @return mixed|string|string[]
     */
    public static function determineBasePath()
    {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $uri = (string) parse_url('http://a' . $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (stripos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
            return $_SERVER['SCRIPT_NAME'];
        } else if ($scriptDir !== '/' && stripos($uri, $scriptDir) === 0) {
            return $scriptDir;
        } else {
            return '';
        }
    }

    /**
     * Configure the Cache
     * @param ContainerInterface $container
     * @param ConfigServiceInterface $configService
     * @param \PSR\Log\LoggerInterface $logWriter
     */
    public static function configureCache($container, $configService, $logWriter)
    {
        $drivers = [];

        if ($configService->getCacheDrivers() != null && is_array($configService->getCacheDrivers())) {
            $drivers = $configService->getCacheDrivers();
        } else {
            // File System Driver
            $realPath = realpath($configService->getSetting('LIBRARY_LOCATION'));
            $cachePath = ($realPath) ? $realPath . '/cache/' : $configService->getSetting('LIBRARY_LOCATION') . 'cache/';

            $drivers[] = new \Stash\Driver\FileSystem(['path' => $cachePath]);
        }

        // Create a composite driver
        $composite = new Composite(['drivers' => $drivers]);

        // Create a pool using this driver set
        $container->set('pool', function() use ($logWriter, $composite, $configService) {
            $pool = new Pool($composite);
            $pool->setLogger($logWriter);
            $pool->setNamespace($configService->getCacheNamespace());
            $configService->setPool($pool);
            return $pool;
        });
    }

    /**
     * Register controllers with DI
     */
    public static function registerControllersWithDi()
    {
        return [
            '\Xibo\Controller\Applications' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Applications(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('session'),
                    $c->get('store'),
                    $c->get('applicationFactory'),
                    $c->get('applicationRedirectUriFactory'),
                    $c->get('applicationScopeFactory'),
                    $c->get('userFactory'),
                    $c->get('view'),
                    $c
                );
            },
            '\Xibo\Controller\AuditLog' => function(ContainerInterface $c) {
                return new \Xibo\Controller\AuditLog(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('auditLogFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Campaign' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Campaign(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('campaignFactory'),
                    $c->get('layoutFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('tagFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Clock' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Clock(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('session')
                );
            },
            '\Xibo\Controller\Command' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Command(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('commandFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\DataSet' => function(ContainerInterface $c) {
                return new \Xibo\Controller\DataSet(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\DataSetColumn' => function(ContainerInterface $c) {
                return new \Xibo\Controller\DataSetColumn(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('dataSetColumnTypeFactory'),
                    $c->get('dataTypeFactory'),
                    $c->get('pool'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\DataSetData' => function(ContainerInterface $c) {
                return new \Xibo\Controller\DataSetData(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('dataSetFactory'),
                    $c->get('mediaFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\DataSetRss' => function(ContainerInterface $c) {
                return new \Xibo\Controller\DataSetRss(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('dataSetRssFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('pool'),
                    $c->get('store'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\DayPart' => function(ContainerInterface $c) {
                return new \Xibo\Controller\DayPart(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('dayPartFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayFactory'),
                    $c->get('layoutFactory'),
                    $c->get('mediaFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Display' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Display(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('playerActionService'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('logFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('mediaFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('displayEventFactory'),
                    $c->get('requiredFileFactory'),
                    $c->get('tagFactory'),
                    $c->get('notificationFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\DisplayGroup' => function(ContainerInterface $c) {
                return new \Xibo\Controller\DisplayGroup(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('playerActionService'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('moduleFactory'),
                    $c->get('mediaFactory'),
                    $c->get('commandFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('tagFactory'),
                    $c->get('campaignFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\DisplayProfile' => function(ContainerInterface $c) {
                return new \Xibo\Controller\DisplayProfile(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('displayProfileFactory'),
                    $c->get('commandFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Error' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Error(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('view'),
                    $c
                );
            },
            '\Xibo\Controller\Fault' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Fault(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('logFactory'),
                    $c->get('displayFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Help' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Help(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('helpFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\IconDashboard' => function(ContainerInterface $c) {
                return new \Xibo\Controller\IconDashboard(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Layout' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Layout(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('session'),
                    $c->get('userFactory'),
                    $c->get('resolutionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('moduleFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('tagFactory'),
                    $c->get('mediaFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('campaignFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('view'),
                    $c
                );
            },
            '\Xibo\Controller\Library' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Library(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('dispatcher'),
                    $c->get('userFactory'),
                    $c->get('moduleFactory'),
                    $c->get('tagFactory'),
                    $c->get('mediaFactory'),
                    $c->get('widgetFactory'),
                    $c->get('permissionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('playlistFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('regionFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Logging' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Logging(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('logFactory'),
                    $c->get('displayFactory'),
                    $c->get('userFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Login' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Login(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('session'),
                    $c->get('userFactory'),
                    $c->get('pool'),
                    $c->get('store'),
                    $c->get('view'),
                    $c->get('flash')
                );
            },
            '\Xibo\Controller\Maintenance' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Maintenance(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('taskFactory'),
                    $c->get('mediaFactory'),
                    $c->get('layoutFactory'),
                    $c->get('widgetFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('playerVersionFactory'),
                    $c
                );
            },
            '\Xibo\Controller\MediaManager' => function(ContainerInterface $c) {
                return new \Xibo\Controller\MediaManager(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('moduleFactory'),
                    $c->get('layoutFactory'),
                    $c->get('regionFactory'),
                    $c->get('playlistFactory'),
                    $c->get('widgetFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\PlaylistDashboard' => function(ContainerInterface $c) {
                return new \Xibo\Controller\PlaylistDashboard(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('playlistFactory'),
                    $c->get('moduleFactory'),
                    $c->get('widgetFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('view'),
                    $c
                );
            },
            '\Xibo\Controller\Module' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Module(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('moduleFactory'),
                    $c->get('playlistFactory'),
                    $c->get('mediaFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('widgetFactory'),
                    $c->get('transitionFactory'),
                    $c->get('regionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('widgetAudioFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('view'),
                    $c
                );
            },
            '\Xibo\Controller\Notification' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Notification(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('notificationFactory'),
                    $c->get('userNotificationFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayNotifyService'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\PlayerSoftware' => function(ContainerInterface $c) {
                return new \Xibo\Controller\PlayerSoftware(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('mediaFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('moduleFactory'),
                    $c->get('layoutFactory'),
                    $c->get('widgetFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Playlist' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Playlist(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('playlistFactory'),
                    $c->get('regionFactory'),
                    $c->get('mediaFactory'),
                    $c->get('permissionFactory'),
                    $c->get('transitionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('moduleFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('userFactory'),
                    $c->get('tagFactory'),
                    $c->get('view'),
                    $c->get('layoutFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory')
                );
            },
            '\Xibo\Controller\Preview' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Preview(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('layoutFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Region' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Region(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('session'),
                    $c->get('regionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('permissionFactory'),
                    $c->get('transitionFactory'),
                    $c->get('moduleFactory'),
                    $c->get('layoutFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Report' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Report(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('reportService'),
                    $c->get('reportScheduleFactory'),
                    $c->get('savedReportFactory'),
                    $c->get('mediaFactory'),
                    $c->get('userFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Resolution' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Resolution(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('resolutionFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Schedule' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Schedule(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('session'),
                    $c->get('pool'),
                    $c->get('scheduleFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('campaignFactory'),
                    $c->get('commandFactory'),
                    $c->get('displayFactory'),
                    $c->get('layoutFactory'),
                    $c->get('mediaFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('scheduleReminderFactory'),
                    $c->get('scheduleExclusionFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Sessions' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Sessions(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('sessionFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Settings' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Settings(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('layoutFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('transitionFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Stats' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Stats(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('reportService'),
                    $c->get('displayFactory'),
                    $c->get('layoutFactory'),
                    $c->get('mediaFactory'),
                    $c->get('userFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\StatusDashboard' => function(ContainerInterface $c) {
                return new \Xibo\Controller\StatusDashboard(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('userFactory'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('mediaFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Task' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Task(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('pool'),
                    $c->get('taskFactory'),
                    $c->get('userFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayFactory'),
                    $c->get('mediaFactory'),
                    $c->get('notificationFactory'),
                    $c->get('userNotificationFactory'),
                    $c->get('view'),
                    $c
                );
            },
            '\Xibo\Controller\Tag' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Tag(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('store'),
                    $c->get('displayGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('tagFactory'),
                    $c->get('userFactory'),
                    $c->get('displayFactory'),
                    $c->get('mediaFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('campaignFactory'),
                    $c->get('playlistFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Template' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Template(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('layoutFactory'),
                    $c->get('tagFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\Transition' => function(ContainerInterface $c) {
                return new \Xibo\Controller\Transition(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('transitionFactory'),
                    $c->get('view')
                );
            },
            '\Xibo\Controller\User' => function(ContainerInterface $c) {
                return new \Xibo\Controller\User(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('userFactory'),
                    $c->get('userTypeFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('pageFactory'),
                    $c->get('permissionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('applicationFactory'),
                    $c->get('campaignFactory'),
                    $c->get('mediaFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('displayFactory'),
                    $c->get('sessionFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('widgetFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('playlistFactory'),
                    $c->get('view'),
                    $c,
                    $c->get('dataSetFactory')
                );
            },
            '\Xibo\Controller\UserGroup' => function(ContainerInterface $c) {
                return new \Xibo\Controller\UserGroup(
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('state'),
                    $c->get('user'),
                    $c->get('helpService'),
                    $c->get('dateService'),
                    $c->get('configService'),
                    $c->get('userGroupFactory'),
                    $c->get('pageFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userFactory'),
                    $c->get('view')
                );
            },
        ];
    }

    /**
     * Register Factories with DI
     */
    public static function registerFactoriesWithDi()
    {
        return [
            'applicationFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ApplicationFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('applicationRedirectUriFactory'),
                    $c->get('applicationScopeFactory')
                );
            },
            'applicationRedirectUriFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ApplicationRedirectUriFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'applicationScopeFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ApplicationScopeFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'auditLogFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\AuditLogFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'bandwidthFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\BandwidthFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'campaignFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\CampaignFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('displayFactory'),
                    $c->get('tagFactory')
                );
            },
            'commandFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\CommandFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory')
                );
            },
            'dataSetColumnFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DataSetColumnFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('dataTypeFactory'),
                    $c->get('dataSetColumnTypeFactory')
                );
            },
            'dataSetColumnTypeFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DataSetColumnTypeFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'dataSetFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DataSetFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('permissionFactory'),
                    $c->get('displayFactory'),
                    $c->get('dateService')
                );
            },
            'dataSetRssFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DataSetRssFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory')
                );
            },
            'dataTypeFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DataTypeFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'dayPartFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DayPartFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory')
                );
            },
            'displayFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DisplayFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('displayNotifyService'),
                    $c->get('configService'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayProfileFactory')
                );
            },
            'displayEventFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DisplayEventFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'displayGroupFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DisplayGroupFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('tagFactory')
                );
            },
            'displayProfileFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\DisplayProfileFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('configService'),
                    $c->get('dispatcher'),
                    $c->get('commandFactory')
                );
            },
            'helpFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\HelpFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'layoutFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\LayoutFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('dateService'),
                    $c->get('dispatcher'),
                    $c->get('permissionFactory'),
                    $c->get('regionFactory'),
                    $c->get('tagFactory'),
                    $c->get('campaignFactory'),
                    $c->get('mediaFactory'),
                    $c->get('moduleFactory'),
                    $c->get('resolutionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('widgetOptionFactory'),
                    $c->get('playlistFactory'),
                    $c->get('widgetAudioFactory')
                );
            },
            'logFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\LogFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'mediaFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\MediaFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('permissionFactory'),
                    $c->get('tagFactory'),
                    $c->get('playlistFactory')
                );
            },
            'moduleFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ModuleFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('moduleService'),
                    $c->get('widgetFactory'),
                    $c->get('regionFactory'),
                    $c->get('playlistFactory'),
                    $c->get('mediaFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('transitionFactory'),
                    $c->get('displayFactory'),
                    $c->get('commandFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('view'),
                    $c
                );
            },
            'notificationFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\NotificationFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayGroupFactory')
                );
            },
            'pageFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\PageFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'permissionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\PermissionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'playerVersionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\PlayerVersionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('mediaFactory')
                );
            },
            'playlistFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\PlaylistFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('configService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('dateService'),
                    $c->get('permissionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('tagFactory')
                );
            },
            'regionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\RegionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('dateService'),
                    $c->get('permissionFactory'),
                    $c->get('regionOptionFactory'),
                    $c->get('playlistFactory')
                );
            },
            'regionOptionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\RegionOptionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'requiredFileFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\RequiredFileFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'reportScheduleFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ReportScheduleFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('dateService')
                );
            },
            'resolutionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ResolutionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'savedReportFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\SavedReportFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('mediaFactory')
                );
            },
            'scheduleFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ScheduleFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('dateService'),
                    $c->get('displayGroupFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('userFactory'),
                    $c->get('scheduleReminderFactory'),
                    $c->get('scheduleExclusionFactory')
                );
            },
            'scheduleReminderFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ScheduleReminderFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService')
                );
            },
            'scheduleExclusionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\ScheduleExclusionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'sessionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\SessionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('dateService')
                );
            },
            'tagFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\TagFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'taskFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\TaskFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'transitionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\TransitionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'userFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\UserFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('configService'),
                    $c->get('permissionFactory'),
                    $c->get('userOptionFactory'),
                    $c->get('applicationScopeFactory')
                );
            },
            'userGroupFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\UserGroupFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory')
                );
            },
            'userNotificationFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\UserNotificationFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory')
                );
            },
            'userOptionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\UserOptionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'userTypeFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\UserTypeFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'widgetFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\WidgetFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('dateService'),
                    $c->get('widgetOptionFactory'),
                    $c->get('widgetMediaFactory'),
                    $c->get('widgetAudioFactory'),
                    $c->get('permissionFactory'),
                    $c->get('displayFactory')
                );
            },
            'widgetMediaFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\WidgetMediaFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'widgetAudioFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\WidgetAudioFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
            'widgetOptionFactory' => function(ContainerInterface $c) {
                return new \Xibo\Factory\WidgetOptionFactory(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('sanitizerService')
                );
            },
        ];
    }

    private function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}