<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleConvertStep.php)
 */


namespace Xibo\Upgrade;


class ScheduleConvertStep implements  Step
{
    public static function doStep()
    {
        // Get all events and their Associated display group id's
        foreach ($this->getStore()->select('SELECT eventId, displayGroupIds FROM `schedule`', []) as $event) {
            // Ping open the displayGroupIds
            $displayGroupIds = explode(',', $event['displayGroupIds']);

            // Construct some SQL to add the link
            $sql = 'INSERT INTO `lkscheduledisplaygroup` (eventId, displayGroupId) VALUES ';

            foreach ($displayGroupIds as $id) {
                $sql .= '(' . $event['eventId'] . ',' . $id . '),';
            }

            $sql = rtrim($sql, ',');

            $this->getStore()->update($sql, []);
        }
    }
}