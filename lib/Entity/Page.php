<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Page.php) is part of Xibo.
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


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Page
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Page implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of the Page")
     * @var int
     */
    public $pageId;

    /**
     * @SWG\Property(description="A code name for the page")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="A user friendly title for this page")
     * @var string
     */
    public $title;

    /**
     * @SWG\Property(description="Flag indicating if the page can be used as a homepage")
     * @var int
     */
    public $asHome;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    public function getOwnerId()
    {
        return 1;
    }

    public function getId()
    {
        return $this->pageId;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->pageId == 0)
            $this->add();
        else
            $this->update();
    }

    private function add()
    {
        $this->pageId = $this->getStore()->insert('
            INSERT INTO `pages` (`name`, `title`, `asHome`)
              VALUES (:name, :title, :asHome)
        ', [
            'name' => $this->name,
            'title' => $this->title,
            'asHome' => $this->asHome
        ]);
    }

    private function update()
    {
        $this->getStore()->update('
            UPDATE `pages` SET `name` = :name, `title` = :title, `asHome` = :asHome
             WHERE `pageId` = :pageId
        ', [
            'pageId' => $this->pageId,
            'name' => $this->name,
            'title' => $this->title,
            'asHome' => $this->asHome
        ]);
    }
}