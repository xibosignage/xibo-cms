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

namespace Xibo\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Layout;
use Xibo\Factory\LayoutFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Every XLF build holds the build lock, and a stored XLF found missing when read is built again.
 */
class LayoutFactoryXlfTest extends TestCase
{
    /** @var string[] lock events, in order */
    private array $events = [];

    private function factory(): LayoutFactory
    {
        $factory = $this->getMockBuilder(LayoutFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['concurrentRequestLock', 'concurrentRequestRelease', 'getLog'])
            ->getMock();
        $factory->method('getLog')->willReturn($this->createMock(LogServiceInterface::class));
        $factory->method('concurrentRequestLock')->willReturnCallback(
            function (Layout $layout, bool $force = false) {
                $this->events[] = $force ? 'lock:force' : 'lock';
                return $layout;
            }
        );
        $factory->method('concurrentRequestRelease')->willReturnCallback(
            function (Layout $layout, bool $force = false) {
                $this->events[] = $force ? 'release:force' : 'release';
            }
        );
        return $factory;
    }

    private function layoutNeedingABuild(bool $needsBuild = true): Layout
    {
        $layout = $this->createMock(Layout::class);
        $layout->method('isBuildRequired')->willReturn($needsBuild);
        return $layout;
    }

    public function testABuildHoldsTheLock(): void
    {
        $layout = $this->layoutNeedingABuild();
        $layout->expects($this->once())->method('buildXlf')->with(['notify' => false])
            ->willReturnCallback(function () {
                $this->events[] = 'build';
            });

        $this->factory()->buildXlfLocked($layout, ['notify' => false]);

        $this->assertSame(['lock', 'build', 'release:force'], $this->events);
    }

    public function testALayoutThatNeedsNoBuildNeverReleasesAnotherRequestsLock(): void
    {
        $this->factory()->buildXlfLocked($this->layoutNeedingABuild(false));

        $this->assertSame(['lock'], $this->events);
    }

    public function testAFailedBuildStillReleasesTheLock(): void
    {
        $layout = $this->layoutNeedingABuild();
        $layout->method('buildXlf')->willThrowException(new GeneralException('outage'));

        try {
            $this->factory()->buildXlfLocked($layout);
            $this->fail('The failure is thrown');
        } catch (GeneralException) {
        }

        $this->assertSame(['lock', 'release:force'], $this->events);
    }

    public function testTheBuiltXlfIsRead(): void
    {
        $layout = $this->layoutNeedingABuild();
        $layout->method('getXlf')->willReturn('<layout/>');
        $layout->expects($this->never())->method('forgetStoredXlf');

        $this->assertSame('<layout/>', $this->factory()->getBuiltXlf($layout));
        $this->assertSame(['lock', 'release:force'], $this->events);
    }

    public function testAnXlfMissingFromStorageIsBuiltAgainUnderTheLock(): void
    {
        $layout = $this->layoutNeedingABuild(false);
        $layout->method('getXlf')->willReturnOnConsecutiveCalls(
            $this->throwException(new NotFoundException('missing')),
            '<rebuilt/>'
        );
        $layout->expects($this->once())->method('forgetStoredXlf');
        $builds = [];
        $layout->method('buildXlf')->willReturnCallback(function (array $options) use (&$builds) {
            $builds[] = $options;
        });

        $this->assertSame('<rebuilt/>', $this->factory()->getBuiltXlf($layout));
        $this->assertSame(['lock', 'lock:force', 'release:force'], $this->events);
        // The bytes rarely change, so displays aren't told to collect.
        $this->assertSame([[], ['notify' => false]], $builds);
    }

    /**
     * A factory whose lock was waited for, so it hands back a reloaded layout.
     */
    private function factoryReloading(Layout $reloaded): LayoutFactory
    {
        $factory = $this->getMockBuilder(LayoutFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['concurrentRequestLock', 'concurrentRequestRelease', 'getLog'])
            ->getMock();
        $factory->method('concurrentRequestLock')->willReturn($reloaded);
        return $factory;
    }

    public function testALayoutReloadedAfterWaitingForTheLockIsLeftToDecideWhetherItStillNeedsABuild(): void
    {
        $layout = $this->layoutNeedingABuild();
        $reloaded = $this->createMock(Layout::class);
        $reloaded->expects($this->never())->method('forgetStoredXlf');
        $reloaded->expects($this->once())->method('buildXlf');
        $layout->expects($this->never())->method('buildXlf');

        $this->assertSame($reloaded, $this->factoryReloading($reloaded)->buildXlfLocked($layout));
    }

    public function testAForcedBuildReloadedAfterWaitingForTheLockIsStillBuilt(): void
    {
        $layout = $this->layoutNeedingABuild();
        $reloaded = $this->createMock(Layout::class);
        $reloaded->expects($this->once())->method('forgetStoredXlf');
        $reloaded->expects($this->once())->method('buildXlf');

        $this->assertSame($reloaded, $this->factoryReloading($reloaded)->buildXlfLocked($layout, [], true));
    }
}
