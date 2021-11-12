<?php
/**
 * Fix default homepage for Playlist Manager User group
 * Fix feature for old Playlist Dashboard User group
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class FixPlaylistManagerUserGroupMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->execute('UPDATE `group` SET defaultHomepageId = \'playlistdashboard.view\' WHERE `group` = \'Playlist Manager\'');

        // get current set of features assigned to Playlist Dashboard User user group
        $features = json_decode($this->fetchRow('SELECT features FROM `group` WHERE `group` = \'Playlist Dashboard User\' ')[0]);

        // add the feature to Playlist Dashboard
        $features[] = 'dashboard.playlist';

        // Update features and default homepage for Playlist Dashboard User UserGroup
        $this->execute('UPDATE `group` SET features = \'' . json_encode($features) . '\' WHERE `group` = \'Playlist Dashboard User\' ');
        $this->execute('UPDATE `group` SET defaultHomepageId = \'playlistdashboard.view\' WHERE `group` = \'Playlist Dashboard User\' ');
    }
}
