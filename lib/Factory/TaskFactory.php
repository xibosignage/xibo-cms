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
        if ($sortOrder == null) {
            $sortOrder = ['name'];
        }
        
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];
        $select = '
          SELECT `taskId`, `name`, `status`, `pid`, `configFile`, `class`, `options`, `schedule`, 
              `lastRunDt`, `lastRunStatus`, `lastRunMessage`, `lastRunDuration`, `lastRunExitCode`,
              `isActive`, `runNow`, `lastRunStartDt`
        ';

        $body = ' FROM `task` 
           WHERE 1 = 1 ';

        if ($sanitizedFilter->getString('name') != null) {
            $params['name'] = $sanitizedFilter->getString('name');
            $body .= ' AND `name` = :name ';
        }

        if ($sanitizedFilter->getString('class') != null) {
            $params['class'] = $sanitizedFilter->getString('class');
            $body .= ' AND `class` = :class ';
        }

        if ($sanitizedFilter->getInt('taskId') !== null) {
            $params['taskId'] = $sanitizedFilter->getString('taskId');
            $body .= ' AND `taskId` = :taskId ';
        }

        // Sorting?
        $body .= 'ORDER BY ' . implode(',', $sortOrder);

        // Paging
        $limit = '';
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
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