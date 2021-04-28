<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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


namespace Xibo\Service;

use Stash\Interfaces\PoolInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Interface HelpServiceInterface
 * @package Xibo\Service
 */
interface HelpServiceInterface
{
    /**
     * HelpServiceInterface constructor.
     * @param StorageServiceInterface $store
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param string $currentPage
     */
    public function __construct($store, $config, $pool, $currentPage);

    /**
     * Get Help Link
     * @param string $topic
     * @param string $category
     * @return string
     */
    public function link($topic = '', $category = "General");

    /**
     * Raw Link
     * @param string $suffix Suffix to append to the end of the manual page URL
     * @return string
     */
    public function address($suffix = '');
}
