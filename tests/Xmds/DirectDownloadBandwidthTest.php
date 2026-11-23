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

namespace Xibo\Tests\Xmds;

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\RequiredFile;
use Xibo\Factory\BandwidthFactory;
use Xibo\Xmds\DirectDownloadBandwidth;

class DirectDownloadBandwidthTest extends TestCase
{
    /**
     * A required file whose claim succeeds when $claimWins, and which is never claimed when $claims is 0.
     */
    private function file(
        int $size,
        int $isDirect,
        int $complete,
        int $claims,
        bool $claimWins = true
    ): RequiredFile {
        $file = $this->getMockBuilder(RequiredFile::class)->disableOriginalConstructor()
            ->onlyMethods(['save', 'claimComplete'])->getMock();
        $file->expects($this->exactly($claims))->method('claimComplete')->willReturnCallback(
            function () use ($file, $claimWins) {
                $file->complete = 1;
                return $claimWins;
            }
        );
        $file->size = $size;
        $file->isDirect = $isDirect;
        $file->complete = $complete;
        return $file;
    }

    private function bandwidthFactory(?int $expectedSize): BandwidthFactory
    {
        $factory = $this->createMock(BandwidthFactory::class);
        if ($expectedSize === null) {
            $factory->expects($this->never())->method('createAndSave');
        } else {
            $factory->expects($this->once())->method('createAndSave')
                ->with(Bandwidth::$GETFILE, 7, $expectedSize);
        }
        return $factory;
    }

    public function testDirectFilesFirstReportedCompleteAreCountedInOneRecord()
    {
        $bandwidth = new DirectDownloadBandwidth($this->bandwidthFactory(350), 7);

        $media = $this->file(100, 1, 0, 1);
        $media->expects($this->never())->method('save');
        $dependency = $this->file(250, 1, 0, 1);
        $dependency->expects($this->never())->method('save');

        $bandwidth->setComplete($media, 1);
        $bandwidth->setComplete($dependency, 1);
        $bandwidth->save();
    }

    public function testAClaimLostToAnotherInventoryIsNotCounted()
    {
        $bandwidth = new DirectDownloadBandwidth($this->bandwidthFactory(null), 7);

        $bandwidth->setComplete($this->file(100, 1, 0, 1, false), 1);
        $bandwidth->save();
    }

    public function testFilesTheCmsSentOrAlreadyCountedAreOnlySaved()
    {
        $bandwidth = new DirectDownloadBandwidth($this->bandwidthFactory(null), 7);

        $cases = [
            'sent by the CMS' => [$this->file(100, 0, 0, 0), 1],
            'already complete' => [$this->file(100, 1, 1, 0), 1],
            'not downloaded yet' => [$this->file(100, 1, 0, 0), 0],
            'missing again' => [$this->file(100, 1, 1, 0), 0],
        ];

        foreach ($cases as $name => [$file, $complete]) {
            $file->expects($this->once())->method('save');
            $bandwidth->setComplete($file, $complete);
            $this->assertSame($complete, $file->complete, $name);
        }
        $bandwidth->save();
    }
}
