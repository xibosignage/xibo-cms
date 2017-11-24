<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (ClearStatsAndLogsAction.php)
 */


namespace Xibo\XMR;

/**
 * Class ClearStatsAndLogsAction
 * @package Xibo\XMR
 */
class ClearStatsAndLogsAction extends PlayerAction
{
    public function getMessage()
    {
        $this->action = 'clearStatsAndLogs';

        return $this->serializeToJson();
    }
}