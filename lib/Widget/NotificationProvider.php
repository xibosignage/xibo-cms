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

namespace Xibo\Widget;

use Carbon\Carbon;
use Xibo\Event\NotificationDataRequestEvent;
use Xibo\Event\NotificationModifiedDtRequestEvent;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderNumItemsTrait;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

class NotificationProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;
    use DurationProviderNumItemsTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        $this->getLog()->debug('fetchData: NotificationProvider passing to event');
        $this->getDispatcher()->dispatch(
            new NotificationDataRequestEvent($dataProvider),
            NotificationDataRequestEvent::$NAME
        );
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        return null;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        $event = new NotificationModifiedDtRequestEvent($dataProvider->getDisplayId());
        $this->getDispatcher()->dispatch($event, NotificationModifiedDtRequestEvent::$NAME);
        return $event->getModifiedDt();
    }
}
