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

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\Display;
use Xibo\Event\DisplayLimitEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\XTR\MaintenanceRegularTask;

/**
 * Tests for Regular Maintenance's licence slot validation: with a limit a listener allows it to enforce, it
 * un-authorises the displays over it (DisplayFactory::unauthoriseOverLimit) and lists them in its run message.
 */
class MaintenanceRegularLicenceSlotTest extends TestCase
{
    public function testDisplaysOverTheLimitAreUnauthorisedAndListed(): void
    {
        $factory = $this->createMock(DisplayFactory::class);
        $factory->expects($this->once())->method('unauthoriseOverLimit')
            ->with(10, $this->stringContains('Regular Maintenance'))
            ->willReturn([$this->display('Lobby'), $this->display('Canteen')]);

        $message = $this->runValidation($factory, limit: 10);

        $this->assertStringContainsString('Disabling Lobby', $message);
        $this->assertStringContainsString('Disabling Canteen', $message);
    }

    public function testNothingIsUnauthorisedWhenNotAllowed(): void
    {
        $factory = $this->createMock(DisplayFactory::class);
        $factory->expects($this->never())->method('unauthoriseOverLimit');

        $this->runValidation($factory, limit: 1, canUnauthorise: false);
    }

    public function testALimitOfZeroUnauthorisesNothing(): void
    {
        $factory = $this->createMock(DisplayFactory::class);
        $factory->expects($this->never())->method('unauthoriseOverLimit');

        $this->runValidation($factory, limit: 0);
    }

    // Without a listener (CE) there is no limit, so nothing is un-authorised.
    public function testNoLimitUnauthorisesNothing(): void
    {
        $factory = $this->createMock(DisplayFactory::class);
        $factory->expects($this->never())->method('unauthoriseOverLimit');

        $this->runValidation($factory, limit: null);
    }

    private function display(string $name): Display
    {
        $display = $this->createMock(Display::class);
        $display->display = $name;

        return $display;
    }

    private function runValidation(DisplayFactory $factory, ?int $limit, bool $canUnauthorise = true): ?string
    {
        $dispatcher = new EventDispatcher();
        if ($limit !== null) {
            $dispatcher->addListener(
                DisplayLimitEvent::$NAME,
                fn (DisplayLimitEvent $event) => $event->setLimit($limit, $canUnauthorise)
            );
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn (string $name) => $name === 'displayFactory' ? $factory : null
        );

        $task = new MaintenanceRegularTask();
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setStore($this->createMock(StorageServiceInterface::class));
        $task->setDispatcher($dispatcher);
        $task->setFactories($container);

        (new \ReflectionMethod(MaintenanceRegularTask::class, 'licenceSlotValidation'))->invoke($task);

        return $task->getRunMessage();
    }
}
