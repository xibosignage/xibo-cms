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
 * Class LogEntry
 * @package Xibo\Entity
 */
#[OA\Schema]
class LogEntry implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Log ID')]
    public $logId;

    /**
     * @var string
     */
    #[OA\Property(description: 'A unique run number for a set of Log Messages.')]
    public $runNo;

    /**
     * @var int
     */
    #[OA\Property(description: 'A timestamp representing the CMS date this log message occured')]
    public $logDate;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Channel that generated this message. WEB/API/MAINT/TEST')]
    public $channel;

    /**
     * @var string
     */
    #[OA\Property(description: 'The requested route')]
    public $page;

    /**
     * @var string
     */
    #[OA\Property(description: 'The request method, GET/POST/PUT/DELETE')]
    public $function;

    /**
     * @var string
     */
    #[OA\Property(description: 'The log message')]
    public $message;

    /**
     * @var int
     */
    #[OA\Property(description: 'The display ID this message relates to or NULL for CMS')]
    public $displayId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Log Level')]
    public $type;

    /**
     * @var string
     */
    #[OA\Property(description: 'The display this message relates to or CMS for CMS.')]
    public $display;

    /**
     * @var int
     */
    #[OA\Property(description: 'Session history id.')]
    public $sessionHistoryId;

    /**
     * @var int
     */
    #[OA\Property(description: 'User id.')]
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
}