<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Entity;

use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Definition\Sql;

/**
 * Class TagLinkTrait
 * @package Xibo\Entity
 */
trait TagLinkTrait
{
    /**
     * Does the Entity have the provided tag?
     * @param $searchTag
     * @return bool
     * @throws NotFoundException
     */
    public function hasTag($searchTag)
    {
        foreach ($this->tags as $tag) {
            if ($tag->tag == $searchTag) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assign Tag
     * @param TagLink $tag
     * @return $this
     */
    public function assignTag(TagLink $tag)
    {
        $this->linkTags[] = $tag;
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Unassign tag
     * @param TagLink $tag
     * @return $this
     */
    public function unassignTag(TagLink $tag)
    {
        $this->unlinkTags[] = $tag;

        foreach ($this->tags as $key => $currentTag) {
            if ($currentTag->tagId === $tag->tagId) {
                array_splice($this->tags, $key, 1);
            }
        }

        return $this;
    }

    /**
     * Link
     */
    public function linkTagToEntity($table, $column, $entityId, $tagId, $value)
    {
        $table = Sql::validateIdentifier($table, 'table');
        $column = Sql::validateIdentifier($column, 'column');

        $this->getLog()->debug(sprintf('Linking %s %d, to tagId %d', $column, $entityId, $tagId));

        $this->getStore()->update('INSERT INTO `' . $table .'` (`tagId`, `'.$column.'`, `value`) VALUES (:tagId, :entityId, :value) ON DUPLICATE KEY UPDATE '.$column.' = :entityId, `value` = :value', [
            'tagId' => $tagId,
            'entityId' => $entityId,
            'value' => $value
        ]);
    }

    /**
     * Unlink
     */
    public function unlinkTagFromEntity($table, $column, $entityId, $tagId)
    {
        $table = Sql::validateIdentifier($table, 'table');
        $column = Sql::validateIdentifier($column, 'column');

        $this->getLog()->debug(sprintf('Unlinking %s %d, from tagId %d', $column, $entityId, $tagId));

        $this->getStore()->update('DELETE FROM `'.$table.'` WHERE tagId = :tagId AND `'.$column.'` = :entityId', [
            'tagId' => $tagId,
            'entityId' => $entityId
        ]);
    }

    /**
     * Unlink all Tags from Entity
     */
    public function unlinkAllTagsFromEntity($table, $column, $entityId)
    {
        $table = Sql::validateIdentifier($table, 'table');
        $column = Sql::validateIdentifier($column, 'column');

        $this->getLog()->debug(sprintf('Unlinking all Tags from %s %d', $column, $entityId));

        $this->getStore()->update('DELETE FROM `'.$table.'` WHERE `'.$column.'` = :entityId', [
            'entityId' => $entityId
        ]);
    }

    /**
     * @param TagLink[] $tags
     */
    public function updateTagLinks($tags = [])
    {
        if ($this->tags != $tags) {
            $this->unlinkTags = array_udiff($this->tags, $tags, function ($a, $b) {
                /* @var TagLink $a */
                /* @var TagLink $b */
                return $a->tagId - $b->tagId;
            });

            $this->getLog()->debug(sprintf('Tags to be removed: %s', json_encode($this->unlinkTags)));

            // see what we need to add
            $this->linkTags = array_udiff($tags, $this->tags, function ($a, $b) {
                /* @var TagLink $a */
                /* @var TagLink $b */
                if ($a->value !== $b->value && $a->tagId === $b->tagId) {
                    return -1;
                } else {
                    return $a->tagId - $b->tagId;
                }
            });

            // Replace the arrays
            $this->tags = $tags;

            $this->getLog()->debug(sprintf('Tags to be added: %s', json_encode($this->linkTags)));

            $this->getLog()->debug(sprintf('Tags remaining: %s', json_encode($this->tags)));
        } else {
            $this->getLog()->debug('Tags were not changed');
        }
    }

    /**
     * Convert TagLink array into a string for use on forms.
     * @return string
     */
    public function getTagString()
    {
        $tagsString = '';

        if (empty($this->tags)) {
            return $tagsString;
        }

        $i = 1;
        foreach ($this->tags as $tagLink) {
            /** @var TagLink $tagLink */
            if ($i > 1) {
                $tagsString .= ',';
            }
            $tagsString .= $tagLink->tag . (($tagLink->value) ? '|' . $tagLink->value : '');
            $i++;
        }

        return $tagsString;
    }
}
