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
        return $this;
    }

    public function save(): void
    {
        if ($this->connectorId == null || $this->connectorId == 0) {
            $this->add();
            // Settings hold credentials (API keys): audit which there are, never their values.
            $this->audit($this->connectorId, 'Added', array_merge(
                $this->toArray(),
                ['settings' => array_keys(is_array($this->settings) ? $this->settings : [])]
            ));
        } else {
            $this->edit();
            // Which settings changed, by name only: their values can be credentials.
            $this->audit($this->connectorId, 'Saved', array_merge(
                $this->getChangedProperties(),
                ['settingsChanged' => $this->getChangedSettingNames()]
            ));
        }
    }

    /**
     * The names of the settings added, removed or changed since the connector was loaded.
     * @return string[]
     */
    private function getChangedSettingNames(): array
    {
        // Loaded from the database as JSON (ConnectorFactory decodes it after hydrating).
        $original = $this->getOriginalValue('settings');
        if (is_string($original)) {
            $original = json_decode($original, true);
        }
        $original = is_array($original) ? $original : [];
        $current = is_array($this->settings) ? $this->settings : [];

        $changed = [];
        foreach (array_unique(array_merge(array_keys($original), array_keys($current))) as $name) {
            if (!array_key_exists($name, $original)
                || !array_key_exists($name, $current)
                || json_encode($original[$name]) !== json_encode($current[$name])
            ) {
                $changed[] = (string)$name;
            }
        }

        return $changed;
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
