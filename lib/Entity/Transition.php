<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Transition.php)
 */


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class Transition
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Transition
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The transition ID")
     * @var int
     */
    public $transitionId;

    /**
     * @SWG\Property(description="The transition name")
     * @var string
     */
    public $transition;

    /**
     * @SWG\Property(description="Code for transition")
     * @var string
     */
    public $code;

    /**
     * @SWG\Property(description="Flag indicating whether this is a directional transition")
     * @var int
     */
    public $hasDirection;

    /**
     * @SWG\Property(description="Flag indicating whether this transition has a duration option")
     * @var int
     */
    public $hasDuration;

    /**
     * @SWG\Property(description="Flag indicating whether this transition should be available for IN assignments")
     * @var int
     */
    public $availableAsIn;

    /**
     * @SWG\Property(description="Flag indicating whether this transition should be available for OUT assignments")
     * @var int
     */
    public $availableAsOut;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

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

        $this->getStore()->update('
            UPDATE `transition` SET AvailableAsIn = :availableAsIn, AvailableAsOut = :availableAsOut WHERE transitionID = :transitionId
        ', [
            'availableAsIn' => $this->availableAsIn,
            'availableAsOut' => $this->availableAsOut,
            'transitionId' => $this->transitionId
        ]);
    }
}