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

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Xibo\Entity\Display;
use Xibo\Entity\RequiredFile;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Xmds\LogProcessor;
use Xibo\Xmds\Soap3;

class Soap3GetFileTest extends TestCase
{
    /** @var string[] keys read from storage */
    private array $read = [];

    /** @var array<string, mixed> the request's parameters */
    private array $params = [];

    private function soap(?string $storageEngine = null): Soap3
    {
        $sanitizer = $this->createMock(SanitizerInterface::class);
        $sanitizer->method('getString')->willReturnCallback(fn ($key) => $this->params[$key]);
        $sanitizer->method('getDouble')->willReturnCallback(fn ($key) => $this->params[$key]);

        $soap = $this->getMockBuilder(Soap3::class)->disableOriginalConstructor()
            ->onlyMethods([
                'getSanitizer', 'getConfig', 'isValidServerKey', 'authDisplay', 'checkBandwidth', 'getLog',
                'logBandwidth', 'getStore',
            ])
            ->getMock();
        $soap->method('getSanitizer')->willReturnCallback(function ($params) use ($sanitizer) {
            $this->params = $params;
            return $sanitizer;
        });
        $soap->method('getConfig')->willReturn($this->createMock(ConfigServiceInterface::class));
        $soap->method('isValidServerKey')->willReturn(true);
        $soap->method('authDisplay')->willReturn(true);
        $soap->method('checkBandwidth')->willReturn(true);
        $soap->method('getLog')->willReturn($this->createMock(LogServiceInterface::class));

        $display = $this->getMockBuilder(Display::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $display->displayId = 7;

        $requiredFile = $this->getMockBuilder(RequiredFile::class)->disableOriginalConstructor()
            ->onlyMethods(['save'])->getMock();
        $requiredFile->bytesRequested = 0;
        $requiredFileFactory = $this->createMock(RequiredFileFactory::class);
        $requiredFileFactory->method('getByDisplayAndMedia')->with(7, '42')->willReturn($requiredFile);

        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->with($this->anything(), ['mediaId' => 42])
            ->willReturn([['storedAs' => '42.mp4', 'storageEngine' => $storageEngine]]);
        $soap->method('getStore')->willReturn($store);

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('readRange')->willReturnCallback(function ($key) {
            $this->read[] = $key;
            return Utils::streamFor('chunk');
        });
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::MEDIA, $storageEngine)->willReturn($storage);

        (function () use ($display, $requiredFileFactory, $registry) {
            $this->logProcessor = (new \ReflectionClass(LogProcessor::class))->newInstanceWithoutConstructor();
            $this->display = $display;
            $this->requiredFileFactory = $requiredFileFactory;
            $this->libraryStorageRegistry = $registry;
        })->call($soap);

        return $soap;
    }

    public function testMediaFileIsReadFromItsStorage()
    {
        $file = $this->soap('local')->GetFile('key', 'hardware', '42.mp4', 'media', 0, 5, 3);

        $this->assertSame('chunk', $file);
        $this->assertSame(['42.mp4'], $this->read);
    }

    public function testAnotherFileSharingTheMediaIdIsRefused()
    {
        $soap = $this->soap();

        try {
            $soap->GetFile('key', 'hardware', '42.xlf', 'media', 0, 5, 3);
            $this->fail('A file other than the media\'s own was served.');
        } catch (\SoapFault $fault) {
            $this->assertSame('Requested an invalid file.', $fault->getMessage());
        }
        $this->assertSame([], $this->read);
    }
}
