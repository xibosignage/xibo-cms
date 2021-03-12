<?php
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
        $tagsToCheck = $this->query('SELECT DISTINCT t1.tagId, t1.tag FROM `tag` t1 INNER JOIN `tag` t2 WHERE (t1.tagId > t2.tagId AND t1.tag = t2.tag) OR (t1.tag = \'\' OR t1.tag = \',\' OR t1.tag = \' \') ');
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
                // remove links to the tags we want to remove from lktag tables
                $this->execute('DELETE FROM `lktagcampaign` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
                $this->execute('DELETE FROM `lktagdisplaygroup` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
                $this->execute('DELETE FROM `lktaglayout` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
                $this->execute('DELETE FROM `lktagmedia` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
                $this->execute('DELETE FROM `lktagplaylist` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
            }

            // for duplicate tags, find the original (lowest id) and update lktag tables with it
            foreach ($tagLinksToUpdate as $tagId => $tag) {
                $lowestIdQuery = $this->fetchRow('SELECT tagId FROM tag WHERE `tag`.tag = \'' . $tag . '\' ORDER BY tagId LIMIT 1');
                $lowestId = $lowestIdQuery['tagId'];

                $this->handleTagLinks('campaignId', 'lktagcampaign', $tagId, $lowestId);
                $this->handleTagLinks('displayGroupId', 'lktagdisplaygroup', $tagId, $lowestId);
                $this->handleTagLinks('layoutId', 'lktaglayout', $tagId, $lowestId);
                $this->handleTagLinks('mediaId', 'lktagmedia', $tagId, $lowestId);
                $this->handleTagLinks('playlistId', 'lktagplaylist', $tagId, $lowestId);
            }

            // finally remove the tag itself from tag table
            $this->execute('DELETE FROM `tag` WHERE tagId IN (' . implode(',', $tagsToRemove) .')');
        }
    }

    private function handleTagLinks($id, $table, $tagId, $lowestId)
    {
        foreach ($this->fetchAll('SELECT ' . $id . ' FROM ' . $table . ' WHERE tagId = ' . $tagId . ';') as $object) {
            if (!$this->fetchRow('SELECT * FROM ' . $table . ' WHERE tagId =' . $lowestId . ' AND ' . $id . ' = ' . $object[$id] .';')) {
                $this->execute('UPDATE ' . $table . ' SET tagId = ' . $lowestId . ' WHERE tagId = ' . $tagId . ' AND ' . $id . ' = ' . $object[$id] .';');
            } else {
                $this->execute('DELETE FROM ' . $table . ' WHERE tagId = ' . $tagId . ' AND '. $id . ' = ' . $object[$id] .';');
            }
        }
    }
}
