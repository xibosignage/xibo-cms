<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Xmr.php)
 */


namespace Xibo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Xibo\Exception\XiboException;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\PlayerActionService;

/**
 * Class Xmr
 * @package Xibo\Middleware
 */
class Xmr implements Middleware
{
    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Call
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;

        self::setXmr($app);

        // Finish
        self::finish($app);

        return $handler->handle($request);
    }

    /**
     * Finish XMR
     * @param App $app
     */
    public static function finish($app)
    {
        $container = $app->getContainer();
        // Handle display notifications
        if ($container->get('displayNotifyService') != null) {
            try {
                $container->get('displayNotifyService')->processQueue();
            } catch (XiboException $e) {
                $container->get('logService')->error('Unable to Process Queue of Display Notifications due to %s', $e->getMessage());
            }
        }

        // Handle player actions
        if ($container->get('playerActionService') != null) {
            try {
                $container->get('playerActionService')->processQueue();
            } catch (\Exception $e) {
                $container->get('logService')->error('Unable to Process Queue of Player actions due to %s', $e->getMessage());
            }
        }
    }

    /**
     * Set XMR
     * @param App $app
     * @param bool $triggerPlayerActions
     */
    public static function setXmr($app, $triggerPlayerActions = true)
    {
        // Player Action Helper
        $app->getContainer()->set('playerActionService', function() use ($app, $triggerPlayerActions) {
            return new PlayerActionService($app->getContainer()->get('configService'), $app->getContainer()->get('logService'), $triggerPlayerActions);
        });

        // Register the display notify service
        $app->getContainer()->set('displayNotifyService', function () use ($app) {
            return new DisplayNotifyService(
                $app->getContainer()->get('configService'),
                $app->getContainer()->get('logService'),
                $app->getContainer()->get('store'),
                $app->getContainer()->get('pool'),
                $app->getContainer()->get('playerActionService'),
                $app->getContainer()->get('dateService'),
                $app->getContainer()->get('scheduleFactory'),
                $app->getContainer()->get('dayPartFactory')
            );
        });
    }
}