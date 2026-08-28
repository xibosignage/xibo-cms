<?php
/**
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class RemoveSettingImportEnabledMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->execute('DELETE FROM `setting` WHERE setting = \'SETTING_IMPORT_ENABLED\'');
    }
}
