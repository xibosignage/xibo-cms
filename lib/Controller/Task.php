<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Controller;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\TaskFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\XTR\TaskInterface;

/**
 * Class Task
 * @package Xibo\Controller
 */
class Task extends Base
{
    /** @var  TaskFactory */
    private $taskFactory;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  TimeSeriesStoreInterface */
    private $timeSeriesStore;

    /** @var  PoolInterface */
    private $pool;

    /** ContainerInterface */
    private $container;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param PoolInterface $pool
     * @param TaskFactory $taskFactory
     * @param ContainerInterface $container
     */
    public function __construct($store, $timeSeriesStore, $pool, $taskFactory, ContainerInterface $container)
    {
        $this->taskFactory = $taskFactory;
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->pool = $pool;
        $this->container = $container;
    }

    /**
     * Display Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'task-page';

        return $this->render($request, $response);
    }

    /**
     * Grid
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $tasks = $this->taskFactory->query(
            $this->gridRenderSort($sanitizedParams),
            $this->gridRenderFilter([], $sanitizedParams)
        );

        foreach ($tasks as $task) {
            /** @var \Xibo\Entity\Task $task */

            $task->setUnmatchedProperty('nextRunDt', $task->nextRunDate());

            if ($this->isApi($request)) {
                continue;
            }

            $task->includeProperty('buttons');

            $task->buttons[] = array(
                'id' => 'task_button_run.now',
                'url' => $this->urlFor($request, 'task.runNow.form', ['id' => $task->taskId]),
                'text' => __('Run Now'),
                'dataAttributes' => [
                    ['name' => 'auto-submit', 'value' => true],
                    [
                        'name' => 'commit-url',
                        'value' => $this->urlFor($request, 'task.runNow', ['id' => $task->taskId]),
                    ],
                    ['name' => 'commit-method', 'value' => 'POST']
                ]
            );

            // Don't show any edit buttons if the config is locked.
            if ($this->getConfig()->getSetting('TASK_CONFIG_LOCKED_CHECKB') == 1
                || $this->getConfig()->getSetting('TASK_CONFIG_LOCKED_CHECKB') == 'Checked'
            ) {
                continue;
            }

            // Edit Button
            $task->buttons[] = array(
                'id' => 'task_button_edit',
                'url' => $this->urlFor($request, 'task.edit.form', ['id' => $task->taskId]),
                'text' => __('Edit')
            );

            // Delete Button
            $task->buttons[] = array(
                'id' => 'task_button_delete',
                'url' => $this->urlFor($request, 'task.delete.form', ['id' => $task->taskId]),
                'text' => __('Delete')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->taskFactory->countLast();
        $this->getState()->setData($tasks);

        return $this->render($request, $response);
    }

