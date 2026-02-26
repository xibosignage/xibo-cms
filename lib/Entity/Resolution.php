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
use Respect\Validation\Validator as v;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Resolution
 * @package Xibo\Entity
 */
#[OA\Schema]
class Resolution implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The ID of this Resolution')]
    public $resolutionId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The resolution name')]
    public $resolution;

    /**
     * @var double
     */
    #[OA\Property(description: 'The display width of the resolution')]
    public $width;

    /**
     * @var double
     */
    #[OA\Property(description: 'The display height of the resolution')]
    public $height;

    /**
     * @var double
     */
    #[OA\Property(description: 'The designer width of the resolution')]
    public $designerWidth;

    /**
     * @var double
     */
    #[OA\Property(description: 'The designer height of the resolution')]
    public $designerHeight;

    /**
     * @var int
     */
    #[OA\Property(description: 'The layout schema version')]
    public $version = 2;

    /**
     * @var int
     */
    #[OA\Property(description: 'A flag indicating whether this resolution is enabled or not')]
    public $enabled = 1;

    /**
     * @var int
     */
    #[OA\Property(description: 'The userId who owns this Resolution')]
    public $userId;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->resolutionId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        // No owner
        return $this->userId;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->resolution)) {
            throw new InvalidArgumentException(__('Please provide a name'), 'name');
        }

        if (!v::intType()->notEmpty()->min(1)->validate($this->width)) {
            throw new InvalidArgumentException(__('Please provide a width'), 'width');
        }

        if (!v::intType()->notEmpty()->min(1)->validate($this->height)) {
            throw new InvalidArgumentException(__('Please provide a height'), 'height');
        }

        // Set the designer width and height
        $factor = min (800 / $this->width, 800 / $this->height);

        $this->designerWidth = round($this->width * $factor);
        $this->designerHeight = round($this->height * $factor);
    }

    /**
     * Save
     * @param bool|true $validate
     * @throws InvalidArgumentException
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->resolutionId == null || $this->resolutionId == 0)
            $this->add();
        else
            $this->edit();

        $this->getLog()->audit('Resolution', $this->resolutionId, 'Saving', $this->getChangedProperties());
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM resolution WHERE resolutionID = :resolutionId', ['resolutionId' => $this->resolutionId]);
    }

    private function add()
    {
        $this->resolutionId = $this->getStore()->insert('
          INSERT INTO `resolution` (resolution, width, height, intended_width, intended_height, version, enabled, `userId`)
            VALUES (:resolution, :width, :height, :intended_width, :intended_height, :version, :enabled, :userId)
        ', [
            'resolution' => $this->resolution,
            'width' => $this->designerWidth,
            'height' => $this->designerHeight,
            'intended_width' => $this->width,
            'intended_height' => $this->height,
            'version' => $this->version,
            'enabled' => $this->enabled,
            'userId' => $this->userId
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
          UPDATE resolution SET resolution = :resolution,
                width = :width,
                height = :height,
                intended_width = :intended_width,
                intended_height = :intended_height,
                enabled = :enabled
           WHERE resolutionID = :resolutionId
        ', [
            'resolutionId' => $this->resolutionId,
            'resolution' => $this->resolution,
            'width' => $this->designerWidth,
            'height' => $this->designerHeight,
            'intended_width' => $this->width,
            'intended_height' => $this->height,
            'enabled' => $this->enabled
        ]);
    }
}