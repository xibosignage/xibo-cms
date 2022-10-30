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

    /** @inheritDoc */
    public function setFactories($container)
    {
        $this->campaignFactory = $container->get('campaignFactory');
        $this->scheduleFactory = $container->get('scheduleFactory');
        return $this;
    }

    /** @inheritDoc */
    public function run()
    {
        // TODO: Implement run() method.
    }
}
