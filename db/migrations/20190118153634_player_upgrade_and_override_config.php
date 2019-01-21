<?php


use Phinx\Migration\AbstractMigration;

/**
 * Class PlayerUpgradeAndOverrideConfig
 * Add Player Software to Pages
 * Remove version_instructions column from Display table
 * Add overrideConfig column to display table
 * Add default profile for Tizen
 */
class PlayerUpgradeAndOverrideConfig extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $pages = $this->table('pages');
        $displayTable = $this->table('display');
        $displayProfileTable = $this->table('displayprofile');

        // add Player Software page
        if (!$this->fetchRow('SELECT * FROM pages WHERE name = \'playersoftware\'')) {
            $pages->insert([
                'name' => 'playersoftware',
                'title' => 'Player Software',
                'asHome' => 0
            ])->save();
        }

        // remove version_instructions from display table
        $displayTable->removeColumn('version_instructions')->save();

        // add overrideConfig column to display table
        $overrideConfigColumn = $displayTable->hasColumn('overrideConfig');
        if (!$overrideConfigColumn)
            $displayTable->addColumn('overrideConfig', 'text')->save();

        // add default display profile for tizen
        if (!$this->fetchRow('SELECT * FROM displayprofile WHERE type = \'sssp\' AND isDefault = 1')) {
            $displayProfileTable->insert([
                'name' => 'Samsung Smart Signage',
                'type' => 'sssp',
                'config' => '[]',
                'isDefault' => 1
            ])->save();
        }
    }
}
