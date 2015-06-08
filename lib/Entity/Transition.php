<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Transition.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;

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

    public function save()
    {
        if ($this->transitionId == null || $this->transitionId == 0)
            throw new \InvalidArgumentException();

        PDOConnect::update('
            UPDATE `transition` SET AvailableAsIn = :availableAsIn, AvailableAsOut = :availableAsOut WHERE transitionID = :transitionId
        ', [
            'availableAsIn' => $this->availableAsIn,
            'availableAsOut' => $this->availableAsOut,
            'transitionId' => $this->transitionId
        ]);
    }
}