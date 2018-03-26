<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MyIsamToInnoDbStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class MyIsamToInnoDbStep
 * @package Xibo\Upgrade
 */
class MyIsamToInnoDbStep implements Step
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * DataSetConvertStep constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $config)
    {
        $this->store = $store;
        $this->log = $log;
        $this->config = $config;
    }

    /**
     * @param \Slim\Helper\Set $container
     * @throws \Xibo\Exception\NotFoundException
     */
    public function doStep($container)
    {
        $sql = '
          SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
           WHERE TABLE_SCHEMA = \'' . $this->config->getDatabaseConfig()['name']  . '\'
            AND ENGINE = \'MyISAM\'
        ';

        foreach ($this->store->select($sql, []) as $table) {
            $this->store->update('ALTER TABLE `' . $table['TABLE_NAME'] . '` ENGINE=INNODB', []);
        }
    }
}