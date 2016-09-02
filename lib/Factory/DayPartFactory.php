<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DayPartFactory.php)
 */


namespace Xibo\Factory;

use Xibo\Entity\DayPart;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DayPartFactory
 * @package Xibo\Factory
 */
class DayPartFactory extends BaseFactory
{
    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($store, $log, $sanitizerService, $scheduleFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->scheduleFactory = $scheduleFactory;
    }

    /**
     * Create Empty
     * @return DayPart
     */
    public function createEmpty()
    {
        return new DayPart(
            $this->getStore(),
            $this->getLog(),
            $this->scheduleFactory
        );
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Schedule]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        if ($sortOrder == null)
            $sortOrder = ['name'];

        $params = array();
        $select = 'SELECT `daypart`.dayPartId,
            `name`, `description`, `isRetired`, `userId`, `monStart`, `monEnd`, `tueStart`, 
            `tueEnd`, `wedStart`, `wedEnd`, `thuStart`, `thuEnd`, `friStart`, `friEnd`, `satStart`, `satEnd`, 
            `sunStart`, `sunEnd`
        ';

        $body = ' FROM `daypart` ';

        $body .= ' WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('dayPartId', $filterBy) !== null) {
            $body .= ' AND `daypart`.dayPartId = :dayPartId ';
            $params['dayPartId'] = $this->getSanitizer()->getInt('dayPartId', $filterBy);
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != null) {
            $body .= ' AND `daypart`.name = :name ';
            $params['name'] = $this->getSanitizer()->getString('name', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}