<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (TestXmr.php)
 */


namespace Xibo\Tests\Middleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Xibo\Support\Exception\GeneralException;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\PlayerActionService;
use Xibo\Tests\Helper\MockPlayerActionService;

/**
 * Class TestXmr
 * @package Xibo\Tests\Middleware
 */
class TestXmr implements Middleware
{
    /* @var App $app */
    private $app;

    /**
     * Xmr constructor.
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Process
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;

        self::setXmr($app);

        // Pass along the request
        $response = $handler->handle($request);

        // Handle display notifications
        if ($app->getContainer()->get('displayNotifyService') != null) {
            try {
                $app->getContainer()->get('displayNotifyService')->processQueue();
            } catch (GeneralException $e) {
                $app->getContainer()->get('logger')->error('Unable to Process Queue of Display Notifications due to %s', $e->getMessage());
            }
        }

        // Re-terminate any DB connections
        $app->getContainer()->get('store')->close();

        return $response;
    }

    /**
     * Set XMR
     * @param \Slim\App $app
     * @param bool $triggerPlayerActions
     */
    public static function setXmr($app, $triggerPlayerActions = true)
    {
        // Player Action Helper
        $app->getContainer()->set('playerActionService', function() use ($app, $triggerPlayerActions) {
            return new MockPlayerActionService(
                $app->getContainer()->get('configService'),
                $app->getContainer()->get('logService'),
                false
            );
        });

        // Register the display notify service
        $app->getContainer()->set('displayNotifyService', function () use ($app) {
            return new DisplayNotifyService(
                $app->getContainer()->get('configService'),
                $app->getContainer()->get('logService'),
                $app->getContainer()->get('store'),
                $app->getContainer()->get('pool'),
                $app->getContainer()->get('playerActionService'),
                $app->getContainer()->get('scheduleFactory')
            );
        });
    }
}