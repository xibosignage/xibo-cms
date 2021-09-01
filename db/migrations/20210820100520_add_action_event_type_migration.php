<?php
/**
 * Add new columns to Schedule table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddActionEventTypeMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('schedule')
            ->addColumn('actionTriggerCode', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('actionType', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('actionLayoutCode', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->save();
    }
}
