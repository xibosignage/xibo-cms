<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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

use Stash\Interfaces\PoolInterface;
use Xibo\Connector\ConnectorInterface;
use Xibo\Entity\Connector;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Connector Factory
 */
class ConnectorFactory extends BaseFactory
{
    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

    /**
     * @param \Stash\Interfaces\PoolInterface $pool
     */
    public function __construct(PoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @param Connector $connector
     * @return ConnectorInterface
     * @throws \Xibo\Support\Exception\NotFoundException|\Xibo\Support\Exception\GeneralException
     */
    public function create(Connector $connector): ConnectorInterface
    {
        // Check to see if this connector class exists
        if (!\class_exists($connector->className)) {
            throw new NotFoundException(sprintf(__('Class %s does not exist'), $connector->className));
        }

        // Instantiate it.
        $out = new $connector->className();

        if (!$out instanceof ConnectorInterface) {
            throw new GeneralException('Connector ' . $connector->className . ' must implement ConnectorInterface');
        }

        return $out
            ->useLogger($this->getLog()->getLoggerInterface())
            ->useSettings($connector->settings)
            ->usePool($this->pool);
    }

    /**
     * @param int $connectorId
     * @return ConnectorInterface
     * @throws \Xibo\Support\Exception\NotFoundException|\Xibo\Support\Exception\GeneralException
     */
    public function createById(int $connectorId): ConnectorInterface
    {
        return $this->create($this->getById($connectorId));
    }

    /**
     * @param $connectorId
     * @return \Xibo\Entity\Connector
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getById($connectorId): Connector
    {
        $connectors = $this->query(['connectorId' => $connectorId]);

        if (count($connectors) !== 1) {
            throw new NotFoundException(__('Connector not found'));
        }

        return $connectors[0];
    }

    /**
     * @return Connector[]
     */
    public function query($filterBy): array
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];

        $sql = 'SELECT `connectorId`, `className`, `settings`, `isEnabled` FROM `connectors` WHERE 1 = 1 ';

        if ($sanitizedFilter->hasParam('connectorId')) {
            $sql .= ' AND connectorId = :connectorId ';
            $params['connectorId'] = $sanitizedFilter->getInt('connectorId');
        }

        if ($sanitizedFilter->hasParam('isEnabled')) {
            $sql .= ' AND isEnabled = :isEnabled ';
            $params['isEnabled'] = $sanitizedFilter->getCheckbox('isEnabled');
        }

        foreach ($this->getStore()->select($sql, $params) as $row) {
            // Construct the class
            $entries[] = $this->hydrate($row);
        }

        // No paging
        $this->_countLast = count($entries);

        return $entries;
    }

    /**
     * @param $row
     * @return \Xibo\Entity\Connector
     */
    private function hydrate($row): Connector
    {
        $connector = new Connector($this->getStore(), $this->getLog());
        $connector->hydrate($row, [
            'intProperties' => ['isEnabled']
        ]);

        if (empty($row['settings'])) {
            $connector->settings = [];
        } else {
            $connector->settings = json_decode($row['settings'], true);
        }
        return $connector;
    }
}
