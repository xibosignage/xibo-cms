<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Theme.php)
 */


namespace Xibo\Middleware;


use Slim\Middleware;
use Xibo\Helper\Log;

class Theme extends Middleware
{
    public function call()
    {
        // Configure the Theme
        \Xibo\Helper\Theme::getInstance();

        // Inject our Theme into the Twig View (if it exists)
        $app = $this->getApplication();

        // Does this theme provide an alternative view path?
        if (\Xibo\Helper\Theme::getConfig('view_path') != '') {
            // Provide the view path to Twig
            $twig = $app->view()->getInstance()->getLoader();
            /* @var \Twig_Loader_Filesystem $twig */
            $twig->prependPath(\Xibo\Helper\Theme::getConfig('view_path'));
        }

        // Configure any extra log handlers
        $logHandlers = \Xibo\Helper\Theme::getConfig('logHandlers');
        if ($logHandlers != null && is_array($logHandlers)) {
            Log::debug('Configuring %d additional log handlers from Theme', count($logHandlers));
            foreach ($logHandlers as $handler) {
                $app->logWriter->addHandler($handler);
            }
        }

        // Configure any extra log processors
        $logProcessors = \Xibo\Helper\Theme::getConfig('logProcessors');
        if ($logProcessors != null && is_array($logProcessors)) {
            Log::debug('Configuring %d additional log processors from Theme', count($logProcessors));
            foreach ($logProcessors as $processor) {
                $app->logWriter->addProcessor($processor);
            }
        }

        // Call Next
        $this->next->call();
    }
}