<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerActionHelper.php)
 */


namespace Xibo\Service;


use Xibo\Entity\Display;
use Xibo\Exception\ConfigurationException;
use Xibo\XMR\PlayerActionException;

/**
 * Class PlayerActionService
 * @package Xibo\Service
 */
class PlayerActionService implements PlayerActionServiceInterface
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @inheritdoc
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    private function getConfig()
    {
        return $this->config;
    }

    /**
     * @inheritdoc
     */
    public function sendAction($displays, $action)
    {
        if (!is_array($displays))
            $displays = [$displays];

        // Check ZMQ
        if (!$this->getConfig()->checkZmq())
            throw new ConfigurationException(__('ZeroMQ is required to send Player Actions. Please check your configuration.'));

        // XMR network address
        $xmrAddress = $this->getConfig()->GetSetting('XMR_ADDRESS');

        if ($xmrAddress == '')
            throw new \InvalidArgumentException(__('XMR address is not set'));

        // Send a message to all displays
        foreach ($displays as $display) {
            /* @var Display $display */
            if ($display->xmrChannel == '' || $display->xmrPubKey == '')
                throw new \InvalidArgumentException(__('This Player is not configured or ready to receive push commands over XMR. Please contact your administrator.'));

            try {
                // Assign the Layout to the Display
                if (!$action->setIdentity($display->xmrChannel, $display->xmrPubKey)->send($xmrAddress))
                    throw new ConfigurationException(__('This command has been refused'));

            } catch (PlayerActionException $sockEx) {
                throw new ConfigurationException(__('Connection Failed'));
            }
        }
    }
}