<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (SettingsFactory.php)
 */


namespace Xibo\Factory;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class SettingsFactory
 * @package Xibo\Factory
 */
class SettingsFactory extends BaseFactory
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
     * Query
     * @param array $sort_order
     * @param array $filter_by
     * @return array
     */
    public function query($sort_order = null, $filter_by = [])
    {
        if ($sort_order == NULL)
            $sort_order = ['cat', 'ordering'];

        $SQL = 'SELECT * FROM `setting` WHERE 1 = 1 ';

        $params = [];

        if ($this->getSanitizer()->getInt('userChange', $filter_by) !== null) {
            $SQL .= ' AND userChange = :userChange ';
            $params['userChange'] = $this->getSanitizer()->getInt('userChange', $filter_by);
        }

        if ($this->getSanitizer()->getInt('userSee', $filter_by) !== null) {
            $SQL .= ' AND userSee = :userSee ';
            $params['userSee'] = $this->getSanitizer()->getInt('userSee', $filter_by);
        }

        // Sorting?
        if (is_array($sort_order))
            $SQL .= 'ORDER BY ' . implode(',', $sort_order);

        $sth = $this->getStore()->getConnection()->prepare($SQL);
        $sth->execute($params);

        return $sth->fetchAll();
    }
}