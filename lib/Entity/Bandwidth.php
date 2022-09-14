<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Xibo\Support\Exception\DeadlockException;


/**
 * Class Bandwidth
 * @package Xibo\Entity
 *
 */
class Bandwidth
{
    use EntityTrait;

    public static $REGISTER = 1;
    public static $RF = 2;
    public static $SCHEDULE = 3;
    public static $GETFILE = 4;
    public static $GETRESOURCE = 5;
    public static $MEDIAINVENTORY = 6;
    public static $NOTIFYSTATUS = 7;
    public static $SUBMITSTATS = 8;
    public static $SUBMITLOG = 9;
    public static $REPORTFAULT = 10;
    public static $SCREENSHOT = 11;
    public static $GET_DATA = 12;
    public static $GET_DEPENDENCY = 13;

    public $displayId;
    public $type;
    public $size;

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

    public function save()
    {
        try {
            // This runs on the "isolated" connection because we do not want a failure here to impact the
            // main transaction we've just completed (we log bandwidth at the end).
            // Running on a separate transaction is cleaner than committing what we already have (debatable)
            $this->getStore()->updateWithDeadlockLoop('
                INSERT INTO `bandwidth` (Month, Type, DisplayID, Size)
                  VALUES (:month, :type, :displayId, :size)
                ON DUPLICATE KEY UPDATE Size = Size + :size2
            ', [
                'month' => strtotime(date('m') . '/02/' . date('Y') . ' 00:00:00'),
                'type' => $this->type,
                'displayId' => $this->displayId,
                'size' => $this->size,
                'size2' => $this->size
            ], 'isolated', false, true);
        } catch (DeadlockException $deadlockException) {
            $this->getLog()->error('Deadlocked inserting bandwidth');
        }
    }
}
