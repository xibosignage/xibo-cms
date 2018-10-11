<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerActionHelper.php)
 */


namespace Xibo\Service;


use Xibo\Entity\Display;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Helper\Environment;
use Xibo\XMR\PlayerAction;
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

    /** @var  LogServiceInterface */
    private $log;

    /** @var bool */
    private $triggerPlayerActions = true;

    /** @var string  */
    private $xmrAddress;

    /** @var array[PlayerAction] */
    private $actions = [];

    /**
     * @inheritdoc
     */
    public function __construct($config, $log, $triggerPlayerActions)
    {
        $this->config = $config;
        $this->log = $log;
        $this->triggerPlayerActions = $triggerPlayerActions;
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
        if (!$this->triggerPlayerActions)
            return;

        // XMR network address
        if ($this->xmrAddress == null)
            $this->xmrAddress = $this->getConfig()->getSetting('XMR_ADDRESS');

        if (!is_array($displays))
            $displays = [$displays];

        // Check ZMQ
        if (!Environment::checkZmq())
            throw new ConfigurationException(__('ZeroMQ is required to send Player Actions. Please check your configuration.'));

        if ($this->xmrAddress == '')
            throw new InvalidArgumentException(__('XMR address is not set'), 'xmrAddress');

        // Send a message to all displays
        foreach ($displays as $display) {
            /* @var Display $display */
            if ($display->xmrChannel == '' || $display->xmrPubKey == '')
                throw new InvalidArgumentException(__('This Player is not configured or ready to receive push commands over XMR. Please contact your administrator.'), 'xmrRegistered');

            $displayAction = clone $action;

            try {
                $displayAction->setIdentity($display->xmrChannel, $display->xmrPubKey);
            } catch (\Exception $exception) {
                throw new InvalidArgumentException(__('Invalid XMR registration'), 'xmrPubKey');
            }

            // Add to collection
            $this->actions[] = $displayAction;
        }
    }

    /**
     * @inheritdoc
     */
    public function processQueue()
    {
        if (count($this->actions) > 0)
            $this->log->debug('Player Action Service is looking to send %d actions', count($this->actions));
        else
            return;

        // XMR network address
        if ($this->xmrAddress == null)
            $this->xmrAddress = $this->getConfig()->getSetting('XMR_ADDRESS');

        $failures = 0;

        foreach ($this->actions as $action) {
            /** @var PlayerAction $action */
            try {
                // Send each action
                if ($action->send($this->xmrAddress) === false) {
                    $this->log->error('Player action refused by XMR (connected but XMR returned false).');
                    $failures++;
                }

            } catch (PlayerActionException $sockEx) {
                $this->log->error('Player action connection failed. E = ' . $sockEx->getMessage());
                $failures++;
            }
        }

        if ($failures > 0)
            throw new ConfigurationException(sprintf(__('%d of %d player actions failed'), $failures, count($this->actions)));
    }
}