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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\RequiredFile;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

class RequiredFileTest extends TestCase
{
    private function requiredFile(StorageServiceInterface $store, array $row): RequiredFile
    {
        return (new RequiredFile(
            $store,
            $this->createMock(LogServiceInterface::class),
            $this->createMock(EventDispatcherInterface::class)
        ))->hydrate($row, ['intProperties' => ['bytesRequested', 'complete', 'isDirect']]);
    }

    private const ROW = [
        'rfId' => 3,
        'displayId' => 7,
        'type' => 'M',
        'itemId' => 5,
        'size' => 100,
        'path' => '5.mp4',
        'bytesRequested' => 0,
        'complete' => 0,
        'isDirect' => 0,
        'md5' => 'aaa',
    ];

    public function testANewRowSavesWhetherItsLinkIsDirect()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->once())->method('insert')
            ->with($this->stringContains('isDirect'), $this->callback(fn ($params) => $params['isDirect'] === 1))
            ->willReturn(9);

        $file = new RequiredFile(
            $store,
            $this->createMock(LogServiceInterface::class),
            $this->createMock(EventDispatcherInterface::class)
        );
        $file->displayId = 7;
        $file->type = 'M';
        $file->itemId = 5;
        $file->isDirect = 1;
        $file->save();

        $this->assertSame(9, $file->rfId);
    }

    public function testAChangedLinkModeSizeOrVersionIsSaved()
    {
        foreach (['isDirect' => 1, 'size' => 250, 'md5' => 'bbb'] as $property => $value) {
            $store = $this->createMock(StorageServiceInterface::class);
            $store->expects($this->once())->method('updateWithDeadlockLoop')
                ->with(
                    $this->logicalAnd($this->stringContains('isDirect'), $this->stringContains('size')),
                    $this->callback(fn ($params) => $params[$property] === $value)
                );

            $file = $this->requiredFile($store, self::ROW);
            $file->{$property} = $value;
            $file->save();
        }
    }

    public function testAnUnchangedRowIsNotSaved()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->never())->method('updateWithDeadlockLoop');

        $this->requiredFile($store, self::ROW)->save();
    }

    public function testANewVersionIsCountedAgain()
    {
        $file = $this->requiredFile(
            $this->createMock(StorageServiceInterface::class),
            ['isDirect' => 1, 'complete' => 1] + self::ROW
        );

        $file->setMd5('bbb');

        $this->assertSame('bbb', $file->md5);
        $this->assertSame(0, $file->complete);
    }

    public function testTheSameOrAFirstRecordedVersionKeepsComplete()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $cases = [
            'same version' => ['aaa', 'aaa'],
            'first recorded after the upgrade' => [null, 'aaa'],
            'no version handed out' => ['aaa', null],
        ];

        foreach ($cases as $name => [$stored, $handedOut]) {
            $file = $this->requiredFile($store, ['isDirect' => 1, 'complete' => 1, 'md5' => $stored] + self::ROW);
            $file->setMd5($handedOut);

            $this->assertSame(1, $file->complete, $name);
            $this->assertSame($stored ?? $handedOut, $file->md5, $name);
        }
    }

    public function testAClaimMarksTheFileCompleteOnlyIfItWasNot()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->expects($this->once())->method('update')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('SET `complete` = 1'),
                    $this->stringContains('`rfId` = :rfId'),
                    $this->stringContains('`complete` = 0')
                ),
                ['rfId' => 3]
            )
            ->willReturn(1);

        $file = $this->requiredFile($store, ['isDirect' => 1] + self::ROW);

        $this->assertTrue($file->claimComplete());
        $this->assertSame(1, $file->complete);
    }

    public function testAClaimAnotherRequestMadeFirstIsLost()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('update')->willReturn(0);

        $file = $this->requiredFile($store, ['isDirect' => 1] + self::ROW);

        $this->assertFalse($file->claimComplete());
        $this->assertSame(1, $file->complete);
    }
}
