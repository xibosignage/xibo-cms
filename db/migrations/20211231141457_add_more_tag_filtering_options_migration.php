<?php
/**
 * Add exactTags and LogicalOperator to Playlist and Display Groups
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */


use Phinx\Migration\AbstractMigration;

class AddMoreTagFilteringOptionsMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('playlist')
            ->addColumn('filterExactTags', 'integer', ['after' => 'filterMediaTags', 'default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('filterLogicalOperator', 'string', ['after' => 'filterExactTags', 'default' => 'OR', 'limit' => 3])
            ->save();

        $this->table('displaygroup')
            ->addColumn('dynamicCriteriaExactTags', 'integer', ['after' => 'dynamicCriteriaTags', 'default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('dynamicCriteriaLogicalOperator', 'string', ['after' => 'dynamicCriteriaExactTags', 'default' => 'OR', 'limit' => 3])
            ->save();
    }
}
