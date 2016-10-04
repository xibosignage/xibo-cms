<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (run.php)
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
 *
 * Xibo Task Runner
 * (xtr.php)
 *
 * This must be called once per minute
 */
define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

// Set up error exceptions
function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}
set_error_handler("exception_error_handler");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

$config = \Xibo\Service\ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

// Set up logging to file
$log = new \Monolog\Logger('importer');
$log->pushHandler(new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Logger::DEBUG));

$log->debug('XTR started');

// Create a DB connection
$db = (new \Xibo\Storage\PdoStorageService())->setConnection();

// Query for a list of tasks to run.
$tasks = $db->select('SELECT taskId, schedule, runNow FROM `task` WHERE isActive = 1 AND status <> :status', [
    'status' => \Xibo\Entity\Task::$STATUS_RUNNING
]);

$log->debug('Found ' . count($tasks) . ' to analyse.');

foreach ($tasks as $task) {
    /** @var \Xibo\Entity\Task $task */
    $cron = \Cron\CronExpression::factory($task['schedule']);

    if ($task['runNow'] == 1 || $cron->isDue()) {
        $log->debug($task['taskId'] . ' due');

        exec('nohup php bin/run.php ' . $task['taskId'] . ' > /dev/null & > /dev/null');

    } else {
        $log->debug($task['taskId'] . ' not due');
    }
}

// Finish - children are still running
$log->debug('Exiting');
