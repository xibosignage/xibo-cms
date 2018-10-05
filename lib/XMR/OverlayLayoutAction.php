<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ChangeLayoutAction.php)
 */


namespace Xibo\XMR;

/**
 * Class OverlayLayoutAction
 * @package Xibo\XMR
 */
class OverlayLayoutAction extends PlayerAction
{
    public $layoutId;
    public $duration;
    public $downloadRequired;
    public $changeMode;

    /**
     * Set details for this layout
     * @param int $layoutId the layoutId to change to
     * @param int $duration the duration this layout should be overlaid
     * @param bool|false $downloadRequired flag indicating whether a download is required before changing to the layout
     * @return $this
     */
    public function setLayoutDetails($layoutId, $duration, $downloadRequired = false)
    {
        $this->layoutId = $layoutId;
        $this->duration = $duration;
        $this->downloadRequired = $downloadRequired;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMessage()
    {
        $this->action = 'overlayLayout';

        if ($this->layoutId == 0)
            throw new PlayerActionException(__('Layout Details not provided'));

        if ($this->duration == 0)
            throw new PlayerActionException(__('Duration not provided'));

        return $this->serializeToJson(['layoutId', 'duration', 'downloadRequired']);
    }
}