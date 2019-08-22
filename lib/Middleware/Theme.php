<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Theme.php)
 */


namespace Xibo\Middleware;


use Slim\Middleware;
use Slim\Slim;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Environment;
use Xibo\Helper\Translate;

/**
 * Class Theme
 * @package Xibo\Middleware
 */
class Theme extends Middleware
{
    public function call()
    {
        // Inject our Theme into the Twig View (if it exists)
        $app = $this->getApplication();

        $app->configService->loadTheme();

        $app->hook('slim.before.dispatch', function() use($app) {
            self::setTheme($app);
        });

        // Call Next
        $this->next->call();
    }

    /**
     * Set theme
     * @param Slim $app
     * @throws \Twig_Error_Loader
     */
    public static function setTheme($app)
    {
        // Provide the view path to Twig
        $twig = $app->view()->getInstance()->getLoader();
        /* @var \Twig_Loader_Filesystem $twig */

        // Append the module view paths
        $twig->setPaths(array_merge($app->moduleFactory->getViewPaths(), [PROJECT_ROOT . '/views', PROJECT_ROOT . '/custom', PROJECT_ROOT . '/reports']));

        // Does this theme provide an alternative view path?
        if ($app->configService->getThemeConfig('view_path') != '') {
            $twig->prependPath(str_replace_first('..', PROJECT_ROOT, $app->configService->getThemeConfig('view_path')));
        }

        $settings = $app->configService->getSettings();

        // Date format
        $settings['DATE_FORMAT_JS'] = $app->dateService->convertPhpToMomentFormat($settings['DATE_FORMAT']);
        $settings['DATE_FORMAT_BOOTSTRAP'] = $app->dateService->convertPhpToBootstrapFormat($settings['DATE_FORMAT']);
        $settings['DATE_FORMAT_BOOTSTRAP_DATEONLY'] = $app->dateService->convertPhpToBootstrapFormat($settings['DATE_FORMAT'], false);
        $settings['TIME_FORMAT'] = $app->dateService->extractTimeFormat($settings['DATE_FORMAT']);
        $settings['TIME_FORMAT_JS'] = $app->dateService->convertPhpToMomentFormat($settings['TIME_FORMAT']);
        $settings['systemDateFormat'] = $app->dateService->convertPhpToMomentFormat($app->dateService->getSystemFormat());
        $settings['systemTimeFormat'] = $app->dateService->convertPhpToMomentFormat($app->dateService->extractTimeFormat($app->dateService->getSystemFormat()));

        // Resolve the current route name
        $routeName = ($app->router()->getCurrentRoute() == null) ? 'notfound' : $app->router()->getCurrentRoute()->getName();

        $app->view()->appendData(array(
            'baseUrl' => $app->urlFor('home'),
            'logoutUrl' => $app->urlFor((empty($app->logoutRoute)) ? 'logout' : $app->logoutRoute),
            'route' => $routeName,
            'theme' => $app->configService,
            'settings' => $settings,
            'helpService' => $app->helpService,
            'translate' => [
                'locale' => Translate::GetLocale(),
                'jsLocale' => Translate::getRequestedJsLocale(),
                'jsShortLocale' => Translate::getRequestedJsLocale(['short' => true])
            ],
            'translations' => '{}',
            'libraryUpload' => [
                'maxSize' => ByteFormatter::toBytes(Environment::getMaxUploadSize()),
                'maxSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), Environment::getMaxUploadSize()),
                'validExt' => implode('|', $app->moduleFactory->getValidExtensions()),
                'validImageExt' => implode('|', $app->moduleFactory->getValidExtensions(['type' => 'image']))
            ],
            'ckeditorConfig' => $app->container->get('\Xibo\Controller\Library')->setApp($app, false)->fontCKEditorConfig(),
            'version' => Environment::$WEBSITE_VERSION_NAME
        ));
    }
}