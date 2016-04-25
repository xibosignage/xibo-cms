<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (RekeyAction.php)
 */


namespace Xibo\XMR;


class RekeyAction extends PlayerAction
{
    public function getMessage()
    {
        $this->action = 'rekeyAction';

        return $this->serializeToJson();
    }
}