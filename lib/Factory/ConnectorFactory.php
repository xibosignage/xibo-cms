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

use Psr\Container\ContainerInterface;
use Stash\Interfaces\PoolInterface;
use Xibo\Connector\ConnectorInterface;
use Xibo\Entity\Connector;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\JwtServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Connector Factory
 */
class ConnectorFactory extends BaseFactory
{
    public function __construct(
        private readonly PoolInterface $pool,
        private readonly ConfigServiceInterface $config,
        private readonly JwtServiceInterface $jwtService,
        private readonly PlayerActionServiceInterface $playerActionService,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @param  Connector $connector
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
            throw new GeneralException(
                'Connector ' . $connector->className . ' must implement ConnectorInterface'
            );
        }

        return $out
            ->setFactories($this->container)
            ->useLogger($this->getLog()->getLoggerInterface())
            ->useSettings($connector->settings)
            ->useSettings($this->config->getConnectorSettings($out->getSourceName()), true)
            ->useHttpOptions($this->config->getGuzzleProxy())
            ->useJwtService($this->jwtService)
            ->usePlayerActionService($this->playerActionService)
            ->usePool($this->pool);
    }

    /**
     * @param  int $connectorId
     * @return ConnectorInterface
     * @throws \Xibo\Support\Exception\NotFoundException|\Xibo\Support\Exception\GeneralException
     */
    public function createById(int $connectorId): ConnectorInterface
    {
        return $this->create($this->getById($connectorId));
    }

    /**
     * @param  $connectorId
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
     * @param  $className
     * @return \Xibo\Entity\Connector[]
     */
    public function getByClassName($className): array
    {
        return $this->query(['className' => $className]);
    }


    /**
     * @param  array $filterBy
     * @return array
     */
    public function query(array $filterBy = []): array
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];

        $sql = 'SELECT `connectorId`, `className`, `settings`, `isEnabled`, `isVisible` FROM `connectors` WHERE 1 = 1 ';

        if ($sanitizedFilter->hasParam('connectorId')) {
            $sql .= ' AND connectorId = :connectorId ';
            $params['connectorId'] = $sanitizedFilter->getInt('connectorId');
        }

        if ($sanitizedFilter->hasParam('isEnabled')) {
            $sql .= ' AND isEnabled = :isEnabled ';
            $params['isEnabled'] = $sanitizedFilter->getCheckbox('isEnabled');
        }

        if ($sanitizedFilter->hasParam('isVisible')) {
            $sql .= ' AND isVisible = :isVisible ';
            $params['isVisible'] = $sanitizedFilter->getCheckbox('isVisible');
        }

        if ($sanitizedFilter->hasParam('className')) {
            $sql .= ' AND `className` = :className ';
            $params['className'] = $sanitizedFilter->getString('className');
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
     * @param  $row
     * @return \Xibo\Entity\Connector
     */
    private function hydrate($row): Connector
    {
        $connector = new Connector($this->getStore(), $this->getLog(), $this->getDispatcher());
        $connector->hydrate($row, [
            'intProperties' => ['isEnabled', 'isVisible']
        ]);

        if (empty($row['settings'])) {
            $connector->settings = [];
        } else {
            $connector->settings = json_decode($row['settings'], true);
        }

        $connector->isSystem = true;

        return $connector;
    }
}
