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

use Xibo\Entity\Display;
use Xibo\Support\Exception\GeneralException;
use Xibo\XMR\PlayerAction;

/**
 * Interface PlayerActionServiceInterface
 * @package Xibo\Service
 */
interface PlayerActionServiceInterface
{
    /**
     * PlayerActionHelper constructor.
     */
    public function __construct(ConfigServiceInterface $config, LogServiceInterface $log, bool $triggerPlayerActions);

    /**
     * @param Display[]|Display $displays
     * @param PlayerAction $action
     * @throws GeneralException
     */
    public function sendAction($displays, $action): void;

    /**
     * Get the queue
     */
    public function getQueue(): array;

    /**
     * Process the Queue of Actions
     * @throws GeneralException
     */
    public function processQueue(): void;
}
