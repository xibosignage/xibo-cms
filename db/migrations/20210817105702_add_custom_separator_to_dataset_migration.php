<?php
/**
 * Add csvSeparator column to Dataset table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddCustomSeparatorToDatasetMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('dataset')
            ->addColumn('csvSeparator', 'string', ['limit' => 5, 'after' => 'limitPolicy', 'default' => null, 'null' => true])
            ->save();
    }
}
