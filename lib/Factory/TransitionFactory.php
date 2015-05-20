<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TransitionFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Transition;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class TransitionFactory
{
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
            SELECT transitionID,
                  transition,
                  `code`,
                  hasDuration,
                  hasDirection,
                  availableAsIn,
                  availableAsOut
             FROM `transition`
            ';

            // Sorting?
            if (is_array($sortOrder))
                $sql .= 'ORDER BY ' . implode(',', $sortOrder);

            Log::sql($sql, $params);

            foreach (PDOConnect::select($sql, $params) as $row) {
                $entries[] = (new Transition())->hydrate($row);
            }

            return $entries;

        } catch (\Exception $e) {

            Log::error($e);

            throw new NotFoundException();
        }
    }
}