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
use Xibo\Event\DisplayAuthoriseEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Tests for Display::save() asking DisplayAuthoriseEvent listeners before a display is authorised.
 */
class DisplayAuthoriseTest extends TestCase
{
    private StorageServiceInterface&MockObject $store;
    private EventDispatcher $dispatcher;

    /** @var string[] event names in the order they were dispatched */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->store = $this->createMock(StorageServiceInterface::class);
        $this->dispatcher = new EventDispatcher();

        foreach ([
            DisplayAuthoriseEvent::$NAME,
            DisplayAuthoriseEvent::$NAME_SAVED,
        ] as $name) {
            $this->dispatcher->addListener($name, function () use ($name) {
                $this->dispatched[] = $name;
            });
        }
    }

    public function testAuthorisingAsksBeforeSavingAndTellsAfter(): void
    {
        $this->store->expects($this->atLeastOnce())->method('update');
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame([DisplayAuthoriseEvent::$NAME, DisplayAuthoriseEvent::$NAME_SAVED], $this->dispatched);
    }

    public function testRefusalStopsTheSave(): void
    {
        $this->refuseWith('Every display licence is in use.');
        $this->store->expects($this->never())->method('update');
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        try {
            $display->save(Display::$saveOptionsMinimum);
            $this->fail('The save was not refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Every display licence is in use.', $e->getMessage());
        }

        $this->assertSame([DisplayAuthoriseEvent::$NAME], $this->dispatched);
    }

    public function testRefusedSaveAsksAgainNextTime(): void
    {
        $this->refuseWith('The licensing server is busy.');
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        foreach ([1, 2] as $attempt) {
            try {
                $display->save(Display::$saveOptionsMinimum);
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }

        $this->assertSame([DisplayAuthoriseEvent::$NAME, DisplayAuthoriseEvent::$NAME], $this->dispatched);
    }

    public function testAuthorisationAskedBeforeTheSaveIsNotAskedAgain(): void
    {
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        // As RegisterDisplay does for a new display.
        $this->assertNull($display->requestAuthorisation());
        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame([DisplayAuthoriseEvent::$NAME, DisplayAuthoriseEvent::$NAME_SAVED], $this->dispatched);
    }

    public function testRefusalAskedBeforeTheSaveIsToldOnceSavedUnauthorised(): void
    {
        $this->refuseWith('Every display licence is in use.');
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        // As RegisterDisplay does for a new display: refused, so saved waiting for authorisation.
        $this->assertNotNull($display->requestAuthorisation());
        $display->licensed = 0;
        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame([DisplayAuthoriseEvent::$NAME, DisplayAuthoriseEvent::$NAME_SAVED], $this->dispatched);
    }

    public function testSecondSaveDoesNotAuthoriseAgain(): void
    {
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        $display->save(Display::$saveOptionsMinimum);
        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame([DisplayAuthoriseEvent::$NAME, DisplayAuthoriseEvent::$NAME_SAVED], $this->dispatched);
    }

    public function testFailedSaveIsNotToldSaved(): void
    {
        $this->store->method('update')->willThrowException(new \PDOException('Duplicate entry'));
        $display = $this->display(currentlyLicensed: 0);
        $display->licensed = 1;

        try {
            $display->save(Display::$saveOptionsMinimum);
            $this->fail('The save did not fail.');
        } catch (\PDOException) {
            // Expected: the listener undoes its work when the transaction rolls back.
        }

        $this->assertSame([DisplayAuthoriseEvent::$NAME], $this->dispatched);
    }

    public function testSavingAnAuthorisedOrUnauthorisedDisplayAsksNothing(): void
    {
        $authorised = $this->display(currentlyLicensed: 1);
        $authorised->licensed = 1;
        $authorised->save(Display::$saveOptionsMinimum);

        $unauthorised = $this->display(currentlyLicensed: 1);
        $unauthorised->licensed = 0;
        $unauthorised->save(Display::$saveOptionsMinimum);

        $this->assertSame([], $this->dispatched);
    }

    private function refuseWith(string $message): void
    {
        $this->dispatcher->addListener(
            DisplayAuthoriseEvent::$NAME,
            fn (DisplayAuthoriseEvent $event) => $event->refuse($message)
        );
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
        $display->displayGroupId = 8;
        $display->display = 'Lobby';
        $display->license = 'hw-1';
        $display->licensed = $currentlyLicensed;

        // As DisplayFactory loads it.
        (new \ReflectionProperty(Display::class, 'currentlyLicensed'))->setValue($display, $currentlyLicensed);
        $display->setOriginals();

        return $display;
    }
}
