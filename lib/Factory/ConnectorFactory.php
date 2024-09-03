<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Illuminate\Support\Str;
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
    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

    /** @var \Xibo\Service\ConfigServiceInterface */
    private $config;

    /** @var \Xibo\Service\JwtServiceInterface */
    private $jwtService;

    /** @var \Psr\Container\ContainerInterface */
    private $container;

    /** @var \Xibo\Service\PlayerActionServiceInterface */
    private $playerActionService;

    /**
     * @param \Stash\Interfaces\PoolInterface $pool
     * @param \Xibo\Service\ConfigServiceInterface $config
     * @param \Xibo\Service\JwtServiceInterface $jwtService
     * @param \Psr\Container\ContainerInterface $container
     * @param \Xibo\Service\PlayerActionServiceInterface $playerActionService
    */
    public function __construct(
        PoolInterface $pool,
        ConfigServiceInterface $config,
        JwtServiceInterface $jwtService,
        PlayerActionServiceInterface $playerActionService,
        ContainerInterface $container
    ) {
        $this->pool = $pool;
        $this->config = $config;
        $this->jwtService = $jwtService;
        $this->playerActionService = $playerActionService;
        $this->container = $container;
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
     * @param $className
     * @return \Xibo\Entity\Connector[]
     */
    public function getByClassName($className): array
    {
        return $this->query(['className' => $className]);
    }

    /**
     * @return Connector[]
     */
    public function query($filterBy): array
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
     * @param $row
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

        $connector->isSystem = !Str::contains(strtolower($connector->className), '\\custom\\');

        return $connector;
    }

    /**
     * @return Connector[]
     */
    public function getUninstalled(): array
    {
        $connectors = [];

        // Any system connectors are installed by default, so we're only concerned here with custom connectors
        // which we would expect to me in the custom folder.
        foreach (glob(PROJECT_ROOT . '/custom/*.connector') as $file) {
            $config = json_decode(file_get_contents($file), true);
            if (!is_array($config)) {
                $this->getLog()->error('Problem with connector config: '
                    . json_last_error_msg() . ' ' . var_export($config, true));
                continue;
            }
            $connector = $this->hydrate($config);

            // Is this connector already installed?
            if (count($this->getByClassName($connector->className)) > 0) {
                continue;
            }

            $connector->connectorId = str_replace([' ', '.'], '-', basename($file));
            $connector->isInstalled = false;
            $connector->isVisible = 1;
            $connector->isEnabled = 0;
            if (empty($connector->settings)) {
                $connector->settings = [];
            }
            $connectors[] = $connector;
        }

        return $connectors;
    }

    /**
     * @param string $id
     * @return \Xibo\Entity\Connector
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getUninstalledById(string $id): Connector
    {
        $connector = null;
        foreach ($this->getUninstalled() as $item) {
            if ($item->connectorId === $id) {
                $connector = $item;
                break;
            }
        }
        if ($connector === null) {
            throw new NotFoundException(__('Connector not found'), 'id');
        }

        return $connector;
    }
}
