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

    /** @var \Xibo\Factory\DisplayFactory */
    private $displayFactory;

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
        $this->displayFactory = $container->get('displayFactory');
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

        // We will need to notify some displays at the end.
        $notifyDisplayGroupIds = [];
        $campaignsProcessed = 0;
        $campaignsScheduled = 0;

        // See what we can schedule for each one.
        foreach ($activeCampaigns as $campaign) {
            $campaignsProcessed++;
            try {
                $this->log->debug('campaignSchedulerTask: active campaign found, id: ' . $campaign->campaignId);

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
                        try {
                            $dayPart = $this->dayPartFactory->getById($layout->dayPartId);
                            $dayPart->adjustForDate($nextHour);
                        } catch (\Exception $exception) {
                            $this->log->debug('campaignSchedulerTask: invalid dayPart, e = '
                                . $exception->getMessage());
                            continue;
                        }

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

                // The campaign is active
                // Display groups
                $displayGroups = [];
                $allDisplays = [];
                $countDisplays = 0;
                $costPerPlay = 0;
                $impressionsPerPlay = 0;

                // First pass uses only logged in displays from the display group
                foreach ($campaign->loadDisplayGroupIds() as $displayGroupId) {
                    $displayGroups[] = $this->displayGroupFactory->getById($displayGroupId);

                    // Record ids to notify
                    if (!in_array($displayGroupId, $notifyDisplayGroupIds)) {
                        $notifyDisplayGroupIds[] = $displayGroupId;
                    }

                    foreach ($this->displayFactory->getByDisplayGroupId($displayGroupId) as $display) {
                        // Keep track of these in case we resolve 0 logged in displays
                        $allDisplays[] = $display;
                        if ($display->licensed === 1 && $display->loggedIn === 1) {
                            $countDisplays++;
                            $costPerPlay += $display->costPerPlay;
                            $impressionsPerPlay += $display->impressionsPerPlay;
                        }
                    }
                }

                $this->log->debug('campaignSchedulerTask: campaign has ' . $countDisplays
                    . ' logged in and authorised displays');

                // If there are 0 displays, then process again ignoring the logged in status.
                if ($countDisplays <= 0) {
                    $this->log->debug('campaignSchedulerTask: processing displays again ignoring logged in status');

                    foreach ($allDisplays as $display) {
                        if ($display->licensed === 1) {
                            $countDisplays++;
                            $costPerPlay += $display->costPerPlay;
                            $impressionsPerPlay += $display->impressionsPerPlay;
                        }
                    }
                }

                $this->log->debug('campaignSchedulerTask: campaign has ' . $countDisplays
                    . ' authorised displays');

                if ($countDisplays <= 0) {
                    $this->log->debug('campaignSchedulerTask: skipping campaign due to no authorised displays');
                    continue;
                }

                // What is the total amount of time we want this campaign to play in this hour period?
                // We work out how much we should have played vs how much we have played
                $progress = $campaign->getProgress($nextHour->copy());

                // A simple assessment of how much of the target we need in this hour period (we assume the campaign
                // will play for 24 hours a day and that adjustments to later scheduling will solve any underplay)
                $targetNeededPerDay = $progress->targetPerDay / 24;

                // If we are more than 5% ahead of where we should be, or we are at 100% already, then don't
                // schedule anything else
                if ($progress->progressTarget >= 100) {
                    $this->log->debug('campaignSchedulerTask: campaign has completed, skipping');
                    continue;
                } else if ($progress->progressTime > 0
                    && ($progress->progressTime - $progress->progressTarget + 5) <= 0
                ) {
                    $this->log->debug('campaignSchedulerTask: campaign is 5% or more ahead of schedule, skipping');
                    continue;
                }

                if ($progress->progressTime > 0 && $progress->progressTarget > 0) {
                    // If we're behind, then increase our play rate accordingly
                    $ratio = $progress->progressTime / $progress->progressTarget;
                    $targetNeededPerDay = $targetNeededPerDay * $ratio;

                    $this->log->debug('campaignSchedulerTask: targetNeededPerDay is ' . $targetNeededPerDay
                        . ', adjusted by ' . $ratio);
                }

                // Spread across the layouts
                $targetNeededPerLayout = $targetNeededPerDay / $countActiveLayouts;

                // Modify the target depending on what units it is expressed in
                // This also caters for spreading the target across the active displays because the
                // cost/impressions/displays are sums.
                if ($campaign->targetType === 'budget') {
                    $playsNeededPerLayout = $targetNeededPerLayout / $costPerPlay;
                } else if ($campaign->targetType === 'imp') {
                    $playsNeededPerLayout = $targetNeededPerLayout / $impressionsPerPlay;
                } else {
                    $playsNeededPerLayout = $targetNeededPerLayout / $countDisplays;
                }

                // Take the ceiling because we can't do part plays
                $playsNeededPerLayout = intval(ceil($playsNeededPerLayout));

                $this->log->debug('campaignSchedulerTask: targetNeededPerLayout is ' . $targetNeededPerLayout
                    . ', targetType: ' . $campaign->targetType
                    . ', playsNeededPerLayout: ' . $playsNeededPerLayout
                    . ', there are ' . $countDisplays . ' displays.');

                foreach ($activeLayouts as $layout) {
                    // We are on an active day of the week and within an active day part
                    // create a scheduled event for all displays assigned.
                    // and for each geo fence
                    // Create our schedule
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
                    $schedule->dayPartId = $this->getCustomDayPart()->dayPartId;
                    $schedule->isGeoAware = 0;
                    $schedule->syncTimezone = 0;
                    $schedule->syncEvent = 0;

                    // We cap SOV at 3600
                    $schedule->shareOfVoice = min($playsNeededPerLayout * $layout->duration, 3600);
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
                        $schedule->save(['notify' => false]);
                    }
                }

                // Handle notify
                foreach ($notifyDisplayGroupIds as $displayGroupId) {
                    $this->displayNotifyService->notifyByDisplayGroupId($displayGroupId);
                }

                $campaignsScheduled++;
            } catch (\Exception $exception) {
                $this->log->error('campaignSchedulerTask: ' . $exception->getMessage());
                $this->appendRunMessage($campaign->campaign . ' failed');
            }
        }

        $this->appendRunMessage($campaignsProcessed . ' campaigns processed, of which ' . $campaignsScheduled
            . ' were scheduled. Skipped ' . ($campaignsProcessed - $campaignsScheduled) . ' for various reasons');
    }

    private function getCustomDayPart(): DayPart
    {
        if ($this->customDayPart === null) {
            $this->customDayPart = $this->dayPartFactory->getCustomDayPart();
        }
        return $this->customDayPart;
    }
}
