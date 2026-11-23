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
 * Class PlayerFault
 * @package Xibo\Entity
 */
#[OA\Schema]
class PlayerFault implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Fault Id')]
    public $playerFaultId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Display Id')]
    public $displayId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Date the error occured')]
    public $incidentDt;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Date the error expires')]
    public $expires;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Code associated with the fault')]
    public $code;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Reason for the fault')]
    public $reason;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Layout Id')]
    public $layoutId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Region Id')]
    public $regionId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Schedule Id')]
    public $scheduleId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Widget Id')]
    public $widgetId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Media Id')]
    public $mediaId;

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
     * @return string
     */
    public function __toString()
    {
        return sprintf('Player Fault Id %d, Code %d, Reason %s, Date %s', $this->playerFaultId, $this->code, $this->reason, $this->incidentDt);
    }
}
