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
