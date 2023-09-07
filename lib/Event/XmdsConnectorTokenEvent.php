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

namespace Xibo\Event;

/**
 * Event used to generate a token for an XMDS request.
 */
class XmdsConnectorTokenEvent extends Event
{
    public static $NAME = 'connector.xmds.token.event';
    private $displayId;
    private $widgetId;
    private $ttl;
    private $token;

    public function setTargets(int $displayId, int $widgetId): XmdsConnectorTokenEvent
    {
        $this->displayId = $displayId;
        $this->widgetId = $widgetId;
        return $this;
    }

    public function getDisplayId(): int
    {
        return $this->displayId;
    }

    public function getWidgetId(): ?int
    {
        return $this->widgetId;
    }

    public function setTtl(int $ttl): XmdsConnectorTokenEvent
    {
        $this->ttl = $ttl;
        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setToken(string $token): XmdsConnectorTokenEvent
    {
        $this->token = $token;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }
}
