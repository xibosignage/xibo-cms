<?php


use Phinx\Migration\AbstractMigration;

class WidgetFromToDtMigration extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        $widget = $this->table('widget');
        $widget
            ->addColumn('fromDt', 'integer')
            ->addColumn('toDt', 'integer')
            ->save();

        $this->execute('UPDATE `widget` SET fromDt = 0, toDt = 2147483647;');
    }
}
