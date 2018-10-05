<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScreenShotAction.php)
 */


namespace Xibo\XMR;


class ScreenShotAction extends PlayerAction
{
    public function getMessage()
    {
        $this->action = 'screenShot';

        return $this->serializeToJson();
    }
}