    /**
     * Add form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addForm(Request $request, Response $response)
    {
        // Provide a list of possible task classes by searching for .task file in /tasks and /custom
        $data = ['tasksAvailable' => []];

        // Do we have any modules to install?!
        if ($this->getConfig()->getSetting('TASK_CONFIG_LOCKED_CHECKB') != 1 && $this->getConfig()->getSetting('TASK_CONFIG_LOCKED_CHECKB') != 'Checked') {
            // Get a list of matching files in the modules folder
            $files = array_merge(glob(PROJECT_ROOT . '/tasks/*.task'), glob(PROJECT_ROOT . '/custom/*.task'));

            // Add to the list of available tasks
            foreach ($files as $file) {
                $config = json_decode(file_get_contents($file));
                $config->file = Str::replaceFirst(PROJECT_ROOT, '', $file);

                $data['tasksAvailable'][] = $config;
            }
        }

        $this->getState()->template = 'task-form-add';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Add
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $task = $this->taskFactory->create();
        $task->name = $sanitizedParams->getString('name');
        $task->configFile = $sanitizedParams->getString('file');
        $task->schedule = $sanitizedParams->getString('schedule');
        $task->status = \Xibo\Entity\Task::$STATUS_IDLE;
        $task->lastRunStatus = 0;
        $task->isActive = 0;
        $task->runNow = 0;
        $task->setClassAndOptions();
        $task->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $task->name),
            'id' => $task->taskId,
            'data' => $task
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $task = $this->taskFactory->getById($id);
        $task->setClassAndOptions();

        $this->getState()->template = 'task-form-edit';
        $this->getState()->setData([
            'task' => $task
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $task = $this->taskFactory->getById($id);

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $task->setClassAndOptions();
        $task->name = $sanitizedParams->getString('name');
        $task->schedule = $sanitizedParams->getString('schedule');
        $task->isActive = $sanitizedParams->getCheckbox('isActive');

        // Loop through each option and see if a new value is provided
        foreach ($task->options as $option => $value) {
            $provided = $sanitizedParams->getString($option);

            if ($provided !== null) {
                $this->getLog()->debug('Setting ' . $option . ' to ' . $provided);
                $task->options[$option] = $provided;
            }
        }

        $this->getLog()->debug('New options = ' . var_export($task->options, true));

        $task->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $task->name),
            'id' => $task->taskId,
            'data' => $task
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $task = $this->taskFactory->getById($id);

        $this->getState()->template = 'task-form-delete';
        $this->getState()->setData([
            'task' => $task
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function delete(Request $request, Response $response, $id)
    {
        $task = $this->taskFactory->getById($id);
        $task->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $task->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function runNowForm(Request $request, Response $response, $id)
    {
        $task = $this->taskFactory->getById($id);

        $this->getState()->template = 'task-form-run-now';
        $this->getState()->autoSubmit = $this->getAutoSubmit('taskRunNowForm');
        $this->getState()->setData([
            'task' => $task
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function runNow(Request $request, Response $response, $id)
    {
        $task = $this->taskFactory->getById($id);
        $task->runNow = 1;
        $task->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Run Now set on %s'), $task->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function run(Request $request, Response $response, $id)
    {
        // Get this task
        if (is_numeric($id)) {
            $task = $this->taskFactory->getById($id);
        } else {
            $task = $this->taskFactory->getByName($id);
        }

        // Set to running
        $this->getLog()->debug('run: Running Task ' . $task->name
            . ' [' . $task->taskId . '], Class = ' . $task->class);

        // Run
        $task->setStarted();

        try {
            // Instantiate
            if (!class_exists($task->class)) {
                throw new NotFoundException(sprintf(__('Task with class name %s not found'), $task->class));
            }

            /** @var TaskInterface $taskClass */
            $taskClass = new $task->class();

            // Record the start time
            $start = Carbon::now()->format('U');

            $taskClass
                ->setSanitizer($this->getSanitizer($request->getParams()))
                ->setUser($this->getUser())
                ->setConfig($this->getConfig())
                ->setLogger($this->getLog())
                ->setPool($this->pool)
                ->setStore($this->store)
                ->setTimeSeriesStore($this->timeSeriesStore)
                ->setDispatcher($this->getDispatcher())
                ->setFactories($this->container)
                ->setTask($task)
                ->run();

            // We should commit anything this task has done
            $this->store->commitIfNecessary();

