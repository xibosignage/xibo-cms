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


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class LogEntry
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class LogEntry implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Log ID")
     * @var int
     */
    public $logId;

    /**
     * @SWG\Property(description="A unique run number for a set of Log Messages.")
     * @var string
     */
    public $runNo;

    /**
     * @SWG\Property(description="A timestamp representing the CMS date this log message occured")
     * @var int
     */
    public $logDate;

    /**
     * @SWG\Property(description="The Channel that generated this message. WEB/API/MAINT/TEST")
     * @var string
     */
    public $channel;

    /**
     * @SWG\Property(description="The requested route")
     * @var string
     */
    public $page;

    /**
     * @SWG\Property(description="The request method, GET/POST/PUT/DELETE")
     * @var string
     */
    public $function;

    /**
     * @SWG\Property(description="The log message")
     * @var string
     */
    public $message;

    /**
     * @SWG\Property(description="The display ID this message relates to or NULL for CMS")
     * @var int
     */
    public $displayId;

    /**
     * @SWG\Property(description="The Log Level")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The display this message relates to or CMS for CMS.")
     * @var string
     */
    public $display;

    /**
     * @SWG\Property(description="Session history id.")
     * @var int
     */
    public $sessionHistoryId;

    /**
     * @SWG\Property(description="User id.")
     * @var int
     */
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