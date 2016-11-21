<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Bandwidth.php)
 */


namespace Xibo\Entity;
use Xibo\Exception\DeadlockException;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


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
    public static $BLACKLIST = 10;
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