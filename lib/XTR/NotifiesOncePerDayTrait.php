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

namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Factory\NotificationFactory;

/**
 * Raises a system notification at most once per subject per day.
 */
trait NotifiesOncePerDayTrait
{
    private function notifyOncePerDay(NotificationFactory $factory, string $subject, string $body, string $type): void
    {
        $date = Carbon::now();
        $existing = $factory->getBySubjectAndDate(
            $subject,
            $date->copy()->startOfDay()->format('U'),
            $date->copy()->addDay()->startOfDay()->format('U')
        );

        if (count($existing) <= 0) {
            $factory->createSystemNotification($subject, $body, Carbon::now(), $type)->save();
        }
    }
}
