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

/**
 * Remove empty and duplicate tags from tag table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

/**
 * Class FixDuplicateTagsMigration
 */
class FixDuplicateTagsMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Get the duplicate tags leaving lowest ids and any empty/odd tags
        $tagsToCheck = $this->query('SELECT DISTINCT t1.tagId, t1.tag FROM `tag` t1 INNER JOIN `tag` t2 WHERE (t1.tagId > t2.tagId AND t1.tag = t2.tag) OR (t1.tag = \'\' OR t1.tag = \',\' OR t1.tag = \' \') ');//phpcs:ignore
        $tagsToCheckData = $tagsToCheck->fetchAll(PDO::FETCH_ASSOC);

        // only execute this code if any tags we need to delete were found
        if (count($tagsToCheckData) > 0) {
            $tagsToRemove = [];
            $tagLinksToRemove = [];
            $tagLinksToUpdate = [];
            foreach ($tagsToCheckData as $row) {
                if ($row['tag'] == '' || $row['tag'] == ' ' || $row['tag'] == ',') {
                    $tagLinksToRemove[] = $row['tagId'];
                } else {
                    $tagLinksToUpdate[$row['tagId']] = $row['tag'];
                }
                $tagsToRemove[] = $row['tagId'];
            }

            if (count($tagLinksToRemove) > 0) {
                $tagLinksString = implode(',', $tagLinksToRemove);
                // remove links to the tags we want to remove from lktag tables
                $this->execute('DELETE FROM `lktagcampaign` WHERE tagId IN (' . $tagLinksString .')');
                $this->execute('DELETE FROM `lktagdisplaygroup` WHERE tagId IN (' . $tagLinksString .')');
                $this->execute('DELETE FROM `lktaglayout` WHERE tagId IN (' . $tagLinksString .')');
                $this->execute('DELETE FROM `lktagmedia` WHERE tagId IN (' . $tagLinksString .')');
                $this->execute('DELETE FROM `lktagplaylist` WHERE tagId IN (' . $tagLinksString .')');
            }

            // for duplicate tags, find the original (lowest id) and update lktag tables with it
            foreach ($tagLinksToUpdate as $tagId => $tag) {
                $lowestIdQuery = $this->query(
                    'SELECT tagId FROM tag WHERE `tag`.tag = :tag ORDER BY tagId LIMIT 1',
                    ['tag' => $tag]
                );
                $lowestIdResult = $lowestIdQuery->fetchAll(PDO::FETCH_ASSOC);
                $lowestId = $lowestIdResult[0]['tagId'];

                $this->handleTagLinks('campaignId', 'lktagcampaign', $tagId, $lowestId);
                $this->handleTagLinks('displayGroupId', 'lktagdisplaygroup', $tagId, $lowestId);
                $this->handleTagLinks('layoutId', 'lktaglayout', $tagId, $lowestId);
                $this->handleTagLinks('mediaId', 'lktagmedia', $tagId, $lowestId);
                $this->handleTagLinks('playlistId', 'lktagplaylist', $tagId, $lowestId);
            }

            $tagsToRemoveString = implode(',', $tagsToRemove);
            // finally remove the tag itself from tag table
            $this->execute('DELETE FROM `tag` WHERE tagId IN (' . $tagsToRemoveString .')');
        }
    }

    private function handleTagLinks($id, $table, $tagId, $lowestId)
    {
        foreach ($this->fetchAll('SELECT ' . $id . ' FROM ' . $table . ' WHERE tagId = ' . $tagId . ';') as $object) {
            if (!$this->fetchRow('SELECT * FROM ' . $table . ' WHERE tagId =' . $lowestId . ' AND ' . $id . ' = ' . $object[$id] .';')) {//phpcs:ignore
                $this->execute('UPDATE ' . $table . ' SET tagId = ' . $lowestId . ' WHERE tagId = ' . $tagId . ' AND ' . $id . ' = ' . $object[$id] .';');//phpcs:ignore
            } else {
                $this->execute('DELETE FROM ' . $table . ' WHERE tagId = ' . $tagId . ' AND '. $id . ' = ' . $object[$id] .';');//phpcs:ignore
            }
        }
    }
}
