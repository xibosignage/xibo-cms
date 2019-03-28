<?php


use Phinx\Migration\AbstractMigration;

class CreateLayoutHistoryTableMigration extends AbstractMigration
{
    public function change()
    {
        $users = $this->table('lklayouthistory', ['id' => 'lkLayoutHistoryId']);
        $users->addColumn('campaignId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addColumn('publishedDate', 'datetime')
            ->addForeignKey('campaignId', 'campaign', 'campaignId')
            ->create();
    }
}
