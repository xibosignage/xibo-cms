<?php
/**
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class RemoveMaintenanceKeyMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->execute('DELETE FROM `setting` WHERE setting = \'MAINTENANCE_KEY\' ');
    }
}
