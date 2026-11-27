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

namespace Xibo\Tests\XTR;

use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\Campaign;
use Xibo\Entity\DayPart;
use Xibo\Entity\Display;
use Xibo\Entity\DisplayGroup;
use Xibo\Entity\LayoutOnCampaign;
use Xibo\Entity\Schedule;
use Xibo\Entity\Task;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\XTR\CampaignSchedulerTask;

/**
 * The Campaign Scheduler schedules every active ad campaign, also those shorter than a day, and a campaign
 * that fails does not stop the others.
 */
class CampaignSchedulerTaskTest extends TestCase
{
    private const NOW = 1795651200;

    private CampaignFactory&MockObject $campaignFactory;
    private ScheduleFactory&MockObject $scheduleFactory;
    private StorageServiceInterface&MockObject $store;

    /** @var int[] the campaign id of each saved schedule */
    private array $scheduled = [];

    protected function setUp(): void
    {
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));

        $this->campaignFactory = $this->createMock(CampaignFactory::class);
        $this->scheduleFactory = $this->createMock(ScheduleFactory::class);
        $this->store = $this->createMock(StorageServiceInterface::class);

        // Every campaign shows on display group 5.
        $this->store->method('select')->willReturn([['displayGroupId' => 5]]);

        $this->scheduleFactory->method('createEmpty')->willReturnCallback(function () {
            $schedule = $this->createMock(Schedule::class);
            $schedule->method('save')->willReturnCallback(function () use ($schedule) {
                $this->scheduled[] = $schedule->parentCampaignId;
            });
            return $schedule;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testACampaignShorterThanADayIsScheduledWithTheOthers(): void
    {
        $short = $this->adCampaign(1, 'Short campaign', self::NOW - 3600, self::NOW + 15 * 3600, 600);
        $week = $this->adCampaign(2, 'Week campaign', self::NOW - 3600, self::NOW + 7 * 86400, 7000);
        $this->campaignFactory->method('query')->willReturn([$short, $week]);
        $this->campaignFactory->method('getLinkedLayouts')->willReturn([$this->layout()]);

        $message = $this->runTask()->getRunMessage();

        $this->assertSame([1, 2], $this->scheduled);
        $this->assertStringContainsString('2 campaigns processed, of which 2 were scheduled', $message);
    }

    public function testACampaignThatFailsIsSkippedAndTheOthersAreScheduled(): void
    {
        $broken = $this->adCampaign(1, 'Broken campaign', self::NOW - 3600, self::NOW + 86400, 600);
        $week = $this->adCampaign(2, 'Week campaign', self::NOW - 3600, self::NOW + 7 * 86400, 7000);
        $this->campaignFactory->method('query')->willReturn([$broken, $week]);
        $this->campaignFactory->method('getLinkedLayouts')->willReturnCallback(function (int $campaignId) {
            if ($campaignId === 1) {
                throw new \Error('broken layout assignment');
            }
            return [$this->layout()];
        });

        $message = $this->runTask()->getRunMessage();

        $this->assertSame([2], $this->scheduled);
        $this->assertStringContainsString('Broken campaign failed', $message);
        $this->assertStringContainsString('2 campaigns processed, of which 1 were scheduled', $message);
    }

    private function runTask(): CampaignSchedulerTask
    {
        $displayGroupFactory = $this->createMock(DisplayGroupFactory::class);
        $displayGroupFactory->method('getById')->willReturn($this->createMock(DisplayGroup::class));

        $display = $this->createMock(Display::class);
        $display->licensed = 1;
        $display->loggedIn = 1;
        $display->costPerPlay = 0;
        $display->impressionsPerPlay = 0;
        $displayFactory = $this->createMock(DisplayFactory::class);
        $displayFactory->method('getByDisplayGroupId')->willReturn([$display]);

        $customDayPart = $this->createMock(DayPart::class);
        $customDayPart->dayPartId = 2;
        $dayPartFactory = $this->createMock(DayPartFactory::class);
        $dayPartFactory->method('getCustomDayPart')->willReturn($customDayPart);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['campaignFactory', $this->campaignFactory],
            ['scheduleFactory', $this->scheduleFactory],
            ['dayPartFactory', $dayPartFactory],
            ['displayGroupFactory', $displayGroupFactory],
            ['displayFactory', $displayFactory],
            ['displayNotifyService', $this->createMock(DisplayNotifyServiceInterface::class)],
        ]);

        $task = new CampaignSchedulerTask();
        $task->setConfig($this->createMock(ConfigServiceInterface::class));
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setStore($this->store);
        $task->setTask($this->createMock(Task::class));
        $task->setFactories($container);
        $task->run();

        return $task;
    }

    private function adCampaign(int $campaignId, string $name, int $startDt, int $endDt, int $target): Campaign
    {
        $campaign = new Campaign(
            $this->store,
            $this->createMock(LogServiceInterface::class),
            new EventDispatcher(),
            $this->campaignFactory,
            null,
            null,
            null
        );
        $campaign->campaignId = $campaignId;
        $campaign->campaign = $name;
        $campaign->ownerId = 1;
        $campaign->type = 'ad';
        $campaign->startDt = $startDt;
        $campaign->endDt = $endDt;
        $campaign->target = $target;
        $campaign->targetType = 'plays';
        $campaign->plays = 0;

        return $campaign;
    }

    private function layout(): LayoutOnCampaign
    {
        $layout = new LayoutOnCampaign();
        $layout->layoutId = 10;
        $layout->layoutCampaignId = 11;
        $layout->duration = 30;
        $layout->daysOfWeek = '1,2,3,4,5,6,7';
        $layout->dayPartId = 0;
        $layout->geoFence = null;

        return $layout;
    }
}
