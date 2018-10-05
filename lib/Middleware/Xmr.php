<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Xmr.php)
 */


namespace Xibo\Middleware;

use Slim\Middleware;
use Slim\Slim;
use Xibo\Exception\XiboException;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\PlayerActionService;

/**
 * Class Xmr
 * @package Xibo\Middleware
 */
class Xmr extends Middleware
{
    /**
     * Call
     */
    public function call()
    {
        $app = $this->getApplication();

        $app->hook('slim.before', function() {

            $app = $this->app;

            self::setXmr($app);
        });

        $this->next->call();

        // Finish
        self::finish($app);
    }

    /**
     * Finish XMR
     * @param Slim $app
     */
    public static function finish($app)
    {
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

    /**
     * Set XMR
     * @param \Slim\Slim $app
     * @param bool $triggerPlayerActions
     */
    public static function setXmr($app, $triggerPlayerActions = true)
    {
        // Player Action Helper
        $app->container->singleton('playerActionService', function() use ($app, $triggerPlayerActions) {
            return new PlayerActionService($app->configService, $app->logService, $triggerPlayerActions);
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
    }
}