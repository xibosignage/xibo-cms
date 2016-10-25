<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Xmr.php)
 */


namespace Xibo\Middleware;


use Slim\Middleware;
use Xibo\Exception\XiboException;
use Xibo\Service\DisplayNotifyService;
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

            // Register the display notify service
            $app->container->singleton('displayNotifyService', function () use ($app) {
                return new DisplayNotifyService(
                    $app->configService,
                    $app->logService,
                    $app->store,
                    $app->pool,
                    $app->playerActionService,
                    $app->dateService,
                    $app->scheduleFactory,
                    $app->dayPartFactory
                );
            });
        });

        $this->next->call();

        // Handle display notifications
        if ($app->displayNotifyService != null) {
            try {
                $app->displayNotifyService->processQueue();
            } catch (XiboException $e) {
                $app->logService->error('Unable to Process Queue of Display Notifications due to %s', $e->getMessage());
            }
        }

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