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


use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Environment;
use Xibo\Helper\Translate;

/**
 * Class Theme
 * @package Xibo\Middleware
 */
class Theme implements Middleware
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
     * @throws \Twig\Error\LoaderError
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Inject our Theme into the Twig View (if it exists)
        $app = $this->app;
        $app->getContainer()->get('configService')->loadTheme();

        self::setTheme($app, $request);

        return $handler->handle($request);
    }

    /**
     * Set theme
     * @param App $app
     * @param Request $request
     * @throws \Twig\Error\LoaderError
     */
    public static function setTheme(App $app, Request $request)
    {
        $container = $app->getContainer();
        $view = $container->get('view');
        // Provide the view path to Twig
        $twig = $view->getLoader();
        /* @var \Twig\Loader\FilesystemLoader $twig */

        // Append the module view paths
        $twig->setPaths(array_merge($container->get('moduleFactory')->getViewPaths(), [PROJECT_ROOT . '/views', PROJECT_ROOT . '/custom', PROJECT_ROOT . '/reports']));

        // Does this theme provide an alternative view path?
        if ($container->get('configService')->getThemeConfig('view_path') != '') {
            $twig->prependPath(Str::replaceFirst('..', PROJECT_ROOT, $container->get('configService')->getThemeConfig('view_path')));
        }

        $settings =  $container->get('configService')->getSettings();

        // Date format
        $settings['DATE_FORMAT_JS'] = $container->get('dateService')->convertPhpToMomentFormat($settings['DATE_FORMAT']);
        $settings['DATE_FORMAT_BOOTSTRAP'] = $container->get('dateService')->convertPhpToBootstrapFormat($settings['DATE_FORMAT']);
        $settings['DATE_FORMAT_BOOTSTRAP_DATEONLY'] = $container->get('dateService')->convertPhpToBootstrapFormat($settings['DATE_FORMAT'], false);
        $settings['TIME_FORMAT'] = $container->get('dateService')->extractTimeFormat($settings['DATE_FORMAT']);
        $settings['TIME_FORMAT_JS'] = $container->get('dateService')->convertPhpToMomentFormat($settings['TIME_FORMAT']);
        $settings['systemDateFormat'] = $container->get('dateService')->convertPhpToMomentFormat($container->get('dateService')->getSystemFormat());
        $settings['systemTimeFormat'] = $container->get('dateService')->convertPhpToMomentFormat($container->get('dateService')->extractTimeFormat($container->get('dateService')->getSystemFormat()));

        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $routeParser = $app->getRouteCollector()->getRouteParser();
        // Resolve the current route name
        $routeName = ($route == null) ? 'notfound' : $route->getName();
        $view['baseUrl'] = $routeParser->urlFor('home');
        $view['logoutUrl'] = $routeParser->urlFor((empty($app->logoutRoute)) ? 'logout' : $app->logoutRoute);
        $view['route'] = $routeName;
        $view['theme'] = $container->get('configService');
        $view['settings'] = $settings;
        $view['helpService'] = $container->get('helpService');
        $view['translate'] = [
            'locale' => Translate::GetLocale(),
            'jsLocale' => Translate::getRequestedJsLocale(),
            'jsShortLocale' => Translate::getRequestedJsLocale(['short' => true])
        ];
        $view['translations'] ='{}';
        $view['libraryUpload'] = [
            'maxSize' => ByteFormatter::toBytes(Environment::getMaxUploadSize()),
            'maxSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), Environment::getMaxUploadSize()),
            'validExt' => implode('|', $container->get('moduleFactory')->getValidExtensions()),
            'validImageExt' => implode('|', $container->get('moduleFactory')->getValidExtensions(['type' => 'image']))
        ];
        $view['ckeditorConfig'] = $container->get('\Xibo\Controller\Library')->fontCKEditorConfig($request);
        $view['version'] = Environment::$WEBSITE_VERSION_NAME;


        /*
        $container->get('view')->appendData(array(
            'baseUrl' => $routeParser->urlFor('home'),
            'logoutUrl' => $routeParser->urlFor((empty($app->logoutRoute)) ? 'logout' : $app->logoutRoute),
            'route' => $routeName,
            'theme' => $container->get('configService'),
            'settings' => $settings,
            'helpService' => $container->get('helpService'),
            'translate' => [
                'locale' => Translate::GetLocale(),
                'jsLocale' => Translate::getRequestedJsLocale(),
                'jsShortLocale' => Translate::getRequestedJsLocale(['short' => true])
            ],
            'translations' => '{}',
            'libraryUpload' => [
                'maxSize' => ByteFormatter::toBytes(Environment::getMaxUploadSize()),
                'maxSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), Environment::getMaxUploadSize()),
                'validExt' => implode('|', $container->get('moduleFactory')->getValidExtensions()),
                'validImageExt' => implode('|', $container->get('moduleFactory')->getValidExtensions(['type' => 'image']))
            ],
            'ckeditorConfig' => $container->get('\Xibo\Controller\Library')->setApp($app, false)->fontCKEditorConfig(),
            'version' => Environment::$WEBSITE_VERSION_NAME
        ));
        */
    }
}