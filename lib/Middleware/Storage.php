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


use Slim\Middleware;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class Storage extends Middleware
{
    public function call()
    {
        $this->app->commit = true;

        if (!PDOConnect::init()->inTransaction())
            PDOConnect::init()->beginTransaction();

        $this->next->call();

        //Log::debug('Commit Required? %d', $this->app->commit);
        if ($this->app->commit) {
            PDOConnect::init()->commit();
        }
        else {
            if (PDOConnect::init()->inTransaction()) {
                Log::debug('Storage rollback.');
                PDOConnect::init()->rollBack();
            }
        }

        PDOConnect::close();
    }
}