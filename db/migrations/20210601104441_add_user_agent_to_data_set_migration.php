<?php
/**
 * Add userAgent column to DataSet table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddUserAgentToDataSetMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('dataset')
            ->addColumn('userAgent', 'text', ['null' => true, 'default' => null, 'after' => 'customHeaders'])
            ->save();
    }
}
