<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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


namespace Xibo\Entity;
use Respect\Validation\Validator as v;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Help
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Help
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Help Record")
     * @var int
     */
    public $helpId;

    /**
     * @SWG\Property(description="The topic for this Help Record")
     * @var string
     */
    public $topic;

    /**
     * @SWG\Property(description="The Category for this Help Record")
     * @var string
     */
    public $category;

    /**
     * @SWG\Property(description="The Link to the Manual for this Help Record")
     * @var string
     */
    public $link;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    public function getId()
    {
        return $this->helpId;
    }

    public function getOwnerId()
    {
        return 1;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->length(1, 254)->validate($this->topic))
            throw new InvalidArgumentException(__('Topic is a required field. It must be between 1 and 254 characters.'), 'topic');

        if (!v::stringType()->notEmpty()->length(1, 254)->validate($this->category))
            throw new InvalidArgumentException(__('Category is a required field. It must be between 1 and 254 characters.'), 'category');

        if (!v::stringType()->notEmpty()->length(1, 254)->validate($this->link))
            throw new InvalidArgumentException(__('Link is a required field. It must be between 1 and 254 characters.'), 'link');
    }

    /**
     * @param bool $validate
     * @throws InvalidArgumentException
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->helpId == null || $this->helpId == 0)
            $this->add();
        else
            $this->edit();
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM `help` WHERE HelpID = :helpid', [
            'helpId' => $this->helpId
        ]);
    }

    private function add()
    {
        $this->helpId = $this->getStore()->insert('INSERT INTO `help` (Topic, Category, Link) VALUES (:topic, :category, :link)', [
            'topic' => $this->topic,
            'category' => $this->category,
            'link' => $this->link
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('UPDATE `help` SET Topic = :topic, Category = :category, Link = :link WHERE HelpID = :helpid', [
            'helpId' => $this->helpId,
            'topic' => $this->topic,
            'category' => $this->category,
            'link' => $this->link
        ]);
    }
}