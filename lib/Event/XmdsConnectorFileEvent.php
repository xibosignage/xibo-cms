<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
