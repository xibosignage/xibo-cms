<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LogFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class LogFactory extends BaseFactory
{
    /**
     * Query
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[\Xibo\Entity\Log]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        if ($sortOrder == null)
            $sortOrder = ['logId DESC'];

        $entries = [];
        $params = [];
        $order = ''; $limit = '';

        $select = 'SELECT logId, runNo, logDate, channel, page, function, message, display.display, type';

        $body = '
              FROM `log`
                  LEFT OUTER JOIN display
                  ON display.displayid = log.displayid
             WHERE 1 = 1
        ';

        if (Sanitize::getInt('fromDt', $filterBy) !== null) {
            $body .= ' AND logdate > :fromDt ';
            $params['fromDt'] = date("Y-m-d H:i:s", Sanitize::getInt('fromDt', $filterBy));
        }

        if (Sanitize::getInt('toDt', $filterBy) !== null) {
            $body .= ' AND logdate <= :toDt ';
            $params['toDt'] = date("Y-m-d H:i:s", Sanitize::getInt('toDt', $filterBy));
        }

        if (Sanitize::getString('runNo', $filterBy) != null) {
            $body .= ' AND runNo = :runNo ';
            $params['runNo'] = Sanitize::getString('runNo', $filterBy);
        }

        if (Sanitize::getString('type', $filterBy) != null) {
            $body .= ' AND type = :type ';
            $params['type'] = Sanitize::getString('type', $filterBy);
        }

        if (Sanitize::getString('page', $filterBy) != null) {
            $body .= ' AND page LIKE :page ';
            $params['page'] = '%' . Sanitize::getString('page', $filterBy) . '%';
        }

        if (Sanitize::getString('function', $filterBy) != null) {
            $body .= ' AND function LIKE :function ';
            $params['function'] = '%' . Sanitize::getString('function', $filterBy) . '%';
        }

        if (Sanitize::getInt('displayId', $filterBy) !== null) {
            $body .= ' AND log.displayId = :displayId ';
            $params['displayId'] = Sanitize::getInt('displayId', $filterBy);
        }

        if (Sanitize::getCheckbox('excludeLog', $filterBy) == 1) {
            $body .= ' AND log.page NOT LIKE \'/log%\' ';
        }

        // Sorting?
        if (is_array($sortOrder))
            $order = ' ORDER BY ' . implode(',', $sortOrder);

        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new \Xibo\Entity\LogEntry())->hydrate($row,  ['htmlStringProperties' => ['message']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}