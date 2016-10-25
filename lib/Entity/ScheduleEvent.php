<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ScheduleEvent.php)
 */


namespace Xibo\Entity;

/**
 * Class ScheduleEvent
 * @package Xibo\Entity
 */
class ScheduleEvent implements \JsonSerializable
{
    use EntityTrait;

    public $fromDt;
    public $toDt;

    /**
     * ScheduleEvent constructor.
     * @param $fromDt
     * @param $toDt
     */
    public function __construct($fromDt, $toDt)
    {
        $this->fromDt = $fromDt;
        $this->toDt = $toDt;
    }
}