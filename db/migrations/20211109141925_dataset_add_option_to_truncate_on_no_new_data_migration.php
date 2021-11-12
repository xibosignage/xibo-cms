<?php
/**
 * Add new option to enable remote dataSet truncate with no new data from the source
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class DatasetAddOptionToTruncateOnNoNewDataMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('dataset')
            ->addColumn('truncateOnEmpty', 'integer', ['default' => 0, 'after' => 'clearRate', 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();
    }
}
