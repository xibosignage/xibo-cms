<?php


use Phinx\Migration\AbstractMigration;

class AddDynamicCriteriaTagsMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $table = $this->table('displaygroup');
        $table
            ->addColumn('dynamicCriteriaTags', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->save();
    }
}
