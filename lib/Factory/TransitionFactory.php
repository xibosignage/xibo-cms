<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Factory;


use Xibo\Entity\Transition;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class TransitionFactory
 * @package Xibo\Factory
 */
class TransitionFactory extends BaseFactory
{
    /**
     * @return Transition
     */
    public function createEmpty()
    {
        return new Transition($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * @param int $transitionId
     * @return Transition
     * @throws NotFoundException
     */
    public function getById($transitionId)
    {
        $transitions = $this->query(null, ['transitionId' => $transitionId]);

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
    public function getByCode($code)
    {
        $transitions = $this->query(null, ['code' => $code]);

        if (count($transitions) <= 0)
            throw new NotFoundException();

        return $transitions[0];
    }

    /**
     * Get enabled by type
     * @param string $type
     * @return array[Transition]
     */
    public function getEnabledByType($type)
    {
        $filter = [];

        if ($type == 'in') {
            $filter['availableAsIn'] = 1;
        } else {
            $filter['availableAsOut'] = 1;
        }

        return $this->query(null, $filter);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $params = [];

        $sanitizedFilter = $this->getSanitizer($filterBy);

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

        if ($sanitizedFilter->getInt('transitionId') !== null) {
            $sql .= ' AND transition.transitionId = :transitionId ';
            $params['transitionId'] = $sanitizedFilter->getInt('transitionId');
        }

        if ($sanitizedFilter->getInt('availableAsIn') !== null) {
            $sql .= ' AND transition.availableAsIn = :availableAsIn ';
            $params['availableAsIn'] = $sanitizedFilter->getInt('availableAsIn');
        }

        if ($sanitizedFilter->getInt('availableAsOut') !== null) {
            $sql .= ' AND transition.availableAsOut = :availableAsOut ';
            $params['availableAsOut'] = $sanitizedFilter->getInt('availableAsOut');
        }

        if ($sanitizedFilter->getString('code') != null) {
            $sql .= ' AND transition.code = :code ';
            $params['code'] = $sanitizedFilter->getString('code');
        }

        // Sorting?
        if (is_array($sortOrder)) {
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);
        }


        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}