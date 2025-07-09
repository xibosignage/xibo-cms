<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Respect\Validation\Factory;
use Slim\App;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Xibo\Entity\User;
use Xibo\Helper\Environment;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\NullSession;
use Xibo\Helper\Session;
use Xibo\Helper\Translate;
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

        // Get to see if upgrade is pending, we don't want to throw this when we are on error page, causes
        // redirect problems with error handler.
        if (Environment::migrationPending() && $request->getUri()->getPath() != '/error') {
            throw new UpgradePendingException();
        }

        // Next middleware
        $response = $handler->handle($request);

        // Do we need SSL/STS?
        if (HttpsDetect::isShouldIssueSts($container->get('configService'), $request)) {
            $response = HttpsDetect::decorateWithSts($container->get('configService'), $response);
        } else if (!HttpsDetect::isHttps()) {
            // We are not HTTPS, should we redirect?
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
        $container->set('reportService', function (ContainerInterface $container) {
            $reportService = new ReportService(
                $container,
                $container->get('store'),
                $container->get('timeSeriesStore'),
                $container->get('logService'),
                $container->get('configService'),
                $container->get('sanitizerService'),
                $container->get('savedReportFactory')
            );
            $reportService->setDispatcher($container->get('dispatcher'));
            return $reportService;
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

        // Set Carbon locale
        Carbon::setLocale(Translate::GetLocale(2));

        // Default timezone
        date_default_timezone_set($container->get('configService')->getSetting('defaultTimezone'));

        $container->set('session', function (ContainerInterface $container) use ($app) {
            if ($container->get('name') == 'web' || $container->get('name') == 'auth') {
                $sessionHandler = new Session($container->get('logService'));

                session_set_save_handler($sessionHandler, true);
                register_shutdown_function('session_write_close');

                // Start the session
                session_cache_limiter(false);
                session_start();
                return $sessionHandler;
            } else {
                return new NullSession();
            }
        });

        // We use Slim Flash Messages so we must immediately start a session (boo)
        $container->get('session')->set('init', '1');

        // App Mode
        $mode = $container->get('configService')->getSetting('SERVER_MODE');
        $container->get('logService')->setMode($mode);

        // Inject some additional changes on a per-container basis
        $containerName = $container->get('name');
        if ($containerName == 'web' || $containerName == 'xtr' || $containerName == 'xmds') {
            /** @var Twig $view */
            $view = $container->get('view');

            if ($containerName == 'web') {
                $container->set('flash', function () {
                    return new \Slim\Flash\Messages();
                });
                $view->addExtension(new TwigMessages(new \Slim\Flash\Messages()));
            }

            $twigEnvironment = $view->getEnvironment();

            // add the urldecode filter to Twig.
            $filter = new \Twig\TwigFilter('url_decode', 'urldecode');
            $twigEnvironment->addFilter($filter);

            // Set Twig auto reload if needed
            // XMDS only renders widget html cache, and shouldn't need auto reload.
            if ($containerName !== 'xmds') {
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

        // Add additional validation rules
        Factory::setDefaultInstance(
            (new Factory())
                ->withRuleNamespace('Xibo\\Validation\\Rules')
                ->withExceptionNamespace('Xibo\\Validation\\Exceptions')
        );

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
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    private function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}
