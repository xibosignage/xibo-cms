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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\Display;
use Xibo\Event\DisplayLimitEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Tests for Display::isDisplaySlotAvailable(): a display being authorised needs a free licence slot.
 */
class DisplaySlotTest extends TestCase
{
    private StorageServiceInterface&MockObject $store;
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->store = $this->createMock(StorageServiceInterface::class);
        $this->dispatcher = new EventDispatcher();
    }

    public function testWithoutAListenerEveryDisplayIsAuthorised(): void
    {
        $this->assertTrue($this->authorising()->isDisplaySlotAvailable());
    }

    public function testWithoutUsageTheAuthorisedDisplaysAreCounted(): void
    {
        $this->limit(10);
        $this->store->method('lockUntilCommit')->willReturn(true);
        $this->store->method('select')->willReturnOnConsecutiveCalls(
            [['CountLicensed' => 9]],
            [['CountLicensed' => 10]]
        );

        $this->assertTrue($this->authorising()->isDisplaySlotAvailable());
        $this->assertFalse($this->authorising()->isDisplaySlotAvailable());
    }

    public function testUsageGivenByTheListenerIsUsed(): void
    {
        $this->limit(10, used: 10);
        $this->store->method('lockUntilCommit')->willReturn(true);
        $this->store->expects($this->never())->method('select');

        $this->assertFalse($this->authorising()->isDisplaySlotAvailable());
    }

    public function testTheSlotsAreCountedOnceTheLockIsTaken(): void
    {
        // Another request authorises a display while this one waits for the lock: 2 of 3 used before it, 3 after.
        $locked = false;
        $this->store->expects($this->once())
            ->method('lockUntilCommit')
            ->with('xibo_display_slots', $this->anything())
            ->willReturnCallback(function () use (&$locked) {
                $locked = true;
                return true;
            });
        $this->dispatcher->addListener(
            DisplayLimitEvent::$NAME,
            function (DisplayLimitEvent $event) use (&$locked) {
                $event->setLimit(3, false);
                $event->setUsed($locked ? 3 : 2);
            }
        );

        $this->assertFalse($this->authorising()->isDisplaySlotAvailable());
    }

    public function testNoLockWithoutALimit(): void
    {
        $this->store->expects($this->never())->method('lockUntilCommit');

        $this->assertTrue($this->authorising()->isDisplaySlotAvailable());
    }

    public function testATimedOutLockRefusesWithoutCounting(): void
    {
        $this->limit(10, used: 0);
        $this->store->method('lockUntilCommit')->willReturn(false);
        $this->store->expects($this->never())->method('select');

        $this->assertFalse($this->authorising()->isDisplaySlotAvailable());
    }

    public function testATimedOutLockAsksToTryAgain(): void
    {
        $this->limit(10, used: 0);
        $this->store->method('lockUntilCommit')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Another display is being authorised. Please try again.');

        $this->authorising()->save(['validate' => false]);
    }

    public function testAFullLimitSaysEveryLicenceIsInUse(): void
    {
        $this->limit(10, used: 10);
        $this->store->method('lockUntilCommit')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Every display licence is in use');

        $this->authorising()->save(['validate' => false]);
    }

    public function testDisplayDecidedElsewhereIsNotChecked(): void
    {
        $this->limit(10, used: 10);
        $this->dispatcher->addListener(DisplayLimitEvent::$NAME, function (DisplayLimitEvent $event) {
            $this->assertNotNull($event->getDisplay());
            $event->setDecidedElsewhere(true);
        });
        $this->store->expects($this->never())->method('lockUntilCommit');

        $this->assertTrue($this->authorising()->isDisplaySlotAvailable());
    }

    public function testDisplayNotBeingAuthorisedIsNotChecked(): void
    {
        $this->limit(0, used: 10);

        $display = $this->display(currentlyLicensed: 1);
        $display->licensed = 1;
        $this->assertTrue($display->isDisplaySlotAvailable());

        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 0;
        $this->assertTrue($display->isDisplaySlotAvailable());
    }

    private function limit(int $limit, ?int $used = null): void
    {
        $this->dispatcher->addListener(
            DisplayLimitEvent::$NAME,
            function (DisplayLimitEvent $event) use ($limit, $used) {
                $event->setLimit($limit, false);
                $event->setUsed($used);
            }
        );
    }

    private function authorising(): Display
    {
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        return $display;
    }

    private function display(int $currentlyLicensed): Display
    {
        $display = new Display(
            $this->store,
            $this->createMock(LogServiceInterface::class),
            $this->dispatcher,
            $this->createMock(ConfigServiceInterface::class),
            $this->createMock(DisplayGroupFactory::class),
            $this->createMock(DisplayProfileFactory::class),
            $this->createMock(DisplayFactory::class),
            $this->createMock(FolderFactory::class)
        );
        $display->displayId = 7;
        $display->clientType = 'windows';
        $display->licensed = $currentlyLicensed;
        (new \ReflectionProperty(Display::class, 'currentlyLicensed'))->setValue($display, $currentlyLicensed);

        return $display;
    }
}
