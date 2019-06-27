<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\XTR;
use Xibo\Entity\User;
use Xibo\Factory\TaskFactory;
use Xibo\Factory\UserFactory;

/**
 * Class StatsMigrationTask
 * @package Xibo\XTR
 */
class StatsMigrationTask implements TaskInterface
{
    use TaskTrait;

    /** @var  User */
    private $archiveOwner;

    /** @var UserFactory */
    private $userFactory;

    /** @var TaskFactory */
    private $taskFactory;

    private $archiveExist;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->userFactory = $container->get('userFactory');
        $this->taskFactory = $container->get('taskFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->migrateStats();
    }

    public function migrateStats()
    {
        $this->runMessage = '# ' . __('Stats Migration') . PHP_EOL . PHP_EOL;

        // read configOverride
        $configOverrideFile = $this->getOption('configOverride', '');
        if (file_exists($configOverrideFile)) {

            $contents = json_decode(file_get_contents($configOverrideFile), true);

            $options = [
                'killSwitch' => $contents['killSwitch'],
                'numberOfRecords' => $contents['numberOfRecords'],
                'numberOfLoops' => $contents['numberOfLoops'],
                'pauseBetweenLoops' => $contents['pauseBetweenLoops'],
                'optimiseOnComplete' => $contents['optimiseOnComplete'],
            ];

        } else {

            // Config options
            $options = [
                'killSwitch' => $this->getOption('killSwitch', 0),
                'numberOfRecords' => $this->getOption('numberOfRecords', 10000),
                'numberOfLoops' => $this->getOption('numberOfLoops', 1000),
                'pauseBetweenLoops' => $this->getOption('pauseBetweenLoops', 10),
                'optimiseOnComplete' => $this->getOption('optimiseOnComplete', 1),
            ];
        }

        if ($options['killSwitch'] == 1) {

            // Check stat_archive table exists
            $this->archiveExist = $this->store->exists('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :name', [
                'schema' => $_SERVER['MYSQL_DATABASE'],
                'name' => 'stat_archive'
            ]);

            // Get timestore engine
            $timeSeriesStore = $this->timeSeriesStore->getEngine();

            if ($timeSeriesStore == 'mongodb') {
                $this->moveStatsFromStatArchiveToStatMongoDb($options);
            }

            // If when the task runs it finds that MongoDB is disabled,
            // and there isn't a stat_archive table, then it should disable itself and not run again
            // (work is considered to be done at that point).
            else {

                if ($this->archiveExist == true) {
                    $this->moveStatsFromStatArchiveToStatMysql($options);

                } else {
                    // Disable the task
                    $this->getTask()->isActive = 0;
                    $this->getTask()->save();
                }
            }
        }
    }

