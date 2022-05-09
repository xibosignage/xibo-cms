<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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


namespace Xibo\Factory;


use Xibo\Entity\Bandwidth;
use Xibo\Helper\ByteFormatter;

/**
 * Class BandwidthFactory
 * @package Xibo\Factory
 */
class BandwidthFactory extends BaseFactory
{
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
     * @param string $limit the bandwidth limit to check against
     * @param int $usage
     * @param null $displayId
     * @return bool
     */
    public function isBandwidthExceeded($limit, &$usage = 0, $displayId = null)
    {
        if ($limit <= 0) {
            return false;
        }

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

            $usage = $sth->fetchColumn(0);

            $this->getLog()->debug(sprintf('Checking bandwidth usage %s against allowance %s ' , ByteFormatter::format($usage), ByteFormatter::format($limit * 1024)));

            return ($usage >= ($limit * 1024));

        } catch (\PDOException $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }
}