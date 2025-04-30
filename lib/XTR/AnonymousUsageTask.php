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
        $this->url = 'https://xibosignage.com/api/stats/usage';
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

        // Is this my "hour of the day" to run?
        // Task runs hourly, but we only do something once every 24 hours


        // Make sure we have a key
        $key = $this->getConfig()->getSetting('PHONE_HOME_KEY');
        if (empty($key)) {
            $this->getConfig()->changeSetting('PHONE_HOME_KEY', bin2hex(random_bytes(16)));
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

        $data = array_merge($data, $this->displayStats());
        $data['countOfDisplays'] = $this->runQuery(
            'SELECT COUNT(*) AS countOf FROM `display` WHERE `lastaccessed` > :recently',
            [
                'recently' => Carbon::now()->subDays(7)->format('U'),
            ]
        );
        $data['countOfDisplaysTotal'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `display`');
        $data['countOfDisplaysUnAuthorised'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `display`');
        $data['countOfUsers'] = $this->runQuery('SELECT COUNT(*) AS countOf FROM `user`');

        $this->getLogger()->debug('Sending stats: ' . json_encode($data));

        (new Client())->post(
            $this->url,
            $this->getConfig()->getGuzzleProxy([
                'json' => $data,
            ])
        );
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
        $record = $this->db->select($sql, $params);
        return $record[0][$property] ?? null;
    }
}
