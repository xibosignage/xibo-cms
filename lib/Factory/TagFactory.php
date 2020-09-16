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


namespace Xibo\Factory;


use Xibo\Entity\Tag;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class TagFactory
 * @package Xibo\Factory
 */
class TagFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return Tag
     */
    public function createEmpty()
    {
        return new Tag($this->getStore(), $this->getLog(), $this);
    }

    /**
     * @param $name
     * @return Tag
     */
    public function create($name)
    {
       $tag = $this->createEmpty();
       $tag->tag = $name;

       return $tag;
    }

    /**
     * Get tags from a string
     * @param string $tagString
     * @return array[Tag]
     * @throws InvalidArgumentException
     */
    public function tagsFromString($tagString)
    {
        $tags = [];

        if ($tagString == '') {
            return $tags;
        }

        // Parse the tag string, create tags
        foreach (explode(',', $tagString) as $tagName) {
            $tagName = trim($tagName);

            $tags[] = $this->tagFromString($tagName);
        }

        return $tags;
    }

    /**
     * Get Tag from String
     * @param string $tagString
     * @return Tag
     * @throws InvalidArgumentException
     */
    public function tagFromString($tagString)
    {
        // Trim the tag
        $tagString = trim($tagString);
        $explode = explode('|', $tagString);

        // Add to the list
        try {
            $tag = $this->getByTag($explode[0]);

            if ($tag->isRequired == 1 && !isset($explode[1])) {
                throw new InvalidArgumentException(sprintf('Selected Tag %s requires a value, please enter the Tag in %s|Value format or provide Tag value in the dedicated field.', $explode[0], $explode[0]), 'options');
            }

            if( isset($explode[1])) {
                $tag->value = $explode[1];
            } else {
                $tag->value = null;
            }
        }
        catch (NotFoundException $e) {
            // New tag
            $tag = $this->createEmpty();
            $tag->tag = $explode[0];
            if( isset($explode[1])) {
                $tag->value = $explode[1];
            } else {
                $tag->value = null;
            }
        }

        return $tag;
    }

    /**
     * Load tag by Tag Name
     * @param string $tagName
     * @return Tag
     * @throws NotFoundException
     */
    public function getByTag($tagName)
    {
        $sql = 'SELECT tag.tagId, tag.tag, tag.isSystem, tag.isRequired, tag.options FROM `tag` WHERE tag.tag = :tag';

        $tags = $this->getStore()->select($sql, ['tag' => $tagName]);

        if (count($tags) <= 0) {
            throw new NotFoundException(sprintf(__('Unable to find Tag %s'), $tagName));
        }

        $row = $tags[0];
        $tag = $this->createEmpty();
        $sanitizedRow = $this->getSanitizer($row);

        $tag->tagId = $sanitizedRow->getInt('tagId');
        $tag->tag = $sanitizedRow->getString('tag');
        $tag->isSystem = $sanitizedRow->getInt('isSystem');
        $tag->isRequired = $sanitizedRow->getInt('isRequired');
        $tag->options = $sanitizedRow->getString('options');

        return $tag;
    }

    /**
     * Gets tags for a layout
     * @param $layoutId
     * @return Tag[]
     */
    public function loadByLayoutId($layoutId)
    {
        $tags = [];

        $sql = 'SELECT tag.tagId, tag.tag, tag.isSystem, tag.isRequired, tag.options, lktaglayout.value FROM `tag` INNER JOIN `lktaglayout` ON lktaglayout.tagId = tag.tagId WHERE lktaglayout.layoutId = :layoutId';

        foreach ($this->getStore()->select($sql, ['layoutId' => $layoutId]) as $row) {
            $sanitizedRow = $this->getSanitizer($row);

            $tag = $this->createEmpty();
            $tag->tagId = $sanitizedRow->getInt('tagId');
            $tag->tag = $sanitizedRow->getString('tag');
            $tag->isSystem = $sanitizedRow->getInt('isSystem');
            $tag->isRequired = $sanitizedRow->getInt('isRequired');
            $tag->options = $sanitizedRow->getString('options');
            $tag->value = $sanitizedRow->getString('value');

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for a playlist
     * @param $playlistId
     * @return Tag[]
     */
    public function loadByPlaylistId($playlistId)
    {
        $tags = [];

        $sql = 'SELECT tag.tagId, tag.tag, tag.isSystem, tag.isRequired, tag.options, lktagplaylist.value FROM `tag` INNER JOIN `lktagplaylist` ON lktagplaylist.tagId = tag.tagId WHERE lktagplaylist.playlistId = :playlistId';

        foreach ($this->getStore()->select($sql, array('playlistId' => $playlistId)) as $row) {
            $sanitizedRow = $this->getSanitizer($row);

            $tag = $this->createEmpty();
            $tag->tagId = $sanitizedRow->getInt('tagId');
            $tag->tag = $sanitizedRow->getString('tag');
            $tag->isSystem = $sanitizedRow->getInt('isSystem');
            $tag->isRequired = $sanitizedRow->getInt('isRequired');
            $tag->options = $sanitizedRow->getString('options');
            $tag->value = $sanitizedRow->getString('value');

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for a campaign
     * @param $campaignId
     * @return Tag[]
     */
    public function loadByCampaignId($campaignId)
    {
        $tags = [];

        $sql = 'SELECT tag.tagId, tag.tag, tag.isSystem, tag.isRequired, tag.options, lktagcampaign.value FROM `tag` INNER JOIN `lktagcampaign` ON lktagcampaign.tagId = tag.tagId WHERE lktagcampaign.campaignId = :campaignId';

        foreach ($this->getStore()->select($sql, array('campaignId' => $campaignId)) as $row) {
            $sanitizedRow = $this->getSanitizer($row);

            $tag = $this->createEmpty();
            $tag->tagId = $sanitizedRow->getInt('tagId');
            $tag->tag = $sanitizedRow->getString('tag');
            $tag->isSystem = $sanitizedRow->getInt('isSystem');
            $tag->isRequired = $sanitizedRow->getInt('isRequired');
            $tag->options = $sanitizedRow->getString('options');
            $tag->value = $sanitizedRow->getString('value');

            $tags[] = $tag;
        }

        return $tags;
    }
    
    /**
     * Gets tags for media
     * @param $mediaId
     * @return Tag[]
     */
    public function loadByMediaId($mediaId)
    {
        $tags = [];

        $sql = 'SELECT tag.tagId, tag.tag, tag.isSystem, tag.isRequired, tag.options, lktagmedia.value FROM `tag` INNER JOIN `lktagmedia` ON lktagmedia.tagId = tag.tagId WHERE lktagmedia.mediaId = :mediaId';

        foreach ($this->getStore()->select($sql, array('mediaId' => $mediaId)) as $row) {
            $sanitizedRow = $this->getSanitizer($row);

            $tag = $this->createEmpty();
            $tag->tagId = $sanitizedRow->getInt('tagId');
            $tag->tag = $sanitizedRow->getString('tag');
            $tag->isSystem = $sanitizedRow->getInt('isSystem');
            $tag->isRequired = $sanitizedRow->getInt('isRequired');
            $tag->options = $sanitizedRow->getString('options');
            $tag->value = $sanitizedRow->getString('value');

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Gets tags for displayGroupId
     * @param $displayGroupId
     * @return Tag[]
     */
    public function loadByDisplayGroupId($displayGroupId)
    {
        $tags = [];

        $sql = 'SELECT tag.tagId, tag.tag, tag.isSystem, tag.isRequired, tag.options, lktagdisplaygroup.value FROM `tag` INNER JOIN `lktagdisplaygroup` ON lktagdisplaygroup.tagId = tag.tagId WHERE lktagdisplaygroup.displayGroupId = :displayGroupId';

        foreach ($this->getStore()->select($sql, array('displayGroupId' => $displayGroupId)) as $row) {
            $sanitizedRow = $this->getSanitizer($row);

            $tag = $this->createEmpty();
            $tag->tagId = $sanitizedRow->getInt('tagId');
            $tag->tag = $sanitizedRow->getString('tag');
            $tag->isSystem = $sanitizedRow->getInt('isSystem');
            $tag->isRequired = $sanitizedRow->getInt('isRequired');
            $tag->options = $sanitizedRow->getString('options');
            $tag->value = $sanitizedRow->getString('value');

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Get Tag by ID
     * @param int $tagId
     * @return Tag
     * @throws NotFoundException
     */
    public function getById($tagId)
    {
        $this->getLog()->debug('TagFactory getById(%d)', $tagId);

        $tags = $this->query(null, ['tagId' => $tagId]);

        if (count($tags) <= 0) {
            $this->getLog()->debug('Tag not found with ID %d', $tagId);
            throw new NotFoundException(\__('Tag not found'));
        }

        return $tags[0];
    }

    /**
     * Get the system tags
     * @return array|Tag
     * @throws NotFoundException
     */
    public function getSystemTags()
    {
        $tags = $this->query(null, ['isSystem' => 1]);

        if (count($tags) <= 0)
            throw new NotFoundException();

        return $tags;
    }

    /**
     * Query
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[\Xibo\Entity\Log]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder == null) {
            $sortOrder = ['tagId DESC'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];
        $order = '';
        $limit = '';

        $select = 'SELECT tagId, tag, isSystem, isRequired, options ';

        $body = '
              FROM `tag`
                  ';

        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('tagId') != null) {
            $body .= " AND `tag`.tagId = :tagId ";
            $params['tagId'] = $sanitizedFilter->getInt('tagId');
        }

        if ($sanitizedFilter->getInt('notTagId', ['default' => 0]) != 0) {
            $body .= " AND tag.tagId <> :notTagId ";
            $params['notTagId'] = $sanitizedFilter->getInt('notTagId');
        }

        if ($sanitizedFilter->getString('tag') != null) {
            $terms = explode(',', $sanitizedFilter->getString('tag'));
            $this->nameFilter('tag', 'tag', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getString('tagExact') != null) {
            $body.= " AND tag.tag = :exact ";
            $params['exact'] = $sanitizedFilter->getString('tagExact');
        }

        //isSystem filter, by default hide tags with isSystem flag
        if ($sanitizedFilter->getCheckbox('isSystem') === 1) {
            $body .= " AND `tag`.isSystem = 1 ";
        } else {
            $body .= " AND `tag`.isSystem = 0 ";
        }

        // isRequired filter, by default hide tags with isSystem flag
        if ($sanitizedFilter->getCheckbox('isRequired') != 0) {
            $body .= " AND `tag`.isRequired = :isRequired ";
            $params['isRequired'] = $sanitizedFilter->getCheckbox('isRequired');
        }

        if ($sanitizedFilter->getCheckbox('haveOptions') === 1) {
            $body .= " AND `tag`.options IS NOT NULL";
        }

        // Sorting?
        if (is_array($sortOrder)) {
            $order = ' ORDER BY ' . implode(',', $sortOrder);
        }

        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $tag = $this->createEmpty()->hydrate($row, ['intProperties' => ['isSystem', 'isRequired']]);
            $tag->excludeProperty('value');

            $entries[] = $tag;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }
        return $entries;
    }

    public function getTagsWithValues($entity)
    {
        $tags = '';
        $arrayOfTags = array_filter(explode(',', $entity->tags));
        $arrayOfTagValues = array_filter(explode(',', $entity->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                $tags .= ',';
            } else {
                $tags .= $arrayOfTags[$i] . ',';
            }
        }

        return $tags;
    }
}