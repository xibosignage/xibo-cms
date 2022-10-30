<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

namespace Xibo\XTR;

use Carbon\Carbon;

/**
 * Campaign Scheduler task
 * This should be run once per hour to create interrupt schedules for applicable advertising campaigns.
 * The schedules will be created for the following hour.
 */
class CampaignSchedulerTask implements TaskInterface
{
    use TaskTrait;

    /** @var \Xibo\Factory\CampaignFactory */
    private $campaignFactory;

    /** @var \Xibo\Factory\ScheduleFactory */
    private $scheduleFactory;

    /** @var \Xibo\Factory\DayPartFactory */
    private $dayPartFactory;

    /** @var \Xibo\Factory\DisplayFactory */
    private $displayFactory;

    /** @inheritDoc */
    public function setFactories($container)
    {
        $this->campaignFactory = $container->get('campaignFactory');
        $this->scheduleFactory = $container->get('scheduleFactory');
        $this->dayPartFactory = $container->get('dayPartFactory');
        $this->displayFactory = $container->get('displayFactory');
        return $this;
    }

    /** @inheritDoc */
    public function run()
    {
        $nextHour = Carbon::now()->startOfHour()->addHour();
        $activeCampaigns = $this->campaignFactory->query(null, [
            'disableUserCheck' => 1,
            'type' => 'ad',
            'startDt' => $nextHour->unix(),
            'endDt' => $nextHour->unix(),
        ]);

        // Sort these by priority.

        // See what we can schedule for each one.
        foreach ($activeCampaigns as $campaign) {
            try {
                // Get displays
                $displays = $this->displayFactory->getByDisplayGroupIds($campaign->loadDisplayGroupIds());

                // What schedules should I create?
                foreach ($campaign->loadLayouts() as $layout) {
                    // Are we on an active day of the week?
                    if (!in_array($nextHour->dayOfWeekIso, $layout->daysOfWeek)) {
                        continue;
                    }

                    // Is this on an active day part?
                    if ($layout->dayPartId != 0) {
                        $dayPart = $this->dayPartFactory->getById($layout->dayPartId);
                        $dayPart->adjustForDate($nextHour);

                        // Is this day part active
                        if (!$nextHour->betweenIncluded($dayPart->adjustedStart, $dayPart->adjustedEnd)) {
                            continue;
                        }
                    }

                    // We are on an active day of the week and within an active day part
                    // create a scheduled event for all displays assigned.
                    // and for each geo fence
                    // how much time do we need to schedule?
                    if (!empty($layout->geoFence)) {
                        // Get some GeoJSON and pull out each Feature (create a schedule for each one)
                    } else {
                        // No geofence, so we just create for each layout/display.
                    }
                }
            } catch (\Exception $exception) {
                $this->log->error('campaignSchedulerTask: ' . $exception->getMessage());
                $this->appendRunMessage($campaign->campaign . ' failed');
            }
        }
    }
}
