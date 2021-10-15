<?php
/**
 * Add new columns to DataSetColumn table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddTooltipAndIsRequiredColumnsMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('datasetcolumn')
            ->addColumn('tooltip', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('isRequired', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->save();
    }
}
