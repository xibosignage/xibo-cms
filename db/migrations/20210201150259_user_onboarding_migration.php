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
            ->save();

        // If we only have the preset user groups, add some more.
        // We add a total of 3 preset groups by this point
        $countGroups = $this->fetchRow('
            SELECT COUNT(*) AS cnt 
              FROM `group` 
             WHERE `group`.isUserSpecific = 0 
                AND `group`.isEveryone = 0
                AND `group`.group NOT IN (\'Users\', \'System Notifications\', \'Playlist Dashboard User\')
        ');

        if ($countGroups['cnt'] <= 0) {
            // These can't be translated out the box as we don't know language on install?
            $this->table('group')
                ->insert([
                    [
                        'group' => 'Content Manager',
                        'description' => 'Management of all features related to Content Creation only.',
                        'defaultHomepageId' => 'icondashboard.view',
                        'isUserSpecific' => 0,
                        'isEveryone' => 0,
                        'isSystemNotification' => 0,
                        'isDisplayNotification' => 0,
                        'isShownForAddUser' => 1,
                        'features' => '["folder.view","folder.add","folder.modify","library.view","library.add","library.modify","dataset.view","dataset.add","dataset.modify","dataset.data","playlist.view","playlist.add","playlist.modify","layout.view","layout.add","layout.modify","layout.export","template.view","template.add","template.modify","resolution.view","resolution.add","resolution.modify","campaign.view","campaign.add","campaign.modify","tag.view","tag.tagging","user.profile"]'
                    ],
                    [
                        'group' => 'Playlist Manager',
                        'description' => 'Management of specific Playlists to edit / replace Media only.',
                        'defaultHomepageId' => 'playlistdashboard.view',
                        'isUserSpecific' => 0,
                        'isEveryone' => 0,
                        'isSystemNotification' => 0,
                        'isDisplayNotification' => 0,
                        'isShownForAddUser' => 1,
                        'features' => '["user.profile","dashboard.playlist"]'
                    ],
                    [
                        'group' => 'Schedule Manager',
                        'description' => 'Management of all features for the purpose of Event Scheduling only.',
                        'defaultHomepageId' => 'icondashboard.view',
                        'isUserSpecific' => 0,
                        'isEveryone' => 0,
                        'isSystemNotification' => 0,
                        'isDisplayNotification' => 0,
                        'isShownForAddUser' => 1,
                        'features' => '["folder.view","schedule.view","schedule.agenda","schedule.add","schedule.modify","schedule.now","daypart.view","daypart.add","daypart.modify","user.profile"]'
                    ],
                    [
                        'group' => 'Display Manager',
                        'description' => 'Management of all features for the purpose of Display Administration only.',
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
        } else {
            // We should add something, otherwise we won't have any options when it comes to the new wizard
            $this->execute('UPDATE `group` SET isShownForAddUser = 1 WHERE isUserSpecific = 0 AND isEveryone = 0 AND isSystemNotification = 0');
        }
    }
}
