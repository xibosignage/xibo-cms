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
        $twig->setPaths(array_merge($app->moduleFactory->getViewPaths(), [PROJECT_ROOT . '/views']));

        // Does this theme provide an alternative view path?
        if ($app->configService->getThemeConfig('view_path') != '') {
            $twig->prependPath($app->configService->getThemeConfig('view_path'));
        }

        $settings = [];
        foreach ($app->settingsFactory->query() as $setting) {
            $settings[$setting['setting']] = $setting['value'];
        }

        // Date format
        $settings['DATE_FORMAT_JS'] = $app->dateService->convertPhpToMomentFormat($settings['DATE_FORMAT']);
        $settings['DATE_FORMAT_BOOTSTRAP'] = $app->dateService->convertPhpToBootstrapFormat($settings['DATE_FORMAT']);

        // Resolve the current route name
        $routeName = ($app->router()->getCurrentRoute() == null) ? 'notfound' : $app->router()->getCurrentRoute()->getName();

        $app->view()->appendData(array(
            'baseUrl' => $app->urlFor('home'),
            'route' => $routeName,
            'theme' => $app->configService,
            'settings' => $settings,
            'translate' => [
                'locale' => Translate::GetLocale(),
                'jsLocale' => Translate::GetJsLocale(),
                'jsShortLocale' => ((strlen(Translate::GetJsLocale()) > 2) ? substr(Translate::GetJsLocale(), 0, 2) : Translate::GetJsLocale()),
                'calendarLanguage' => ((strlen(Translate::GetJsLocale()) <= 2) ? Translate::GetJsLocale() . '-' . strtoupper(Translate::GetJsLocale()) : Translate::GetJsLocale())
            ],
            'translations' => '{}',
            'libraryUpload' => [
                'maxSize' => ByteFormatter::toBytes($app->configService->getMaxUploadSize()),
                'maxSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), $app->configService->getMaxUploadSize()),
                'validExt' => implode('|', $app->moduleFactory->getValidExtensions())
            ],
            'ckeditorConfig' => $app->container->get('\Xibo\Controller\Library')->setApp($app, false)->fontCKEditorConfig()
        ));
    }
}