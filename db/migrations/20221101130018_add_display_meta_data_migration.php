<?php


use Phinx\Migration\AbstractMigration;

class AddDisplayMetaDataMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('display')
            ->addColumn('screenSize', 'integer', ['after' => 'display', 'default' => null, 'null' => true])
            ->addColumn('displayTypeId', 'integer', ['after' => 'display', 'default' => null, 'null' => true])
            ->addColumn('impressionsPerPlay', 'integer', ['after' => 'lanIpAddress'])
            ->addColumn('costPerPlay', 'integer', ['after' => 'lanIpAddress'])
            ->addColumn('isOutdoor', 'integer', ['after' => 'lanIpAddress', 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('customId', 'string', ['after' => 'lanIpAddress', 'limit' => 254])
        ->save();

        $this->table('displaygroup')
            ->addColumn('ref5', 'text', ['after' => 'bandwidthLimit', 'default' => null, 'null' => true])
            ->addColumn('ref4', 'text', ['after' => 'bandwidthLimit', 'default' => null, 'null' => true])
            ->addColumn('ref3', 'text', ['after' => 'bandwidthLimit', 'default' => null, 'null' => true])
            ->addColumn('ref2', 'text', ['after' => 'bandwidthLimit', 'default' => null, 'null' => true])
            ->addColumn('ref1', 'text', ['after' => 'bandwidthLimit', 'default' => null, 'null' => true])
            ->save();
    }
}
