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

namespace Xibo\Middleware;

use Carbon\Carbon;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\User;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\LayoutOwnerChangeEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\MediaFullLoadEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Helper\Environment;
use Xibo\Helper\NullSession;
use Xibo\Helper\Session;
use Xibo\Helper\Translate;
use Xibo\Listener\OnDisplayGroupLoad\DisplayGroupDisplayListener;
use Xibo\Listener\OnDisplayGroupLoad\DisplayGroupLayoutListener;
use Xibo\Listener\OnDisplayGroupLoad\DisplayGroupMediaListener;
use Xibo\Listener\OnDisplayGroupLoad\DisplayGroupScheduleListener;
use Xibo\Listener\OnLayoutOwnerChange;
use Xibo\Listener\OnMediaDelete;
use Xibo\Listener\OnMediaLoad\DisplayGroupListener;
use Xibo\Listener\OnMediaLoad\LayoutListener;
use Xibo\Listener\OnMediaLoad\WidgetListener;
use Xibo\Listener\OnUserDelete;
use Xibo\Service\ReportService;
use Xibo\Support\Exception\InstanceSuspendedException;
use Xibo\Support\Exception\UpgradePendingException;
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;
        $container = $app->getContainer();

        // Set state
        $request = State::setState($app, $request);

        // Check to see if the instance has been suspended, if so call the special route
        if ($container->get('configService')->getSetting('INSTANCE_SUSPENDED') == 1) {
            throw new InstanceSuspendedException();
        }

        // Get to see if upgrade is pending, we don't want to throw this when we are on error page, causes redirect problems with error handler.
        if (Environment::migrationPending() && $request->getUri()->getPath() != '/error') {
            throw new UpgradePendingException();
        }

        // Next middleware
        $response = $handler->handle($request);

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
                $response = $response->withHeader('strict-transport-security', 'max-age=' . $container->get('configService')->getSetting('STS_TTL', 600));
            }
        } else {
            // Get the current route pattern
            $routeContext = RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();
            $resource = $route->getPattern();

            // Allow non-https access to the clock page, otherwise force https
            if ($resource !== '/clock' && $container->get('configService')->getSetting('FORCE_HTTPS', 0) == 1) {
                $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $response = $response->withHeader('Location', $redirect)
                                     ->withStatus(302);
            }
        }

        // Reset the ETAGs for GZIP
        $requestEtag = $request->getHeaderLine('IF_NONE_MATCH');
        if ($requestEtag) {
            $response = $response->withHeader('IF_NONE_MATCH', str_replace('-gzip', '', $requestEtag));
        }

        // Handle correctly outputting cache headers for AJAX requests
        // IE cache busting
        if ($this->isAjax($request) && $request->getMethod() == 'GET' && $request->getAttribute('name') == 'web') {
            $response = $response->withHeader('Cache-control', 'no-cache')
                     ->withHeader('Cache-control', 'no-store')
                     ->withHeader('Pragma', 'no-cache')
                     ->withHeader('Expires', '0');
        }

        return $response;
    }

    /**
     * @param App $app
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public static function setState(App $app, Request $request): Request
    {
        $container = $app->getContainer();

        // Set the config dependencies
        $container->get('configService')->setDependencies($container->get('store'), $container->get('rootUri'));

        // set the system user for XTR/XMDS
        if ($container->get('name') == 'xtr' || $container->get('name') == 'xmds') {
            // Configure a user
            /** @var User $user */
            $user = $container->get('userFactory')->getSystemUser();
            $user->setChildAclDependencies($container->get('userGroupFactory'));

            // Load the user
            $user->load(false);
            $container->set('user', $user);
        }

        // Register the report service
        $container->set('reportService', function(ContainerInterface $container) {
            return new ReportService(
                $container,
                $container->get('state'),
                $container->get('store'),
                $container->get('timeSeriesStore'),
                $container->get('logService'),
                $container->get('configService'),
                $container->get('sanitizerService'),
                $container->get('savedReportFactory')
            );
        });

        // Set some public routes
        $request = $request->withAttribute('publicRoutes', array_merge($request->getAttribute('publicRoutes', []), [
            '/login',
            '/login/forgotten',
            '/clock',
            '/about',
            '/login/ping',
            '/rss/{psk}',
            '/sssp_config.xml',
            '/sssp_dl.wgt',
            '/playersoftware/{nonce}/sssp_dl.wgt',
            '/playersoftware/{nonce}/sssp_config.xml',
            '/tfa',
            '/error',
            '/notFound'
        ]));

        // Setup the translations for gettext
        Translate::InitLocale($container->get('configService'));

        // Default timezone
        date_default_timezone_set($container->get('configService')->getSetting("defaultTimezone"));

        $container->set('session', function(ContainerInterface $container) use ($app) {
            if ($container->get('name') == 'web' || $container->get('name') == 'auth') {
                return new Session($container->get('logService'));
            } else {
                return new NullSession();
            }
        });

        // We use Slim Flash Messages so we must immediately start a session (boo)
        $container->get('session')->set('init', '1');

        // App Mode
        $mode = $container->get('configService')->getSetting('SERVER_MODE');
        $container->get('logService')->setMode($mode);


        if ($container->get('name') == 'web' || $container->get('name') == 'xtr') {

            /** @var Twig $view */
            $view = $container->get('view');

            if ($container->get('name') == 'web') {
                $container->set('flash', function () {
                    return new \Slim\Flash\Messages();
                });
                $view->addExtension(new TwigMessages(new \Slim\Flash\Messages()));
            }

            $twigEnvironment = $view->getEnvironment();

            // add the urldecode filter to Twig.
            $filter = new \Twig\TwigFilter('url_decode', 'urldecode');
            $twigEnvironment->addFilter($filter);

            // set Twig auto reload if we are in dev mode
            if (Environment::isDevMode()) {
                $twigEnvironment->enableAutoReload();
            }
        }

        // Configure logging
        // -----------------
        // Standard handlers
        if (Environment::isForceDebugging() || strtolower($mode) == 'test') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            $container->get('logService')->setLevel(Logger::DEBUG);
        } else {
            // Log level
            $level = \Xibo\Service\LogService::resolveLogLevel($container->get('configService')->getSetting('audit'));
            $restingLevel = \Xibo\Service\LogService::resolveLogLevel($container->get('configService')->getSetting('RESTING_LOG_LEVEL'));

            // the higher the number the less strict the logging.
            if ($level < $restingLevel) {
                // Do we allow the log level to be this high
                $elevateUntil = $container->get('configService')->getSetting('ELEVATE_LOG_UNTIL');
                if (intval($elevateUntil) < Carbon::now()->format('U')) {
                    // Elevation has expired, revert log level
                    $container->get('configService')->changeSetting('audit', $container->get('configService')->getSetting('RESTING_LOG_LEVEL'));
                    $level = $restingLevel;
                }
            }

            $container->get('logService')->setLevel($level);
        }

        // Configure any extra log handlers
        // we do these last so that they can provide their own log levels independent of the system settings
        if ($container->get('configService')->logHandlers != null && is_array($container->get('configService')->logHandlers)) {
            $container->get('logService')->debug('Configuring %d additional log handlers from Config', count($container->get('configService')->logHandlers));
            foreach ($container->get('configService')->logHandlers as $handler) {
                // Direct access to the LoggerInterface here, rather than via our log service
                $container->get('logger')->pushHandler($handler);
            }
        }

        // Configure any extra log processors
        if ($container->get('configService')->logProcessors != null && is_array($container->get('configService')->logProcessors)) {
            $container->get('logService')->debug('Configuring %d additional log processors from Config', count($container->get('configService')->logProcessors));
            foreach ($container->get('configService')->logProcessors as $processor) {
                $container->get('logger')->pushProcessor($processor);
            }
        }

        return $request;
    }

    /**
     * Set additional middleware
     * @param App $app
     */
    public static function setMiddleWare($app)
    {
        // Handle additional Middleware
        if (isset($app->getContainer()->get('configService')->middleware) && is_array($app->getContainer()->get('configService')->middleware)) {
            foreach ($app->getContainer()->get('configService')->middleware as $object) {
                // Decorate our middleware with the App if it has a method to do so
                if (method_exists($object, 'setApp')) {
                    $object->setApp($app);
                }

                // Add any new routes from custom middleware
                if (method_exists($object, 'addRoutes')) {
                    $object->addRoutes();
                }

                $app->add($object);
            }
        }
    }

    /**
     * Register controllers with DI
     */
    public static function registerControllersWithDi()
    {
        return [
            '\Xibo\Controller\Action' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Action(
                    $c->get('actionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('regionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('moduleFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Applications' => function(ContainerInterface $c) {
                $controller =  new \Xibo\Controller\Applications(
                    $c->get('session'),
                    $c->get('applicationFactory'),
                    $c->get('applicationRedirectUriFactory'),
                    $c->get('applicationScopeFactory'),
                    $c->get('userFactory')
                );

                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\AuditLog' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\AuditLog(
                    $c->get('auditLogFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Campaign' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Campaign(
                    $c->get('campaignFactory'),
                    $c->get('layoutFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('tagFactory'),
                    $c->get('folderFactory')
                );

                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Clock' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Clock(
                    $c->get('session')
                );

                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Command' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Command(
                    $c->get('commandFactory'),
                    $c->get('displayProfileFactory')
                );

                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSet' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSet(
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('userFactory'),
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSetColumn' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSetColumn(
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('dataSetColumnTypeFactory'),
                    $c->get('dataTypeFactory'),
                    $c->get('pool')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSetData' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSetData(
                    $c->get('dataSetFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSetRss' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSetRss(
                    $c->get('dataSetRssFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('pool'),
                    $c->get('store')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DayPart' => function(ContainerInterface $c) {
                $controller =  new \Xibo\Controller\DayPart(
                    $c->get('dayPartFactory'),
                    $c->get('scheduleFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Display' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Display(
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('playerActionService'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('displayEventFactory'),
                    $c->get('requiredFileFactory'),
                    $c->get('tagFactory'),
                    $c->get('notificationFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('dayPartFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                $controller->useDispatcher($c->get('dispatcher'));
                return $controller;
            },
            '\Xibo\Controller\DisplayGroup' => function(ContainerInterface $c) {
                $controller =  new \Xibo\Controller\DisplayGroup(
                    $c->get('playerActionService'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('moduleFactory'),
                    $c->get('mediaFactory'),
                    $c->get('commandFactory'),
                    $c->get('tagFactory'),
                    $c->get('campaignFactory'),
                    $c->get('folderFactory')
                );
                $controller->useDispatcher($c->get('dispatcher'));
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DisplayProfile' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\DisplayProfile(
                    $c->get('pool'),
                    $c->get('displayProfileFactory'),
                    $c->get('commandFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('dayPartFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Fault' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Fault(
                    $c->get('store'),
                    $c->get('logFactory'),
                    $c->get('displayFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Folder' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Folder(
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Help' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Help(
                    $c->get('helpFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\IconDashboard' => function(ContainerInterface $c) {
                $controller =  new \Xibo\Controller\IconDashboard();
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Layout' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Layout(
                    $c->get('session'),
                    $c->get('userFactory'),
                    $c->get('resolutionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('moduleFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('tagFactory'),
                    $c->get('mediaFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('campaignFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('pool'),
                    $c->get('mediaService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Library' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Library(
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('userFactory'),
                    $c->get('moduleFactory'),
                    $c->get('tagFactory'),
                    $c->get('mediaFactory'),
                    $c->get('widgetFactory'),
                    $c->get('permissionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('playlistFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('httpCache'),
                    $c->get('folderFactory')
                );
                $controller->useDispatcher($c->get('dispatcher'));
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Logging' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Logging(
                    $c->get('store'),
                    $c->get('logFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Login' => function(ContainerInterface $c) {
                $controller =  new \Xibo\Controller\Login(
                    $c->get('session'),
                    $c->get('userFactory'),
                    $c->get('pool'),
                    $c->get('flash')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Maintenance' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Maintenance(
                    $c->get('store'),
                    $c->get('mediaFactory'),
                    $c->get('layoutFactory'),
                    $c->get('widgetFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('mediaService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MediaManager' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\MediaManager(
                    $c->get('moduleFactory'),
                    $c->get('layoutFactory'),
                    $c->get('regionFactory'),
                    $c->get('widgetFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MenuBoard' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\MenuBoard(
                    $c->get('menuBoardFactory'),
                    $c->get('userFactory'),
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MenuBoardCategory' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\MenuBoardCategory(
                    $c->get('menuBoardFactory'),
                    $c->get('menuBoardCategoryFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MenuBoardProduct' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\MenuBoardProduct(
                    $c->get('menuBoardFactory'),
                    $c->get('menuBoardCategoryFactory'),
                    $c->get('menuBoardProductOptionFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\PlaylistDashboard' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\PlaylistDashboard(
                    $c->get('playlistFactory'),
                    $c->get('moduleFactory'),
                    $c->get('widgetFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayGroupFactory'),
                    $c
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Module' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Module(
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
                    $c->get('dataSetFactory'),
                    $c->get('menuBoardFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                $controller->useDispatcher($c->get('dispatcher'));
                return $controller;
            },
            '\Xibo\Controller\Notification' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Notification(
                    $c->get('notificationFactory'),
                    $c->get('userNotificationFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayNotifyService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\PlayerSoftware' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\PlayerSoftware(
                    $c->get('pool'),
                    $c->get('mediaFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('moduleFactory'),
                    $c->get('displayFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                $controller->useDispatcher($c->get('dispatcher'));
                return $controller;
            },
            '\Xibo\Controller\Playlist' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Playlist(
                    $c->get('playlistFactory'),
                    $c->get('mediaFactory'),
                    $c->get('widgetFactory'),
                    $c->get('moduleFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('userFactory'),
                    $c->get('tagFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Preview' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Preview(
                    $c->get('layoutFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Region' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Region(
                    $c->get('regionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('transitionFactory'),
                    $c->get('moduleFactory'),
                    $c->get('layoutFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Report' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Report(
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('reportService'),
                    $c->get('reportScheduleFactory'),
                    $c->get('savedReportFactory'),
                    $c->get('mediaFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Resolution' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Resolution(
                    $c->get('resolutionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Schedule' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Schedule(
                    $c->get('session'),
                    $c->get('scheduleFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('campaignFactory'),
                    $c->get('commandFactory'),
                    $c->get('displayFactory'),
                    $c->get('layoutFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('scheduleReminderFactory'),
                    $c->get('scheduleExclusionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Sessions' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Sessions(
                    $c->get('store'),
                    $c->get('sessionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Settings' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Settings(
                    $c->get('layoutFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('transitionFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Stats' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Stats(
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('reportService'),
                    $c->get('displayFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\StatusDashboard' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\StatusDashboard(
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('userFactory'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Task' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Task(
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('pool'),
                    $c->get('taskFactory'),
                    $c
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Tag' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Tag(
                    $c->get('displayGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('tagFactory'),
                    $c->get('userFactory'),
                    $c->get('displayFactory'),
                    $c->get('mediaFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('campaignFactory'),
                    $c->get('playlistFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Template' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Template(
                    $c->get('layoutFactory'),
                    $c->get('tagFactory'),
                    $c->get('resolutionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Transition' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\Transition(
                    $c->get('transitionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\User' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\User(
                    $c->get('userFactory'),
                    $c->get('userTypeFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('permissionFactory'),
                    $c->get('applicationFactory'),
                    $c->get('sessionFactory'),
                    $c->get('permissionService'),
                    $c->get('mediaService')
                );
                $controller->useDispatcher($c->get('dispatcher'));
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\UserGroup' => function(ContainerInterface $c) {
                $controller = new \Xibo\Controller\UserGroup(
                    $c->get('userGroupFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
        ];
    }

    /**
     * Register Factories with DI
     */
    public static function registerFactoriesWithDi()
    {
        return [
            'actionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ActionFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'applicationFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ApplicationFactory(
                    $c->get('user'),
                    $c->get('applicationRedirectUriFactory'),
                    $c->get('applicationScopeFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'applicationRedirectUriFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ApplicationRedirectUriFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'applicationScopeFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ApplicationScopeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'auditLogFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\AuditLogFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'bandwidthFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\BandwidthFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'campaignFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\CampaignFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('displayNotifyService'),
                    $c->get('tagFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'commandFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\CommandFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetColumnFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetColumnFactory(
                    $c->get('dataTypeFactory'),
                    $c->get('dataSetColumnTypeFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetColumnTypeFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetColumnTypeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('permissionFactory'),
                    $c->get('displayNotifyService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetRssFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetRssFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataTypeFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataTypeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dayPartFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DayPartFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('displayNotifyService'),
                    $c->get('configService'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('folderFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayEventFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayEventFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayGroupFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayGroupFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('tagFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayProfileFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayProfileFactory(
                    $c->get('configService'),
                    $c->get('commandFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'folderFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\FolderFactory(
                    $c->get('permissionFactory'),
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'helpFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\HelpFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'layoutFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\LayoutFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
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
                    $c->get('widgetAudioFactory'),
                    $c->get('actionFactory'),
                    $c->get('folderFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'logFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\LogFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'mediaFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\MediaFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('permissionFactory'),
                    $c->get('tagFactory'),
                    $c->get('playlistFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'menuBoardCategoryFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\MenuBoardCategoryFactory(
                    $c->get('menuBoardProductOptionFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'menuBoardProductOptionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\MenuBoardProductOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'menuBoardFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\MenuBoardFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('permissionFactory'),
                    $c->get('menuBoardCategoryFactory'),
                    $c->get('displayNotifyService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'moduleFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ModuleFactory(
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
                    $c->get('menuBoardFactory'),
                    $c->get('menuBoardCategoryFactory'),
                    $c->get('view'),
                    $c->get('httpCache')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'notificationFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\NotificationFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayGroupFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'permissionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\PermissionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'playerVersionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\PlayerVersionFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('mediaFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'playlistFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\PlaylistFactory(
                    $c->get('configService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('tagFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'regionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\RegionFactory(
                    $c->get('permissionFactory'),
                    $c->get('regionOptionFactory'),
                    $c->get('playlistFactory'),
                    $c->get('actionFactory'),
                    $c->get('campaignFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'regionOptionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\RegionOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'requiredFileFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\RequiredFileFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'reportScheduleFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ReportScheduleFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'resolutionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ResolutionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'savedReportFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\SavedReportFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('mediaFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'scheduleFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ScheduleFactory(
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('displayGroupFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('userFactory'),
                    $c->get('scheduleReminderFactory'),
                    $c->get('scheduleExclusionFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'scheduleReminderFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ScheduleReminderFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'scheduleExclusionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\ScheduleExclusionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'sessionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\SessionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'tagFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\TagFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'taskFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\TaskFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'transitionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\TransitionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserFactory(
                    $c->get('configService'),
                    $c->get('permissionFactory'),
                    $c->get('userOptionFactory'),
                    $c->get('applicationScopeFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userGroupFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserGroupFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userNotificationFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserNotificationFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userOptionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userTypeFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserTypeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('widgetOptionFactory'),
                    $c->get('widgetMediaFactory'),
                    $c->get('widgetAudioFactory'),
                    $c->get('permissionFactory'),
                    $c->get('displayNotifyService'),
                    $c->get('actionFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetMediaFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetMediaFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetAudioFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetAudioFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetOptionFactory' => function(ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
        ];
    }

    public static function registerDispatcherWithDi()
    {
        return [
            'dispatcher' => function(ContainerInterface $c) {
                $dispatcher = new EventDispatcher();

                // Media Delete Events
                $dispatcher->addListener(MediaDeleteEvent::$NAME, (new OnMediaDelete\MenuBoardListener(
                    $c->get('menuBoardCategoryFactory')
                )));

                $dispatcher->addListener(MediaDeleteEvent::$NAME, (new OnMediaDelete\LayoutListener(
                    $c->get('layoutFactory')
                )));

                $dispatcher->addListener(MediaDeleteEvent::$NAME, (new OnMediaDelete\WidgetListener(
                    $c->get('store'),
                    $c->get('widgetFactory')
                )));

                $dispatcher->addListener(MediaDeleteEvent::$NAME, (new OnMediaDelete\DisplayGroupListener(
                    $c->get('displayGroupFactory')
                ))->useLogger($c->get('logger')));

                // User Delete Events
                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\CampaignListener(
                    $c->get('store'),
                    $c->get('campaignFactory'),
                    $c->get('layoutFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\DataSetListener(
                    $c->get('store'),
                    $c->get('dataSetFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\DayPartListener(
                    $c->get('store'),
                    $c->get('dayPartFactory'),
                    $c->get('scheduleFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\DisplayGroupListener(
                    $c->get('store'),
                    $c->get('displayGroupFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\LayoutListener(
                    $c->get('layoutFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\MediaListener(
                    $c->get('store'),
                    $c->get('mediaFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\MenuBoardListener(
                    $c->get('store'),
                    $c->get('menuBoardFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\PlaylistListener(
                    $c->get('playlistFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\ScheduleListener(
                    $c->get('store'),
                    $c->get('scheduleFactory')
                ))->useLogger($c->get('logger')));

                $dispatcher->addListener(UserDeleteEvent::$NAME, (new OnUserDelete\OnUserDelete(
                    $c->get('store')
                ))->useLogger($c->get('logger')));

                // Display Group Load events
                $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new DisplayGroupMediaListener(
                    $c->get('mediaFactory')
                )));

                $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new DisplayGroupLayoutListener(
                    $c->get('layoutFactory')
                )));

                $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new DisplayGroupDisplayListener(
                    $c->get('displayFactory')
                )));

                $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new DisplayGroupScheduleListener(
                    $c->get('scheduleFactory')
                )));

                // Media full load events
                $dispatcher->addListener(MediaFullLoadEvent::$NAME, (new DisplayGroupListener(
                    $c->get('displayGroupFactory')
                )));

                $dispatcher->addListener(MediaFullLoadEvent::$NAME, (new LayoutListener(
                    $c->get('layoutFactory')
                )));

                $dispatcher->addListener(MediaFullLoadEvent::$NAME, (new WidgetListener(
                    $c->get('widgetFactory')
                )));

                $dispatcher->addListener(LayoutOwnerChangeEvent::$NAME, new OnLayoutOwnerChange(
                    $c->get('layoutFactory')
                ));

                return $dispatcher;
            },
        ];
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    private function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}