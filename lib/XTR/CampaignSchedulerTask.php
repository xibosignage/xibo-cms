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
use GeoJson\Feature\FeatureCollection;
use GeoJson\GeoJson;
use Xibo\Entity\DayPart;
use Xibo\Entity\Schedule;

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

    /** @var \Xibo\Factory\DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var \Xibo\Service\DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /** @var \Xibo\Entity\DayPart */
    private $customDayPart = null;

    /** @inheritDoc */
    public function setFactories($container)
    {
        $this->campaignFactory = $container->get('campaignFactory');
        $this->scheduleFactory = $container->get('scheduleFactory');
        $this->dayPartFactory = $container->get('dayPartFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->displayNotifyService = $container->get('displayNotifyService');
        return $this;
    }

    /** @inheritDoc */
    public function run()
    {
        $nextHour = Carbon::now()->startOfHour()->addHour();
        $nextHourEnd = $nextHour->copy()->addHour();
        $activeCampaigns = $this->campaignFactory->query(null, [
            'disableUserCheck' => 1,
            'type' => 'ad',
            'startDt' => $nextHour->unix(),
            'endDt' => $nextHour->unix(),
        ]);

        // Do not schedule more than an hours worth of schedules.
        $totalSovAvailable = 3600;

        // See what we can schedule for each one.
        $notifyDisplayGroupIds = [];
        foreach ($activeCampaigns as $campaign) {
            try {
                $this->log->debug('campaignSchedulerTask: active campaign found, id: ' . $campaign->campaignId);

                // Display groups
                $displayGroups = [];
                foreach ($campaign->loadDisplayGroupIds() as $displayGroupId) {
                    $displayGroups[] = $this->displayGroupFactory->getById($displayGroupId);

                    // Add to our list of displays to notify once finished.
                    if (!in_array($displayGroupId, $notifyDisplayGroupIds)) {
                        $notifyDisplayGroupIds[] = $displayGroupId;
                    }
                }

                $this->log->debug('campaignSchedulerTask: campaign has ' . count($displayGroups) . ' displays');

                // What schedules should I create?
                $activeLayouts = [];
                foreach ($campaign->loadLayouts() as $layout) {
                    $this->log->debug('campaignSchedulerTask: layout assignment: ' . $layout->layoutId);

                    // Is the layout value
                    if ($layout->duration <= 0) {
                        $this->log->error('campaignSchedulerTask: layout without duration');
                        continue;
                    }

                    // Are we on an active day of the week?
                    if (!in_array($nextHour->dayOfWeekIso, explode(',', $layout->daysOfWeek))) {
                        $this->log->debug('campaignSchedulerTask: day of week not active');
                        continue;
                    }

                    // Is this on an active day part?
                    if ($layout->dayPartId != 0) {
                        $this->log->debug('campaignSchedulerTask: dayPartId set, testing');

                        // Check the day part
                        $dayPart = $this->dayPartFactory->getById($layout->dayPartId);
                        $dayPart->adjustForDate($nextHour);

                        // Is this day part active
                        if (!$nextHour->betweenIncluded($dayPart->adjustedStart, $dayPart->adjustedEnd)) {
                            $this->log->debug('campaignSchedulerTask: dayPart not active');
                            continue;
                        }
                    }

                    $this->log->debug('campaignSchedulerTask: layout is valid and needs a schedule');
                    $activeLayouts[] = $layout;
                }

                $countActiveLayouts = count($activeLayouts);
                $this->log->debug('campaignSchedulerTask: there are ' . $countActiveLayouts . ' active layouts');

                if ($countActiveLayouts <= 0) {
                    $this->log->debug('campaignSchedulerTask: no active layouts for campaign');
                    continue;
                }

                // What is the total amount of time we want this campaign to play in this hour period?
                // We work out how much we should have played vs how much we have played
                $progress = $campaign->getProgress($nextHour->copy());

                // TODO: need to think about this.
                //  can we calculate a fudge factor for whether we over or under schedule?
                $playsNeeded = $progress->targetPerDay / 24;

                // Spread across the layouts
                $playsNeededPerLayout = intval(ceil($playsNeeded / $countActiveLayouts));

                $this->log->debug('campaignSchedulerTask: playsNeededPerLayout is ' . $playsNeededPerLayout);

                foreach ($activeLayouts as $layout) {
                    // We are on an active day of the week and within an active day part
                    // create a scheduled event for all displays assigned.
                    // and for each geo fence
                    // how much time do we need to schedule?
                    if ($totalSovAvailable <= 0) {
                        $this->log->debug('campaignSchedulerTask: total SOV available has been consumed');
                        break 2;
                    }

                    $schedule = $this->scheduleFactory->createEmpty();
                    $schedule->setCampaignFactory($this->campaignFactory);

                    // Date
                    $schedule->fromDt = $nextHour->unix();
                    $schedule->toDt = $nextHourEnd->unix();

                    // Displays
                    foreach ($displayGroups as $displayGroup) {
                        $schedule->assignDisplayGroup($displayGroup);
                    }

                    // Interrupt Layout
                    $schedule->eventTypeId = Schedule::$INTERRUPT_EVENT;
                    $schedule->userId = $campaign->ownerId;
                    $schedule->parentCampaignId = $campaign->campaignId;
                    $schedule->campaignId = $layout->layoutCampaignId;
                    $schedule->displayOrder = 0;
                    $schedule->isPriority = 0;
                    $schedule->dayPartId = $layout->dayPartId == 0
                        ? $this->getCustomDayPart()->dayPartId
                        : $layout->dayPartId;
                    $schedule->isGeoAware = 0;
                    $schedule->syncTimezone = 0;
                    $schedule->syncEvent = 0;

                    // We cap SOV at 3600
                    $schedule->shareOfVoice = min($playsNeededPerLayout * $layout->duration, $totalSovAvailable);
                    $schedule->maxPlaysPerHour = $playsNeededPerLayout;

                    // Do we have a geofence? (geo schedules do not count against totalSovAvailable)
                    if (!empty($layout->geoFence)) {
                        $this->log->debug('campaignSchedulerTask: layout has a geo fence');
                        $schedule->isGeoAware = 1;

                        // Get some GeoJSON and pull out each Feature (create a schedule for each one)
                        $geoJson = GeoJson::jsonUnserialize(json_decode($layout->geoFence, true));
                        if ($geoJson instanceof FeatureCollection) {
                            $this->log->debug('campaignSchedulerTask: layout has multiple features');
                            foreach ($geoJson->getFeatures() as $feature) {
                                $schedule->geoLocation = json_encode($feature->jsonSerialize());
                                $schedule->save(['notify' => false]);

                                // Clone a new one
                                $schedule = clone $schedule;
                            }
                        } else {
                            $schedule->geoLocation = $layout->geoFence;
                            $schedule->save(['notify' => false]);
                        }
                    } else {
                        // Reduce the total available
                        // (geo schedules do not count against totalSovAvailable)
                        $totalSovAvailable -= $schedule->shareOfVoice;
                        $schedule->save(['notify' => false]);
                    }
                }

                // Handle notify
                foreach ($notifyDisplayGroupIds as $displayGroupId) {
                    $this->displayNotifyService->notifyByDisplayGroupId($displayGroupId);
                }
            } catch (\Exception $exception) {
                $this->log->error('campaignSchedulerTask: ' . $exception->getMessage());
                $this->appendRunMessage($campaign->campaign . ' failed');
            }
        }
    }

    private function getCustomDayPart(): DayPart
    {
        if ($this->customDayPart === null) {
            $this->customDayPart = $this->dayPartFactory->getCustomDayPart();
        }
        return $this->customDayPart;
    }
}
