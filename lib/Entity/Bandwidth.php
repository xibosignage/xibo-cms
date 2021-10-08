<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

    public $displayId;
    public $type;
    public $size;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    public function save()
    {
        try {
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
            ]);
        } catch (DeadlockException $deadlockException) {
            $this->getLog()->error('Deadlocked inserting bandwidth');
        }
    }
}