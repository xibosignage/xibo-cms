<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CollectNowAction.php)
 */


namespace Xibo\XMR;


class CollectNowAction extends PlayerAction
{
    public function getMessage()
    {
        $this->action = 'collectNow';

        return $this->serializeToJson();
    }
}