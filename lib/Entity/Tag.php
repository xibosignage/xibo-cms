<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Xibo\Factory\TagFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\InvalidArgumentException;


/**
 * Class Tag
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Tag implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Tag ID")
     * @var int
     */
    public $tagId;

    /**
     * @SWG\Property(description="The Tag Name")
     * @var string
     */
    public $tag;

    /**
     * @SWG\Property(description="Flag, whether the tag is a system tag")
     * @var int
     */
    public $isSystem = 0;

    /**
     * @SWG\Property(description="Flag, whether the tag requires additional values")
     * @var int
     */
    public $isRequired = 0;

    /**
     * @SWG\Property(description="An array of options assigned to this Tag")
     * @var ?string
     */
    public $options;

    /** @var  TagFactory */
    private $tagFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $dispatcher, $tagFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->tagFactory = $tagFactory;
    }

    public function __clone()
    {
        $this->tagId = null;
    }

    /**
     * @throws InvalidArgumentException
     * @throws DuplicateEntityException
     */
    public function validate()
    {
        // Name Validation
        if (strlen($this->tag) > 50 || strlen($this->tag) < 1) {
            throw new InvalidArgumentException(__("Tag must be between 1 and 50 characters"), 'tag');
        }

        // Check for duplicates
        $duplicates = $this->tagFactory->query(null, [
            'tagExact' => $this->tag,
            'notTagId' => $this->tagId,
            'disableUserCheck' => 1
        ]);

        if (count($duplicates) > 0) {
            throw new DuplicateEntityException(sprintf(__("You already own a Tag called '%s'. Please choose another name."), $this->tag));
        }
    }

    /**
     * Save
     * @param array $options
     * @throws DuplicateEntityException
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'validate' => true
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        // If the tag doesn't exist already - save it
        if ($this->tagId == null || $this->tagId == 0) {
            $this->add();
        } else {
            $this->update();
        }

        $this->getLog()->debug('Saving Tag: %s, %d', $this->tag, $this->tagId);
    }

    /**
     * Add a tag
     * @throws \PDOException
     */
    private function add()
    {
        $this->tagId = $this->getStore()->insert('INSERT INTO `tag` (tag, isRequired, options) VALUES (:tag, :isRequired, :options) ON DUPLICATE KEY UPDATE tag = tag', [
            'tag' => $this->tag,
            'isRequired' => $this->isRequired,
            'options' => ($this->options == null) ? null : $this->options
        ]);
    }

    /**
     * Update a Tag
     * @throws \PDOException
     */
    private function update()
    {
        $this->getStore()->update('UPDATE `tag` SET tag = :tag, isRequired = :isRequired, options = :options WHERE tagId = :tagId', [
            'tagId' => $this->tagId,
            'tag' => $this->tag,
            'isRequired' => $this->isRequired,
            'options' => ($this->options == null) ? null : $this->options
        ]);
    }

    /**
     * Delete Tag
     */
    public function delete()
    {
        // Delete the Tag record
        $this->getStore()->update('DELETE FROM `tag` WHERE tagId = :tagId', ['tagId' => $this->tagId]);
    }

    /**
     * Is this tag a system tag?
     * @return bool
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function isSystemTag()
    {
        $tag = $this->tagFactory->getById($this->tagId);

        return $tag->isSystem === 1;
    }

    /**
     * Removes Tag value from lktagtables
     *
     * @param array $values An Array of values that should be removed from assignment
     */
    public function updateTagValues($values)
    {
        $this->getLog()->debug('Tag options were changed, the following values need to be removed ' . json_encode($values));

        foreach ($values as $value) {
            $this->getLog()->debug('removing following value from lktag tables ' . $value);

            $this->getStore()->update('UPDATE `lktagcampaign` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktagdisplaygroup` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktaglayout` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktagmedia` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

            $this->getStore()->update('UPDATE `lktagplaylist` SET `value` = null WHERE tagId = :tagId AND value = :value',
                [
                    'value' => $value,
                    'tagId' => $this->tagId
                ]);

        }
    }
}