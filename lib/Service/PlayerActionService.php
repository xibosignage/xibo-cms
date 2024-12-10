<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Xibo\Entity\Display;
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
    private ?string $xmrAddress;

    /** @var PlayerAction[] */
    private array $actions = [];

    /**
     * @inheritdoc
     */
    public function __construct(
        private readonly ConfigServiceInterface $config,
        private readonly LogServiceInterface $log,
        private readonly bool $triggerPlayerActions
    ) {
        $this->xmrAddress = null;
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    private function getConfig(): ConfigServiceInterface
    {
        return $this->config;
    }

    /**
     * @inheritdoc
     */
    public function sendAction($displays, $action): void
    {
        if (!$this->triggerPlayerActions) {
            return;
        }

        // XMR network address
        if ($this->xmrAddress == null) {
            $this->xmrAddress = $this->getConfig()->getSetting('XMR_ADDRESS');
        }

        if (empty($this->xmrAddress)) {
            throw new InvalidArgumentException(__('XMR address is not set'), 'xmrAddress');
        }

        if (!is_array($displays)) {
            $displays = [$displays];
        }

        // Send a message to all displays
        foreach ($displays as $display) {
            /* @var Display $display */
            $isEncrypt = false;

            if ($display->xmrChannel == '') {
                throw new InvalidArgumentException(
                    sprintf(
                        __('%s is not configured or ready to receive push commands over XMR. Please contact your administrator.'),//phpcs:ignore
                        $display->display
                    ),
                    'xmrChannel'
                );
            }

            if ($display->clientType !== 'chromeOS') {
                // We also need a xmrPubKey
                $isEncrypt = true;

                if ($display->xmrPubKey == '') {
                    throw new InvalidArgumentException(
                        sprintf(
                            __('%s is not configured or ready to receive push commands over XMR. Please contact your administrator.'),//phpcs:ignore
                            $display->display
                        ),
                        'xmrPubKey'
                    );
                }
            }

            $displayAction = clone $action;

            try {
                $displayAction->setIdentity($display->xmrChannel, $isEncrypt, $display->xmrPubKey ?? null);
            } catch (\Exception $exception) {
                throw new InvalidArgumentException(
                    sprintf(
                        __('%s Invalid XMR registration'),
                        $display->display
                    ),
                    'xmrPubKey'
                );
            }

            // Add to collection
            $this->actions[] = $displayAction;
        }
    }

    /** @inheritDoc */
    public function getQueue(): array
    {
        return $this->actions;
    }

    /**
     * @inheritdoc
     */
    public function processQueue(): void
    {
        if (count($this->actions) > 0) {
            $this->log->debug('Player Action Service is looking to send %d actions', count($this->actions));
        } else {
            return;
        }

        // XMR network address
        if ($this->xmrAddress == null) {
            $this->xmrAddress = $this->getConfig()->getSetting('XMR_ADDRESS');
        }

        $client = new Client($this->config->getGuzzleProxy([
            'base_uri' => $this->getConfig()->getSetting('XMR_ADDRESS'),
        ]));

        $failures = 0;

        // TODO: could I send them all in one request instead?
        foreach ($this->actions as $action) {
            /** @var PlayerAction $action */
            try {
                // Send each action
                $client->post('/', [
                    'json' => $action->finaliseMessage(),
                ]);
            } catch (GuzzleException | PlayerActionException $e) {
                $this->log->error('Player action connection failed. E = ' . $e->getMessage());
                $failures++;
            }
        }

        if ($failures > 0) {
            throw new ConfigurationException(
                sprintf(
                    __('%d of %d player actions failed'),
                    $failures,
                    count($this->actions)
                )
            );
        }
    }
}