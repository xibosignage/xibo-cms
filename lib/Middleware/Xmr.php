<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Xmr.php)
 */


namespace Xibo\Middleware;


use Slim\Middleware;
use Xibo\Service\PlayerActionService;

/**
 * Class Xmr
 * @package Xibo\Middleware
 */
class Xmr extends Middleware
{
    public function call()
    {
        $app = $this->getApplication();

        $app->hook('slim.before', function() {

            $app = $this->app;

            // Player Action Helper
            $app->container->singleton('playerActionService', function() use ($app) {
                return new PlayerActionService($app->configService, $app->logService);
            });
        });

        $this->next->call();

        // Handle player actions
        if ($app->playerActionService != null) {
            try {
                $app->playerActionService->processQueue();
            } catch (\Exception $e) {
                $app->logService->error('Unable to Process Queue of Player actions due to %s', $e->getMessage());
            }
        }
    }
}