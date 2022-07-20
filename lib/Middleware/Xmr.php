<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\NullDisplayNotifyService;
use Xibo\Service\PlayerActionService;
use Xibo\Support\Exception\GeneralException;

/**
 * Class Xmr
 * @package Xibo\Middleware
 *
 * NOTE: This must be the very last layer in the onion
 */
class Xmr implements Middleware
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
     * Call
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;

        // Start
        self::setXmr($app);

        // Pass along the request
        $response = $handler->handle($request);

        // Finish
        // this must happen at the very end of the request
        self::finish($app);

        // Return the response to the browser
        return $response;
    }

    /**
     * Finish XMR
     * @param App $app
     */
    public static function finish($app)
    {
        $container = $app->getContainer();

        // Handle display notifications
        if ($container->has('displayNotifyService')) {
            try {
                $container->get('displayNotifyService')->processQueue();
            } catch (GeneralException $e) {
                $container->get('logService')->error(
                    'Unable to Process Queue of Display Notifications due to %s',
                    $e->getMessage()
                );
            }
        }

        // Handle player actions
        if ($container->has('playerActionService')) {
            try {
                $container->get('playerActionService')->processQueue();
            } catch (\Exception $e) {
                $container->get('logService')->error(
                    'Unable to Process Queue of Player actions due to %s',
                    $e->getMessage()
                );
            }
        }

        // Re-terminate any DB connections
        $app->getContainer()->get('store')->close();
    }

    /**
     * Set XMR
     * @param App $app
     * @param bool $triggerPlayerActions
     */
    public static function setXmr($app, $triggerPlayerActions = true)
    {
        // Player Action Helper
        $app->getContainer()->set('playerActionService', function () use ($app, $triggerPlayerActions) {
            return new PlayerActionService(
                $app->getContainer()->get('configService'),
                $app->getContainer()->get('logService'),
                $triggerPlayerActions
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
