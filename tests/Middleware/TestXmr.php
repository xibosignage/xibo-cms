<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (TestXmr.php)
 */


namespace Xibo\Tests\Middleware;

use Slim\Middleware;
use Xibo\Exception\XiboException;
use Xibo\Service\DisplayNotifyService;
use Xibo\Tests\Helper\MockPlayerActionService;

/**
 * Class TestXmr
 * @package Xibo\Tests\Middleware
 */
class TestXmr extends Middleware
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

        // Handle display notifications
        if ($app->displayNotifyService != null) {
            try {
                $app->displayNotifyService->processQueue();
            } catch (XiboException $e) {
                $app->logService->error('Unable to Process Queue of Display Notifications due to %s', $e->getMessage());
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
            return new MockPlayerActionService(
                $app->configService,
                $app->logService,
                false);
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