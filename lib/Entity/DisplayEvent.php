<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DisplayEvent.php)
 */


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayEvent
 * @package Xibo\Entity
 */
class DisplayEvent implements \JsonSerializable
{
    use EntityTrait;

    public $displayEventId;
    public $displayId;
    public $eventDate;
    public $start;
    public $end;

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
        if ($this->displayEventId == null)
            $this->add();
        else
            $this->edit();
    }

    private function add()
    {
        $this->displayEventId = $this->getStore()->insert('
            INSERT INTO `displayevent` (eventDate, start, end, displayID)
              VALUES (:eventDate, :start, :end, :displayId)
        ', [
            'eventDate' => time(),
            'start' => $this->start,
            'end' => $this->end,
            'displayId' => $this->displayId
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('UPDATE `displayevent` SET `end` = :end WHERE statId = :statId', [
            'displayevent' => $this->displayEventId, 'end' => $this->end
        ]);
    }

    /**
     * Record the display coming online
     * @param $displayId
     */
    public function displayUp($displayId)
    {
        $this->getStore()->update('UPDATE `displayevent` SET `end` = :toDt WHERE displayId = :displayId AND `end` IS NULL', [
            'toDt' => time(),
            'displayId' => $displayId
        ]);
    }
}