            // Collect results
            $task->lastRunDuration = Carbon::now()->format('U') - $start;
            $task->lastRunMessage = $taskClass->getRunMessage();
            $task->lastRunStatus = \Xibo\Entity\Task::$STATUS_SUCCESS;
            $task->lastRunExitCode = 0;
        } catch (\Exception $e) {
            $this->getLog()->error('run: ' . $e->getMessage() . ' Exception Type: ' . get_class($e));
            $this->getLog()->debug($e->getTraceAsString());

            // We should roll back anything we've done so far
            if ($this->store->getConnection()->inTransaction()) {
                $this->store->getConnection()->rollBack();
            }

            // Set the results to error
            $task->lastRunMessage = $e->getMessage();
            $task->lastRunStatus = \Xibo\Entity\Task::$STATUS_ERROR;
            $task->lastRunExitCode = 1;
        }

        $task->lastRunDt = Carbon::now()->format('U');
        $task->runNow = 0;
        $task->status = \Xibo\Entity\Task::$STATUS_IDLE;

        // lastRunMessage columns has a limit of 254 characters, if the message is longer, we need to truncate it.
        if (strlen($task->lastRunMessage) >= 255) {
            $task->lastRunMessage = substr($task->lastRunMessage, 0, 249) . '(...)';
        }

        // Finished
        $task->setFinished();

        $this->getLog()->debug('run: Finished Task ' . $task->name . ' [' . $task->taskId . '] Run Dt: '
            . Carbon::now()->format(DateFormatHelper::getSystemFormat()));

        $this->setNoOutput();

        return $this->render($request, $response);
    }

    /**
     * Poll for tasks to run
     *  continue polling until there aren't anymore to run
     *  allow for multiple polls to run at the same time
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function poll(Request $request, Response $response)
    {
        $this->getLog()->debug('poll: XTR poll started');

        // Process timeouts
        $this->pollProcessTimeouts();

        // Keep track of tasks we've run during this poll period
        // we will use this as a catch-all so that we do not run a task more than once.
        $tasksRun = [];

        // We loop until we have gone through without running a task
        // each loop we are expecting to run ONE task only, to allow for multiple runs of XTR at the
        // same time.
        while (true) {
            // Get tasks that aren't running currently
            // we have to get them all here because we can't calculate the CRON schedule with SQL,
            // therefore we return them all and process one and a time.
            $tasks = $this->store->select('
                SELECT taskId, `schedule`, runNow, lastRunDt
                  FROM `task`
                 WHERE isActive = 1
                   AND `status` <> :status
                ORDER BY lastRunDuration
            ', ['status' => \Xibo\Entity\Task::$STATUS_RUNNING], 'xtr', true);

            // Assume we won't run anything
            $taskRun = false;

            foreach ($tasks as $task) {
                /** @var \Xibo\Entity\Task $task */
                $taskId = $task['taskId'];

                // Skip tasks that have already been run
                if (in_array($taskId, $tasksRun)) {
                    continue;
                }

                try {
                    $cron = new CronExpression($task['schedule']);
                } catch (\Exception $e) {
                    $this->getLog()->info('run: CRON syntax error for taskId  ' . $taskId
                        . ', e: ' . $e->getMessage());

                    // Try and take the first X characters instead.
                    try {
                        $cron = new CronExpression(substr($task['schedule'], 0, strlen($task['schedule']) - 2));
                    } catch (\Exception) {
                        $this->getLog()->error('run: cannot fix CRON syntax error  ' . $taskId);
                        continue;
                    }
                }

                // Is the next run date of this event earlier than now, or is the task set to runNow
                $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $task['lastRunDt']))
                    ->format('U');

                if ($task['runNow'] == 1 || $nextRunDt <= Carbon::now()->format('U')) {
                    $this->getLog()->info('poll: Running Task ' . $taskId);

                    try {
                        // Pass to run.
                        $this->run($request, $response, $taskId);
                    } catch (\Exception $exception) {
                        // The only thing which can fail inside run is core code,
                        // so it is reasonable here to disable the task.
                        $this->getLog()->error('poll: Task run error for taskId ' . $taskId
                            . '. E = ' . $exception->getMessage());
                        $this->getLog()->debug($exception->getTraceAsString());

                        // Set to error and disable.
                        $this->store->update('
                            UPDATE `task` SET status = :status, isActive = :isActive, lastRunMessage = :lastRunMessage 
                             WHERE taskId = :taskId
                        ', [
                            'taskId' => $taskId,
                            'status' => \Xibo\Entity\Task::$STATUS_ERROR,
                            'isActive' => 0,
                            'lastRunMessage' => 'Fatal Error: ' . $exception->getMessage()
                        ], 'xtr', true, false);
                    }

                    // We have run a task
                    $taskRun = true;

                    // We've run this task during this polling period
                    $tasksRun[] = $taskId;

                    // As mentioned above, we only run 1 task at a time to allow for concurrent runs of XTR.
                    break;
                }
            }

            // If we haven't run a task, then stop
            if (!$taskRun) {
                break;
            }
        }

        $this->getLog()->debug('XTR poll stopped');
        $this->setNoOutput();

        return $this->render($request, $response);
    }

    private function pollProcessTimeouts()
    {
        $count = $this->store->update('
            UPDATE `task` SET `status` = :newStatus
               WHERE `isActive` = 1 
                AND `status` = :currentStatus
                AND `lastRunStartDt` < :timeout
        ', [
            'timeout' => Carbon::now()->subHours(12)->format('U'),
            'currentStatus' => \Xibo\Entity\Task::$STATUS_RUNNING,
            'newStatus' => \Xibo\Entity\Task::$STATUS_TIMEOUT,
        ], 'xtr', false, false);

        if ($count > 0) {
            $this->getLog()->error($count . ' timed out tasks.');
        } else {
            $this->getLog()->debug('No timed out tasks.');
        }
    }
}
