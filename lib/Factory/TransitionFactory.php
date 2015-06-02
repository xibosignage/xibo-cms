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
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class TransitionFactory
{
    /**
     * @param int $transitionId
     * @return Transition
     * @throws NotFoundException
     */
    public static function getById($transitionId)
    {
        $transitions = TransitionFactory::query(null, ['transitionId' => $transitionId]);

        if (count($transitions) <= 0)
            throw new NotFoundException();

        return $transitions[0];
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
            SELECT transitionId,
                  transition,
                  `code`,
                  hasDuration,
                  hasDirection,
                  availableAsIn,
                  availableAsOut
              FROM `transition`
             WHERE 1 = 1
            ';

            if (Sanitize::getInt('transitionId', $filterBy) != null) {
                $sql .= ' AND transition.transitionId = :transitionId ';
                $params['transitionId'] = Sanitize::getInt('transitionId', $filterBy);
            }

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