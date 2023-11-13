<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\XMR;

/**
 * Trigger a web hook on the player. This is intended mainly for testing purposes as you'd
 * expect the trigger to be sent locally on the player
 */
class TriggerWebhookAction extends PlayerAction
{
    public $triggerCode;

    public function __construct($triggerCode)
    {
        $this->triggerCode = $triggerCode;
        $this->setQos(3);
    }

    /**
     * @return string
     * @throws PlayerActionException
     */
    public function getMessage(): string
    {
        $this->action = 'triggerWebhook';

        if ($this->triggerCode == '') {
            throw new PlayerActionException('Layout Details not provided');
        }

        return $this->serializeToJson(['triggerCode']);
    }
}
