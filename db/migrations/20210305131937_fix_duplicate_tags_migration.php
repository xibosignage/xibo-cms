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

            // remove links to the tags we want to remove from lktag tables
            $this->execute('DELETE FROM `lktagcampaign` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
            $this->execute('DELETE FROM `lktagdisplaygroup` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
            $this->execute('DELETE FROM `lktaglayout` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
            $this->execute('DELETE FROM `lktagmedia` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');
            $this->execute('DELETE FROM `lktagplaylist` WHERE tagId IN (' . implode(',', $tagLinksToRemove) .')');

            // for duplicate tags, find the original (lowest id) and update lktag tables with it
            foreach ($tagLinksToUpdate as $tagId => $tag) {
                $lowestIdQuery = $this->fetchRow('SELECT tagId FROM tag WHERE `tag`.tag = \'' . $tag . '\' ORDER BY tagId LIMIT 1');
                $lowestId = $lowestIdQuery['tagId'];

                $this->execute('UPDATE `lktagcampaign` SET tagId = ' . $lowestId . ' WHERE tagId = ' . $tagId . ';');
                $this->execute('UPDATE `lktagdisplaygroup` SET tagId = ' . $lowestId . ' WHERE tagId = ' . $tagId . ';');
                $this->execute('UPDATE `lktaglayout` SET tagId = ' . $lowestId . ' WHERE tagId = ' . $tagId . ';');
                $this->execute('UPDATE `lktagmedia` SET tagId = ' . $lowestId . ' WHERE tagId = ' . $tagId . ';');
                $this->execute('UPDATE `lktagplaylist` SET tagId = ' . $lowestId . ' WHERE tagId = ' . $tagId . ';');
            }

            // finally remove the tag itself from tag table
            $this->execute('DELETE FROM `tag` WHERE tagId IN (' . implode(',', $tagsToRemove) .')');
        }
    }
}
