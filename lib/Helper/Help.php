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
namespace Xibo\Helper;
use Exception;
use Slim\Slim;
use Xibo\Storage\StorageInterface;


class Help
{
    /**
     * @var Slim
     */
    private $app;

    /**
     * Help constructor.
     * @param Slim $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get the App
     * @return Slim
     * @throws \Exception
     */
    public function getApp()
    {
        if ($this->app == null)
            throw new \RuntimeException(__('Help Application DI not configured'));

        return $this->app;
    }

    /**
     * Get Cache Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    protected function getPool()
    {
        return $this->getApp()->pool;
    }

    /**
     * Get Store
     * @return StorageInterface
     */
    protected function getStore()
    {
        return $this->getApp()->store;
    }

    /**
     * Get Log
     * @return Log
     */
    protected function getLog()
    {
        return $this->getApp()->logHelper;
    }

    /**
     * Get Help Link
     * @param string $topic
     * @param string $category
     * @return string
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
     * Raw Link
     * @param $link
     * @return string
     */
    public function rawLink($link)
    {
        return $this->getConfig()->GetSetting('HELP_BASE') . $link;
    }
}
