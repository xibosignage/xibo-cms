<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (FixDatabaseIndexesAndContraints.php)
 */


namespace Xibo\Upgrade;

use Xibo\Exception\InvalidArgumentException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class FixDatabaseIndexesAndContraints
 * @package Xibo\Upgrade
 */
class FixDatabaseIndexesAndContraints implements Step
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
        if (!$this->checkIndexExists('lkdisplaydg', ['displayGroupId', 'displayId'], 1))
            $this->addUniqueIndexForLkDisplayDg();

        $this->addForeignKeyToOAuthClients();
    }

    /**
     * Check if an index exists
     * @param string $table
     * @param string[] $columns
     * @param bool $isUnique
     * @return bool
     * @throws InvalidArgumentException
     */
    private function checkIndexExists($table, $columns, $isUnique)
    {
        if (!is_array($columns) || count($columns) <= 0)
            throw new InvalidArgumentException('Incorrect call to checkIndexExists', 'columns');

        // Use the information schema to see if the index exists or not.
        // all users have permission to the information schema
        $sql = '
          SELECT * 
            FROM INFORMATION_SCHEMA.STATISTICS 
           WHERE table_schema=DATABASE() 
            AND table_name = :table 
            AND non_unique = :non_unique
            AND (
        ';

        $params = [
            'table' => $table,
            'non_unique' => ($isUnique) ? 0 : 1
        ];

        $i = 0;
        foreach ($columns as $column) {
            $i++;

            $sql .= (($i == 1) ? '' : ' OR') . ' (seq_in_index = :seq_' . $i . ' AND column_name = :col_' . $i . ') ';
            $params['seq_' . $i] = $i;
            $params['col_' . $i] = $column;
        }

        $sql .= ' )';

        $indexes = $this->store->select($sql, $params);

        return (count($indexes) === count($columns));
    }

    /**
     * Adds a unique index to lkdisplaydg
     */
    private function addUniqueIndexForLkDisplayDg()
    {
        $index = 'CREATE UNIQUE INDEX lkdisplaydg_displayGroupId_displayId_uindex ON `lkdisplaydg` (displayGroupId, displayId);';

        // Try to create the index, if we fail, assume duplicates
        try {
            $this->store->update($index, []);
        } catch (\PDOException $e) {
            $this->log->info('Unable to create missing index, duplicate keys in table');

            // Create a verify table
            $this->store->update('CREATE TABLE lkdisplaydg_verify AS SELECT * FROM lkdisplaydg WHERE 1 GROUP BY displaygroupId, displayId;', []);

            // Delete from original table
            $this->store->update('DELETE FROM lkdisplaydg;', []);

            // Insert the de-duped records
            $this->store->update('INSERT INTO lkdisplaydg SELECT * FROM lkdisplaydg_verify;', []);

            // Drop the verify table
            $this->store->update('DROP TABLE lkdisplaydg_verify;', []);

            // Create the index fresh, now that duplicates removed
            $this->store->update($index, []);
        }
    }

    private function addForeignKeyToOAuthClients()
    {
        // Does the constraint already exist?
        if ($this->store->exists('
            SELECT *
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema=DATABASE()
                  AND table_name = \'oauth_clients\'
            AND index_name LIKE \'%_fk\'
            AND column_name = \'userId\'
        ;', [])) {
            return;
        }

        // Detect any client records that have userIds which do not exist and update them
        $this->store->update('
          UPDATE `oauth_clients` SET userId = (SELECT userId FROM `user` WHERE userTypeId = 1 LIMIT 1)
           WHERE userId NOT IN (SELECT userId FROM `user`);
        ', []);

        // Create the index fresh, now that duplicates removed
        $this->store->update('ALTER TABLE `oauth_clients` ADD CONSTRAINT oauth_clients_user_UserID_fk FOREIGN KEY (userId) REFERENCES `user` (UserID);', []);
    }
}