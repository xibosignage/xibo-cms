<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (HelpFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Help;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class HelpFactory extends BaseFactory
{
    /**
     * @param int $helpId
     * @return Help
     * @throws NotFoundException
     */
    public static function getById($helpId)
    {
        $help = HelpFactory::query(null, ['helpId' => $helpId]);

        if (count($help) <= 0)
            throw new NotFoundException();

        return $help[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     * @throws NotFoundException
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $select = 'SELECT `helpId`, `topic`, `category`, `link` ';

        $body = '
          FROM `help`
         WHERE 1 = 1
        ';

        if (Sanitize::getInt('helpId', $filterBy) !== null) {
            $body .= ' AND help.helpId = :helpId ';
            $params['helpId'] = Sanitize::getInt('helpId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit .= ' LIMIT ' . intval(Sanitize::getInt('start')) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Help())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}