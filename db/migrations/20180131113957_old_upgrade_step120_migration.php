<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep120Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 120;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
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
                $pages
                    ->removeIndexByName('pages_ibfk_1')
                    ->dropForeignKey('pageGroupId')
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

                $linkRegionPlaylist = $this->table('lkregionplaylist', ['id' => false, 'primary_key' => 'regionId', 'playlistId', 'displayOrder']);
                $linkRegionPlaylist->addColumn('regionId', 'integer')
                    ->addColumn('playlistId', 'integer')
                    ->addColumn('displayOrder', 'integer')
                    ->save();

                $linkWidgetMedia = $this->table('lkwidgetmedia', ['id' => false, 'primary_key' => ['widgetId', 'mediaId']]);
                $linkWidgetMedia->addColumn('widgetId', 'integer')
                    ->addColumn('mediaId', 'integer')
                    ->save();

                $playlist = $this->table('playlist', ['id' => 'playlistId']);
                $playlist->addColumn('name', 'string', ['limit' => 254])
                    ->addColumn('ownerId', 'integer')
                    ->save();

                $region = $this->table('region', ['id' => 'regionId']);
                $region
                    ->addColumn('layoutId', 'integer')
                    ->addColumn('ownerId', 'integer')
                    ->addColumn('name', 'string', ['limit' => 254, 'null' => true])
                    ->addColumn('width', 'decimal')
                    ->addColumn('height', 'decimal')
                    ->addColumn('top', 'decimal')
                    ->addColumn('left', 'decimal')
                    ->addColumn('zIndex', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
                    ->addColumn('duration', 'integer', ['default' => 0])
                    ->save();

                $regionOption = $this->table('regionoption', ['id' => false, 'primary_key' => ['regionId', 'option']]);
                $regionOption->addColumn('regionId', 'integer')
                    ->addColumn('option', 'string', ['limit' => 50])
                    ->addColumn('value', 'text', ['null' => true])
                    ->save();

                $widget = $this->table('widget', ['id' => 'widgetId']);
                $widget
                    ->addColumn('playlistId', 'integer')
                    ->addColumn('ownerId', 'integer')
                    ->addColumn('type', 'string', ['limit' => 50])
                    ->addColumn('duration', 'integer')
                    ->addColumn('displayOrder', 'integer')
                    ->addColumn('calculatedDuration', 'integer')
                    ->addColumn('useDuration', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
                    ->addForeignKey('playlistId', 'playlist', 'playlistId')
                    ->addForeignKey('ownerId', 'user', 'userId')
                    ->save();

                $widgetOption = $this->table('widgetoption', ['id' => false, 'primary_key' => ['widgetId', 'type', 'option']]);
                $widgetOption->addColumn('widgetId', 'integer')
                    ->addColumn('type', 'string', ['limit' => 50])
                    ->addColumn('option', 'string', ['limit' => 254])
                    ->addColumn('value', 'text', ['null' => true])
                    ->addForeignKey('widgetId', 'widget', 'widgetId')
                    ->save();

                $this->dropTable('oauth_log');
                $this->dropTable('oauth_server_nonce');
                $this->dropTable('oauth_server_token');
                $this->dropTable('oauth_server_registry');

                // New oAuth tables
                $oauthClients = $this->table('oauth_clients', ['id' => false, 'primary_key' => ['id']]);
                $oauthClients
                    ->addColumn('id', 'string', ['limit' => 254])
                    ->addColumn('secret', 'string', ['limit' => 254])
                    ->addColumn('name', 'string', ['limit' => 254])
                    ->addColumn('userId', 'integer')
                    ->addColumn('authCode', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('clientCredentials', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->save();

                $oauthSessions = $this->table('oauth_sessions');
                $oauthSessions
                    ->addColumn('owner_type', 'string', ['limit' => 254])
                    ->addColumn('owner_id', 'string', ['limit' => 254])
                    ->addColumn('client_id', 'string', ['limit' => 254])
                    ->addColumn('client_redirect_uri', 'string', ['limit' => 500, 'null' => true])
                    ->addForeignKey('client_id', 'oauth_clients', 'id', ['delete' => 'CASCADE'])
                    ->save();

                $oauthScopes = $this->table('oauth_scopes', ['id' => false, 'primary_key' => ['id']]);
                $oauthScopes
                    ->addColumn('id', 'string', ['limit' => 254])
                    ->addColumn('description', 'string', ['limit' => 1000])
                    ->save();

                $oauthAccessTokens = $this->table('oauth_access_tokens', ['id' => false, 'primary_key' => ['access_token']]);
                $oauthAccessTokens
                    ->addColumn('access_token', 'string', ['limit' => 254])
                    ->addColumn('session_id', 'integer')
                    ->addColumn('expire_time', 'integer')
                    ->addForeignKey('session_id', 'oauth_sessions', 'id', ['delete' => 'CASCADE'])
                    ->save();

                $oauthAccessTokenScopes = $this->table('oauth_access_token_scopes');
                $oauthAccessTokenScopes
                    ->addColumn('access_token', 'string', ['limit' => 254])
                    ->addColumn('scope', 'string', ['limit' => 254])
                    ->addForeignKey('access_token', 'oauth_access_tokens', 'access_token', ['delete' => 'CASCADE'])
                    ->addForeignKey('scope', 'oauth_scopes', 'id', ['delete' => 'CASCADE'])
                    ->save();

                $oauthAuthCodes = $this->table('oauth_auth_codes', ['id' => false, 'primary_key' => ['auth_code']]);
                $oauthAuthCodes
                    ->addColumn('auth_code', 'string', ['limit' => 254])
                    ->addColumn('session_id', 'integer')
                    ->addColumn('expire_time', 'integer')
                    ->addColumn('client_redirect_uri', 'string', ['limit' => 500])
                    ->addForeignKey('session_id', 'oauth_sessions', 'id', ['delete' => 'CASCADE'])
                    ->save();

                $oauthAuthCodeScopes = $this->table('oauth_auth_code_scopes');
                $oauthAuthCodeScopes
                    ->addColumn('auth_code', 'string', ['limit' => 254])
                    ->addColumn('scope', 'string', ['limit' => 254])
                    ->addForeignKey('auth_code', 'oauth_auth_codes', 'auth_code', ['delete' => 'CASCADE'])
                    ->addForeignKey('scope', 'oauth_scopes', 'id', ['delete' => 'CASCADE'])
                    ->save();

                $oauthClientRedirects = $this->table('oauth_client_redirect_uris');
                $oauthClientRedirects
                    ->addColumn('client_id', 'string', ['limit' => 254])
                    ->addColumn('redirect_uri', 'string', ['limit' => 500])
                    ->save();

                $oauthRefreshToeksn = $this->table('oauth_refresh_tokens', ['id' => false, 'primary_key' => ['refresh_token']]);
                $oauthRefreshToeksn
                    ->addColumn('refresh_token', 'string', ['limit' => 254])
                    ->addColumn('expire_time', 'integer')
                    ->addColumn('access_token', 'string', ['limit' => 254])
                    ->addForeignKey('access_token', 'oauth_access_tokens', 'access_token', ['delete' => 'CASCADE'])
                    ->save();

                $oauthSessionsScopes = $this->table('oauth_session_scopes');
                $oauthSessionsScopes
                    ->addColumn('session_id', 'integer')
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

                $linkScheduleDisplayGroup = $this->table('lkscheduledisplaygroup', ['id' => false, 'primary_key' => ['eventId', 'displayGroupId']]);
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

                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Image\' WHERE module = \'Image\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Video\' WHERE module = \'Video\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Flash\' WHERE module = \'Flash\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\PowerPoint\' WHERE module = \'PowerPoint\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\WebPage\' WHERE module = \'Webpage\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Ticker\' WHERE module = \'Ticker\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Text\' WHERE module = \'Text\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Embedded\' WHERE module = \'Embedded\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\DataSetView\' WHERE module = \'datasetview\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\ShellCommand\' WHERE module = \'shellcommand\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\LocalVideo\' WHERE module = \'localvideo\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\GenericFile\' WHERE module = \'genericfile\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Clock\' WHERE module = \'Clock\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Font\' WHERE module = \'Font\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Twitter\' WHERE module = \'Twitter\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\ForecastIo\' WHERE module = \'forecastio\';');
                $this->execute('UPDATE `module` SET `class` = \'\\\\Xibo\\\\Widget\\\\Finance\' WHERE module = \'Finance\';');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
