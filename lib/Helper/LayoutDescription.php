<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

namespace Xibo\Helper;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Xibo\Entity\Task;

class LayoutDescription
{
    // XTR is triggered every minute, a run this late means XTR isn't running
    private const XTR_OVERDUE_SECONDS = 300;

    /**
     * Get the layout status description
     * @param $status
     * @param Task|null $maintenanceTask the Regular Maintenance task, used to say when a pending Layout is notified
     * @return string
     */
    public static function getLayoutStatusDescription($status, ?Task $maintenanceTask = null): string
    {
        return match ($status) {
            Status::$STATUS_VALID => __('This Layout is ready to play'),
            Status::$STATUS_PLAYER => __('There are items on this Layout that can only be assessed by the Display'),
            Status::$STATUS_NOT_BUILT => __('This Layout has not been built yet'),
            Status::$STATUS_PENDING_NOTIFY => self::getPendingNotifyDescription($maintenanceTask),
            default => __('This Layout is invalid and should not be scheduled'),
        };
    }

    /**
     * Get the description for a Layout waiting for Regular Maintenance to notify Displays, saying when that will be
     * @param Task|null $maintenanceTask
     * @return string
     */
    public static function getPendingNotifyDescription(?Task $maintenanceTask): string
    {
        if ($maintenanceTask === null || $maintenanceTask->isActive != 1) {
            return __(
                "This Layout has been built, but Regular Maintenance is disabled so Displays won't be updated"
            );
        }

        // Check the status before nextRunDate(), which sets an error status on an invalid CRON
        if ($maintenanceTask->status == Task::$STATUS_RUNNING) {
            return __('This Layout has been built. Displays will be updated shortly');
        }

        $now = Carbon::now();
        $nextRunDt = $maintenanceTask->nextRunDate();

        // If the XTR is overdue by more than 5 minutes, inform the user
        // Checked before runNow, as a stopped XTR never picks up a Run Now either
        if ($nextRunDt <= $now->format('U') - self::XTR_OVERDUE_SECONDS) {
            return __("This Layout has been built, but XTR is not running so Displays won't be updated");
        }

        // Due, waiting for XTR to start the run
        if ($maintenanceTask->runNow == 1 || $nextRunDt <= $now->format('U')) {
            return __('This Layout has been built. Displays will be updated shortly');
        }

        $relative = DateFormatHelper::createFromTimestamp($nextRunDt)
            ->diffForHumans($now, CarbonInterface::DIFF_RELATIVE_TO_NOW);

        // Relative time
        // e.g. This Layout has been built and Displays will be updated at the next Regular Maintenance run, 3 minutes from now
        return str_replace(
            '%s',
            $relative,
            __('This Layout has been built and Displays will be updated at the next Regular Maintenance run, %s')
        );
    }

    /**
     * Get the layout enable stat description
     * @param $enableStat
     * @return string
     */
    public static function getLayoutEnableStatDescription($enableStat): string
    {
        return match ($enableStat) {
            1 => __('This Layout has enable stat collection set to ON'),
            default => __('This Layout has enable stat collection set to OFF'),
        };
    }
}
