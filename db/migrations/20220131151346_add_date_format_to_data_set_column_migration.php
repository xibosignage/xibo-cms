<?php
/**
 * Add a new column (dateFormat) to datasetcolumn table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddDateFormatToDataSetColumnMigration extends AbstractMigration
{
    public function change()
    {
        $this->table('datasetcolumn')
            ->addColumn('dateFormat', 'string', ['null' => true, 'default' => null, 'limit' => 20])
            ->save();
    }
}
