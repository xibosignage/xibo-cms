<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

namespace Xibo\Entity;

use OpenApi\Attributes as OA;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Connector\ConnectorInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Represents the database object for a Connector
 */
#[OA\Schema]
class Connector implements \JsonSerializable
{
    use EntityTrait;

    // Status properties
    public $isInstalled = true;
    public $isSystem = true;

    // Database properties
    public $connectorId;
    public $className;
    public $settings;
    public $isEnabled;
    public $isVisible;

    // Decorated properties
    public $title;
    public $description;
    public $thumbnail;

    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * @param \Xibo\Connector\ConnectorInterface $connector
     * @return $this
     */
    public function decorate(ConnectorInterface $connector): Connector
    {
        $this->title = $connector->getTitle();
        $this->description = $connector->getDescription();
        $this->thumbnail = $connector->getThumbnail();
        if (empty($this->thumbnail)) {
            $this->thumbnail = 'theme/default/img/connectors/placeholder.png';
        }
        return $this;
    }

    public function save(): void
    {
        if ($this->connectorId == null || $this->connectorId == 0) {
            $this->add();
            $this->audit($this->connectorId, 'Added');
        } else {
            $this->edit();
            $this->audit($this->connectorId, 'Saved');
        }
    }

    private function add(): void
    {
        $this->connectorId = $this->getStore()->insert('
          INSERT INTO `connectors` (`className`, `isEnabled`, `isVisible`, `settings`)
            VALUES (:className, :isEnabled, :isVisible, :settings)
        ', [
            'className' => $this->className,
            'isEnabled' => $this->isEnabled,
            'isVisible' => $this->isVisible,
            'settings' => json_encode($this->settings)
        ]);
    }

    private function edit(): void
    {
        $this->getStore()->update('
          UPDATE `connectors` SET
              `className` = :className,
              `isEnabled` = :isEnabled,
              `isVisible` = :isVisible,
              `settings` = :settings
           WHERE connectorId = :connectorId
        ', [
            'connectorId' => $this->connectorId,
            'className' => $this->className,
            'isEnabled' => $this->isEnabled,
            'isVisible' => $this->isVisible,
            'settings' => json_encode($this->settings)
        ]);
    }

    /**
     * @return void
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function delete(): void
    {
        if ($this->isSystem) {
            throw new InvalidArgumentException(
                __('Sorry we cannot delete a system connector.'),
                'isSystem'
            );
        }

        $this->getStore()->update('DELETE FROM `connectors` WHERE connectorId = :connectorId', [
            'connectorId' => $this->connectorId
        ]);

        $this->audit($this->connectorId, 'Deleted');
    }
}
