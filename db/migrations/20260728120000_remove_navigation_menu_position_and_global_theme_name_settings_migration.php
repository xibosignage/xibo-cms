<?php
/**
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class RemoveNavigationMenuPositionAndGlobalThemeNameSettingsMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->execute('DELETE FROM `setting` WHERE setting = \'NAVIGATION_MENU_POSITION\'');
        $this->execute('DELETE FROM `setting` WHERE setting = \'GLOBAL_THEME_NAME\'');
    }
}
