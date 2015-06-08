<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Stat.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;

class Stat
{
    use EntityTrait;

    public $statId;
    public $type;
    public $fromDt;
    public $toDt;
    public $displayId;

    public $scheduleId;
    public $layoutId;
    public $mediaId;
    public $tag;

    public function save()
    {
        if ($this->statId == null || $this->statId == 0)
            $this->add();
        else
            $this->edit();
    }

    private function add()
    {
        $this->statId = PDOConnect::insert('
            INSERT INTO `stat` (type, statDate, start, end, scheduleID, displayID, layoutID, mediaID, Tag)
              VALUES (:type, :statdate, :start, :end, :scheduleId, :displayId, :layoutId, :mediaId, :tag)
        ', [
            'type' => $this->type,
            'statDate' => date("Y-m-d H:i:s"),
            'start' => $this->fromDt,
            'end' => $this->toDt,
            'scheduleId' => $this->scheduleId,
            'displayId' => $this->displayId,
            'layoutId' => $this->layoutId,
            'mediaId' => $this->mediaId,
            'tag' => $this->tag
        ]);
    }

    private function edit()
    {
        PDOConnect::update('UPDATE stat SET end = :toDt WHERE statId = :statId', ['statId' => $this->statId, 'toDt' => $this->toDt]);
    }
}