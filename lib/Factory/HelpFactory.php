<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (HelpFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Help;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class HelpFactory
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

        try {
            $sql = '
            SELECT `helpId`, `topic`, `category`, `link`
              FROM `help`
             WHERE 1 = 1
            ';

            if (Sanitize::getInt('helpId', $filterBy) != null) {
                $sql .= ' AND help.helpId = :helpId ';
                $params['helpId'] = Sanitize::getInt('helpId', $filterBy);
            }

            // Sorting?
            if (is_array($sortOrder))
                $sql .= ' ORDER BY ' . implode(',', $sortOrder);

            if (Sanitize::getInt('start') !== null && Sanitize::getInt('length') !== null) {
                $sql .= ' LIMIT ' . intval(Sanitize::getInt('start')) . ', ' . Sanitize::getInt('length', 10);
            }

            Log::sql($sql, $params);

            foreach (PDOConnect::select($sql, $params) as $row) {
                $entries[] = (new Help())->hydrate($row);
            }

            return $entries;

        } catch (\Exception $e) {

            Log::error($e);

            throw new NotFoundException();
        }
    }
}