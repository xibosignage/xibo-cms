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


namespace Xibo\Factory;


use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Bandwidth;
use Xibo\Helper\ByteFormatter;

/**
 * Class BandwidthFactory
 * @package Xibo\Factory
 */
class BandwidthFactory extends BaseFactory
{
    public function __construct(private readonly PoolInterface $pool)
    {
    }

    /**
     * @return Bandwidth
     */
    public function createEmpty()
    {
        return new Bandwidth($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Create and Save Bandwidth record
     * @param int $type
     * @param int $displayId
     * @param int $size
     * @return Bandwidth
     */
    public function createAndSave($type, $displayId, $size)
    {
        $bandwidth = $this->createEmpty();
        $bandwidth->type = $type;
        $bandwidth->displayId = $displayId;
        $bandwidth->size = $size;
        $bandwidth->save();

        return $bandwidth;
    }

    /**
     * Is the bandwidth limit exceeded
     * @param int $limit the bandwidth limit to check against
     * @param int $usage
     * @param int $displayId
     * @return bool
     */
    public function isBandwidthExceeded(int $limit, int &$usage = 0, int $displayId = 0): bool
    {
        if ($limit <= 0) {
            return false;
        }

        $usage = $this->getUsage($displayId);

        $this->getLog()->debug(sprintf(
            'isBandwidthExceeded: Checking bandwidth usage %s against allowance %s',
            ByteFormatter::format($usage),
            ByteFormatter::format($limit * 1024)
        ));

        return ($usage >= ($limit * 1024));
    }

    /**
     * The bandwidth used this month, in bytes
     * @param int $displayId
     * @return int
     */
    public function getUsage(int $displayId = 0): int
    {
        // Get from cache.
        $cache = $this->pool->getItem('bandwidth_' . $displayId);
        $usage = $cache->get();

        if ($cache->isMiss() || $usage === null) {
            // Get from the database
            $usage = $this->getBandwidth($displayId);

            // Save to the cache
            $cache->set($usage);
            $cache->setTTL(600);
            $cache->save();
        }

        return intval($usage);
    }

    private function getBandwidth(int $displayId): int
    {
        try {
            $dbh = $this->getStore()->getConnection();

            // Test bandwidth for the current month
            $sql = 'SELECT IFNULL(SUM(Size), 0) AS BandwidthUsage FROM `bandwidth` WHERE `Month` = :month';
            $params = [
                'month' => strtotime(date('m') . '/02/' . date('Y') . ' 00:00:00')
            ];

            // if we are testing the bandwidth usage for specific display, add the information to the query
            if ($displayId != null) {
                $sql .= ' AND `displayId` = :displayId';
                $params['displayId'] = $displayId;
            }

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            return $sth->fetchColumn(0);
        } catch (\PDOException $e) {
            $this->getLog()->error('getBandwidth: e = ' . $e->getMessage());
            return 0;
        }
    }
}