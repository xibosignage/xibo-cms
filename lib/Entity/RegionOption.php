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
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class RegionOption
 * @package Xibo\Entity
 */
#[OA\Schema(schema: 'RegionOption')]
class RegionOption implements \JsonSerializable
{
    use EntityTrait;

    #[OA\Property(description: 'The regionId that this Option applies to')]
    /**
     * @var int
     */
    public $regionId;

    #[OA\Property(description: 'The option name')]
    /**
     * @var string
     */
    public $option;

    #[OA\Property(description: 'The option value')]
    /**
     * @var string
     */
    public $value;

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
     * Clone
     */
    public function __clone()
    {
        $this->regionId = null;
    }

    public function save()
    {
        $sql = 'INSERT INTO `regionoption` (`regionId`, `option`, `value`) VALUES (:regionId, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2';
        $this->getStore()->insert($sql, array(
            'regionId' => $this->regionId,
            'option' => $this->option,
            'value' => $this->value,
            'value2' => $this->value,
        ));
    }

    public function delete()
    {
        $sql = 'DELETE FROM `regionoption` WHERE `regionId` = :regionId AND `option` = :option';
        $this->getStore()->update($sql, array('regionId' => $this->regionId, 'option' => $this->option));
    }
}
