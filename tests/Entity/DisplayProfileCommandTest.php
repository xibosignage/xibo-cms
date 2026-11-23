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

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Command;
use Xibo\Entity\DisplayProfile;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * A display profile links only the commands it gives its own command string, storing that string.
 */
class DisplayProfileCommandTest extends TestCase
{
    /** @var array[] the parameters of each lkcommanddisplayprofile insert */
    private array $links = [];

    /** @var array|null the parameters of the lkcommanddisplayprofile delete */
    private ?array $unlink = null;

    private function command(int $id, ?string $default, ?string $profileString): Command
    {
        $command = new Command(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(LogServiceInterface::class),
            null
        );
        $command->commandId = $id;
        $command->commandString = $default;
        $command->createAlertOn = 'never';
        $command->commandStringDisplayProfile = $profileString;
        return $command;
    }

    /**
     * @param Command[] $commands what the profile loads: every command available on its type
     */
    private function newProfile(array $commands): DisplayProfile
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            if (str_contains($sql, 'INSERT INTO `lkcommanddisplayprofile`')) {
                $this->links[] = $params;
            } else if (str_contains($sql, 'DELETE FROM `lkcommanddisplayprofile`')) {
                $this->unlink = $params;
            }
        });

        $commandFactory = $this->createMock(CommandFactory::class);
        $commandFactory->method('getByDisplayProfileId')->willReturn($commands);
        $displayProfileFactory = $this->createMock(DisplayProfileFactory::class);
        $displayProfileFactory->method('loadForType')->willReturn([]);

        $profile = new DisplayProfile(
            $store,
            $this->createMock(LogServiceInterface::class),
            null,
            $this->createMock(ConfigServiceInterface::class),
            $commandFactory,
            $displayProfileFactory
        );
        $profile->displayProfileId = 6;
        $profile->type = 'chromeOS';
        $profile->load();

        return $profile;
    }

    public function testSaveSkipsCommandsWithOnlyADefault()
    {
        $profile = $this->newProfile([$this->command(1, null, null), $this->command(2, 'default', 'profile')]);

        $profile->save(false);

        $this->assertCount(1, $this->links);
        $this->assertSame(2, $this->links[0]['commandId']);
        $this->assertSame('profile', $this->links[0]['commandString']);
        $this->assertSame(['displayProfileId' => 6, 'commandId1' => 2], $this->unlink);
    }

    public function testAssignedStringIsLinkedAndTheDefaultKept()
    {
        $loaded = $this->command(1, 'default', null);
        $profile = $this->newProfile([$loaded]);

        $profile->assignCommand($this->command(1, 'assigned', null));
        $profile->save(false);

        $this->assertSame('assigned', $this->links[0]['commandString']);
        $this->assertSame('default', $loaded->commandString);
        $this->assertSame('assigned', $loaded->getCommandString());
    }

    public function testUnassignedCommandIsUnlinked()
    {
        $linked = $this->command(1, null, 'profile');
        $profile = $this->newProfile([$linked]);

        $profile->unassignCommand($linked);
        $profile->save(false);

        $this->assertSame([], $this->links);
        $this->assertSame(['displayProfileId' => 6], $this->unlink);
    }
}
