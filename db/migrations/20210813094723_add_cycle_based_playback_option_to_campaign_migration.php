<?php
/**
 * Add two new columns to Campaign table to support cycle based playback
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddCycleBasedPlaybackOptionToCampaignMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('campaign')
            ->addColumn('cyclePlaybackEnabled', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,'default' => 0])
            ->addColumn('playCount', 'integer', ['default' => null, 'null' => true])
            ->save();
    }
}
