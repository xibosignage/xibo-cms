<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (PlayerActionHelperInterface.php)
 */


namespace Xibo\Service;


use Xibo\Entity\Display;
use Xibo\Exception\ConfigurationException;
use Xibo\XMR\PlayerAction;

/**
 * Interface PlayerActionServiceInterface
 * @package Xibo\Service
 */
interface PlayerActionServiceInterface
{
    /**
     * PlayerActionHelper constructor.
     * @param ConfigServiceInterface
     * @param LogServiceInterface
     * @param bool
     */
    public function __construct($config, $log, $triggerPlayerActions);

    /**
     * @param Display[]|Display $displays
     * @param PlayerAction $action
     * @throws ConfigurationException
     */
    public function sendAction($displays, $action);

    /**
     * Process the Queue of Actions
     */
    public function processQueue();
}