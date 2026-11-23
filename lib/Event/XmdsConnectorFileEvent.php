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

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Xibo\Entity\Widget;

class XmdsConnectorFileEvent extends Event
{
    public static $NAME = 'connector.xmds.file.event';
    private $widget;
    private $response;
    /**
     * @var boolean
     */
    private $isDebug;

    public function __construct($widget, $isDebug = false)
    {
        $this->widget = $widget;
        $this->isDebug = $isDebug;
    }

    /**
     * @return \Xibo\Entity\Widget|null
     */
    public function getWidget(): ?Widget
    {
        return $this->widget;
    }

    public function isDebug(): bool
    {
        return $this->isDebug;
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        if ($this->response === null) {
            $psr17Factory = new Psr17Factory();
            $this->response = $psr17Factory->createResponse(404, 'Not Found');
        }
        return $this->response;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return $this
     */
    public function setResponse(ResponseInterface $response): XmdsConnectorFileEvent
    {
        $this->response = $response;
        return $this;
    }
}
