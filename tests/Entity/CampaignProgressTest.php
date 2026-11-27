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

namespace Xibo\Tests\Entity;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\Campaign;
use Xibo\Factory\CampaignFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Tests for Campaign::getProgress(): the per-day target of an ad campaign follows its exact length.
 */
class CampaignProgressTest extends TestCase
{
    private const START = 1795651200;

    public function testACampaignShorterThanADayIsSharedOutProRata(): void
    {
        $campaign = $this->adCampaign(self::START, self::START + 16 * 3600, 600);

        $progress = $campaign->getProgress(Carbon::createFromTimestamp(self::START + 3600));

        $this->assertSame(0, $progress->daysTotal);
        $this->assertEqualsWithDelta(900.0, $progress->targetPerDay, 0.001);
        $this->assertEqualsWithDelta(6.25, $progress->progressTime, 0.001);
    }

    public function testACampaignOfWholeDaysKeepsItsTargetPerDay(): void
    {
        $campaign = $this->adCampaign(self::START, self::START + 7 * 86400, 7000);

        $progress = $campaign->getProgress(Carbon::createFromTimestamp(self::START + 86400));

        $this->assertSame(7, $progress->daysTotal);
        $this->assertEqualsWithDelta(1000.0, $progress->targetPerDay, 0.001);
    }

    public function testACampaignWithTheSameStartAndEndCountsAsOneDay(): void
    {
        $campaign = $this->adCampaign(self::START, self::START, 600);

        $progress = $campaign->getProgress(Carbon::createFromTimestamp(self::START - 3600));

        $this->assertSame(1, $progress->daysTotal);
        $this->assertEqualsWithDelta(600.0, $progress->targetPerDay, 0.001);
    }

    private function adCampaign(int $startDt, int $endDt, int $target): Campaign
    {
        $campaign = new Campaign(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(LogServiceInterface::class),
            new EventDispatcher(),
            $this->createMock(CampaignFactory::class),
            null,
            null,
            null
        );
        $campaign->type = 'ad';
        $campaign->startDt = $startDt;
        $campaign->endDt = $endDt;
        $campaign->target = $target;
        $campaign->targetType = 'plays';
        $campaign->plays = 0;

        return $campaign;
    }
}
