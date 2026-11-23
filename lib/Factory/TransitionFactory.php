<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
    public function getById(int $transitionId): Transition
    {
        $transitions = $this->query(null, ['transitionId' => $transitionId]);

        if (count($transitions) <= 0) {
            throw new NotFoundException();
        }

        return $transitions[0];
    }

    /**
     * Get by Code
     * @param string $code
     * @return Transition
     * @throws NotFoundException
     */
    public function getByCode(string $code): Transition
    {
        $transitions = $this->query(null, ['code' => $code]);

        if (count($transitions) <= 0) {
            throw new NotFoundException();
        }

        return $transitions[0];
    }

    /**
     * Get enabled by type
     * @param string $type
     * @return array[Transition]
     */
    public function getEnabledByType(string $type): array
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
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
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
        $allowedColumns = [
            'transitionId',
            'transition',
            'code',
            'hasDuration',
            'hasDirection',
            'availableAsIn',
            'availableAsOut'
        ];

        $sortOrder = $this->buildSortQuery(
            $sortOrder,
            $allowedColumns,
            defaultSort: ['transition ASC'],
            uniqueColumn: 'transitionId'
        );

        $sql .= !empty($sortOrder) ? ' ORDER BY ' . implode(', ', $sortOrder) : '';

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}