<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (BandwidthFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Bandwidth;
use Xibo\Helper\ByteFormatter;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class BandwidthFactory
 * @package Xibo\Factory
 */
class BandwidthFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return Bandwidth
     */
    public function createEmpty()
    {
        return new Bandwidth($this->getStore(), $this->getLog());
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

            $this->getLog()->debug('Checking bandwidth usage against allowance: ' . ByteFormatter::format($limit * 1024) . '. ' . ByteFormatter::format($usage));

            return ($usage >= ($limit * 1024));

        } catch (\PDOException $e) {
            $this->getLog()->error($e->getMessage());
            return false;
        }
    }
}