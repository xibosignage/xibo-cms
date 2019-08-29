<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TaskFactory.php)
 */


namespace Xibo\Factory;
use Xibo\Entity\Task;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class TaskFactory
 * @package Xibo\Factory
 */
class TaskFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * Create empty
     * @return Task
     */
    public function create()
    {
        return new Task($this->getStore(), $this->getLog());
    }

    /**
     * Get by ID
     * @param int $taskId
     * @return Task
     * @throws NotFoundException if the task cannot be resolved from the provided route
     */
    public function getById($taskId)
    {
        $tasks = $this->query(null, array('taskId' => $taskId));

        if (count($tasks) <= 0)
            throw new NotFoundException();

        return $tasks[0];
    }

    /**
     * Get by Name
     * @param string $task
     * @return Task
     * @throws NotFoundException if the task cannot be resolved from the provided route
     */
    public function getByName($task)
    {
        $tasks = $this->query(null, array('name' => $task));

        if (count($tasks) <= 0)
            throw new NotFoundException();

        return $tasks[0];
    }

    /**
     * Get by Class
     * @param string $class
     * @return Task
     * @throws NotFoundException if the task cannot be resolved from the provided route
     */
    public function getByClass($class)
    {
        $tasks = $this->query(null, array('class' => $class));

        if (count($tasks) <= 0)
            throw new NotFoundException();

        return $tasks[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return array
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder == null)
            $sortOrder = ['name'];

        $entries = array();
        $params = array();
        $select = '
          SELECT `taskId`, `name`, `status`, `pid`, `configFile`, `class`, `options`, `schedule`, 
              `lastRunDt`, `lastRunStatus`, `lastRunMessage`, `lastRunDuration`, `lastRunExitCode`,
              `isActive`, `runNow`, `lastRunStartDt`
        ';

        $body = ' FROM `task` 
           WHERE 1 = 1 ';

        if ($this->getSanitizer()->getString('name', $filterBy) != null) {
            $params['name'] = $this->getSanitizer()->getString('name', $filterBy);
            $body .= ' AND `name` = :name ';
        }

        if ($this->getSanitizer()->getString('class', $filterBy) != null) {
            $params['class'] = $this->getSanitizer()->getString('class', $filterBy);
            $body .= ' AND `class` = :class ';
        }

        if ($this->getSanitizer()->getInt('taskId', $filterBy) !== null) {
            $params['taskId'] = $this->getSanitizer()->getString('taskId', $filterBy);
            $body .= ' AND `taskId` = :taskId ';
        }

        // Sorting?
        $body .= 'ORDER BY ' . implode(',', $sortOrder);

        // Paging
        $limit = '';
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $task = $this->create()->hydrate($row, [
                'intProperties' => [
                    'status', 'lastRunStatus', 'nextRunDt', 'lastRunDt', 'lastRunStartDt', 'lastRunExitCode', 'runNow', 'isActive', 'pid'
                ]
            ]);

            if ($task->options != null)
                $task->options = json_decode($task->options, true);
            else
                $task->options = [];

            $entries[] = $task;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}