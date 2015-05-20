<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Transition.php)
 */


namespace Xibo\Entity;


class Transition
{
    use EntityTrait;

    public $transitionId;
    public $transition;
    public $code;
    public $hasDirection;
    public $hasDuration;
    public $availableAsIn;
    public $availableAsOut;

    public function getId()
    {
        return $this->transitionId;
    }

    public function getOwnerId()
    {
        return 1;
    }
}