<?php
/**
 * Migration for user onboarding form and user group modifications
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

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
                'default' => null,
                'limit' => '255'
            ])
            ->addColumn('defaultLibraryQuota', 'integer', [
                'default' => 0
            ])
            ->save();

        // If we only have the preset user groups, add some more.
        $countGroups = $this->execute('SELECT COUNT(*) AS cnt FROM `group` WHERE isUserSpecific = 0 AND isEveryone = 0');

        if ($countGroups['cnt'] <= 2) {
            // These can't be translated out the box as we don't know language on install?
            $this->table('group')
                ->insert([
                    [
                        'group' => 'Content Manager',
                        'description' => 'Management of all features related to Content Creation only.',
                        'defaultHomepageId' => 'statusdashboard.view',
                        'isUserSpecific' => 0,
                        'isEveryone' => 0,
                        'isSystemNotification' => 0,
                        'isDisplayNotification' => 1,
                        'isShownForAddUser' => 1,
                        'features' => '["report.view","displays.reporting","proof-of-play","folder.view","folder.add","folder.modify","tag.tagging","schedule.view","schedule.agenda","displays.view","displays.add","displays.modify","displaygroup.view","displaygroup.add","displaygroup.modify","displayprofile.view","displayprofile.add","displayprofile.modify","playersoftware.view","command.view","user.profile","notification.centre","notification.add","notification.modify","dashboard.status","log.view"]'
                    ],
                ])
                ->save();
        }
    }
}
