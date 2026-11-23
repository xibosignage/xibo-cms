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
use Xibo\Entity\Display;
use Xibo\Entity\RequiredFile;
use Xibo\Factory\BandwidthFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Xmds\LogProcessor;
use Xibo\Xmds\Soap;

class MediaInventoryBandwidthTest extends TestCase
{
    /** @var string[] What was saved, in order */
    private array $saved = [];

    private function file(int $size, int $isDirect): RequiredFile
    {
        $file = $this->getMockBuilder(RequiredFile::class)->disableOriginalConstructor()
            ->onlyMethods(['save', 'claimComplete'])->getMock();
        $file->method('claimComplete')->willReturn(true);
        $file->size = $size;
        $file->isDirect = $isDirect;
        $file->complete = 0;
        return $file;
    }

    private function soap(RequiredFileFactory $requiredFileFactory): Soap
    {
        $sanitizer = $this->createMock(SanitizerInterface::class);
        $sanitizer->method('getString')->willReturnArgument(0);

        $soap = $this->getMockBuilder(Soap::class)->disableOriginalConstructor()
            ->onlyMethods(['getSanitizer', 'isValidServerKey', 'authDisplay', 'checkBandwidth', 'getLog'])
            ->getMock();
        $soap->method('getSanitizer')->willReturn($sanitizer);
        $soap->method('isValidServerKey')->willReturn(true);
        $soap->method('authDisplay')->willReturn(true);
        $soap->method('checkBandwidth')->willReturn(true);
        $soap->method('getLog')->willReturn($this->createMock(LogServiceInterface::class));

        $display = $this->getMockBuilder(Display::class)->disableOriginalConstructor()
            ->onlyMethods(['saveMediaInventoryStatus'])->getMock();
        $display->method('saveMediaInventoryStatus')->willReturnCallback(function () {
            $this->saved[] = 'display';
        });
        $display->displayId = 7;

        $bandwidthFactory = $this->createMock(BandwidthFactory::class);
        $bandwidthFactory->method('createAndSave')->willReturnCallback(function ($type, $displayId, $size) {
            $this->saved[] = $type . '/' . $displayId . '/' . $size;
        });

        (function () use ($display, $bandwidthFactory, $requiredFileFactory) {
            $this->logProcessor = (new \ReflectionClass(LogProcessor::class))->newInstanceWithoutConstructor();
            $this->display = $display;
            $this->bandwidthFactory = $bandwidthFactory;
            $this->requiredFileFactory = $requiredFileFactory;
        })->call($soap);

        return $soap;
    }

    public function testOneInventoryCountsItsDirectFilesInOneRecordAfterEverythingElseIsSaved()
    {
        $media = $this->file(100, 1);
        $dependency = $this->file(250, 1);
        $resource = $this->file(0, 0);
        $resource->expects($this->once())->method('save');
        $missing = $this->file(400, 1);
        $missing->expects($this->never())->method('claimComplete');

        $requiredFileFactory = $this->createMock(RequiredFileFactory::class);
        $requiredFileFactory->method('getByDisplayAndMedia')->willReturnCallback(
            fn ($displayId, $id) => match ((int)$id) {
                2 => $media,
                3 => $missing,
                default => throw new NotFoundException(),
            }
        );
        $requiredFileFactory->method('getByDisplayAndDependency')->willReturn($dependency);
        $requiredFileFactory->method('getByDisplayAndWidget')->willReturn($resource);

        $inventory = '<files>'
            . '<file type="media" id="2" complete="1"/>'
            . '<file type="dependency" fileType="font" id="4" complete="1"/>'
            . '<file type="resource" id="1" complete="1"/>'
            . '<file type="media" id="3"/>'
            . '<file type="media" id="99" complete="1"/>'
            . '</files>';

        $result = (fn () => $this->doMediaInventory('key', 'hardware', $inventory))
            ->call($this->soap($requiredFileFactory));

        $this->assertTrue($result);
        $this->assertSame(0, $missing->complete);
        $this->assertSame([
            'display',
            Bandwidth::$MEDIAINVENTORY . '/7/' . strlen($inventory),
            Bandwidth::$GETFILE . '/7/350',
        ], $this->saved);
    }
}
