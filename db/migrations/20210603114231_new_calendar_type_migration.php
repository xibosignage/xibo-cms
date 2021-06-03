<?php
/**
 * Remove not needed column from saved_report table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class NewCalendarTypeMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Rename old calendar to agenda
        $this->execute('UPDATE `module` SET module.module = \'agenda\', module.class = \'Xibo\\\\Widget\\\\Agenda\', module.name = \'Agenda\' WHERE module.module = \'calendar\' ');

        // Update widgets type to the new agenda
        $this->execute('UPDATE `widget` SET widget.type = \'agenda\' WHERE widget.type = \'calendar\' ');
    }
}
