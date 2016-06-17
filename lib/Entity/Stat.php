<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Stat.php)
 */


namespace Xibo\Entity;


use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Stat
 * @package Xibo\Entity
 */
class Stat
{
    use EntityTrait;

    public $statId;
    public $type;
    public $fromDt;
    public $toDt;
    public $displayId;

    public $scheduleId = 0;
    public $layoutId = 0;
    public $mediaId = 0;
    public $widgetId = 0;
    public $tag;

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
        if ($this->statId == null || $this->statId == 0)
            $this->add();
        else
            $this->edit();
    }

    private function add()
    {
        $this->statId = $this->getStore()->insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag, `widgetId`)
              VALUES (:type, :statDate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag, :widgetId)
        ', [
            'type' => $this->type,
            'statDate' => date("Y-m-d H:i:s"),
            'start' => $this->fromDt,
            'end' => $this->toDt,
            'scheduleId' => $this->scheduleId,
            'displayId' => $this->displayId,
            'layoutId' => $this->layoutId,
            'mediaId' => $this->mediaId,
            'tag' => $this->tag,
            'widgetId' => $this->widgetId
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('UPDATE stat SET end = :toDt WHERE statId = :statId', ['statId' => $this->statId, 'toDt' => $this->toDt]);
    }
}