<?php
/**
 * Add isCustom column to DisplayProfile table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddIsCustomToDisplayProfileMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('displayprofile')
            ->addColumn('isCustom', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();
    }
}
