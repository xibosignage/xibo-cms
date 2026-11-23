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
use Xibo\Entity\DisplayGroup;
use Xibo\Event\DisplayAuthoriseEvent;
use Xibo\Event\DisplayReleaseEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Tests for Display telling DisplayReleaseEvent listeners once an authorised display is un-authorised or deleted.
 */
class DisplayReleaseTest extends TestCase
{
    private StorageServiceInterface&MockObject $store;
    private DisplayGroupFactory&MockObject $displayGroupFactory;
    private EventDispatcher $dispatcher;

    /** @var bool[] isDelete of each release asked for */
    private array $released = [];

    protected function setUp(): void
    {
        $this->store = $this->createMock(StorageServiceInterface::class);
        $this->displayGroupFactory = $this->createMock(DisplayGroupFactory::class);
        $this->displayGroupFactory->method('getByDisplayId')->willReturn([]);
        $this->displayGroupFactory->method('getById')->willReturn($this->createMock(DisplayGroup::class));

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(DisplayReleaseEvent::$NAME, function (DisplayReleaseEvent $event) {
            $this->released[] = $event->isDelete();
        });
    }

    public function testUnauthorisingIsToldOnceWritten(): void
    {
        $order = [];
        $this->store->method('update')->willReturnCallback(function () use (&$order) {
            $order[] = 'write';
        });
        $this->dispatcher->addListener(DisplayReleaseEvent::$NAME, function () use (&$order) {
            $order[] = 'release';
        });
        $display = $this->display(currentlyLicensed: 1);
        $display->licensed = 0;

        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame([false], $this->released);
        $this->assertSame('release', end($order));
        $this->assertContains('write', $order);
    }

    public function testFailedUnauthoriseIsNotTold(): void
    {
        $this->store->method('update')->willThrowException(new \PDOException('Lock wait timeout'));
        $display = $this->display(currentlyLicensed: 1);
        $display->licensed = 0;

        try {
            $display->save(Display::$saveOptionsMinimum);
            $this->fail('The save did not fail.');
        } catch (\PDOException) {
            // Expected.
        }

        $this->assertSame([], $this->released);
    }

    public function testSecondSaveDoesNotReleaseAgain(): void
    {
        $display = $this->display(currentlyLicensed: 1);
        $display->licensed = 0;

        $display->save(Display::$saveOptionsMinimum);
        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame([false], $this->released);
    }

    public function testSavingWithoutUnauthorisingReleasesNothing(): void
    {
        $authorised = $this->display(currentlyLicensed: 1);
        $authorised->save(Display::$saveOptionsMinimum);

        $unauthorised = $this->display(currentlyLicensed: 0);
        $unauthorised->save(Display::$saveOptionsMinimum);

        $this->assertSame([], $this->released);
    }

    public function testChangingTheKeyWhileUnauthorisingReleasesTheOldKey(): void
    {
        $keys = $this->releasedKeys();
        $display = $this->display(currentlyLicensed: 1);
        $display->licensed = 0;
        $display->license = 'hw-new';

        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame(['hw-1'], $keys->getArrayCopy());
    }

    public function testChangingTheKeyOfAnAuthorisedDisplayReleasesTheOldKeyAndAuthorisesTheNew(): void
    {
        $keys = $this->releasedKeys();
        $authorised = [];
        $this->dispatcher->addListener(
            DisplayAuthoriseEvent::$NAME,
            function (DisplayAuthoriseEvent $event) use (&$authorised) {
                $authorised[] = $event->getDisplay()->license;
            }
        );
        $display = $this->display(currentlyLicensed: 1);
        $display->license = 'hw-new';

        $display->save(Display::$saveOptionsMinimum);
        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame(['hw-new'], $authorised);
        $this->assertSame(['hw-1'], $keys->getArrayCopy());
    }

    public function testRefusedNewKeyKeepsTheOldOne(): void
    {
        $keys = $this->releasedKeys();
        $this->dispatcher->addListener(
            DisplayAuthoriseEvent::$NAME,
            fn (DisplayAuthoriseEvent $event) => $event->refuse('Every display licence is in use.')
        );
        $display = $this->display(currentlyLicensed: 1);
        $display->license = 'hw-new';

        try {
            $display->save(Display::$saveOptionsMinimum);
            $this->fail('The save was not refused.');
        } catch (InvalidArgumentException) {
            // Expected.
        }

        $this->assertSame([], $keys->getArrayCopy());
    }

    public function testChangingTheKeyOfAnUnauthorisedDisplayReleasesNothing(): void
    {
        $display = $this->display(currentlyLicensed: 0);
        $display->license = 'hw-new';

        $display->save(Display::$saveOptionsMinimum);

        $this->assertSame([], $this->released);
    }

    public function testDeletingAfterAKeyChangeReleasesTheLoadedKey(): void
    {
        $keys = $this->releasedKeys();
        $display = $this->display(currentlyLicensed: 1);
        $display->license = 'hw-typed-but-not-saved';

        $display->delete();

        $this->assertSame(['hw-1'], $keys->getArrayCopy());
    }

    public function testDeletingAnAuthorisedDisplayIsToldOnceDeleted(): void
    {
        $deleted = false;
        $this->store->method('update')->willReturnCallback(function (string $sql) use (&$deleted) {
            if (str_contains($sql, 'DELETE FROM `display` ')) {
                $deleted = true;
            }
        });
        $this->dispatcher->addListener(DisplayReleaseEvent::$NAME, function () use (&$deleted) {
            $this->assertTrue($deleted, 'told before the display was deleted');
        });

        $this->display(currentlyLicensed: 1)->delete();

        $this->assertSame([true], $this->released);
    }

    public function testFailedDeleteIsNotTold(): void
    {
        $this->store->method('update')->willThrowException(new \PDOException('Lock wait timeout'));

        try {
            $this->display(currentlyLicensed: 1)->delete();
            $this->fail('The delete did not fail.');
        } catch (\PDOException) {
            // Expected.
        }

        $this->assertSame([], $this->released);
    }

    public function testDeletingAnUnauthorisedDisplayReleasesNothing(): void
    {
        $this->display(currentlyLicensed: 0)->delete();

        $this->assertSame([], $this->released);
    }

    /**
     * The hardware keys released, as they are.
     */
    private function releasedKeys(): \ArrayObject
    {
        $keys = new \ArrayObject();
        $this->dispatcher->addListener(DisplayReleaseEvent::$NAME, function (DisplayReleaseEvent $event) use ($keys) {
            $keys->append($event->getHardwareKey());
        });

        return $keys;
    }

    private function display(int $currentlyLicensed): Display
    {
        $display = new Display(
            $this->store,
            $this->createMock(LogServiceInterface::class),
            $this->dispatcher,
            $this->createMock(ConfigServiceInterface::class),
            $this->displayGroupFactory,
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
