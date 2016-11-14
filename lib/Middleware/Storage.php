<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiStorage.php) is part of Xibo.
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


use Slim\Helper\Set;
use Slim\Middleware;
use Xibo\Service\LogService;
use Xibo\Storage\PdoStorageService;

/**
 * Class Storage
 * @package Xibo\Middleware
 */
class Storage extends Middleware
{
    public function call()
    {
        $app = $this->app;

        $app->startTime = microtime();
        $app->commit = true;

        // Configure storage
        self::setStorage($app->container);

        $this->next->call();

        // Are we in a transaction coming out of the stack?
        if ($app->store->getConnection()->inTransaction()) {
            // We need to commit or rollback? Default is commit
            if ($app->commit) {
                $app->store->commitIfNecessary();
            } else {

                $app->logService->debug('Storage rollback.');

                $app->store->getConnection()->rollBack();
            }
        }

        // Get the stats for this connection
        $stats = $app->store->stats();
        $stats['length'] = microtime() - $app->startTime;

        $app->logService->info('Request stats: %s.', json_encode($stats, JSON_PRETTY_PRINT));

        $app->store->close();
    }

    /**
     * Set Storage
     * @param Set $container
     */
    public static function setStorage($container)
    {
        // Register the log service
        $container->singleton('logService', function($container) {
            return new LogService($container->log, $container->mode);
        });

        // Register the database service
        $container->singleton('store', function($container) {
            return (new PdoStorageService($container->logService))->setConnection();
        });
    }
}