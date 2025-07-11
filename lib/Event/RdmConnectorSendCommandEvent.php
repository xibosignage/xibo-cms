<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

namespace Xibo\Event;

/**
 * Event raised when sending commands via connected displays.
 */
class RdmConnectorSendCommandEvent extends Event
{
    public static string $NAME = 'rdmConnector.sendCommand';

    /**
     * var int
     */
    private $displayId;

    /**
     * var string
     */
    private $command;

    /**
     * var array
     */
    private $params;

    /**
     * RdmConnectorSendCommandEvent constructor.
     * @param int $displayId
     * @param string $command
     * @param array $params
     */
    public function __construct(int $displayId, string $command, array $params)
    {
        $this->displayId = $displayId;
        $this->command = $command;
        $this->params = $params;
    }

    /**
     * Get the display ID
     * return int
     */
    public function getDisplayId(): int
    {
        return $this->displayId;
    }

    /**
     * Get the command
     * return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the params
     * return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
