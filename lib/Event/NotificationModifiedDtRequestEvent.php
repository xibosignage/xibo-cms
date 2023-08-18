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

use Carbon\Carbon;

/**
 * Request for the latest released notification.
 */
class NotificationModifiedDtRequestEvent extends Event
{
    public static $NAME = 'notification.modifiedDt.request.event';

    /** @var int displayId */
    private $displayId;

    /** @var Carbon */
    private $modifiedDt;

    public function __construct(int $displayId)
    {
        $this->displayId = $displayId;
    }

    public function getDisplayId(): int
    {
        return $this->displayId;
    }

    public function setModifiedDt(Carbon $modifiedDt): NotificationModifiedDtRequestEvent
    {
        $this->modifiedDt = $modifiedDt;
        return $this;
    }

    public function getModifiedDt(): ?Carbon
    {
        return $this->modifiedDt;
    }
}
