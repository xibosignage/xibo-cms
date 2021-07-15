<?php
/**
 * Install Agenda Widget and update current Calendar Widgets type to Agenda.
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class NewCalendarTypeMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Add new calendar type to agenda
        $this->table('module')->insert(
            [
                'module' => 'agenda',
                'name' => 'Agenda',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'A module for displaying an agenda based on an iCal feed',
                'schemaVersion' => 1,
                'validExtensions' => '',
                'previewEnabled' => 1,
                'assignable' => 1,
                'render_as' => 'html',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\Agenda',
                'defaultDuration' => 60,
                'installName' => 'agenda'
            ]
        )->save();

        // Update widgets type to the new agenda
        $this->execute('UPDATE `widget` SET widget.type = \'agenda\' WHERE widget.type = \'calendar\' ');
    }
}
