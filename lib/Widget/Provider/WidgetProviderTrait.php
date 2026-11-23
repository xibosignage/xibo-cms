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

namespace Xibo\Widget\Provider;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A trait to set common objects on a Widget Provider Interface
 */
trait WidgetProviderTrait
{
    private $log;
    private $dispatcher;

    public function getLog(): LoggerInterface
    {
        if ($this->log === null) {
            $this->log = new NullLogger();
        }
        return $this->log;
    }

    public function setLog(LoggerInterface $logger): WidgetProviderInterface
    {
        $this->log = $logger;
        return $this;
    }

    /** @inheritDoc */
    public function getDispatcher(): EventDispatcherInterface
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new EventDispatcher();
        }
        return $this->dispatcher;
    }

    /** @inheritDoc */
    public function setDispatcher(EventDispatcherInterface $dispatcher): WidgetProviderInterface
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }
}
