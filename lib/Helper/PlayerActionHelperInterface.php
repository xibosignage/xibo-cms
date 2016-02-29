<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (PlayerActionHelperInterface.php)
 */


namespace Xibo\Helper;


use Slim\Slim;
use Xibo\Exception\ConfigurationException;
use Xibo\XMR\PlayerAction;

interface PlayerActionHelperInterface
{
    /**
     * PlayerActionHelper constructor.
     * @param Slim $app
     */
    public function __construct($app);

    /**
     * @param array[Display]|Display $displays
     * @param PlayerAction $action
     * @throws ConfigurationException
     */
    public function sendAction($displays, $action);
}