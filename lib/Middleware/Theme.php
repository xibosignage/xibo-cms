<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
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
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Inject our Theme into the Twig View (if it exists)
        $app = $this->app;
        $app->getContainer()->get('configService')->loadTheme();

        self::setTheme($app->getContainer(), $request, $app->getRouteCollector()->getRouteParser());

        return $handler->handle($request);
    }

    /**
     * Set theme
     * @param \Psr\Container\ContainerInterface $container
     * @param Request $request
     * @param RouteParserInterface $routeParser
     * @throws \Twig\Error\LoaderError
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public static function setTheme(ContainerInterface $container, Request $request, RouteParserInterface $routeParser)
    {
        $view = $container->get('view');

        // Provide the view path to Twig
        $twig = $view->getLoader();
        /* @var \Twig\Loader\FilesystemLoader $twig */

        // Does this theme provide an alternative view path?
        if ($container->get('configService')->getThemeConfig('view_path') != '') {
            $twig->prependPath(
                Str::replaceFirst(
                    '..',
                    PROJECT_ROOT,
                    $container->get('configService')->getThemeConfig('view_path')
                )
            );
        }

        $settings =  $container->get('configService')->getSettings();

        // Date format
        $settings['DATE_FORMAT_JS'] = DateFormatHelper::convertPhpToMomentFormat($settings['DATE_FORMAT']);
        $settings['DATE_FORMAT_JALALI_JS'] = DateFormatHelper::convertMomentToJalaliFormat($settings['DATE_FORMAT_JS']);
        $settings['TIME_FORMAT'] = DateFormatHelper::extractTimeFormat($settings['DATE_FORMAT']);
        $settings['TIME_FORMAT_JS'] = DateFormatHelper::convertPhpToMomentFormat($settings['TIME_FORMAT']);
        $settings['DATE_ONLY_FORMAT'] = DateFormatHelper::extractDateOnlyFormat($settings['DATE_FORMAT']);
        $settings['DATE_ONLY_FORMAT_JS'] = DateFormatHelper::convertPhpToMomentFormat($settings['DATE_ONLY_FORMAT']);
        $settings['DATE_ONLY_FORMAT_JALALI_JS'] = DateFormatHelper::convertMomentToJalaliFormat(
            $settings['DATE_ONLY_FORMAT_JS']
        );
        $settings['systemDateFormat'] = DateFormatHelper::convertPhpToMomentFormat(DateFormatHelper::getSystemFormat());
        $settings['systemTimeFormat'] = DateFormatHelper::convertPhpToMomentFormat(
            DateFormatHelper::extractTimeFormat(DateFormatHelper::getSystemFormat())
        );

        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // Resolve the current route name
        $routeName = ($route == null) ? 'notfound' : $route->getName();
        $view['baseUrl'] = $routeParser->urlFor('home');

        try {
            $logoutRoute = empty($container->get('logoutRoute')) ? 'logout' : $container->get('logoutRoute');
            $view['logoutUrl'] = $routeParser->urlFor($logoutRoute);
        } catch (\Exception $e) {
            $view['logoutUrl'] = $routeParser->urlFor('logout');
        }

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
            'maxSizeMessage' => sprintf(
                __('This form accepts files up to a maximum size of %s'),
                Environment::getMaxUploadSize()
            ),
            'validExt' => implode('|', $container->get('moduleFactory')->getValidExtensions()),
            'validImageExt' => implode('|', $container->get('moduleFactory')->getValidExtensions(['type' => 'image']))
        ];
        $view['version'] = Environment::$WEBSITE_VERSION_NAME;
        $view['revision'] = Environment::getGitCommit();
        $view['playerVersion'] = Environment::$PLAYER_SUPPORT;
        $view['isDevMode'] = Environment::isDevMode();
        $view['accountId'] = defined('ACCOUNT_ID') ? constant('ACCOUNT_ID') : null;

        $samlSettings = $container->get('configService')->samlSettings;
        if (isset($samlSettings['workflow'])
            && isset($samlSettings['workflow']['slo'])
            && $samlSettings['workflow']['slo'] == false) {
            $view['hideLogout'] = true;
        }
    }
}
