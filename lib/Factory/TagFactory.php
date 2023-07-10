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


namespace Xibo\Factory;


use Xibo\Entity\Tag;
use Xibo\Entity\TagLink;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class TagFactory
 * @package Xibo\Factory
 */
class TagFactory extends BaseFactory
{
    use TagTrait;
    /**
     * @return Tag
     */
    public function createEmpty()
    {
        return new Tag($this->getStore(), $this->getLog(), $this->getDispatcher(), $this);
    }

    /**
     * @return TagLink
     */
    public function createEmptyLink()
    {
        return new TagLink($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * @param $name
     * @return Tag
     */
    public function create($name)
    {
        $tag = $this->createEmpty();
        $tag->tag = trim($name);

        return $tag;
    }

    public function createTagLink($tagId, $tag, $value)
    {
        $tagLink = $this->createEmptyLink();
        $tagLink->tag = trim($tag);
        $tagLink->tagId = $tagId;
        $tagLink->value = trim($value ?? '');

        return $tagLink;
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
     * @return TagLink
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
            $tagLink = $this->createTagLink($tag->tagId, $tag->tag, $explode[1] ?? null);
            $tagLink->validateOptions($tag);
        }
        catch (NotFoundException $e) {
            // New tag
            $tag = $this->createEmpty();
            $tag->tag = $explode[0];
            $tag->save();
            $tagLink = $this->createTagLink($tag->tagId, $tag->tag, $explode[1] ?? null);
        }

        return $tagLink;
    }

    public function tagsFromJson($tagArray)
    {
        $tagLinks = [];
        foreach ($tagArray as $tag) {
            if (!is_array($tag)) {
                $tag = json_decode($tag);
            }
            try {
                $tagCheck = $this->getByTag($tag->tag);
                $tagLink = $this->createTagLink($tagCheck->tagId, $tag->tag, $tag->value ?? null);
                $tagLink->validateOptions($tag);
            } catch (NotFoundException $exception) {
                $newTag = $this->createEmpty();
                $newTag->tag = $tag->tag;
                $newTag->save();
                $tagLink = $this->createTagLink($newTag->tagId, $tag->tag, $tag->value ?? null);
            }
            $tagLinks[] = $tagLink;
        }

        return $tagLinks;
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
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'tag',
                'tag',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($sanitizedFilter->getString('tagExact') != null) {
            $body.= " AND tag.tag = :exact ";
            $params['exact'] = $sanitizedFilter->getString('tagExact');
        }

        //isSystem filter, by default hide tags with isSystem flag
        if ($sanitizedFilter->getInt('allTags') !== 1) {
            $body .= ' AND `tag`.isSystem = :isSystem ';
            $params['isSystem'] = $sanitizedFilter->getCheckbox('isSystem');
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
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
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


    public function getAllLinks($sortOrder, $filterBy)
    {
        $entries = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder == null) {
            $sortOrder = ['type ASC'];
        }

        $params['tagId'] = $sanitizedFilter->getInt('tagId');

        $body = 'SELECT
         `lktagmedia`.mediaId AS entityId,
         `media`.name AS name,
         `lktagmedia`.value,
         \'Media\' AS type
         FROM `lktagmedia` INNER JOIN `media` ON `lktagmedia`.mediaId = `media`.mediaId
         WHERE `lktagmedia`.tagId = :tagId
         UNION ALL
         SELECT
         `lktaglayout`.layoutId AS entityId,
         `layout`.layout AS name,
         `lktaglayout`.value,
         \'Layout\' AS type
         FROM `lktaglayout` INNER JOIN `layout` ON `lktaglayout`.layoutId = `layout`.layoutId
         WHERE `lktaglayout`.tagId = :tagId
         UNION ALL
         SELECT
         `lktagcampaign`.campaignId AS entityId,
         `campaign`.campaign AS name,
         `lktagcampaign`.value,
         \'Campaign\' AS type
         FROM `lktagcampaign` INNER JOIN `campaign` ON `lktagcampaign`.campaignId = `campaign`.campaignId
         WHERE `lktagcampaign`.tagId = :tagId
         UNION ALL
         SELECT
         `lktagdisplaygroup`.displayGroupId AS entityId,
         `displaygroup`.displayGroup AS name,
         `lktagdisplaygroup`.value,
         \'Display Group\' AS type
         FROM `lktagdisplaygroup` INNER JOIN `displaygroup` ON `lktagdisplaygroup`.displayGroupId = `displaygroup`.displayGroupId AND `displaygroup`.isDisplaySpecific = 0
         WHERE `lktagdisplaygroup`.tagId = :tagId
         UNION ALL
         SELECT
         `lktagdisplaygroup`.displayGroupId AS entityId,
         `display`.display AS name,
         `lktagdisplaygroup`.value,
         \'Display\' AS type
         FROM `display` INNER JOIN `lkdisplaydg` ON `lkdisplaydg`.displayId = `display`.displayId
         INNER JOIN `displaygroup` ON `displaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId AND `displaygroup`.isDisplaySpecific = 1
         INNER JOIN lktagdisplaygroup ON `lktagdisplaygroup`.displayGroupId = `displaygroup`.displayGroupId
         WHERE `lktagdisplaygroup`.tagId = :tagId
         UNION ALL
         SELECT
         `lktagplaylist`.playListId AS entityId,
         `playlist`.name AS name,
         `lktagplaylist`.value,
         \'Playlist\' AS type
         FROM `lktagplaylist` INNER JOIN `playlist` ON `lktagplaylist`.playlistId = `playlist`.playlistId
         WHERE `lktagplaylist`.tagId = :tagId
         ';

        // Sorting?
        $sort = '';
        if (is_array($sortOrder)) {
            $sort .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        // Paging
        $limit = '';
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $body . $sort . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $row;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total FROM (' . $body .') x', $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
