<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LogFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\LogEntry;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class LogFactory
 * @package Xibo\Factory
 */
class LogFactory extends BaseFactory
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
     * Create Empty
     * @return LogEntry
     */
    public function createEmpty()
    {
        return new LogEntry($this->getStore(), $this->getLog());
    }

    /**
     * Query
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[\Xibo\Entity\Log]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder == null)
            $sortOrder = ['logId DESC'];

        $entries = [];
        $params = [];
        $order = ''; $limit = '';

        $select = 'SELECT logId, runNo, logDate, channel, page, function, message, display.displayId, display.display, type';

        $body = '
              FROM `log`
                  LEFT OUTER JOIN display
                  ON display.displayid = log.displayid
                  ';
        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = log.displayid ';
        }

        $body .= ' WHERE 1 = 1 ';


        if ($this->getSanitizer()->getInt('fromDt', $filterBy) !== null) {
            $body .= ' AND logdate > :fromDt ';
            $params['fromDt'] = date("Y-m-d H:i:s", $this->getSanitizer()->getInt('fromDt', $filterBy));
        }

        if ($this->getSanitizer()->getInt('toDt', $filterBy) !== null) {
            $body .= ' AND logdate <= :toDt ';
            $params['toDt'] = date("Y-m-d H:i:s", $this->getSanitizer()->getInt('toDt', $filterBy));
        }

        if ($this->getSanitizer()->getString('runNo', $filterBy) != null) {
            $body .= ' AND runNo = :runNo ';
            $params['runNo'] = $this->getSanitizer()->getString('runNo', $filterBy);
        }

        if ($this->getSanitizer()->getString('type', $filterBy) != null) {
            $body .= ' AND type = :type ';
            $params['type'] = $this->getSanitizer()->getString('type', $filterBy);
        }

        if ($this->getSanitizer()->getString('channel', $filterBy) != null) {
            $body .= ' AND channel LIKE :channel ';
            $params['channel'] = '%' . $this->getSanitizer()->getString('channel', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('page', $filterBy) != null) {
            $body .= ' AND page LIKE :page ';
            $params['page'] = '%' . $this->getSanitizer()->getString('page', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('function', $filterBy) != null) {
            $body .= ' AND function LIKE :function ';
            $params['function'] = '%' . $this->getSanitizer()->getString('function', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('message', $filterBy) != null) {
            $body .= ' AND message LIKE :message ';
            $params['message'] = '%' . $this->getSanitizer()->getString('message', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $body .= ' AND log.displayId = :displayId ';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND log.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        if ($this->getSanitizer()->getCheckbox('excludeLog', $filterBy) == 1) {
            $body .= ' AND (log.page NOT LIKE \'/log%\' OR log.page = \'/login\') ';
            $body .= ' AND log.page <> \'/user/pref\' AND log.page <> \'/clock\' AND log.page <> \'/library/fontcss\' ';
        }

        // Filter by Display Name?
        if ($this->getSanitizer()->getString('display', $filterBy) != null) {
            $terms = explode(',', $this->getSanitizer()->getString('display', $filterBy));
            $this->nameFilter('display', 'display', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= ' AND lkdisplaydg.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $order = ' ORDER BY ' . implode(',', $sortOrder);

        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row,  ['htmlStringProperties' => ['message']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}