<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner
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
 * Class HelpService
 * @package Xibo\Service
 */
class HelpService
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @inheritdoc
     */
    public function __construct($store, $config, $pool)
    {
        $this->store = $store;
        $this->config = $config;
        $this->pool = $pool;
    }

    /**
     * Get Cache Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    private function getPool()
    {
        return $this->pool;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    private function getStore()
    {
        return $this->store;
    }

    /**
     * Get Config
     * @return ConfigService
     */
    private function getConfig()
    {
        return $this->config;
    }

    /**
     * @inheritdoc
     */
    public function link($topic = '', $category = "General")
    {
        // if topic is empty use the page name
        $topic = ucfirst($topic);

        $dbh = $this->getStore()->getConnection();

        $sth = $dbh->prepare('SELECT Link FROM `help` WHERE Topic = :topic AND Category = :cat');
        $sth->execute(array('topic' => $topic, 'cat' => $category));

        if (!$link = $sth->fetchColumn(0)) {
            $sth->execute(array('topic' => $topic, 'cat' => 'General'));
            $link = $sth->fetchColumn(0);
        }

        return $this->getConfig()->GetSetting('HELP_BASE') . $link;
    }

    /**
     * @inheritdoc
     */
    public function rawLink($link)
    {
        return $this->getConfig()->GetSetting('HELP_BASE') . $link;
    }
}
