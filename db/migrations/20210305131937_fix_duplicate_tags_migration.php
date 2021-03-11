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
        $tagsToRemove = $this->query('SELECT DISTINCT t1.tagId FROM `tag` t1 INNER JOIN `tag` t2 WHERE (t1.tagId > t2.tagId AND t1.tag = t2.tag) OR (t1.tag = \'\' OR t1.tag = \',\' OR t1.tag = \' \') ');
        $tagsToRemoveData = $tagsToRemove->fetchAll(PDO::FETCH_ASSOC);

        // only execute this code if any tags we need to delete were found
        if (count($tagsToRemoveData) > 0) {
            $tagIds = [];
            foreach ($tagsToRemoveData as $row) {
                $tagIds[] = $row['tagId'];
            }

            // convert the array to comma separated string which we can use in a sql statement
            $tagIds = "'" .implode("','", $tagIds  ) . "'";

            // remove links to the tags we want to remove from lktag tables
            $this->execute("DELETE FROM `lktagcampaign` WHERE tagId IN ($tagIds)");
            $this->execute("DELETE FROM `lktagdisplaygroup` WHERE tagId IN ($tagIds)");
            $this->execute("DELETE FROM `lktaglayout` WHERE tagId IN ($tagIds)");
            $this->execute("DELETE FROM `lktagmedia` WHERE tagId IN ($tagIds)");
            $this->execute("DELETE FROM `lktagplaylist` WHERE tagId IN ($tagIds)");

            // finally remove the tag itself from tag table
            $this->execute("DELETE FROM `tag` WHERE tagId IN ($tagIds)");
        }
    }
}
