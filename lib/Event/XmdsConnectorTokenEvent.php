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
