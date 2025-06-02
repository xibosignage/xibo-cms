<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Carbon\Carbon;
use GuzzleHttp\Client;
use Xibo\Helper\Environment;
use Xibo\Storage\StorageServiceInterface;

/**
 * Collects anonymous usage stats
 */
class AnonymousUsageTask implements TaskInterface
{
    use TaskTrait;

    private readonly string $url;

    private StorageServiceInterface $db;

    public function __construct()
    {
        $this->url = 'https://test.xibo.org.uk/api/stats/usage';
    }

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->db = $container->get('store');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $isCollectUsage = $this->getConfig()->getSetting('PHONE_HOME') == 1;
        if (!$isCollectUsage) {
            $this->appendRunMessage('Anonymous usage disabled');
            return;
        }

        // Make sure we have a key
        $key = $this->getConfig()->getSetting('PHONE_HOME_KEY');
        if (empty($key)) {
            $key = bin2hex(random_bytes(16));

            // Save it.
            $this->getConfig()->changeSetting('PHONE_HOME_KEY', $key);
        }

        // Set PHONE_HOME_TIME to NOW.
        $this->getConfig()->changeSetting('PHONE_HOME_DATE', Carbon::now()->format('U'));

        // Collect the data and report it.
        $data = [
            'id' => $key,
            'version' => Environment::$WEBSITE_VERSION_NAME,
            'accountId' => defined('ACCOUNT_ID') ? constant('ACCOUNT_ID') : null,
        ];

        // What type of install are we?
        $data['installType'] = 'custom';
        if (isset($_SERVER['INSTALL_TYPE'])) {
            $data['installType'] = $_SERVER['INSTALL_TYPE'];
        } else if ($this->getConfig()->getSetting('cloud_demo') !== null) {
            $data['installType'] = 'cloud';
        }

        // General settings
        $data['calendarType'] = strtolower($this->getConfig()->getSetting('CALENDAR_TYPE'));
        $data['defaultLanguage'] = $this->getConfig()->getSetting('DEFAULT_LANGUAGE');
        $data['isDetectLanguage'] = $this->getConfig()->getSetting('DETECT_LANGUAGE') == 1 ? 1 : 0;

        // Connectors
        $data['isSspConnector'] = $this->runQuery('SELECT `isEnabled` FROM `connectors` WHERE `className` = :name', [
            'name' => '\\Xibo\\Connector\\XiboSspConnector'
        ]) ?? 0;
        $data['isDashboardConnector'] =
            $this->runQuery('SELECT `isEnabled` FROM `connectors` WHERE `className` = :name', [
                'name' => '\\Xibo\\Connector\\XiboDashboardConnector'
            ]) ?? 0;

