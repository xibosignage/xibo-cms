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

namespace Xibo\Tests\Service;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Xibo\Entity\SavedReport;
use Xibo\Factory\SavedReportFactory;
use Xibo\Report\ReportInterface;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportService;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;

/**
 * Converting a saved report stores the new zip under a new key and deletes the original only once committed.
 */
class SavedReportConvertTest extends TestCase
{
    private const ORIGINAL_KEY = 'rs_1_100.zip';

    private string $library;

    /** @var string[] what happens, in order */
    private array $events = [];

    /** @var callable[] work waiting for the transaction to commit */
    private array $afterCommit = [];

    /** @var array{0: int, 1: string}|null size and md5 of the zip when it was moved into storage */
    private ?array $movedFile = null;

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/saved-report-convert-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->library . 'temp', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->library));
    }

    private function reportService(): ReportService
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) {
            $this->afterCommit[] = $callback;
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            $this->events[] = 'record:' . $params['state'] . ':' . $params['key'];
            return $params['state'] === 'pending_upload' ? 3 : 4;
        });
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            $this->events[] = 'remove:' . $params['id'];
            return 1;
        });

        // The original zip, as stored by an older version.
        $originalZip = $this->library . 'original.zip';
        $zip = new \ZipArchive();
        $zip->open($originalZip, \ZipArchive::CREATE);
        $zip->addFromString('reportschedule.json', '{"old":true}');
        $zip->close();

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('downloadToTemp')->with(self::ORIGINAL_KEY)->willReturn($originalZip);
        $storage->method('moveFromLocalPath')->willReturnCallback(function (string $path, string $key) {
            $this->events[] = 'move:' . $key;
            $this->movedFile = [filesize($path), md5_file($path)];
            unlink($path);
        });
        $storage->method('delete')->willReturnCallback(function (string $key) {
            $this->events[] = 'delete:' . $key;
        });
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::SAVED_REPORT)->willReturn($storage);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('libraryStorageRegistry')->willReturn($registry);

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'LIBRARY_LOCATION' ? $this->library : null
        );

        $savedReport = $this->createMock(SavedReport::class);
        $savedReport->fileName = self::ORIGINAL_KEY;
        $savedReports = $this->createMock(SavedReportFactory::class);
        $savedReports->method('getById')->willReturn($savedReport);

        $report = $this->createMock(ReportInterface::class);
        $report->method('restructureSavedReportOldJson')->willReturn(['new' => true]);

        $reportService = $this->getMockBuilder(ReportService::class)
            ->setConstructorArgs([
                $container,
                $store,
                null,
                $this->createMock(LogServiceInterface::class),
                $config,
                null,
                $savedReports,
            ])
            ->onlyMethods(['getReportClass', 'createReportObject'])
            ->getMock();
        $reportService->method('getReportClass')->willReturn('Report');
        $reportService->method('createReportObject')->willReturn($report);

        return $reportService;
    }

    public function testTheOriginalIsDeletedOnlyAfterTheConvertedZipIsCommitted()
    {
        $converted = $this->reportService()->convertSavedReportResults(1, 'proofofplay');

        $this->assertSame('rs_1_100_v2.zip', $converted['fileName']);
        $this->assertSame($this->movedFile, [$converted['size'], $converted['md5']]);
        $this->assertSame(
            [
                'record:pending_upload:rs_1_100_v2.zip',
                'move:rs_1_100_v2.zip',
                'record:pending_delete:' . self::ORIGINAL_KEY,
            ],
            $this->events,
            'nothing is cleared or deleted before commit'
        );

        foreach ($this->afterCommit as $callback) {
            $callback();
        }

        // The delete is queued first, as recording it opens the transaction the upload's clearing waits for.
        $this->assertSame(
            ['delete:' . self::ORIGINAL_KEY, 'remove:4', 'remove:3'],
            array_slice($this->events, 3)
        );
        $this->assertSame(['.', '..'], scandir($this->library . 'temp'));
    }

    public function testAStoredZipReturnsTheJournalHoldingItsUploadRecord()
    {
        $stored = $this->reportService()->storeSavedReportZip(['rows' => [1]], 'rs_1_200.zip');

        $this->assertInstanceOf(LibraryFileJournal::class, $stored['journal']);
        $this->assertSame(3, $stored['journalId']);

        $stored['journal']->clearUploadAfterCommit($stored['journalId']);
        foreach ($this->afterCommit as $callback) {
            $callback();
        }
        $this->assertSame(['record:pending_upload:rs_1_200.zip', 'move:rs_1_200.zip', 'remove:3'], $this->events);
    }
}
