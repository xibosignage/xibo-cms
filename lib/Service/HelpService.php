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
class HelpService implements HelpServiceInterface
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

    /** @var  string */
    private $currentPage;

    /**
     * @inheritdoc
     */
    public function __construct($store, $config, $pool, $currentPage)
    {
        $this->store = $store;
        $this->config = $config;
        $this->pool = $pool;

        // Only take the first element of the current page
        $currentPage = explode('/', ltrim($currentPage, '/'));
        $this->currentPage = $currentPage[0];
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
     * @return ConfigServiceInterface
     */
    private function getConfig()
    {
        return $this->config;
    }

    /**
     * @inheritdoc
     */
    public function link($topic = null, $category = 'General')
    {
        // if topic is empty use the page name
        $topic = ucfirst(($topic === null) ? $this->currentPage : $topic);

        $dbh = $this->getStore()->getConnection();

        $sth = $dbh->prepare('SELECT Link FROM `help` WHERE Topic = :topic AND Category = :cat');
        $sth->execute(array('topic' => $topic, 'cat' => $category));

        if (!$link = $sth->fetchColumn(0)) {
            $sth->execute(array('topic' => $topic, 'cat' => 'General'));
            $link = $sth->fetchColumn(0);
        }

        return $this->getBaseUrl() . $link;
    }

    /**
     * @inheritdoc
     */
    public function address($suffix = '')
    {
        return $this->getBaseUrl() . $suffix;
    }

    /**
     * @return string
     */
    private function getBaseUrl()
    {
        $helpBase = $this->getConfig()->getSetting('HELP_BASE');

        if (stripos($helpBase, 'http://') === false && stripos($helpBase, 'https://') === false) {
            // We need to convert the URL to a full URL
            $helpBase = $this->getConfig()->rootUri() . $helpBase;
        }

        return $helpBase;
    }
}
