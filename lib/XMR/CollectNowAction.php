<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CollectNowAction.php)
 */


namespace Xibo\XMR;

/**
 * Class CollectNowAction
 * @package Xibo\XMR
 */
class CollectNowAction extends PlayerAction
{
    /**
     * @inheritdoc
     */
    public function getMessage()
    {
        $this->setQos(1);
        $this->action = 'collectNow';

        return $this->serializeToJson();
    }
}