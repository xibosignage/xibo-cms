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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * One screenshot kept in a display's history, with the status the player last reported.
 * @package Xibo\Entity
 */
class DisplayScreenshot implements \JsonSerializable
{
    use EntityTrait;

    public $displayScreenshotId;
    public $displayId;

    /** @var int Unix timestamp of when the screenshot arrived. */
    public $createdDt;

    /** @var string File name under the library's screenshots folder. */
    public $storedAs;

    /** @var string|null The player's status window as JSON, or null if it had not reported one. */
    public $statusJson;

    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function save(): void
    {
        if ($this->displayScreenshotId == null) {
            $this->displayScreenshotId = $this->getStore()->insert('
                INSERT INTO `displayscreenshot` (displayId, createdDt, storedAs, statusJson)
                  VALUES (:displayId, :createdDt, :storedAs, :statusJson)
            ', [
                'displayId' => $this->displayId,
                'createdDt' => $this->createdDt,
                'storedAs' => $this->storedAs,
                'statusJson' => $this->statusJson,
            ]);
        } else {
            $this->getStore()->update('
                UPDATE `displayscreenshot`
                   SET createdDt = :createdDt, storedAs = :storedAs, statusJson = :statusJson
                 WHERE displayScreenshotId = :displayScreenshotId
            ', [
                'displayScreenshotId' => $this->displayScreenshotId,
                'createdDt' => $this->createdDt,
                'storedAs' => $this->storedAs,
                'statusJson' => $this->statusJson,
            ]);
        }
    }

    public function delete(): void
    {
        $this->getStore()->update('
            DELETE FROM `displayscreenshot` WHERE displayScreenshotId = :displayScreenshotId
        ', [
            'displayScreenshotId' => $this->displayScreenshotId,
        ]);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'displayScreenshotId' => $this->displayScreenshotId,
            'displayId' => $this->displayId,
            'createdDt' => $this->createdDt,
            'storedAs' => $this->storedAs,
            // Decoded so callers get an object rather than a string holding JSON.
            'status' => $this->statusJson === null ? null : json_decode($this->statusJson, true),
        ];
    }
}
