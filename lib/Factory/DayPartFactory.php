<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2016 Spring Signage Ltd - http://www.springsignage.com
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

use Xibo\Entity\DayPart;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
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
     * @param User $user
     * @param UserFactory $userFactory
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $scheduleFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
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
     * Get DayPart by Id
     * @param $dayPartId
     * @return DayPart
     * @throws NotFoundException
     */
    public function getById($dayPartId)
    {
        $dayParts = $this->query(null, ['dayPartId' => $dayPartId]);

        if (count($dayParts) <= 0)
            throw new NotFoundException();

        return $dayParts[0];
    }

    /**
     * Get all dayparts with the system entries (always and custom)
     * @return DayPart[]
     */
    public function allWithSystem()
    {
        $dayParts = $this->query();

        // Add system and custom
        array_unshift($dayParts, ['dayPartId' => 1, 'name' => __('Always')]);
        array_unshift($dayParts, ['dayPartId' => 0, 'name' => __('Custom')]);

        return $dayParts;
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
        $select = 'SELECT `daypart`.dayPartId, `name`, `description`, `isRetired`, `userId`, `startTime`, `endTime`, `exceptions`';

        $body = ' FROM `daypart` ';

        $body .= ' WHERE 1 = 1 ';

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DayPart', $body, $params, '`daypart`.dayPartId');

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
            $dayPart = $this->createEmpty()->hydrate($row);
            $dayPart->exceptions = json_decode($dayPart->exceptions, true);

            $entries[] = $dayPart;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}