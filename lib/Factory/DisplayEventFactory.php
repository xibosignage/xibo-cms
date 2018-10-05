<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DisplayEventFactory.php)
 */


namespace Xibo\Factory;
use Xibo\Entity\DisplayEvent;

/**
 * Class DisplayEventFactory
 * @package Xibo\Factory
 */
class DisplayEventFactory extends BaseFactory
{
    /**
     * DisplayEventFactory constructor.
     * @param $store
     * @param $log
     * @param $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return DisplayEvent
     */
    public function createEmpty()
    {
        return new DisplayEvent($this->getStore(), $this->getLog());
    }
}