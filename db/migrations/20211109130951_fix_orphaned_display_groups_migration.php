<?php
/**
 * Set Display Group owner to existing Super Admin user
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class FixOrphanedDisplayGroupsMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->execute('UPDATE `displaygroup` SET userId = (SELECT userId FROM `user` WHERE userTypeId = 1 LIMIT 1) WHERE userId NOT IN (SELECT userId FROM `user`)');
    }
}
