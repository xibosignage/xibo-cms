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

class TransitionFactory extends BaseFactory
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
     * Get by Code
     * @param string $code
     * @return Transition
     * @throws NotFoundException
     */
    public static function getByCode($code)
    {
        $transitions = TransitionFactory::query(null, ['code' => $code]);

        if (count($transitions) <= 0)
            throw new NotFoundException();

        return $transitions[0];
    }

    /**
     * Get enabled by type
     * @param string $type
     * @return array[Transition]
     */
    public static function getEnabledByType($type)
    {
        $filter = [];

        if ($type == 'in') {
            $filter['availableAsIn'] = 1;
        } else {
            $filter['availableAsOut'] = 1;
        }

        return TransitionFactory::query(null, $filter);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

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

        if (Sanitize::getInt('transitionId', $filterBy) !== null) {
            $sql .= ' AND transition.transitionId = :transitionId ';
            $params['transitionId'] = Sanitize::getInt('transitionId', $filterBy);
        }

        if (Sanitize::getInt('availableAsIn', $filterBy) !== null) {
            $sql .= ' AND transition.availableAsIn = :availableAsIn ';
            $params['availableAsIn'] = Sanitize::getInt('availableAsIn', $filterBy);
        }

        if (Sanitize::getInt('availableAsOut', $filterBy) !== null) {
            $sql .= ' AND transition.availableAsOut = :availableAsOut ';
            $params['availableAsOut'] = Sanitize::getInt('availableAsOut', $filterBy);
        }

        if (Sanitize::getString('code', $filterBy) != null) {
            $sql .= ' AND transition.code = :code ';
            $params['code'] = Sanitize::getString('code', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Transition())->hydrate($row);
        }

        return $entries;
    }
}