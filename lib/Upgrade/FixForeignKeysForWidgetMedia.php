<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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


namespace Xibo\Upgrade;

use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class FixDatabaseIndexesAndContraints
 * @package Xibo\Upgrade
 */
class FixForeignKeysForWidgetMedia implements Step
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
        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lkwidgetmedia\' AND referenced_table_name = \'media\';', [])) {
            // Delete any records that might be conflicting
            $this->store->update('DELETE FROM `lkwidgetmedia` WHERE NOT EXISTS (SELECT * FROM `media` WHERE `media`.mediaId = `lkwidgetmedia`.mediaId)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `lkwidgetmedia` ADD CONSTRAINT `lkwidgetmedia_ibfk_1` FOREIGN KEY (`mediaId`) REFERENCES `media` (`mediaId`);', []);
        }

        if (!$this->store->exists('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lkwidgetmedia\' AND referenced_table_name = \'widget\';', [])) {
            // Delete any records that might be conflicting
            $this->store->update('DELETE FROM `lkwidgetmedia` WHERE NOT EXISTS (SELECT * FROM `widget` WHERE `widget`.widgetId = `lkwidgetmedia`.widgetId)', []);

            // Add the constraint
            $this->store->update('ALTER TABLE `lkwidgetmedia` ADD CONSTRAINT `lkwidgetmedia_ibfk_2` FOREIGN KEY (`widgetId`) REFERENCES `widget` (`widgetId`);', []);
        }
    }
}