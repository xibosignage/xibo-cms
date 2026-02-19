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
 * Class AuditLog
 * @package Xibo\Entity
 */
#[OA\Schema]
class AuditLog implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Log Id')]
    public $logId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Log Date')]
    public $logDate;

    /**
     * @var int
     */
    #[OA\Property(description: 'The userId of the User that took this action')]
    public $userId;

    /**
     * @var string
     */
    #[OA\Property(description: 'Message describing the action taken')]
    public $message;

    /**
     * @var string
     */
    #[OA\Property(description: 'The effected entity')]
    public $entity;

    /**
     * @var int
     */
    #[OA\Property(description: 'The effected entityId')]
    public $entityId;

    /**
     * @var string
     */
    #[OA\Property(description: 'A JSON representation of the object after it was changed')]
    public $objectAfter;

    /**
     * @var string
     */
    #[OA\Property(description: 'The User Name of the User that took this action')]
    public $userName;

    /**
     * @var string
     */
    #[OA\Property(description: 'The IP Address of the User that took this action')]
    public $ipAddress;

    /**
     * @var int
     */
    #[OA\Property(description: 'Session history id.')]
    public $sessionHistoryId;

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
}
