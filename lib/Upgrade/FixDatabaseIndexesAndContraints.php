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

    /** @inheritdoc */
    public function doStep($container)
    {
        if (!$this->checkIndexExists('lkdisplaydg', ['displayGroupId', 'displayId'], 1))
            $this->addUniqueIndexForLkDisplayDg();

        $this->addForeignKeyToOAuthClients();

        $this->addForeignKeyToTags();

        $this->addForeignKeyToPermissions();

        if (!$this->checkIndexExists('permission', ['objectId'], 0)) {
            $this->store->update('CREATE INDEX permission_objectId_index ON permission (objectId);', []);
        }
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
            AND index_name LIKE \'%fk\'
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

    /**
     * Adds a foreign key for the tag link tables
     */
    private function addForeignKeyToTags()
    {
        // Does the constraint already exist?
        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE()
                AND `table_name` = \'lktagcampaign\' AND `index_name` LIKE \'%fk_%\' AND `column_name` = \'campaignId\';', [])) {

            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->store->update('DELETE FROM `lktagcampaign` WHERE campaignId NOT IN (SELECT campaignId FROM `campaign`)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `lktagcampaign` ADD CONSTRAINT `lktagcampaign_ibfk_1` FOREIGN KEY (`campaignId`) REFERENCES `campaign` (`campaignId`);', []);
        }

        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE()
                AND `table_name` = \'lktaglayout\' AND `index_name` LIKE \'%fk_%\' AND `column_name` = \'layoutId\';', [])) {

            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->store->update('DELETE FROM `lktaglayout` WHERE layoutId NOT IN (SELECT layoutId FROM `layout`)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `lktaglayout` ADD CONSTRAINT `lktaglayout_ibfk_1` FOREIGN KEY (`layoutId`) REFERENCES `layout` (`layoutId`);', []);
        }

        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE()
                AND `table_name` = \'lktagmedia\' AND `index_name` LIKE \'%fk_%\' AND `column_name` = \'mediaId\';', [])) {

            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->store->update('DELETE FROM `lktagmedia` WHERE mediaId NOT IN (SELECT mediaId FROM `media`)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `lktagmedia` ADD CONSTRAINT `lktagmedia_ibfk_1` FOREIGN KEY (`mediaId`) REFERENCES `media` (`mediaId`);', []);
        }

        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE()
                AND `table_name` = \'lktagdisplaygroup\' AND `index_name` LIKE \'%fk_%\' AND `column_name` = \'displaygroupId\';', [])) {

            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->store->update('DELETE FROM `lktagdisplaygroup` WHERE displayGroupId NOT IN (SELECT displayGroupId FROM `displaygroup`)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `lktagdisplaygroup` ADD CONSTRAINT `lktagdisplaygroup_ibfk_1` FOREIGN KEY (`displayGroupId`) REFERENCES `displaygroup` (`displayGroupId`);', []);
        }
    }

    /**
     * Adds a foreign key for the permissions tables
     */
    private function addForeignKeyToPermissions()
    {
        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE()
                AND `table_name` = \'permission\' AND `index_name` LIKE \'%fk_%\' AND `column_name` = \'groupId\';', [])) {

            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->store->update('DELETE FROM `permission` WHERE groupId NOT IN (SELECT groupId FROM `group`)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `permission` ADD CONSTRAINT `permission_ibfk_1` FOREIGN KEY (`groupId`) REFERENCES `group` (`groupId`);', []);
        }

        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE()
                AND `table_name` = \'permission\' AND `index_name` LIKE \'%fk_%\' AND `column_name` = \'entityId\';', [])) {

            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->store->update('DELETE FROM `permission` WHERE entityId NOT IN (SELECT entityId FROM `permissionentity`)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `permission` ADD CONSTRAINT `permission_ibfk_2` FOREIGN KEY (`entityId`) REFERENCES `permissionentity` (`entityId`);', []);
        }
    }
}