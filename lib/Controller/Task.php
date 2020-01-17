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

namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\TaskFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
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

    /** @var  UserFactory */
    private $userFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  NotificationFactory */
    private $notificationFactory;

    /** @var  UserNotificationFactory */
    private $userNotificationFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param PoolInterface $pool
     * @param TaskFactory $taskFactory
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param MediaFactory $mediaFactory
     * @param NotificationFactory $notificationFactory
     * @param UserNotificationFactory $userNotificationFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $timeSeriesStore, $pool, $taskFactory, $userFactory, $userGroupFactory, $layoutFactory, $displayFactory, $mediaFactory, $notificationFactory, $userNotificationFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);
        $this->taskFactory = $taskFactory;
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->userGroupFactory = $userGroupFactory;
        $this->pool = $pool;
        $this->userFactory = $userFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->mediaFactory = $mediaFactory;
        $this->notificationFactory = $notificationFactory;
        $this->userNotificationFactory = $userNotificationFactory;
    }

    /**
     * Display Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $tasks = $this->taskFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([], $request));

        foreach ($tasks as $task) {
            /** @var \Xibo\Entity\Task $task */

            $task->nextRunDt = $task->nextRunDate();

            if ($this->isApi($request))
                continue;

            $task->includeProperty('buttons');

            $task->buttons[] = array(
                'id' => 'task_button_run.now',
                'url' => $this->urlFor($request,'task.runNow.form', ['id' => $task->taskId]),
                'text' => __('Run Now')
            );

            // Don't show any edit buttons if the config is locked.
            if ($this->getConfig()->getSetting('TASK_CONFIG_LOCKED_CHECKB') == 1 || $this->getConfig()->getSetting('TASK_CONFIG_LOCKED_CHECKB') == 'Checked')
                continue;

            // Edit Button
            $task->buttons[] = array(
                'id' => 'task_button_edit',
                'url' => $this->urlFor($request,'task.edit.form', ['id' => $task->taskId]),
                'text' => __('Edit')
            );

            // Delete Button
            $task->buttons[] = array(
                'id' => 'task_button_delete',
                'url' => $this->urlFor($request,'task.delete.form', ['id' => $task->taskId]),
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
                $config->file = str_replace_first(PROJECT_ROOT, '', $file);

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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function runNowForm(Request $request, Response $response, $id)
    {
        $task = $this->taskFactory->getById($id);

        $this->getState()->template = 'task-form-run-now';
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @param $taskId
     * @throws \Exception
     */
    public function run(Request $request, Response $response, $id)
    {
        // Get this task
        $task = $this->taskFactory->getById($id);

        // Set to running
        $this->getLog()->debug('Running Task ' . $task->name . ' [' . $task->taskId . '], Class = ' . $task->class);

        // Run
        try {
            // Instantiate
            if (!class_exists($task->class))
                throw new NotFoundException('Task with class name ' . $task->class . ' not found');

            /** @var TaskInterface $taskClass */
            $taskClass = new $task->class();

            // Record the start time
            $start = time();
// TODO
            $taskClass
                ->setSanitizer($this->getSanitizer())
                ->setUser($this->getUser())
                ->setConfig($this->getConfig())
                ->setLogger($this->getLog())
                ->setDate($this->getDate())
                ->setPool($this->pool)
                ->setStore($this->store)
                ->setTimeSeriesStore($this->timeSeriesStore)
                ->setFactories($this->getApp()->container)
                ->setTask($task)
                ->run();

            // We should commit anything this task has done
            $this->store->commitIfNecessary();

            // Collect results
            $task->lastRunDuration = time() - $start;
            $task->lastRunMessage = $taskClass->getRunMessage();
            $task->lastRunStatus = \Xibo\Entity\Task::$STATUS_SUCCESS;
        }
        catch (\Exception $e) {
            $this->getLog()->error($e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());

            // We should rollback anything we've done so far
            if ($this->store->getConnection()->inTransaction())
                $this->store->getConnection()->rollBack();

            // Set the results to error
            $task->lastRunMessage = $e->getMessage();
            $task->lastRunStatus = \Xibo\Entity\Task::$STATUS_ERROR;
        }

        $task->lastRunDt = $this->getDate()->getLocalDate(null, 'U');
        $task->runNow = 0;

        // Save (on the XTR connection)
        $task->save(['connection' => 'xtr', 'validate' => false, 'reconnect' => true]);

        $this->getLog()->debug('Finished Task ' . $task->name . ' [' . $task->taskId . '] Run Dt: ' . $this->getDate()->getLocalDate());

        // No output
        $this->setNoOutput(true);
        return $this->render($request, $response);
    }

    /**
     * Poll for tasks to run
     *  continue polling until there aren't any more to run
     *  allow for multiple polls to run at the same time
     */
    public function poll()
    {
        $this->getLog()->debug('XTR poll started');

        // Process timeouts
        $this->pollProcessTimeouts();

        // Keep track of tasks we've run during this poll period
        // we will use this as a catch all so that we do not run a task more than once.
        $tasksRun = [];

        // The getting/updating of tasks runs in a separate DB connection
        $sqlForActiveTasks = 'SELECT taskId, `schedule`, runNow, lastRunDt FROM `task` WHERE isActive = 1 AND `status` <> :status ORDER BY lastRunDuration';

        // Update statements
        $updateSth = 'UPDATE `task` SET status = :status WHERE taskId = :taskId';

        // We loop until we have gone through without running a task
        // we select new tasks each time
        while (true) {
            // Get tasks that aren't running currently
            $tasks = $this->store->select($sqlForActiveTasks, ['status' => \Xibo\Entity\Task::$STATUS_RUNNING], 'xtr', true);

            // Assume we wont run anything
            $taskRun = false;

            foreach ($tasks as $task) {
                /** @var \Xibo\Entity\Task $task */
                $taskId = $task['taskId'];

                // Skip tasks that have already been run
                if (in_array($taskId, $tasksRun)) {
                    continue;
                }

                $cron = \Cron\CronExpression::factory($task['schedule']);

                // Is the next run date of this event earlier than now, or is the task set to runNow
                $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $task['lastRunDt']))->format('U');

                if ($task['runNow'] == 1 || $nextRunDt <= time()) {

                    $this->getLog()->info('Running Task ' . $taskId);

                    // Set to running
                    $this->store->update('UPDATE `task` SET status = :status, lastRunStartDt = :lastRunStartDt WHERE taskId = :taskId', [
                        'taskId' => $taskId,
                        'status' => \Xibo\Entity\Task::$STATUS_RUNNING,
                        'lastRunStartDt' => $this->getDate()->getLocalDate(null, 'U')
                    ], 'xtr');
                    $this->store->commitIfNecessary('xtr');

                    // Pass to run.
                    try {
                        // Run is isolated
                        $this->run($taskId);

                        // Set to idle
                        $this->store->update($updateSth, ['taskId' => $taskId, 'status' => \Xibo\Entity\Task::$STATUS_IDLE], 'xtr', true);

                    } catch (\Exception $exception) {
                        // This is a completely unexpected exception, and we should disable the task
                        $this->getLog()->error('Task run error for taskId ' . $taskId . '. E = ' . $exception->getMessage());

                        // Set to error
                        $this->store->update('
                            UPDATE `task` SET status = :status, isActive = :isActive, lastRunMessage = :lastRunMessage 
                             WHERE taskId = :taskId
                        ', [
                            'taskId' => $taskId,
                            'status' => \Xibo\Entity\Task::$STATUS_ERROR,
                            'isActive' => 0,
                            'lastRunMessage' => 'Fatal Error: ' . $exception->getMessage()
                        ], 'xtr', true);
                    }

                    $this->store->commitIfNecessary('xtr');

                    // We have run a task
                    $taskRun = true;

                    // We've run this task during this polling period
                    $tasksRun[] = $taskId;

                    break;
                }
            }

            // If we haven't run a task, then stop
            if (!$taskRun)
                break;
        }

        $this->getLog()->debug('XTR poll stopped');
    }

    private function pollProcessTimeouts()
    {
        $db = $this->store->getConnection('xtr');

        // Get timed out tasks and deal with them
        $command = $db->prepare('
          SELECT taskId, lastRunStartDt 
            FROM `task` 
           WHERE isActive = 1 
            AND `status` = :status
            AND lastRunStartDt < :timeout
        ');

        $updateFatalErrorSth = $db->prepare('UPDATE `task` SET `status` = :status WHERE taskId = :taskId');

        $command->execute([
            'status' => \Xibo\Entity\Task::$STATUS_RUNNING,
            'timeout' => $this->getDate()->parse()->subHours(12)->format('U')
        ]);

        foreach ($command->fetchAll(\PDO::FETCH_ASSOC) as $task) {
            $this->getLog()->error('Timed out task detected, marking as timed out. TaskId: ' . $task['taskId']);

            $updateFatalErrorSth->execute([
                'taskId' => intval($task['taskId']),
                'status' => \Xibo\Entity\Task::$STATUS_TIMEOUT
            ]);
        }
    }
}