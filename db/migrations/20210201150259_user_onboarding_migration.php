<?php


use Phinx\Migration\AbstractMigration;

/**
 * Class UserOnboardingMigration
 */
class UserOnboardingMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Add new options to user group
        $this->table('group')
            ->addColumn('description', 'string', [
                'default' => null,
                'null' => true,
                'limit' => 500
            ])
            ->addColumn('isShownForAddUser', 'integer', [
                'default' => 0,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY
            ])
            ->addColumn('defaultHomepageId', 'string', [
                'null' => true,
                'default' => 'null',
                'limit' => '255'
            ])
            ->addColumn('defaultLibraryQuota', 'integer', [
                'default' => 0
            ])
            ->save();
    }
}
