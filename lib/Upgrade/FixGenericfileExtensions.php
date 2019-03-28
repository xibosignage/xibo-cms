<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
 * Class FixGenericfileExtensions
 * @package Xibo\Upgrade
 */
class FixGenericfileExtensions implements Step
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * FixGenericfileExtensions constructor.
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
        // get the current validExtensions for genericfile module
        $dbh = $this->store->getConnection();
        $extensionsData = $dbh->query('SELECT `validExtensions` FROM `module` WHERE `Module` = \'genericfile\'');
        $extensions = $extensionsData->fetchAll(\PDO::FETCH_ASSOC);
        $newExtensions = [];

        // iterate through the array
        foreach ($extensions as $extension) {
            foreach ($extension as $validExt) {

                // make an array out of comma separated string
                $explode = explode(',', $validExt);

                // iterate through our array, remove apk and ipk extensions from it and put other elements into a new array
                foreach ($explode as $item) {
                    if ($item != 'apk' && $item != 'ipk') {
                        $newExtensions[] = $item;
                    }
                }
            }
        }

        // make a comma separated string from our new array
        $newValidExtensions = implode(',', $newExtensions);

        // update validExtensions for genericfile module with our adjusted extensions
        $dbh->exec('UPDATE `module` SET `validExtensions` = ' . "'" . $newValidExtensions . "'" .' WHERE module = \'genericfile\' LIMIT 1;');
    }
}