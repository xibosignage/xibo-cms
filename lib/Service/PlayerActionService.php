<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Service;


use Xibo\Entity\Display;
use Xibo\Helper\Environment;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;
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