        // Displays
        $data = array_merge($data, $this->displayStats());
        $data['countOfDisplays'] = $this->runQuery(
            'SELECT COUNT(*) AS countOf FROM `display` WHERE `lastaccessed` > :recently',
            [
                'recently' => Carbon::now()->subDays(7)->format('U'),
            ]
        );
        $data['countOfDisplaysTotal'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `display`');
        $data['countOfDisplaysUnAuthorised'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `display` WHERE licensed = 0');
        $data['countOfDisplayGroups'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `displaygroup` WHERE isDisplaySpecific = 0');

        // Users
        $data['countOfUsers'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `user`');
        $data['countOfUsersActiveInLastTwentyFour'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE `lastAccessed` > :recently', [
                'recently' => Carbon::now()->subHours(24)->format('Y-m-d H:i:s'),
            ]);
        $data['countOfUserGroups '] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `group` WHERE isUserSpecific = 0');
        $data['countOfUsersWithStatusDashboard'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'statusdashboard.view\'');
        $data['countOfUsersWithIconDashboard'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'icondashboard.view\'');
        $data['countOfUsersWithMediaDashboard'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'mediamanager.view\'');
        $data['countOfUsersWithPlaylistDashboard'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `user` WHERE homePageId = \'playlistdashboard.view\'');

        // Other objects
        $data['countOfFolders'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `folder`');
        $data['countOfLayouts'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `campaign` WHERE isLayoutSpecific = 1');
        $data['countOfLayoutsWithPlaylists'] = $this->runQuery('
            SELECT COUNT(DISTINCT `region`.`layoutId`) AS countOf 
              FROM `widget`
                INNER JOIN `playlist` ON `playlist`.`playlistId` = `widget`.`playlistId`
                INNER JOIN `region` ON `playlist`.`regionId` = `region`.`regionId`
             WHERE `widget`.`type` = \'subplaylist\'
        ');
        $data['countOfAdCampaigns'] =
            $this->runQuery('
                SELECT COUNT(*) AS countOf
                  FROM `campaign`
                WHERE `type` = \'ad\'
                  AND `isLayoutSpecific` = 0
            ');
        $data['countOfListCampaigns'] =
            $this->runQuery('
                SELECT COUNT(*) AS countOf 
                  FROM `campaign`
                 WHERE `type` = \'list\'
                  AND `isLayoutSpecific` = 0
            ');
        $data['countOfMedia'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `media`');
        $data['countOfPlaylists'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `playlist` WHERE `regionId` IS NULL');
        $data['countOfDataSets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `dataset`');
        $data['countOfRemoteDataSets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `dataset` WHERE `isRemote` = 1');
        $data['countOfDataConnectorDataSets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `dataset` WHERE `isRealTime` = 1');
        $data['countOfApplications'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `oauth_clients`');
        $data['countOfApplicationsUsingClientCredentials'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `oauth_clients` WHERE `clientCredentials` = 1');
        $data['countOfApplicationsUsingUserCode'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `oauth_clients` WHERE `authCode` = 1');
        $data['countOfScheduledReports'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `reportschedule`');
        $data['countOfSavedReports'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `saved_report`');

        // Widgets
        $data['countOfImageWidgets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'image\'');
        $data['countOfVideoWidgets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'video\'');
        $data['countOfPdfWidgets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'pdf\'');
        $data['countOfEmbeddedWidgets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'embedded\'');
        $data['countOfCanvasWidgets'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `widget` WHERE `type` = \'global\'');

        // Schedules
        $data['countOfSchedulesThisMonth'] = $this->runQuery('
            SELECT COUNT(*) AS countOf 
              FROM `schedule`
             WHERE `fromDt` <= :toDt AND `toDt` > :fromDt
        ', [
            'fromDt' => Carbon::now()->startOfMonth()->unix(),
            'toDt' => Carbon::now()->endOfMonth()->unix(),
        ]);
        $data['countOfSyncSchedulesThisMonth'] = $this->runQuery('
            SELECT COUNT(*) AS countOf 
              FROM `schedule`
             WHERE `fromDt` <= :toDt AND `toDt` > :fromDt
                AND `eventTypeId` = 9
        ', [
            'fromDt' => Carbon::now()->startOfMonth()->unix(),
            'toDt' => Carbon::now()->endOfMonth()->unix(),
        ]);
        $data['countOfAlwaysSchedulesThisMonth'] = $this->runQuery('
            SELECT COUNT(*) AS countOf 
              FROM `schedule`
                INNER JOIN `daypart` ON `daypart`.dayPartId = `schedule`.`dayPartId`
             WHERE `daypart`.`isAlways` = 1
        ');
        $data['countOfRecurringSchedules'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `schedule` WHERE IFNULL(recurrence_type, \'\') <> \'\'');
        $data['countOfSchedulesWithCriteria'] =
            $this->runQuery('SELECT COUNT(DISTINCT `eventId`) AS countOf FROM `schedule_criteria`');
        $data['countOfDayParts'] =
            $this->runQuery('SELECT COUNT(*) AS countOf FROM `daypart`');

        // Finished collecting, send.
        $this->getLogger()->debug('run: sending stats ' . json_encode($data));

        try {
            (new Client())->post(
                $this->url,
                $this->getConfig()->getGuzzleProxy([
                    'json' => $data,
                ])
            );
        } catch (\Exception $e) {
            $this->appendRunMessage('Unable to send stats.');
            $this->log->error('run: stats send failed, e=' . $e->getMessage());
        }

        $this->appendRunMessage('Completed');
    }

    private function displayStats(): array
    {
        // Retrieve number of displays
        $stats = $this->db->select('
                        SELECT client_type, COUNT(*) AS cnt
                          FROM `display`
                         WHERE licensed = 1
                        GROUP BY client_type
                    ', []);

        $counts = [
            'total' => 0,
            'android' => 0,
            'windows' => 0,
            'linux' => 0,
            'lg' => 0,
            'sssp' => 0,
            'chromeOS' => 0,
        ];
        foreach ($stats as $stat) {
            $counts['total'] += intval($stat['cnt']);
            $counts[$stat['client_type']] += intval($stat['cnt']);
        }

        return [
            'countOfDisplaysAuthorised' => $counts['total'],
            'countOfAndroid' => $counts['android'],
            'countOfLinux' => $counts['linux'],
            'countOfWebos' => $counts['lg'],
            'countOfWindows' => $counts['windows'],
            'countOfTizen' => $counts['sssp'],
            'countOfChromeOS' => $counts['chromeOS'],
        ];
    }

    /**
     * Run a query and return the value of a property
     * @param string $sql
     * @param string $property
     * @param array $params
     * @return string|null
     */
    private function runQuery(string $sql, array $params = [], string $property = 'countOf'): ?string
    {
        try {
            $record = $this->db->select($sql, $params);
            return $record[0][$property] ?? null;
        } catch (\PDOException $PDOException) {
            $this->getLogger()->debug('runQuery: error returning specific stat, e: ' . $PDOException->getMessage());
            return null;
        }
    }
}