    public function moveStatsFromStatArchiveToStatMysql($options = [])
    {

        $fileName = $this->config->getSetting('LIBRARY_LOCATION') . '.watermark_stat_archive_mysql.txt';

        $options = array_merge([
            'numberOfRecords' => 10000,
            'numberOfLoops' => 1000,
            'pauseBetweenLoops' => 10,
        ], $options);

        // Get low watermark from file
        $watermark = $this->getWatermarkFromFile($fileName, 'stat_archive');

        $numberOfLoops = 0;
        $count = 0;
        while ($watermark > 0) {

            $stats = $this->store->getConnection()
                ->prepare('SELECT * FROM stat_archive WHERE statId < :watermark ORDER BY statId DESC LIMIT :limit');
            $stats->bindParam(':watermark', $watermark, \PDO::PARAM_INT);
            $stats->bindParam(':limit', $options['numberOfRecords'], \PDO::PARAM_INT);

            // Run the select
            $stats->execute();

            // Keep count how many stats we've inserted
            $recordCount = $stats->rowCount();
            $count+= $recordCount;

            // End of records
            if ($this->checkEndOfRecords($recordCount, $fileName) === true) {

                // Drop the stat_archive table
                $this->store->update('DROP TABLE `stat_archive`;', []);
                break;
            }

            // Loops limit end - task will need to rerun again to start from the saved watermark
            if ($this->checkLoopLimits($numberOfLoops, $options['numberOfLoops'], $fileName, $watermark) === true) {
                break;
            }
            $numberOfLoops++;

            foreach ($stats->fetchAll() as $stat) {

                $columns = 'type, statDate, scheduleId, displayId, campaignId, layoutId, mediaId, widgetId, `start`, `end`, tag, duration, `count`';
                $values = ':type, :statDate, :scheduleId, :displayId, :campaignId, :layoutId, :mediaId, :widgetId, :start, :end, :tag, :duration, :count';

                $params = [
                    'type' => $stat['type'],
                    'statDate' =>  $this->date->parse($stat['statDate'])->format('U'),
                    'scheduleId' => (int) $stat['scheduleId'],
                    'displayId' => (int) $stat['displayId'],
                    'campaignId' => (int) $stat['campaignId'],
                    'layoutId' => (int) $stat['layoutId'],
                    'mediaId' => (int) $stat['mediaId'],
                    'widgetId' => (int) $stat['widgetId'],
                    'start' => $this->date->parse($stat['start'])->format('U'),
                    'end' => $this->date->parse($stat['end'])->format('U'),
                    'tag' => $stat['tag'],
                    'duration' => isset($stat['duration']) ? (int) $stat['duration'] : $this->date->parse($stat['end'])->format('U') - $this->date->parse($stat['start'])->format('U'),
                    'count' => isset($stat['count']) ? (int) $stat['count'] : 1,
                ];

                $watermark = $stat['statId'];

                // Do the insert
                $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);
                $this->store->commitIfNecessary();

            }

            // Give SQL time to recover
            if ($watermark > 0) {
                $this->log->debug('Stats migration effected '.$count.' rows, sleeping.');
                sleep($options['pauseBetweenLoops']);
            }
        }

    }

    public function moveStatsFromStatArchiveToStatMongoDb($options = [])
    {

        $options = array_merge([

            'numberOfRecords' => 10000,
            'numberOfLoops' => 1000,
            'pauseBetweenLoops' => 10,
        ], $options);

        // Migration from stat table to Mongo
        $this->migrationStatToMongo($options);

        // Migration from stat_archive table to Mongo
        // After migration delete only stat_archive
        if ($this->archiveExist == true) {
            $this->migrationStatArchiveToMongo($options);
        }
    }

    function migrationStatToMongo($options)
    {
        // Stat Archive Task
        $archiveTask = $this->taskFactory->getByClass('\Xibo\XTR\\StatsArchiveTask');

        $fileName = $this->config->getSetting('LIBRARY_LOCATION') . '.watermark_stat_mongo.txt';

        // Get low watermark from file
        $watermark = $this->getWatermarkFromFile($fileName, 'stat');

        $sql = $this->store->getConnection()->prepare('SELECT statId FROM stat WHERE statId < :watermark ORDER BY statId DESC LIMIT 1');
        $sql->bindParam(':watermark', $watermark, \PDO::PARAM_INT);
        $sql->execute();

        // Mark the Stats Archiver as disabled if there are records in stat table and set option archiveStats off
        if ($sql->rowCount() > 0) {

            // Quit the StatsArchiveTask if it is running
            if ($archiveTask->runNow == 1) {
                $this->log->debug('Quitting the stat migration task as stat archive task is running');
                return;
            }
            $archiveTask->isActive = 0;
            $archiveTask->options['archiveStats'] = 'Off';
            $archiveTask->save();
            $this->store->commitIfNecessary();
        }

        $numberOfLoops = 0;
        $count = 0;
        while ($watermark > 0) {

            $stats = $this->store->getConnection()
                ->prepare('SELECT * FROM stat WHERE statId < :watermark ORDER BY statId DESC LIMIT :limit');
            $stats->bindParam(':watermark', $watermark, \PDO::PARAM_INT);
            $stats->bindParam(':limit', $options['numberOfRecords'], \PDO::PARAM_INT);

            // Run the select
            $stats->execute();

            // Keep count how many stats we've inserted
            $recordCount = $stats->rowCount();
            $count+= $recordCount;

            // End of records
            if ($this->checkEndOfRecords($recordCount, $fileName) === true) {

                // Enable the StatsArchiver task and set option archiveStats on
                $archiveTask->isActive = 1;
                $archiveTask->options['archiveStats'] = 'On';
                $archiveTask->save();
                $this->store->commitIfNecessary();

                $this->log->debug('End of records in stat . Truncate and Optimize');

                // Truncate stat table
                $this->store->update('TRUNCATE TABLE stat', []);

                // Optimize stat table
                if ($options['optimiseOnComplete'] == 1) {
                    $this->store->update('OPTIMIZE TABLE stat', []);
                }

                break;
            }

            // Loops limit end - task will need to rerun again to start from the saved watermark
            if ($this->checkLoopLimits($numberOfLoops, $options['numberOfLoops'], $fileName, $watermark) === true) {
                break;
            }
            $numberOfLoops++;

            $statDataMongo = [];

            foreach ($stats->fetchAll() as $stat) {

                $entry = [];

                $entry['type'] = $stat['type'];
                $entry['fromDt'] = $this->date->parse($stat['start'], 'U')->format('Y-m-d H:i:s');
                $entry['toDt'] = $this->date->parse($stat['end'], 'U')->format('Y-m-d H:i:s');
                $entry['scheduleId'] = $stat['scheduleId'];
                $entry['mediaId'] = $stat['mediaId'];
                $entry['layoutId'] = $stat['layoutId'];
                $entry['displayId'] = $stat['displayId'];
                $entry['campaignId'] = $stat['campaignId'];
                $entry['tag'] = $stat['tag'];
                $entry['widgetId'] = $stat['widgetId'];
                $entry['duration'] = $stat['duration'];
                $entry['count'] = $stat['count'];

                $statDataMongo[] = $entry;

                $watermark = $stat['statId'];
            }

            // Do the insert in chunk
            if (count($statDataMongo) > 0) {
                $this->timeSeriesStore->addStat($statDataMongo);
            } else {
                $this->log->debug('No stat to migrate from stat to mongo');
            }

            // Give Mongo time to recover
            if ($watermark > 0) {
                $this->log->debug('Stats migration effected '.$count.' rows, sleeping.');
                sleep($options['pauseBetweenLoops']);
            }
        }
    }

    function migrationStatArchiveToMongo($options)
    {

        $fileName = $this->config->getSetting('LIBRARY_LOCATION') . '.watermark_stat_archive_mongo.txt';

        // Get low watermark from file
        $watermark = $this->getWatermarkFromFile($fileName, 'stat_archive');

        $numberOfLoops = 0;
        $count = 0;
        while ($watermark > 0) {

            $stats = $this->store->getConnection()
                ->prepare('SELECT * FROM stat_archive WHERE statId < :watermark ORDER BY statId DESC LIMIT :limit');
            $stats->bindParam(':watermark', $watermark, \PDO::PARAM_INT);
            $stats->bindParam(':limit', $options['numberOfRecords'], \PDO::PARAM_INT);

            // Run the select
            $stats->execute();

            // Keep count how many stats we've inserted
            $recordCount = $stats->rowCount();
            $count+= $recordCount;

            // End of records
            if ($this->checkEndOfRecords($recordCount, $fileName) === true) {

                // Drop the stat_archive table
                $this->store->update('DROP TABLE `stat_archive`;', []);
                break;
            }

            // Loops limit end - task will need to rerun again to start from the saved watermark
            if ($this->checkLoopLimits($numberOfLoops, $options['numberOfLoops'], $fileName, $watermark) === true) {
                break;
            }
            $numberOfLoops++;

            $statDataMongo = [];

            foreach ($stats->fetchAll() as $stat) {

                $entry = [];

                $start = $this->date->parse($stat['start']);
                $end = $this->date->parse($stat['end']);

                $entry['type'] = $stat['type'];
                $entry['fromDt'] = $start;
                $entry['toDt'] = $end;
                $entry['scheduleId'] = $stat['scheduleId'];
                $entry['displayId'] = $stat['displayId'];
                $entry['campaignId'] = $stat['campaignId'];
                $entry['layoutId'] = $stat['layoutId'];
                $entry['mediaId'] = $stat['mediaId'];
                $entry['tag'] = $stat['tag'];
                $entry['widgetId'] = $stat['widgetId'];
                $entry['duration'] = $end->diffInSeconds($start);
                $entry['count'] = isset($stat['count']) ? (int) $stat['count'] : 1;

                $statDataMongo[] = $entry;

                $watermark = $stat['statId'];
            }

            // Do the insert in chunk
            if (count($statDataMongo) > 0) {
                $this->timeSeriesStore->addStat($statDataMongo);
            } else {
                $this->log->debug('No stat to migrate from stat archive to mongo');
            }

            // Give Mongo time to recover
            if ($watermark > 0) {
                $this->log->debug('Stats migration effected '.$count.' rows, sleeping.');
                sleep($options['pauseBetweenLoops']);
            }
        }
    }

    // Get low watermark from file
    function getWatermarkFromFile($fileName, $tableName)
    {
        if (file_exists($fileName)) {

            $file = fopen($fileName, 'r');
            $line = fgets($file);
            fclose($file);
            $watermark = (int) $line;

        } else {

            // Save mysql low watermark in file if .watermark.txt file is not found
            $statId = $this->store->select('SELECT MAX(statId) as statId FROM '.$tableName, []);
            $watermark = (int) $statId[0]['statId'];

            $out = fopen($fileName, 'w');
            fwrite($out, $watermark);
            fclose($out);
        }

        // We need to increase it
        $watermark+= 1;

        return $watermark;
    }

    // Check if end of records
    function checkEndOfRecords($recordCount, $fileName)
    {

        if($recordCount == 0) {
            // No records in stat, save watermark in file
            $watermark = -1;

            $out = fopen($fileName, 'w');
            fwrite($out, $watermark);
            fclose($out);

            return true;
        }

        return false;
    }

    // Check loop limits
    function checkLoopLimits($numberOfLoops, $optionsNumberOfLoops, $fileName, $watermark)
    {

        if($numberOfLoops == $optionsNumberOfLoops) {

            // Save watermark in file
            $watermark = $watermark - 1;
            $this->log->debug(' Loop reached limit. Watermark is now '.$watermark);

            $out = fopen($fileName, 'w');
            fwrite($out, $watermark);
            fclose($out);

            return true;
        }

        return false;
    }

}
