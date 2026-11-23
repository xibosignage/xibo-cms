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
use Xibo\Entity\Task;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\XTR\StatsMigrationTask;

/**
 * The stats migration keeps its watermarks in hidden settings, so any web server or task runner can resume it.
 */
class StatsMigrationTaskTest extends TestCase
{
    private string $library;

    private Task $task;

    /** @var array<string, array{value: string, userSee: int, userChange: int}> */
    private array $settings = [];

    /** @var array<int, array> */
    private array $taskUpdates = [];

    private int $taskSaves = 0;

    private int $commits = 0;

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/stats-migration-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->library);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->library . '.*.txt') ?: []);
        rmdir($this->library);
    }

    private function newTask(array $taskOptions = [], int $maxStatId = 0): StatsMigrationTask
    {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->with('LIBRARY_LOCATION')->willReturn($this->library);
        $config->expects($this->never())->method('changeSetting');

        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('select')->willReturnCallback(function ($sql, $params) use ($maxStatId) {
            if (str_contains($sql, 'FROM `setting`')) {
                return isset($this->settings[$params['setting']])
                    ? [['value' => $this->settings[$params['setting']]['value']]]
                    : [];
            }
            return [['statId' => $maxStatId]];
        });
        $store->method('exists')->willReturnCallback(function ($sql, $params) {
            return isset($this->settings[$params['setting']]);
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            $this->assertStringContainsString('INSERT INTO `setting`', $sql);
            $this->settings[$params['setting']] = [
                'value' => (string) $params['value'],
                'userSee' => 0,
                'userChange' => 0,
            ];
            $this->assertStringContainsString('`userSee`, `userChange`', $sql);
            $this->assertStringContainsString(':value, 0, 0', $sql);
            return 1;
        });
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            if (str_contains($sql, 'UPDATE `setting`')) {
                $this->settings[$params['setting']]['value'] = (string) $params['value'];
            } else {
                $this->taskUpdates[] = ['sql' => $sql, 'params' => $params];
            }
            return 1;
        });
        $store->method('commitIfNecessary')->willReturnCallback(function () {
            $this->commits++;
        });

        $this->task = $this->createMock(Task::class);
        $this->task->taskId = 7;
        $this->task->options = $taskOptions;
        $this->task->method('save')->willReturnCallback(function () {
            $this->taskSaves++;
        });

        $task = new StatsMigrationTask();
        $task->setConfig($config);
        $task->setLogger($this->createMock(LogServiceInterface::class));
        $task->setStore($store);
        $task->setTask($this->task);

        return $task;
    }

    private function setting(string $name): ?string
    {
        return $this->settings[$name]['value'] ?? null;
    }

    public function testAFirstRunRecordsTheHighestStatInAHiddenSetting()
    {
        $task = $this->newTask([], 41);

        $this->assertSame(42, $task->getWatermark('watermark_stat_mongo', 'stat'));
        $this->assertSame('41', $this->setting('STATS_MIGRATION_WATERMARK_STAT_MONGO'));
        $this->assertSame(1, $this->commits);
        $this->assertSame(0, $this->taskSaves);
        $this->assertSame([], $this->taskUpdates);
        $this->assertSame([], $this->task->options);
    }

    public function testASavedSettingIsResumedAndWinsOverOptionsAndFiles()
    {
        $this->settings['STATS_MIGRATION_WATERMARK_STAT_MONGO'] = ['value' => '17', 'userSee' => 0, 'userChange' => 0];
        file_put_contents($this->library . '.watermark_stat_mongo.txt', '9');
        $task = $this->newTask(['watermark_stat_mongo' => 5], 41);

        $this->assertSame(18, $task->getWatermark('watermark_stat_mongo', 'stat'));
        $this->assertSame(0, $this->commits);
        $this->assertSame(0, $this->taskSaves);
        $this->assertSame([], $this->taskUpdates);
    }

    public function testAWatermarkInTheTaskOptionsIsAdoptedAndOnlyThatKeyRemoved()
    {
        file_put_contents($this->library . '.watermark_stat_mongo.txt', '9');
        $task = $this->newTask(['watermark_stat_mongo' => 17, 'killSwitch' => 0], 41);

        $this->assertSame(18, $task->getWatermark('watermark_stat_mongo', 'stat'));
        $this->assertSame('17', $this->setting('STATS_MIGRATION_WATERMARK_STAT_MONGO'));
        $this->assertSame(['killSwitch' => 0], $this->task->options);

        $this->assertCount(1, $this->taskUpdates);
        $this->assertStringContainsString('JSON_REMOVE(`options`, :path)', $this->taskUpdates[0]['sql']);
        $this->assertSame(['path' => '$.watermark_stat_mongo', 'taskId' => 7], $this->taskUpdates[0]['params']);
        $this->assertSame(0, $this->taskSaves);
        $this->assertSame(1, $this->commits);
    }

    public function testAWatermarkFileFromAnEarlierVersionIsAdoptedAndRemoved()
    {
        file_put_contents($this->library . '.watermark_stat_archive_mysql.txt', '9');
        $task = $this->newTask([], 41);

        $this->assertSame(10, $task->getWatermark('watermark_stat_archive_mysql', 'stat_archive'));
        $this->assertSame('9', $this->setting('STATS_MIGRATION_WATERMARK_STAT_ARCHIVE_MYSQL'));
        $this->assertFileDoesNotExist($this->library . '.watermark_stat_archive_mysql.txt');
        $this->assertSame(0, $this->taskSaves);
        $this->assertSame([], $this->taskUpdates);
    }

    public function testTheLoopLimitAndTheEndOfRecordsSaveTheWatermarkWithoutSavingTheTask()
    {
        $task = $this->newTask(['killSwitch' => 0]);

        $this->assertTrue($task->checkLoopLimits(5, 5, 'watermark_stat_archive_mongo', 30));
        $this->assertSame('29', $this->setting('STATS_MIGRATION_WATERMARK_STAT_ARCHIVE_MONGO'));

        $this->assertTrue($task->checkEndOfRecords(0, 'watermark_stat_archive_mongo'));
        $this->assertSame('-1', $this->setting('STATS_MIGRATION_WATERMARK_STAT_ARCHIVE_MONGO'));

        $this->assertFalse($task->checkLoopLimits(4, 5, 'watermark_stat_archive_mongo', 30));
        $this->assertFalse($task->checkEndOfRecords(3, 'watermark_stat_archive_mongo'));
        $this->assertSame(2, $this->commits);
        $this->assertSame(0, $this->taskSaves);
        $this->assertSame([], $this->taskUpdates);
        $this->assertSame(['killSwitch' => 0], $this->task->options);
    }

    public function testAFinishedMigrationStaysFinished()
    {
        $this->settings['STATS_MIGRATION_WATERMARK_STAT_MONGO'] = ['value' => '-1', 'userSee' => 0, 'userChange' => 0];
        $task = $this->newTask([], 41);

        $this->assertSame(0, $task->getWatermark('watermark_stat_mongo', 'stat'));
    }
}
