<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (RevertToSchedule.php)
 */


namespace Xibo\XMR;


class RevertToSchedule extends PlayerAction
{
    public function getMessage()
    {
        $this->action = 'revertToSchedule';

        return $this->serializeToJson();
    }
}