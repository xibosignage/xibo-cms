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
use Slim\Views\Twig;
use Xibo\Entity\Notification;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Entity\SavedReport;
use Xibo\Entity\Task;
use Xibo\Entity\User;
use Xibo\Entity\UserGroup;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\BrandService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\XTR\ReportScheduleTask;

/**
 * A scheduled report's zip is built in temp, measured, then moved into journalled saved report storage.
 */
class ReportScheduleTaskTest extends TestCase
{
    private string $library;

    /** @var string[] what happens, in order */
    private array $events = [];

    /** @var array{0: int, 1: string}|null size and md5 of the zip when it was moved into storage */
    private ?array $movedFile = null;

    /** @var string|null the first bytes of the PDF when it was moved into attachment storage */
    private ?string $movedPdf = null;

    /** @var array|null the size and md5 the saved report was created with */
    private ?array $createdWith = null;

    /** @var int how many times the schedule was saved */
    private int $scheduleSaves = 0;

    /** @var string|null the task's run message */
    private ?string $runMessage = null;

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/report-schedule-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->library . 'temp', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->library));
    }

    /**
     * @param string $emailOutcome 'none', or how emailing the report goes: 'readFails', 'notified' or 'notificationFails'
     */
    private function runTask(bool $saveFails, string $emailOutcome = 'none', bool $moveFails = false): ReportSchedule
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) {
            $this->events[] = 'commit';
            $callback();
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            $this->events[] = 'record:' . $params['state'] . ':' . $params['assetType'] . ':' . $params['key'];
            return 3;
        });
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            $this->events[] = 'remove:' . $params['id'];
            return 1;
        });

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('moveFromLocalPath')->willReturnCallback(function ($path, $key) use ($moveFails) {
            $this->events[] = 'move:' . $key;
            if ($moveFails) {
                throw new GeneralException('storage outage');
            }
            $this->movedFile = [filesize($path), md5_file($path)];
            unlink($path);
        });
        $storage->method('delete')->willReturnCallback(function (string $key) {
            $this->events[] = 'delete:' . $key;
        });
        $attachmentStorage = $this->createMock(LibraryStorageServiceInterface::class);
        $attachmentStorage->method('moveFromLocalPath')->willReturnCallback(function (string $path, string $key) {
            $this->events[] = 'moveAttachment:' . $key;
            $this->movedPdf = file_get_contents($path, false, null, 0, 5);
            unlink($path);
        });
        $attachmentStorage->method('delete')->willReturnCallback(function (string $key) {
            $this->events[] = 'deleteAttachment:' . $key;
        });
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->willReturnCallback(fn (string $asset) => match ($asset) {
            LibraryAsset::SAVED_REPORT => $storage,
            LibraryAsset::ATTACHMENT => $attachmentStorage,
        });

        $schedule = $this->createMock(ReportSchedule::class);
        $schedule->reportScheduleId = 1;
        $schedule->reportName = 'proofofplay';
        $schedule->filterCriteria = $emailOutcome === 'none' ? '{}' : '{"sendEmail":1}';
        $schedule->schedule = '0 0 1 1 *';
        $schedule->lastRunDt = 0;
        $schedule->fromDt = 0;
        $schedule->toDt = 0;
        $schedule->isActive = 1;
        $schedule->userId = 1;
        $schedule->method('save')->willReturnCallback(function () {
            $this->scheduleSaves++;
        });

        $schedules = $this->createMock(ReportScheduleFactory::class);
        $schedules->method('query')->willReturn([$schedule]);

        $savedReport = $this->createMock(SavedReport::class);
        $savedReport->userId = 1;
        $savedReport->method('save')->willReturnCallback(function () use ($savedReport, $saveFails) {
            if ($saveFails) {
                throw new GeneralException('save failed');
            }
            $savedReport->savedReportId = 5;
        });
        $savedReports = $this->createMock(SavedReportFactory::class);
        $savedReports->method('create')->willReturnCallback(
            function ($saveAs, $scheduleId, $generatedOn, $userId, $fileName, $size, $md5) use ($savedReport) {
                $this->events[] = 'create:' . $fileName;
                $this->createdWith = [$size, $md5];
                return $savedReport;
            }
        );

        $users = $this->createMock(UserFactory::class);
        $user = $this->createMock(User::class);
        $user->groupId = 2;
        $users->method('getById')->willReturn($user);
        $userGroups = $this->createMock(UserGroupFactory::class);
        $userGroups->method('getById')->willReturn($this->createMock(UserGroup::class));

        $notification = $this->createMock(Notification::class);
        $notification->method('save')->willReturnCallback(function () use ($notification, $emailOutcome) {
            if ($emailOutcome === 'notificationFails') {
                throw new GeneralException('notification save failed');
            }
            $this->events[] = 'notify:' . $notification->filename;
            $notification->notificationId = 9;
        });
        $notifications = $this->createMock(NotificationFactory::class);
        $notifications->method('createEmpty')->willReturn($notification);

        $view = $this->createMock(Twig::class);
        $view->method('fetch')->willReturn('<p>Report</p>');

        $reportService = null;
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(function ($id) use (
            $users,
            $savedReports,
            $schedules,
            &$reportService,
            $registry,
            $userGroups,
            $notifications,
            $view
        ) {
            return match ($id) {
                'view' => $view,
                'userFactory' => $users,
                'userGroupFactory' => $userGroups,
                'notificationFactory' => $notifications,
                'savedReportFactory' => $savedReports,
                'reportScheduleFactory' => $schedules,
                'reportService' => $reportService,
                'libraryStorageRegistry' => $registry,
                'brandService' => new BrandService($this->library),
                default => null,
            };
        });

        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(
            fn ($setting) => $setting === 'LIBRARY_LOCATION' ? $this->library : null
        );

        // The real service builds and stores the zip; only the report itself is stubbed.
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
            ->onlyMethods([
                'generateSavedReportName',
                'runReport',
                'getReportEmailTemplate',
                'getSavedReportResults',
                'getReportByName',
            ])
            ->getMock();
        $reportService->method('generateSavedReportName')->willReturn('Report');
        $reportService->method('runReport')->willReturn(['rows' => [1, 2, 3]]);
        $reportService->method('getReportEmailTemplate')->willReturn('bandwidth-email-template.twig');
        $reportService->method('getReportByName')
            ->willReturn((object)['description' => 'Proof of Play', 'output_type' => 'table']);
        // Only emailing reads the saved report back.
        $savedReportRead = $reportService->expects($emailOutcome === 'none' ? $this->never() : $this->once())
            ->method('getSavedReportResults');
        if ($emailOutcome === 'readFails') {
            $savedReportRead->willThrowException(new GeneralException('storage outage'));
        } else {
            $savedReportRead->willReturn(new ReportResult([], [['row' => 1]]));
        }

        $task = new ReportScheduleTask();
        $task->setConfig($config);
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setStore($store);
        $task->setTask($this->createMock(Task::class));
        $task->setFactories($container);
        $task->run();
        $this->runMessage = $task->getRunMessage();

        return $schedule;
    }

    public function testTheZipIsMeasuredBeforeItIsMovedAndItsRecordClearedOnceSaved()
    {
        $this->runTask(false);

        $key = current(array_filter($this->events, fn ($e) => str_starts_with($e, 'move:')));
        $key = substr($key, 5);
        $this->assertMatchesRegularExpression('/^rs_1_\d+\.zip$/', $key);
        $this->assertSame(
            ['record:pending_upload:savedreport:' . $key, 'move:' . $key, 'create:' . $key, 'commit', 'remove:3'],
            $this->events
        );
        $this->assertSame($this->movedFile, $this->createdWith);
        $this->assertSame(['.', '..'], scandir($this->library . 'temp'));
    }

    public function testAFailedReadBackSkipsTheEmailButKeepsTheScheduleAndItsSavedReport()
    {
        $schedule = $this->runTask(false, 'readFails');

        $this->assertSame(1, $schedule->isActive);
        $this->assertSame(5, $schedule->lastSavedReportId);
        $this->assertNotContains('delete:', array_map(fn ($e) => substr($e, 0, 7), $this->events));
    }

    public function testAZipWhoseSavedReportFailsToSaveIsDeleted()
    {
        $schedule = $this->runTask(true);

        $key = substr(current(array_filter($this->events, fn ($e) => str_starts_with($e, 'move:'))), 5);
        $this->assertSame(['delete:' . $key, 'remove:3'], array_slice($this->events, -2));
        $this->assertSame(0, $schedule->isActive);
        $this->assertSame('save failed', $schedule->message);
        $this->assertSame(1, $this->scheduleSaves);
        $this->assertSame(['.', '..'], scandir($this->library . 'temp'));
    }

    public function testAZipThatFailsToMoveIsDiscardedAndKeepsTheScheduleActiveForItsNextRun()
    {
        $schedule = $this->runTask(false, 'none', true);

        $key = substr(current(array_filter($this->events, fn ($e) => str_starts_with($e, 'move:'))), 5);
        $this->assertSame(
            ['record:pending_upload:savedreport:' . $key, 'move:' . $key, 'delete:' . $key, 'remove:3'],
            $this->events
        );
        $this->assertSame(1, $schedule->isActive);
        $this->assertSame('storage outage', $schedule->message);
        $this->assertStringContainsString('storage outage', $this->runMessage);
        $this->assertGreaterThan(0, $schedule->lastRunDt);
        $this->assertSame(1, $this->scheduleSaves);
        $this->assertSame(['.', '..'], scandir($this->library . 'temp'));
    }

    public function testTheEmailedPdfIsMovedIntoJournalledAttachmentStorageAndNotified()
    {
        $schedule = $this->runTask(false, 'notified');

        $this->assertSame([
            'record:pending_upload:attachment:filename-5.pdf',
            'moveAttachment:filename-5.pdf',
            'notify:filename-5.pdf',
            'commit',
            'remove:3',
        ], array_slice($this->events, -5));
        $this->assertSame('%PDF-', $this->movedPdf);
        $this->assertSame(5, $schedule->lastSavedReportId);
        $this->assertSame([], glob($this->library . 'temp/*.pdf'));
    }

    public function testThePdfIsDiscardedWhenItsNotificationFailsToSave()
    {
        $schedule = $this->runTask(false, 'notificationFails');

        $this->assertSame([
            'record:pending_upload:attachment:filename-5.pdf',
            'moveAttachment:filename-5.pdf',
            'deleteAttachment:filename-5.pdf',
            'remove:3',
        ], array_slice($this->events, -4));
        $this->assertSame(1, $schedule->isActive, 'the saved report stands without its email');
        $this->assertSame(5, $schedule->lastSavedReportId);
        $this->assertSame([], glob($this->library . 'temp/*.pdf'));
    }
}
