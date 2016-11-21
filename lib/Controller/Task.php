<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Task.php)
 */


namespace Xibo\Controller;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\TaskFactory;
use Xibo\Factory\UpgradeFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\XTR\MaintenanceDailyTask;
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

    /** @var  UpgradeFactory */
    private $upgradeFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  NotificationFactory */
    private $notificationFactory;

    /** @var  UserNotificationFactory */
    private $userNotificationFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param TaskFactory $taskFactory
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param UpgradeFactory $upgradeFactory
     * @param MediaFactory $mediaFactory
     * @param NotificationFactory $notificationFactory
     * @param UserNotificationFactory $userNotificationFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $pool, $taskFactory, $userFactory, $userGroupFactory, $layoutFactory, $displayFactory, $upgradeFactory, $mediaFactory, $notificationFactory, $userNotificationFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
        $this->taskFactory = $taskFactory;
        $this->store = $store;
        $this->userGroupFactory = $userGroupFactory;
        $this->pool = $pool;
        $this->userFactory = $userFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->upgradeFactory = $upgradeFactory;
        $this->mediaFactory = $mediaFactory;
        $this->notificationFactory = $notificationFactory;
        $this->userNotificationFactory = $userNotificationFactory;
    }

    /**
     * Display Page
     */
    public function displayPage()
    {
        $this->getState()->template = 'task-page';
    }

    /**
     * Grid
     */
    public function grid()
    {
        $tasks = $this->taskFactory->query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($tasks as $task) {
            /** @var \Xibo\Entity\Task $task */

            $task->nextRunDt = $task->nextRunDate();

            if ($this->isApi())
                continue;

            $task->includeProperty('buttons');

            $task->buttons[] = array(
                'id' => 'task_button_run.now',
                'url' => $this->urlFor('task.runNow.form', ['id' => $task->taskId]),
                'text' => __('Run Now')
            );

            // Don't show any edit buttons if the config is locked.
            if ($this->getConfig()->GetSetting('TASK_CONFIG_LOCKED_CHECKB') == 'Checked')
                continue;

            // Edit Button
            $task->buttons[] = array(
                'id' => 'task_button_edit',
                'url' => $this->urlFor('task.edit.form', ['id' => $task->taskId]),
                'text' => __('Edit')
            );

            // Delete Button
            $task->buttons[] = array(
                'id' => 'task_button_delete',
                'url' => $this->urlFor('task.delete.form', ['id' => $task->taskId]),
                'text' => __('Delete')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->taskFactory->countLast();
        $this->getState()->setData($tasks);
    }

    /**
     * Add form
     */
    public function addForm()
    {
        // Provide a list of possible task classes by searching for .task file in /tasks and /custom
        $data = ['tasksAvailable' => []];

        // Do we have any modules to install?!
        if ($this->getConfig()->GetSetting('TASK_CONFIG_LOCKED_CHECKB') != 'Checked') {
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
    }

    /**
     * Add
     */
    public function add()
    {
        $task = $this->taskFactory->create();
        $task->name = $this->getSanitizer()->getString('name');
        $task->configFile = $this->getSanitizer()->getString('file');
        $task->schedule = $this->getSanitizer()->getString('schedule');
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
    }

    /**
     * Edit Form
     * @param $taskId
     */
    public function editForm($taskId)
    {
        $task = $this->taskFactory->getById($taskId);
        $task->setClassAndOptions();

        $this->getState()->template = 'task-form-edit';
        $this->getState()->setData([
            'task' => $task
        ]);
    }

    /**
     * @param $taskId
     */
    public function edit($taskId)
    {
        $task = $this->taskFactory->getById($taskId);
        $task->setClassAndOptions();
        $task->name = $this->getSanitizer()->getString('name');
        $task->schedule = $this->getSanitizer()->getString('schedule');
        $task->isActive = $this->getSanitizer()->getCheckbox('isActive');

        // Loop through each option and see if a new value is provided
        foreach ($task->options as $option => $value) {
            $provided = $this->getSanitizer()->getString($option);

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
    }

    /**
     * Delete Form
     * @param $taskId
     */
    public function deleteForm($taskId)
    {
        $task = $this->taskFactory->getById($taskId);

        $this->getState()->template = 'task-form-delete';
        $this->getState()->setData([
            'task' => $task
        ]);
    }

    /**
     * @param $taskId
     */
    public function delete($taskId)
    {
        $task = $this->taskFactory->getById($taskId);
        $task->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $task->name)
        ]);
    }

    /**
     * Delete Form
     * @param $taskId
     */
    public function runNowForm($taskId)
    {
        $task = $this->taskFactory->getById($taskId);

        $this->getState()->template = 'task-form-run-now';
        $this->getState()->setData([
            'task' => $task
        ]);
    }

    /**
     * @param $taskId
     */
    public function runNow($taskId)
    {
        $task = $this->taskFactory->getById($taskId);
        $task->runNow = 1;
        $task->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Run Now set on %s'), $task->name)
        ]);
    }

    /**
     * @param $taskId
     * @throws \Exception
     */
    public function run($taskId)
    {
        // Handle cases where we arrive from older versions of the application.
        // that is versions without tasks
        if (DBVERSION < 128) {
            // We need to manually create and run task 1 so that we trigger an upgrade.
            $task = new MaintenanceDailyTask();
            $task->setApp($this->getApp())
                ->setSanitizer($this->getSanitizer())
                ->setUser($this->getUser())
                ->setConfig($this->getConfig())
                ->setLogger($this->getLog())
                ->setDate($this->getDate())
                ->setPool($this->pool)
                ->setStore($this->store)
                ->setFactories(
                    $this->userFactory,
                    $this->userGroupFactory,
                    $this->layoutFactory,
                    $this->displayFactory,
                    $this->upgradeFactory,
                    $this->mediaFactory,
                    $this->notificationFactory,
                    $this->userNotificationFactory
                )
                ->setTask($task)
                ->run();

            if ($taskId == 1)
                return;
        }

        // Get this task
        $task = $this->taskFactory->getById($taskId);

        // Instantiate
        if (!class_exists($task->class))
            throw new NotFoundException();

        /** @var TaskInterface $taskClass */
        $taskClass = new $task->class();

        // Set to running
        $this->getLog()->debug('Running Task ' . $task->name . ' [' . $task->taskId . ']');

        // Run
        try {
            $start = time();

            $taskClass
                ->setApp($this->getApp())
                ->setSanitizer($this->getSanitizer())
                ->setUser($this->getUser())
                ->setConfig($this->getConfig())
                ->setLogger($this->getLog())
                ->setDate($this->getDate())
                ->setPool($this->pool)
                ->setStore($this->store)
                ->setFactories(
                    $this->userFactory,
                    $this->userGroupFactory,
                    $this->layoutFactory,
                    $this->displayFactory,
                    $this->upgradeFactory,
                    $this->mediaFactory,
                    $this->notificationFactory,
                    $this->userNotificationFactory
                )
                ->setTask($task)
                ->run();

            // Collect results
            $task->lastRunDuration = time() - $start;
            $task->lastRunMessage = $taskClass->getRunMessage();
            $task->lastRunStatus = \Xibo\Entity\Task::$STATUS_SUCCESS;
        }
        catch (\Exception $e) {
            $this->getLog()->error($e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());

            $task->lastRunMessage = $e->getMessage();
            $task->lastRunStatus = \Xibo\Entity\Task::$STATUS_ERROR;
        }

        $task->lastRunDt = $this->getDate()->getLocalDate(null, 'U');
        $task->runNow = 0;

        // Save
        $task->save();

        $this->getLog()->debug('Finished Task ' . $task->name . ' [' . $task->taskId . '] Run Dt: ' . $this->getDate()->getLocalDate());

        // No output
        $this->setNoOutput(true);
    }

    /**
     * Take the quickest due task and run it.
     */
    public function poll()
    {
        $this->getLog()->debug('XTR poll started');

        // The getting/updating of tasks runs in a separate DB connection
        // Query for a list of tasks to run.
        $db = $this->store->getConnection('xtr');

        $pollSth = $db->prepare('SELECT taskId, schedule, runNow, lastRunDt FROM `task` WHERE isActive = 1 AND status <> :status ORDER BY lastRunDuration');
        $updateSth = $db->prepare('UPDATE `task` SET status = :status WHERE taskId = :taskId');

        while (true) {

            $pollSth->execute(['status' => \Xibo\Entity\Task::$STATUS_RUNNING]);
            $this->store->incrementStat('xtr', 'select');

            $tasks = $pollSth->fetchAll(\PDO::FETCH_ASSOC);

            $taskRun = false;

            foreach ($tasks as $task) {
                /** @var \Xibo\Entity\Task $task */
                $cron = \Cron\CronExpression::factory($task['schedule']);
                $taskId = $task['taskId'];

                // Is the next run date of this event earlier than now, or is the task set to runNow
                $nextRunDt = $cron->getNextRunDate(\DateTime::createFromFormat('U', $task['lastRunDt']))->format('U');

                if ($task['runNow'] == 1 || $nextRunDt < time()) {

                    $this->getLog()->info('Running Task ' . $taskId);

                    // Set to running
                    $updateSth->execute(['taskId' => $taskId, 'status' => \Xibo\Entity\Task::$STATUS_ERROR]);
                    $this->store->incrementStat('xtr', 'update');

                    // Pass to run.
                    try {
                        $this->run($taskId);

                        // Commit the default connection at this point.
                        $this->store->commitIfNecessary();

                        // Set to idle
                        $updateSth->execute(['taskId' => $taskId, 'status' => \Xibo\Entity\Task::$STATUS_IDLE]);
                        $this->store->incrementStat('xtr', 'update');

                    } catch (\Exception $exception) {
                        $this->getLog()->error('Task run error for taskId ' . $taskId . '. E = ' . $exception->getMessage());

                        // Rollback
                        if ($this->store->getConnection()->inTransaction())
                            $this->store->getConnection()->rollBack();

                        // Set to error
                        $updateSth->execute(['taskId' => $taskId, 'status' => \Xibo\Entity\Task::$STATUS_ERROR]);
                        $this->store->incrementStat('xtr', 'update');
                    }

                    // Only do 1
                    $taskRun = true;
                    break;
                }
            }

            if (!$taskRun)
                break;
        }

        $this->getLog()->debug('XTR poll stopped');
    }
}