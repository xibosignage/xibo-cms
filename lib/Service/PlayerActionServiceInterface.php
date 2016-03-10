<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (PlayerActionHelperInterface.php)
 */


namespace Xibo\Service;


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
     */
    public function __construct($config);

    /**
     * @param array[Display]|Display $displays
     * @param PlayerAction $action
     * @throws ConfigurationException
     */
    public function sendAction($displays, $action);
}