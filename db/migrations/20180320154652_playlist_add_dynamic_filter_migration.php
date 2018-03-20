<?php


use Phinx\Migration\AbstractMigration;

class PlaylistAddDynamicFilterMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $table = $this->table('playlist');

        $table
            ->addColumn('isDynamic', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('filterMediaName', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('filterMediaTags', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();
    }
}
