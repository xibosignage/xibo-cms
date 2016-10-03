<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Task.php)
 */


namespace Xibo\Controller;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\TaskFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\XTR\TaskInterface;

/**
 * Class Task
 * @package Xibo\Controller
 */
class Task extends Base
{
    /** @var  TaskFactory */
    private $taskFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param TaskFactory $taskFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $taskFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
        $this->taskFactory = $taskFactory;
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
        $tasks = $this->taskFactory->query();

        foreach ($tasks as $task) {
            /** @var \Xibo\Entity\Task $task */

            $task->nextRunDt = $task->nextRunDate();

            if ($this->isApi())
                continue;
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->taskFactory->countLast();
        $this->getState()->setData($tasks);
    }

    /**
     * @param $taskId
     * @throws \Exception
     */
    public function run($taskId)
    {
        // Get this task
        $task = $this->taskFactory->getById($taskId);

        // Instantiate
        if (!class_exists($task->class))
            throw new NotFoundException();

        /** @var TaskInterface $taskClass */
        $taskClass = new $task->class();

        // Run
        $taskClass->setLogger($this->getLog())->setConfig($task->options)->run();

        // Collect results
        $task->lastRunDt = $this->getDate()->parse()->format('U');
        $task->lastRunMessage = $taskClass->getRunMessage();
        $task->lastRunStatus = $taskClass->getRunStatus();

        // Save
        $task->save();

        // No output
        $this->setNoOutput(true);
    }
}