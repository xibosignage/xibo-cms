<?php


use Phinx\Migration\AbstractMigration;

class AddDefaultTransitionDurationSettingMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Add a setting allowing users enable event sync on applicable events
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DEFAULT_TRANSITION_DURATION\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'DEFAULT_TRANSITION_DURATION',
                    'value' => 1000,
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }
    }
}
