<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
use Slim\App;

/**
 * Class Storage
 * @package Xibo\Middleware
 */
class Storage implements Middleware
{
    /* @var App $app */
    private $app;

    /**
     * Storage constructor.
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Middleware process
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $container = $this->app->getContainer();

        $startTime = microtime(true);

        // Pass straight down to the next middleware
        $response = $handler->handle($request);

        // Are we in a transaction coming out of the stack?
        if ($container->get('store')->getConnection()->inTransaction()) {
            // We need to commit or rollback? Default is commit
            if ($container->get('state')->getCommitState()) {
                $container->get('store')->commitIfNecessary();
            } else {
                $container->get('logService')->debug('Storage rollback.');

                $container->get('store')->getConnection()->rollBack();
            }
        }

        // Get the stats for this connection
        $stats = $container->get('store')->stats();
        $stats['length'] = microtime(true) - $startTime;
        $stats['memoryUsage'] = memory_get_usage();
        $stats['peakMemoryUsage'] = memory_get_peak_usage();

        $container->get('logService')->info('Request stats: %s.', json_encode($stats, JSON_PRETTY_PRINT));

        $container->get('store')->close();

        return $response;
    }
}