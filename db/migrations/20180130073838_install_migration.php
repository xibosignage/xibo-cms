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

            // That is all we do for this migration - we've checked that the upgrade is supported
            // subsequent migrations will make the necessary changes

        } else {
            // No version table - add initial structure and data.
            // This is a fresh installation!
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

        $dayPart = $this->table('daypart', ['id' => 'dayPartId']);
        $dayPart
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('description', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('isRetired', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('userId', 'integer')
            ->addColumn('startTime', 'string', ['limit' => 8, 'default' => '00:00:00'])
            ->addColumn('endTime', 'string', ['limit' => 8, 'default' => '00:00:00'])
            ->addColumn('exceptions', 'text')
            ->save();

        $displayEvent = $this->table('displayevent', ['id' => 'displayEventId']);
        $displayEvent
            ->addColumn('eventDate', 'integer')
            ->addColumn('displayId', 'integer')
            ->addColumn('start', 'integer')
            ->addColumn('end', 'integer', ['null' => true])
            ->addIndex('eventDate')
            ->addIndex('end')
            ->save();

        $linkWidgetAudio = $this->table('lkwidgetaudio', ['id' => false, 'primaryKey' => ['widgetId', 'mediaId']]);
        $linkWidgetAudio->addColumn('widgetId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addColumn('volume', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('loop', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();


        $oauthClientScopes = $this->table('oauth_client_scopes');
        $oauthClientScopes
            ->addColumn('clientId', 'string', ['limit' => 254])
            ->addColumn('scopeId', 'string', ['limit' => 254])
            ->addIndex(['clientId', 'scopeId'], ['unique' => true])
            ->save();

        $oauthRouteScopes = $this->table('oauth_scope_routes');
        $oauthRouteScopes
            ->addColumn('scopeId', 'string', ['limit' => 254])
            ->addColumn('root', 'string', ['limit' => 1000])
            ->addColumn('method', 'string', ['limit' => 8])
            ->save();



        $notification = $this->table('notification', ['id' => 'notificationId']);
        $notification
            ->addColumn('subject', 'string', ['limit' => 255])
            ->addColumn('body', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
            ->addColumn('createDt', 'integer')
            ->addColumn('releaseDt', 'integer')
            ->addColumn('isEmail', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('isInterrupt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('isSystem', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('userId', 'integer')
            ->save();

        $linkNotificationDg = $this->table('lknotificationdg', ['id' => 'lkNotificationDgId']);
        $linkNotificationDg
            ->addColumn('notificationId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->addIndex(['notificationId', 'displayGroupId'], ['unique' => true])
            ->save();

        $linkNotificationGroup = $this->table('lknotificationgroup', ['id' => 'lkNotificationGroupId']);
        $linkNotificationGroup
            ->addColumn('notificationId', 'integer')
            ->addColumn('groupId', 'integer')
            ->addIndex(['notificationId', 'groupId'], ['unique' => true])
            ->save();

        $linkNotificationUser = $this->table('lknotificationuser', ['id' => 'lkNotificationUserId']);
        $linkNotificationUser
            ->addColumn('notificationId', 'integer')
            ->addColumn('userId', 'integer')
            ->addIndex(['notificationId', 'userId'], ['unique' => true])
            ->save();

        $userOption = $this->table('userOption'. ['id' => false, ['primaryKey' => ['userId', 'option']]]);
        $userOption->addColumn('userId', 'integer')
            ->addColumn('option', 'string', ['limit' => 50])
            ->addColumn('value', 'text')
            ->save();

        $linkLayoutDisplayGroup = $this->table('lklayoutdisplaygroup', ['comment' => 'Layout associations directly to Display Groups']);
        $linkLayoutDisplayGroup->addColumn('layoutId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->addIndex(['layoutId', 'displayGroupId'], ['unique' => true])
            ->save();

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
            ->save();

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

        $requiredFile = $this->table('requiredfile');
        $requiredFile->addColumn('requestKey', 'string', ['limit' => 10])
            ->addColumn('bytesRequested', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addColumn('complete' , 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();

        $linkScheduleDisplayGroup = $this->table('lkscheduledisplaygroup', ['id' => false, 'primaryKey' => ['eventId'], 'displayGroupId']);
        $linkScheduleDisplayGroup
            ->addColumn('eventId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->save();

        $task = $this->table('task', ['id' => 'taskId']);
        $task
            ->addColumn('name', 'string', ['limit' => 254])
            ->addColumn('class', 'string', ['limit' => 254])
            ->addColumn('status', 'integer', ['default' => 2, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('pid', 'integer')
            ->addColumn('options', 'text')
            ->addColumn('schedule', 'string', ['limit' => 254])
            ->addColumn('lastRunDt', 'integer')
            ->addColumn('lastRunMessage', 'string', ['null' => true])
            ->addColumn('lastRunStatus', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('lastRunDuration', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('lastRunExitCode', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('isActive', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('runNow', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('configFile', 'string', ['limit' => 254])
            ->addColumn('lastRunStartDt', 'integer', ['null' => true])
            ->save();

        $requiredFile = $this->table('requiredfile', ['id' => 'rfId']);
        $requiredFile
            ->addColumn('displayId', 'integer')
            ->addColumn('class', 'string', ['limit' => 1])
            ->addColumn('itemId', 'integer', ['null' => true])
            ->addColumn('bytesRequested', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addColumn('complete', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('path', 'string', ['null' => true, 'limit' => 255])
            ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => 0])
            ->addIndex(['displayId', 'type'])
            ->save();

        $linkCampaignTag = $this->table('lktagcampaign', ['id' => 'lkTagCampaignId']);
        $linkCampaignTag
            ->addColumn('tagId', 'integer')
            ->addColumn('campaignId', 'integer')
            ->addIndex(['tagId', 'campaignId'], ['unique' => true])
            ->save();
    }

    private function addData()
    {

    }
}
