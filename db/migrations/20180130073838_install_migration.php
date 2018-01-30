<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

use Phinx\Migration\AbstractMigration;

/**
 * Class InstallMigration
 *  migration for initial installation of database
 */
class InstallMigration extends AbstractMigration
{
    /**
     * Migrate Up
     * Create a new Database if necessary
     * @throws Exception
     */
    public function up()
    {
        // At this point, we've no idea if we're an upgrade from a version without phinx or a fresh installation.
        // if we're an upgrade, we'd expect to find a version table
        // note: if we are a phinx "upgrade" we've already run this migration before and therefore don't need
        // to worry about anything below
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // we must be on at least DB version 84 to continue
            if ($dbVersion < 84)
                throw new Exception('Upgrading from an unsupported version, please ensure you have at least 1.7.0');

            // Step through our versions from the current one all the way to 1.8.5 (which is 135)
            for ($i = $dbVersion; $i <= 135, $i++;) {
                $method = 'step' . $i;
                if (method_exists($this, $method))
                    $this->$method($dbVersion);
            }

            // Remove the upgrade and version table
            $this->dropTable('upgrade');
            $this->dropTable('version');

        } else {
            // No version table - add initial structure and data.
            $this->addStructure();
            $this->addData();
        }
    }

    private function addStructure()
    {
        $auditLog = $this->table('auditlog', ['id' => 'logId']);
        $auditLog->addColumn('logDate', 'integer')
            ->addColumn('userId', 'integer')
            ->addColumn('message', 'string', ['limit' => 255])
            ->addColumn('entity', 'string', ['limit' => 50])
            ->addColumn('entityId', 'integer')
            ->addColumn('objectAfter', 'text')
            ->save();

        $bandwidthType = $this->table('bandwidthtype', ['id' => 'bandwidthTypeId']);
        $bandwidthType->addColumn('name', 'string', ['limit' => 25])
            ->save();

        $bandwidth = $this->table('bandwidth', ['primaryKey' => ['displayId', 'type', 'month']]);
        $bandwidth->addColumn('displayId', 'integer')
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('month', 'integer')
            ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addForeignKey('type', 'bandwidthtype')
            ->save();
    }

    private function addData()
    {

    }

    private function step85($dbFrom)
    {
        $display = $this->table('display');

        if (!$display->hasColumn('storageAvailableSpace')) {
            $display
                ->addColumn('storageAvailableSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                ->addColumn('storageTotalSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                ->save();
        }
    }

    private function step86($dbFrom)
    {
        $settings = $this->table('settings');
        $settings
            ->insert([
                [
                    'setting' => 'DASHBOARD_LATEST_NEWS_ENABLED',
                    'title' => 'Enable Latest News?',
                    'helptext' => 'Should the Dashboard show latest news? The address is provided by the theme.',
                    'value' => '1',
                    'fieldType' => 'checkbox',
                    'options' => '',
                    'cat' => 'general',
                    'userChange' => '1',
                    'type' => 'checkbox',
                    'validation' => '',
                    'ordering' => '110',
                    'default' => '1',
                    'userSee' => '1',
                ],
                [
                    'setting' => 'LIBRARY_MEDIA_DELETEOLDVER_CHECKB',
                    'title' => 'Default for \"Delete old version of Media\" checkbox. Shown when Editing Library Media.',
                    'helptext' => 'Default the checkbox for Deleting Old Version of media when a new file is being uploaded to the library.',
                    'value' => 'Unchecked',
                    'fieldType' => 'dropdown',
                    'options' => 'Checked|Unchecked',
                    'cat' => 'defaults',
                    'userChange' => '1',
                    'type' => 'dropdown',
                    'validation' => '',
                    'ordering' => '50',
                    'default' => 'Unchecked',
                    'userSee' => '1',
                ]
            ])
            ->save();

        // Update a setting
        $this->execute('UPDATE `setting` SET `type` = \'checkbox\', `fieldType` = \'checkbox\' WHERE setting = \'SETTING_LIBRARY_TIDY_ENABLED\' OR setting = \'SETTING_IMPORT_ENABLED\';');
    }

    private function step87($dbFrom)
    {
        $settings = $this->table('settings');
        $settings
            ->insert([
                'setting' => 'PROXY_EXCEPTIONS',
                'title' => 'Proxy Exceptions',
                'helptext' => 'Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.',
                'value' => '1',
                'fieldType' => 'text',
                'options' => '',
                'cat' => 'network',
                'userChange' => '1',
                'type' => 'string',
                'validation' => '',
                'ordering' => '32',
                'default' => '',
                'userSee' => '1',
            ])
            ->save();

        // If we haven't run step85 during this migration, then we will want to update our storageAvailable columns
        if ($dbFrom > 85) {
            // Change to big ints.
            $display = $this->table('display');
            $display
                ->changeColumn('storageAvailableSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                ->changeColumn('storageTotalSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                ->save();
        }
    }

    private function step88()
    {
        $auditLog = $this->table('auditlog', ['id' => 'logId']);
        $auditLog->addColumn('logDate', 'integer')
            ->addColumn('userId', 'integer')
            ->addColumn('message', 'string', ['limit' => 255])
            ->addColumn('entity', 'string', ['limit' => 50])
            ->addColumn('entityId', 'integer')
            ->addColumn('objectAfter', 'text')
            ->save();

        $this->execute('INSERT INTO `pages` (`name`, `pagegroupID`) SELECT \'auditlog\', pagegroupID FROM `pagegroup` WHERE pagegroup.pagegroup = \'Reports\';');

        $group = $this->table('group');
        if (!$group->hasColumn('libraryQuota')) {
            $group->addColumn('libraryQuota', 'int', ['null' => true])
                ->save();
        }
    }

    private function step92()
    {
        $settings = $this->table('settings');
        $settings
            ->insert([
                'setting' => 'CDN_URL',
                'title' => 'CDN Address',
                'helptext' => 'Content Delivery Network Address for serving file requests to Players',
                'value' => '',
                'fieldType' => 'text',
                'options' => '',
                'cat' => 'network',
                'userChange' => '0',
                'type' => 'string',
                'validation' => '',
                'ordering' => '33',
                'default' => '',
                'userSee' => '0',
            ])
            ->save();

        $this->execute('ALTER TABLE  `datasetcolumn` CHANGE  `ListContent`  `ListContent` VARCHAR( 1000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');

        $this->execute('ALTER TABLE `stat` ADD INDEX Type (`displayID`, `end`, `Type`);');
    }

    private function step120()
    {
        $log = $this->table('log');
        $log->removeColumn('scheduleId')
            ->removeColumn('layoutId')
            ->removeColumn('mediaId')
            ->removeColumn('requestUri')
            ->removeColumn('remoteAddr')
            ->removeColumn('userAgent')
            ->changeColumn('type', 'string', ['limit' => 254])
            ->addColumn('channel', 'string', ['limit' => 5, 'after' => 'logDate'])
            ->addColumn('runNo', 'string', ['limit' => 10])
            ->save();

        $module = $this->table('module');
        $module->addColumn('viewPath', 'string', ['limit' => 254, 'default' => '../modules'])
            ->addColumn('class', 'string', ['limit' => 254])
            ->save();

        $permission = $this->table('permission', ['id' => 'permissionId']);
        $permission->addColumn('entityId', 'integer')
            ->addColumn('groupId', 'integer')
            ->addColumn('objectId', 'integer')
            ->addColumn('view', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('edit', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('delete', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();

        $permissionEntity = $this->table('permissionentity', ['id' => 'entityId']);
        $permissionEntity->addColumn('entity', 'string', ['limit' => 50])
            ->addIndex('entity', ['unique' => true])
            ->insert([
                ['entity' => 'Xibo\\Entity\\Campaign'],
                ['entity' => 'Xibo\\Entity\\DataSet'],
                ['entity' => 'Xibo\\Entity\\DisplayGroup'],
                ['entity' => 'Xibo\\Entity\\Media'],
                ['entity' => 'Xibo\\Entity\\Page'],
                ['entity' => 'Xibo\\Entity\\Playlist'],
                ['entity' => 'Xibo\\Entity\\Region'],
                ['entity' => 'Xibo\\Entity\\Widget'],
            ])
            ->save();

        $this->execute('INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`) SELECT groupId, 1, pageId, 1, 0, 0 FROM `lkpagegroup`;');
        $this->execute('INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`) SELECT groupId, 5, campaignId, view, edit, del FROM `lkcampaigngroup`;');
        $this->execute('INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`) SELECT groupId, 4, mediaId, view, edit, del FROM `lkmediagroup`;');
        $this->execute('INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`) SELECT groupId, 9, dataSetId, view, edit, del FROM `lkdatasetgroup`;');
        $this->execute('INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`) SELECT groupId, 3, displayGroupId, view, edit, del FROM `lkdisplaygroupgroup`;');

        $this->dropTable('lkpagegroup');
        $this->dropTable('lkmenuitemgroup');
        $this->dropTable('lkcampaigngroup');
        $this->dropTable('lkmediagroup');
        $this->dropTable('lkdatasetgroup');
        $this->dropTable('lkdisplaygroupgroup');

        $pages = $this->table('pages');
        $pages->removeIndexByName('pages_ibfk_1')
            ->removeColumn('pageGroupId')
            ->addColumn('title', 'string', ['limit' => 50])
            ->addColumn('asHome', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->insert([
                ['name' => 'region', 'title' => ''],
                ['name' => 'playlist', 'title' => ''],
                ['name' => 'maintenance', 'title' => ''],
            ])
            ->save();

        $this->execute('INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`) SELECT `groupId`, 1, (SELECT pageId FROM `pages` WHERE `name` = \'region\'), `view`, `edit`, `delete` FROM `permission` WHERE `objectId` = (SELECT pageId FROM `pages` WHERE `name` = \'layout\') AND `entityId` = 1;');
        $this->execute('INSERT INTO `permission` (`groupId`, `entityId`, `objectId`, `view`, `edit`, `delete`) SELECT `groupId`, 1, (SELECT pageId FROM `pages` WHERE `name` = \'playlist\'), `view`, `edit`, `delete` FROM `permission` WHERE `objectId` = (SELECT pageId FROM `pages` WHERE `name` = \'layout\') AND `entityId` = 1;');
        $this->execute('UPDATE `pages` SET title = CONCAT(UCASE(LEFT(name, 1)), SUBSTRING(name, 2)), asHome = 1;');
        $this->execute('UPDATE `pages` SET `name` = \'audit\' WHERE `name` = \'auditlog\';');
        $this->execute('UPDATE `pages` SET asHome = 0 WHERE `name` IN (\'update\',\'admin\',\'manual\',\'help\',\'clock\',\'preview\',\'region\',\'playlist\',\'maintenance\');');
        $this->execute('UPDATE `pages` SET `name` =  \'library\', `title` = \'Library\' WHERE  `pages`.`name` = \'content\';');
        $this->execute('UPDATE `pages` SET `name` =  \'applications\', `title` = \'Applications\' WHERE  `pages`.`name` = \'oauth\';');
        $this->execute('UPDATE `pages` SET `title` = \'Media Dashboard\' WHERE  `pages`.`name` = \'mediamanager\';');
        $this->execute('UPDATE `pages` SET `title` = \'Status Dashboard\' WHERE  `pages`.`name` = \'statusdashboard\';');
        $this->execute('UPDATE `pages` SET `title` = \'Display Profiles\' WHERE  `pages`.`name` = \'displayprofile\';');
        $this->execute('UPDATE `pages` SET `title` = \'Display Groups\' WHERE  `pages`.`name` = \'displaygroup\';');
        $this->execute('UPDATE `pages` SET `title` = \'Home\' WHERE  `pages`.`name` = \'index\';');
        $this->execute('UPDATE `pages` SET `title` = \'Audit Trail\' WHERE  `pages`.`name` = \'auditlog\';');

        $this->dropTable('menuitem');
        $this->dropTable('menu');
        $this->dropTable('pagegroup');

        $layout = $this->table('layout');
        $layout->addColumn('width', 'decimal')
            ->addColumn('height', 'decimal')
            ->addColumn('backgroundColor', 'string', ['limit' => 25, 'null' => true])
            ->addColumn('backgroundzIndex', 'integer', ['default' => 1])
            ->addColumn('schemaVersion', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->changeColumn('xml', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('statusMessage', 'text', ['null' => true])
            ->save();

        $this->execute('UPDATE `user` SET homepage = IFNULL((SELECT pageId FROM `pages` WHERE pages.name = `user`.homepage LIMIT 1), 1);');
        $this->execute('ALTER TABLE  `user` CHANGE  `homepage`  `homePageId` INT NOT NULL DEFAULT  \'1\' COMMENT  \'The users homepage\';');

        $this->execute('DELETE FROM module WHERE module = \'counter\';');

        $linkRegionPlaylist = $this->table('lkregionplaylist', ['id' => false, 'primaryKey' => 'regionId', 'playlistId', 'displayOrder']);
        $linkRegionPlaylist->addColumn('regionId', 'integer')
            ->addColumn('playlistId', 'integer')
            ->addColumn('displayOrder', 'integer')
            ->save();

        $linkWidgetMedia = $this->table('lkwidgetmedia', ['id' => false, 'primaryKey' => ['widgetId', 'mediaId']]);
        $linkWidgetMedia->addColumn('widgetId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->save();

        $playlist = $this->table('playlist', ['id' => 'playlistId']);
        $playlist->addColumn('name', 'string', ['limit' => 254])
            ->addColumn('ownerId', 'integer')
            ->save();

        $region = $this->table('region', ['id' => 'regionId']);
        $region->addColumn('layoutId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addColumn('ownerId', 'integer')
            ->addColumn('name', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('width', 'decimal')
            ->addColumn('height', 'decimal')
            ->addColumn('zIndex', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('duration', 'integer', ['default' => 0])
            ->save();

        $regionOption = $this->table('regionoption', ['id' => false, 'primaryKey' => ['regionId', 'option']]);
        $regionOption->addColumn('regionId', 'integer')
            ->addColumn('option', 'string', ['limit' => 50])
            ->addColumn('value', 'text', ['null' => true])
            ->save();

        $widget = $this->table('widget', ['id' => 'widgetId']);
        $widget->addColumn('widgetId', 'integer')
            ->addColumn('playlistId', 'integer')
            ->addColumn('ownerId', 'integer')
            ->addColumn('type', 'string', ['limit' => 50])
            ->addColumn('duration', 'integer')
            ->addColumn('displayOrder', 'integer')
            ->addColumn('calculatedDuration', 'integer')
            ->addColumn('useDuration', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->save();

        $widgetOption = $this->table('widgetOption', ['id' => false, 'primaryKey' => ['widgetId', 'type', 'option']]);
        $widgetOption->addColumn('widgetId', 'integer')
            ->addColumn('type', 'string', ['limit' => 50])
            ->addColumn('option', 'string', ['limit' => 254])
            ->addColumn('value', 'text', ['null' => true])
            ->save();

        $this->dropTable('oauth_log');
        $this->dropTable('oauth_server_nonce');
        $this->dropTable('oauth_server_token');
        $this->dropTable('oauth_server_registry');

        $oauthAccessTokens = $this->table('oauth_access_tokens', ['id' => false, 'primaryKey' => ['access_token']]);
        $oauthAccessTokens
            ->addColumn('access_token', 'string', ['limit' => 254])
            ->addColumn('session_id', 'integer', ['signed' => false])
            ->addColumn('expire_time', 'integer')
            ->addForeignKey('session_id', 'oauth_sessions', 'id', ['delete' => 'CASCADE'])
            ->save();

        $oauthAccessTokenScopes = $this->table('oauth_access_token_scopes');
        $oauthAccessTokenScopes
            ->addColumn('access_token', 'string', ['limit' => 254])
            ->addColumn('scope', 'string', ['limit' => 254])
            ->addForeignKey('access_token', 'oauth_access_tokens', 'access_token', ['delete' => 'CASCADE'])
            ->addForeignKey('scope', 'oauth_access_tokens', 'access_token', ['delete' => 'CASCADE'])
            ->save();

        $oauthAuthCodes = $this->table('oauth_auth_codes', ['id' => false, 'primaryKey' => ['auth_code']]);
        $oauthAuthCodes
            ->addColumn('auth_code', 'string', ['limit' => 254])
            ->addColumn('session_id', 'integer', ['signed' => false])
            ->addColumn('expire_time', 'integer')
            ->addColumn('client_redirect_uri', 'string', ['limit' => 500])
            ->addForeignKey('session_id', 'oauth_sessions', 'id', ['delete' => 'CASCADE'])
            ->save();

        $oauthAuthCodeScopes = $this->table('oauth_access_token_scopes');
        $oauthAuthCodeScopes
            ->addColumn('auth_code', 'string', ['limit' => 254])
            ->addColumn('scope', 'string', ['limit' => 254])
            ->addForeignKey('auth_code', 'oauth_auth_codes', 'auth_code', ['delete' => 'CASCADE'])
            ->addForeignKey('scope', 'oauth_scopes', 'id', ['delete' => 'CASCADE'])
            ->save();

        $oauthClients = $this->table('oauth_clients', ['id' => false, 'primaryKey' => ['id']]);
        $oauthClients
            ->addColumn('id', 'string', ['limit' => 254])
            ->addColumn('secret', 'string', ['limit' => 254])
            ->addColumn('secret', 'string', ['limit' => 254])
            ->addColumn('userId', 'integer')
            ->addColumn('authCode', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('clientCredentials', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();

        $oauthClientRedirects = $this->table('oauth_client_redirect_uris');
        $oauthClientRedirects
            ->addColumn('client_id', 'string', ['limit' => 254])
            ->addColumn('redirect_uri', 'string', ['limit' => 500])
            ->save();

        $oauthRefreshToeksn = $this->table('oauth_refresh_tokens', ['id' => false, 'primaryKey' => ['refresh_token']]);
        $oauthRefreshToeksn
            ->addColumn('refresh_token', 'string', ['limit' => 254])
            ->addColumn('expire_time', 'integer')
            ->addColumn('access_token', 'string', ['limit' => 254])
            ->addForeignKey('access_token', 'oauth_access_tokens', 'access_token', ['delete' => 'CASCADE'])
            ->save();

        $oauthScopes = $this->table('oauth_scopes', ['id' => false, 'primaryKey' => ['id']]);
        $oauthScopes
            ->addColumn('id', 'string', ['limit' => 254])
            ->addColumn('description', 'string', ['limit' => 1000])
            ->save();

        $oauthSessions = $this->table('oauth_sessions');
        $oauthSessions
            ->addColumn('id', 'string', ['limit' => 254])
            ->addColumn('owner_type', 'string', ['limit' => 254])
            ->addColumn('owner_id', 'string', ['limit' => 254])
            ->addColumn('client_id', 'string', ['limit' => 254])
            ->addColumn('client_redirect_uri', 'string', ['limit' => 500, 'null' => true])
            ->addForeignKey('client_id', 'oauth_clients', 'id', ['delete' => 'CASCADE'])
            ->save();

        $oauthSessionsScopes = $this->table('oauth_session_scopes');
        $oauthSessionsScopes
            ->addColumn('id', 'string', ['limit' => 254])
            ->addColumn('session_id', 'integer', ['signed' => false])
            ->addColumn('scope', 'string', ['limit' => 254])
            ->addForeignKey('session_id', 'oauth_sessions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('scope', 'oauth_scopes', 'id', ['delete' => 'CASCADE'])
            ->save();

        $this->dropTable('file');

        $this->execute('TRUNCATE TABLE `xmdsnonce`;');
        $this->execute('RENAME TABLE `xmdsnonce` TO `requiredfile`;');

        $requiredFile = $this->table('requiredfile');
        $requiredFile->addColumn('requestKey', 'string', ['limit' => 10])
            ->addColumn('bytesRequested', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addColumn('complete' , 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();

        $this->execute('ALTER TABLE  `requiredfile` CHANGE  `nonceId`  `rfId` BIGINT( 20 ) NOT NULL AUTO_INCREMENT;');
        $this->execute('ALTER TABLE  `requiredfile` CHANGE  `regionId`  `regionId` INT NULL;');
        $this->execute('ALTER TABLE `requiredfile` DROP `fileId`;');

        $display = $this->table('display');
        $display
            ->removeColumn('MediaInventoryXml')
            ->save();

        $this->execute('DELETE FROM `setting` WHERE setting = \'USE_INTL_DATEFORMAT\';');
        $this->execute('UPDATE `setting` SET `options` = \'Emergency|Alert|Critical|Error|Warning|Notice|Info|Debug\', value = \'Error\' WHERE setting = \'audit\';');
        $this->execute('UPDATE  `setting` SET  `options` =  \'private|group|public\' WHERE  `setting`.`setting` IN (\'MEDIA_DEFAULT\', \'LAYOUT_DEFAULT\');');
        $this->execute('INSERT INTO `setting` (`settingid`, `setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES (NULL, \'INSTANCE_SUSPENDED\', \'0\', \'checkbox\', \'Is this instance suspended?\', NULL, \'general\', \'0\', \'Instance Suspended\', \'\', \'120\', \'0\', \'0\', \'checkbox\'),(NULL, \'INHERIT_PARENT_PERMISSIONS\', \'1\', \'checkbox\', \'Inherit permissions from Parent when adding a new item?\', NULL, \'permissions\', \'1\', \'Inherit permissions\', \'\', \'50\', \'1\', \'1\', \'checkbox\');');
        $this->execute('INSERT INTO `datatype` (`DataTypeID`, `DataType`) VALUES (\'5\', \'Library Image\');');
        $this->execute('UPDATE  `datatype` SET  `DataType` =  \'External Image\' WHERE  `datatype`.`DataTypeID` =4 AND  `datatype`.`DataType` =  \'Image\' LIMIT 1 ;');

        $this->dropTable('lkdatasetlayout');

        $this->execute('CREATE TABLE `temp_lkmediadisplaygroup` AS SELECT `mediaid` ,`displaygroupid` FROM `lkmediadisplaygroup` WHERE 1 GROUP BY `mediaid` ,`displaygroupid`;');
        $this->execute('DROP TABLE `lkmediadisplaygroup`;');
        $this->execute('RENAME TABLE `temp_lkmediadisplaygroup` TO `lkmediadisplaygroup`;');

        $this->execute('ALTER TABLE  `lkmediadisplaygroup` ADD UNIQUE (`mediaid` ,`displaygroupid`);');
        $this->execute('ALTER TABLE  `lkcampaignlayout` ADD UNIQUE (`CampaignID` ,`LayoutID` ,`DisplayOrder`);');

        $linkScheduleDisplayGroup = $this->table('lkscheduledisplaygroup', ['id' => false, 'primaryKey' => ['eventId'], 'displayGroupId']);
        $linkScheduleDisplayGroup
            ->addColumn('eventId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->save();

        $this->execute('ALTER TABLE `schedule_detail` DROP FOREIGN KEY  `schedule_detail_ibfk_8` ;');
        $this->execute('ALTER TABLE `schedule_detail` DROP `DisplayGroupID`;');

        // Get all events and their Associated display group id's
        foreach ($this->fetchAll('SELECT eventId, displayGroupIds FROM `schedule`') as $event) {
            // Ping open the displayGroupIds
            $displayGroupIds = explode(',', $event['displayGroupIds']);

            // Construct some SQL to add the link
            $sql = 'INSERT INTO `lkscheduledisplaygroup` (eventId, displayGroupId) VALUES ';

            foreach ($displayGroupIds as $id) {
                $sql .= '(' . $event['eventId'] . ',' . $id . '),';
            }

            $sql = rtrim($sql, ',');

            $this->execute($sql);
        }

        $this->execute('ALTER TABLE `schedule` DROP `DisplayGroupIDs`;');

        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Image\' WHERE module = \'Image\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Video\' WHERE module = \'Video\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Flash\' WHERE module = \'Flash\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\PowerPoint\' WHERE module = \'PowerPoint\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Webpage\' WHERE module = \'Webpage\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Ticker\' WHERE module = \'Ticker\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Text\' WHERE module = \'Text\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Embedded\' WHERE module = \'Embedded\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\DataSetView\' WHERE module = \'datasetview\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\ShellCommand\' WHERE module = \'shellcommand\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\LocalVideo\' WHERE module = \'localvideo\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\GenericFile\' WHERE module = \'genericfile\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Clock\' WHERE module = \'Clock\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Font\' WHERE module = \'Font\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Twitter\' WHERE module = \'Twitter\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\ForecastIo\' WHERE module = \'forecastio\';');
        $this->execute('UPDATE `module` SET `class` = \'\\Xibo\\Widget\\Finance\' WHERE module = \'ForecastIo\';');
    }

    private function step121()
    {
        $display = $this->table('display');
        $display
            ->addColumn('xmrChannel', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('xmrPubKey', 'text', ['null' => true])
            ->addColumn('lastCommandSuccess', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 2])
            ->save();

        $settings = $this->table('settings');
        $settings
            ->insert([
                [
                    'setting' => 'XMR_ADDRESS',
                    'title' => 'XMR Private Address',
                    'helptext' => 'Please enter the private address for XMR.',
                    'value' => 'tcp:://localhost:5555',
                    'fieldType' => 'checkbox',
                    'options' => '',
                    'cat' => 'displays',
                    'userChange' => '1',
                    'type' => 'string',
                    'validation' => '',
                    'ordering' => '5',
                    'default' => 'tcp:://localhost:5555',
                    'userSee' => '1',
                ],
                [
                    'setting' => 'XMR_PUB_ADDRESS',
                    'title' => 'XMR Public Address',
                    'helptext' => 'Please enter the public address for XMR.',
                    'value' => 'tcp:://localhost:5556',
                    'fieldType' => 'dropdown',
                    'options' => 'Checked|Unchecked',
                    'cat' => 'displays',
                    'userChange' => '1',
                    'type' => 'string',
                    'validation' => '',
                    'ordering' => '6',
                    'default' => 'tcp:://localhost:5556',
                    'userSee' => '1',
                ]
            ])
            ->save();

        $linkLayoutDisplayGroup = $this->table('lklayoutdisplaygroup', ['comment' => 'Layout associations directly to Display Groups']);
        $linkLayoutDisplayGroup->addColumn('layoutId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->addIndex(['layoutId', 'displayGroupId'], ['unique' => true])
            ->save();

        $pages = $this->table('pages');
        $pages->insert([
            'name' => 'command',
            'title' => 'Commands',
            'asHome' => 1
        ])->save();

        $command = $this->table('command', ['id' => 'commandId']);
        $command->addColumn('command', 'string', ['limit' => 254])
            ->addColumn('code', 'string', ['limit' => 50])
            ->addColumn('description', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('userId', 'integer')
            ->save();

        $linkCommandDisplayProfile = $this->table('lkcommanddisplayprofile', ['id' => false, ['primaryKey' => ['commandId', 'displayProfileId']]]);
        $linkCommandDisplayProfile->addColumn('commandId', 'integer')
            ->addColumn('displayProfileId', 'integer')
            ->addColumn('commandString', 'string', ['limit' => 1000])
            ->addColumn('validationString', 'string', ['limit' => 1000])
            ->save();

        $schedule = $this->table('schedule');
        $schedule->changeColumn('campaignId', 'integer', ['null' => true])
            ->addColumn('eventTypeId', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'after' => 'eventId', 'default' => 1])
            ->addColumn('commandId', 'integer', ['after' => 'campaignId'])
            ->changeColumn('toDt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
            ->save();

        $this->execute('UPDATE `schedule` SET `eventTypeId` = 1;');

        $scheduleDetail = $this->table('schedule_detail');
        $scheduleDetail->changeColumn('toDt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
            ->save();

        $media = $this->table('media');
        $media->addColumn('released', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('apiRef', 'string', ['limit' => 254, 'null' => true])
            ->save();

        $user = $this->table('user');
        $user->addColumn('firstName', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('lastName', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref1', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref2', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref3', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref4', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref5', 'string', ['limit' => 254, 'null' => true])
            ->save();
    }
}
