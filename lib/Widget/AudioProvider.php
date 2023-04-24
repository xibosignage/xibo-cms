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
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Handles setting the correct audio duration.
 */
class AudioProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        $fileName = $durationProvider->getFile();

        $this->getLog()->debug('AudioProvider: fetchDuration with file: ' . $fileName);

        // If we don't have a file name, then we use the default duration of 0 (end-detect)
        if (empty($fileName)) {
            $durationProvider->setDuration(0);
        } else {
            $info = new \getID3();
            $file = $info->analyze($fileName);
            $durationProvider->setDuration(intval($file['playtime_seconds'] ?? 0));
        }
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        // No special cache key requirements.
        return null;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        return null;
    }
}
