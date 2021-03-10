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
        // remove empty/odd tags
        $this->execute('DELETE FROM `tag` WHERE `tag`.tag = \'\' OR `tag`.tag = \',\' OR `tag`.tag = \' \' ; ');

        // remove duplicates, keeping lowest tagId
        $this->execute('DELETE t1 FROM `tag` t1 INNER JOIN `tag` t2 WHERE t1.tagId > t2.tagId AND t1.tag = t2.tag; ');
    }
}
