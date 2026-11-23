<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */
namespace Xibo\Service;

use Xibo\Entity\XmrTargetInterface;
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
     * @param XmrTargetInterface[]|XmrTargetInterface $displays
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
