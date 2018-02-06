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
        // Session
        $session = $this->table('session', ['id' => false, 'primaryKey' => 'session_id']);
        $session
            ->addColumn('session_id', 'string', ['limit' => 160])
            ->addColumn('session_data', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
            ->addColumn('session_expiration', 'integer', ['limit' => 10, 'unsigned' => true, 'default' => 0])
            ->addColumn('lastAccessed', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('userId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('isExpired', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('userAgent', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('remoteAddr', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addIndex('userId')
            ->save();

        // Settings table
        $settings = $this->table('settings', ['id' => 'settingId']);
        $settings
            ->addColumn('setting', 'string', ['limit' => 50])
            ->addColumn('type', 'string', ['limit' => 50])
            ->addColumn('title', 'string', ['limit' => 50])
            ->addColumn('value', 'string', ['limit' => 1000])
            ->addColumn('fieldType', 'string', ['limit' => 24])
            ->addColumn('helpText', 'text')
            ->addColumn('options', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->addColumn('cat', 'string', ['limit' => 24, 'default' => 'General'])
            ->addColumn('validation', 'string', ['limit' => 50])
            ->addColumn('ordering', 'integer')
            ->addColumn('userSee', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('userChange', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->save();

        // User Type
        $userType = $this->table('usertype', ['id' => 'userTypeId']);
        $userType
            ->addColumn('userType', 'string', ['limit' => 16])
            ->save();

        // Start with the user table
        $user = $this->table('user', ['id' => 'userId']);
        $user
            ->addColumn('userTypeId', 'integer')
            ->addColumn('userName', 'string', ['limit' => 50])
            ->addColumn('userPassword', 'string', ['limit' => 255])
            ->addColumn('loggedIn', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('lastAccessed', 'datetime', ['null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('homePageId', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('retired', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('csprng', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('newUserWizard', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('firstName', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('lastName', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref1', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref2', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref3', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref4', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('ref5', 'string', ['limit' => 254, 'null' => true])
            ->addForeignKey('userTypeId', 'usertype', 'userTypeId')
            ->save();

        $userOption = $this->table('userOption', ['id' => false, ['primaryKey' => ['userId', 'option']]]);
        $userOption
            ->addColumn('userId', 'integer')
            ->addColumn('option', 'string', ['limit' => 50])
            ->addColumn('value', 'text')
            ->save();

        // User Group
        $userGroup = $this->table('group', ['id' => 'groupId']);
        $userGroup
            ->addColumn('group', 'string', ['limit' => 50])
            ->addColumn('isUserSpecific', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('isEveryone', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('libraryQuota', 'integer', ['null' => true, 'default' => null])
            ->addColumn('isSystemNotification', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('isDisplayNotification', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->save();

        // Link User and User Group
        $linkUserUserGroup = $this->table('lkusergroup', ['id' => 'lkUserGroupID']);
        $linkUserUserGroup
            ->addColumn('groupId', 'integer')
            ->addColumn('userId', 'integer')
            ->addIndex(['groupId', 'userId'], ['unique' => true])
            ->addForeignKey('groupId', 'group', 'groupId')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();


        // Display Profile
        $displayProfile = $this->table('displayprofile', ['id' => 'displayProfileId']);
        $displayProfile
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('type', 'string', ['limit' => 15])
            ->addColumn('config', 'text')
            ->addColumn('isDefault', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('userId', 'integer')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        // Display Table
        $display = $this->table('display', ['id' => 'displayId']);
        $display
            ->addColumn('display', 'string', ['limit' => 50])
            ->addColumn('auditingUntil', 'integer', ['default' => 0])
            ->addColumn('defaultLayoutId', 'integer')
            ->addColumn('license', 'string', ['limit' => 40])
            ->addColumn('licensed', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('loggedIn', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('lastAccessed', 'integer')
            ->addColumn('inc_schedule', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('email_alert', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('alert_timeout', 'integer')
            ->addColumn('clientAddress', 'string', ['limit' => 50])
            ->addColumn('mediaInventoryStatus', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('macAddress', 'string', ['limit' => 254])
            ->addColumn('lastChanged', 'integer')
            ->addColumn('numberOfMacAddressChanges', 'integer')
            ->addColumn('lastWakeOnLanCommandSent', 'integer')
            ->addColumn('wakeOnLan', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('wakeOnLanTime', 'string', ['limit' => 5, 'default' => null, 'null' => true])
            ->addColumn('broadCastAddress', 'string', ['limit' => 100, 'default' => null, 'null' => true])
            ->addColumn('secureOn', 'string', ['limit' => 17, 'default' => null, 'null' => true])
            ->addColumn('cidr', 'string', ['limit' => 6, 'default' => null, 'null' => true])
            ->addColumn('geoLocation', 'point', ['default' => null, 'null' => true])
            ->addColumn('version_instructions', 'string', ['limit' => 255, 'default' => null, 'null' => true])
            ->addColumn('client_type', 'string', ['limit' => 20, 'default' => null, 'null' => true])
            ->addColumn('client_version', 'string', ['limit' => 15, 'default' => null, 'null' => true])
            ->addColumn('client_code', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL, 'null' => true])
            ->addColumn('displayProfileId', 'integer', ['null' => true])
            ->addColumn('screenShotRequested', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('storageAvailableSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
            ->addColumn('storageTotalSpace', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
            ->addColumn('xmrChannel', 'string', ['limit' => 15, 'default' => null, 'null' => true])
            ->addColumn('xmrPubKey', 'text')
            ->addColumn('lastCommandSuccess', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 2])
            ->addColumn('deviceName', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('timeZone', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addIndex('defaultLayoutId')
            ->addForeignKey('displayProfileId', 'displayprofile', 'displayProfileId')
            ->save();

        // Display Group
        $displayGroup = $this->table('displaygroup', ['id' => 'displayGroupId']);
        $displayGroup
            ->addColumn('displayGroup', 'string', ['limit' => 50])
            ->addColumn('description', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('isDisplaySpecific', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('isDynamic', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('dynamicCriteria', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('userId', 'integer')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        // Link Display Group / Display
        $linkDisplayGroupDisplay = $this->table('lkdisplaydg', ['id' => 'LkDisplayDGID']);
        $linkDisplayGroupDisplay
            ->addColumn('displayGroupId', 'integer')
            ->addColumn('displayId', 'integer')
            ->addIndex(['displayGroupId', 'displayId'], ['unique' => true])
            ->addForeignKey('displayGroupId', 'displaygroup', 'displayGroupId')
            ->addForeignKey('displayId', 'display', 'displayId')
            ->save();

        // Link Display Group / Display Group
        $linkDisplayGroup = $this->table('lkdgdg', ['id' => false, ['primaryKey' => ['parentId', 'childId', 'depth']]]);
        $linkDisplayGroup
            ->addColumn('parentId', 'integer')
            ->addColumn('childId', 'integer')
            ->addColumn('depth', 'integer')
            ->addIndex(['childId', 'parentId', 'depth'], ['unique' => true])
            ->save();

        // Module Table
        $module = $this->table('module', ['id' => 'moduleId']);
        $module
            ->addColumn('module', 'string', ['limit' => 50])
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('enabled', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('regionSpecific', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('description', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('imageUri', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('schemaVersion', 'integer', ['default' => 1])
            ->addColumn('validExtensions', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('assignable', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('renderAs', 'string', ['limit' => 10, 'default' => null, 'null' => true])
            ->addColumn('settings', 'text')
            ->addColumn('viewPath', 'string', ['limit' => 254, 'default' => '../modules'])
            ->addColumn('class', 'string', ['limit' => 254])
            ->addColumn('defaultDuration', 'integer')
            ->addColumn('installName', 'string', ['limit' => 254])
            ->save();

        // Media Table
        $media = $this->table('media', ['id' => 'mediaId']);
        $media
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('type', 'string', ['limit' => 15])
            ->addColumn('duration', 'integer')
            ->addColumn('originalFileName', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('storedAs', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('md5', 'string', ['limit' => 32, 'default' => null, 'null' => true])
            ->addColumn('fileSize', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('userId', 'integer')
            ->addColumn('retired', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('isEdited', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('editedMediaId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('moduleSystemFile', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('valid', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('expires', 'integer', ['default' => null, 'null' => true])
            ->addColumn('released', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('apiRef', 'string', ['limit' => 254])
            ->addColumn('createdDt', 'datetime')
            ->addColumn('modifiedDt', 'datetime')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        // Link Media to Display
        $linkMediaDisplayGroup = $this->table('lkmediadisplaygroup');
        $linkMediaDisplayGroup
            ->addColumn('displayGroupId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addIndex(['displayGroupId', 'mediaId'], ['unique' => true])
            ->addForeignKey('displayGroupId', 'displaygroup', 'displayGroupId')
            ->addForeignKey('mediaId', 'media', 'mediaId')
            ->save();

        // Resolution
        $resolution = $this->table('resolution', ['id' => 'resolutionId']);
        $resolution
            ->addColumn('resolution', 'string', ['limit' => 254])
            ->addColumn('width', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('height', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('intended_width', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('intended_height', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('version', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('enabled', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('userId', 'integer')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        // Layout
        $layout = $this->table('layout', ['id' => 'layoutId']);
        $layout
            ->addColumn('layout', 'string', ['limit' => 254])
            ->addColumn('userId', 'integer')
            ->addColumn('createdDt', 'integer')
            ->addColumn('modifiedDt', 'integer')
            ->addColumn('description', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('retired', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('duration', 'integer')
            ->addColumn('backgroundImageId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('width', 'decimal')
            ->addColumn('height', 'decimal')
            ->addColumn('backgroundColor', 'string', ['limit' => 25, 'default' => null, 'null' => true])
            ->addColumn('backgroundzIndex', 'integer', ['default' => 1])
            ->addColumn('schemaVersion', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 2])
            ->addColumn('statusMessage', 'text', ['default' => null, 'null' => true])
            ->addForeignKey('userId', 'user', 'userId')
            ->addForeignKey('backgroundImageId', 'media', 'mediaId')
            ->save();

        // Campaign Table
        $campaign = $this->table('campaign', ['id' => 'campaignId']);
        $campaign
            ->addColumn('campaign', 'string', ['limit' => 254])
            ->addColumn('isLayoutSpecific', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('userId', 'integer')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        // Layout/Campaign Link
        $linkCampaignLayout = $this->table('lkcampaignlayout', ['id' => 'lkCampaignLayoutId']);
        $linkCampaignLayout
            ->addColumn('campaignId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addColumn('displayOrder', 'integer')
            ->addIndex(['campaignId', 'layoutId', 'displayOrder'], ['unique' => true])
            ->addForeignKey('campaignId', 'campaign', 'campaignId')
            ->addForeignKey('layoutId', 'layout', 'layoutId')
            ->save();

        // Layout/Display Group Link
        $linkLayoutDisplayGroup = $this->table('lklayoutdisplaygroup');
        $linkLayoutDisplayGroup
            ->addColumn('displayGroupId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addIndex(['displayGroupId', 'layoutId'], ['unique' => true])
            ->addForeignKey('displayGroupId', 'displaygroup', 'displayGroupId')
            ->addForeignKey('layoutId', 'layout', 'layoutId')
            ->save();

        // Region
        $region = $this->table('region', ['id' => 'regionId']);
        $region
            ->addColumn('layoutId', 'integer')
            ->addColumn('ownerId', 'integer')
            ->addColumn('name', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('width', 'decimal')
            ->addColumn('height', 'decimal')
            ->addColumn('zIndex', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('duration', 'integer', ['default' => 0])
            ->addForeignKey('ownerId', 'user', 'userId')
            ->save();

        $regionOption = $this->table('regionoption', ['id' => false, 'primaryKey' => ['regionId', 'option']]);
        $regionOption
            ->addColumn('regionId', 'integer')
            ->addColumn('option', 'string', ['limit' => 50])
            ->addColumn('value', 'text', ['null' => true])
            ->save();

        // Playlist
        $playlist = $this->table('playlist', ['id' => 'playlistId']);
        $playlist
            ->addColumn('name', 'string', ['limit' => 254])
            ->addColumn('ownerId', 'integer')
            ->addForeignKey('ownerId', 'user', 'userId')
            ->save();

        $linkRegionPlaylist = $this->table('lkregionplaylist', ['id' => false, 'primaryKey' => 'regionId', 'playlistId', 'displayOrder']);
        $linkRegionPlaylist
            ->addColumn('regionId', 'integer')
            ->addColumn('playlistId', 'integer')
            ->addColumn('displayOrder', 'integer')
            // No point in adding the foreign keys here, we know they will be removed in a future migration (we drop the table)
            ->save();

        // Widget
        $widget = $this->table('widget', ['id' => 'widgetId']);
        $widget
            ->addColumn('widgetId', 'integer')
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

        $widgetOption = $this->table('widgetOption', ['id' => false, 'primaryKey' => ['widgetId', 'type', 'option']]);
        $widgetOption->addColumn('widgetId', 'integer')
            ->addColumn('type', 'string', ['limit' => 50])
            ->addColumn('option', 'string', ['limit' => 254])
            ->addColumn('value', 'text', ['null' => true])
            ->addForeignKey('widgetId', 'widget', 'widgetId')
            ->save();

        $linkWidgetMedia = $this->table('lkwidgetmedia', ['id' => false, 'primaryKey' => ['widgetId', 'mediaId']]);
        $linkWidgetMedia
            ->addColumn('widgetId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addForeignKey('widgetId', 'widget', 'widgetId')
            ->addForeignKey('mediaId', 'media', 'mediaId')
            ->save();

        $linkWidgetAudio = $this->table('lkwidgetaudio', ['id' => false, 'primaryKey' => ['widgetId', 'mediaId']]);
        $linkWidgetAudio
            ->addColumn('widgetId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addColumn('volume', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('loop', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addForeignKey('widgetId', 'widget', 'widgetId')
            ->addForeignKey('mediaId', 'media', 'mediaId')
            ->save();


        // Day Part
        $dayPart = $this->table('daypart', ['id' => 'dayPartId']);
        $dayPart
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('description', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('isRetired', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('userId', 'integer')
            ->addColumn('startTime', 'string', ['limit' => 8, 'default' => '00:00:00'])
            ->addColumn('endTime', 'string', ['limit' => 8, 'default' => '00:00:00'])
            ->addColumn('exceptions', 'text')
            ->addForeignKey('userId', 'user', 'userId')
            // Get 1 and 2 ID's reserved for Always and Custom
            ->insert([
                [
                    'name' => 'Always',
                    'userId' => 1
                ],
                [
                    'name' => 'Custom',
                    'userId' => 1
                ]
            ])
            ->save();

        // Schedule
        $schedule = $this->table('schedule', ['id' => 'eventId']);
        $schedule
            ->addColumn('eventTypeId', 'integer')
            ->addColumn('campaignId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('commandId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('dayPartId', 'integer', ['default' => 0])
            ->addColumn('userId', 'integer')
            ->addColumn('fromDt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('toDt', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('is_priority', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('displayOrder', 'integer', ['default' => 0])
            ->addColumn('lastRecurrenceWatermakr', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('syncTimezone', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('recurrence_type', 'enum', ['limit' => ['Minute', 'Hour', 'Day', 'Week', 'Month', 'Year'], 'default' => null, 'null' => true])
            ->addColumn('recurrence_detail', 'integer', ['default' => null, 'null' => true])
            ->addColumn('recurrence_range', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('recurrenceRepeatsOn', 'string', ['limit' => 14, 'default' => null, 'null' => true])
            ->addIndex('campaignId')
            ->addForeignKey('userId', 'user', 'userId')
            ->addForeignKey('campaignId', 'campaign', 'campaignId')
            ->addForeignKey('commandId', 'command', 'commandId')
            ->save();

        $linkScheduleDisplayGroup = $this->table('lkscheduledisplaygroup', ['id' => false, 'primaryKey' => ['eventId'], 'displayGroupId']);
        $linkScheduleDisplayGroup
            ->addColumn('eventId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->addForeignKey('eventId', 'schedule', 'eventId')
            ->addForeignKey('displayGroupId', 'displaygroup', 'displayGroupId')
            ->save();

        // DataSet
        $dataSet = $this->table('dataset', ['id' => 'dataSetId']);
        $dataSet
            ->addColumn('dataSet', 'string', ['limit' => 50])
            ->addColumn('description', 'string', ['limit' => 254, 'null' => true])
            ->addColumn('userId', 'integer')
            ->addColumn('lastDataEdit', 'integer')
            ->addColumn('code', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('isLookup', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('isRemote', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('method', 'enum', ['limit' => ['GET', 'POST'], 'null' => true])
            ->addColumn('uri', 'string', ['limit' => 250, 'null' => true])
            ->addColumn('postData', 'text', ['null' => true])
            ->addColumn('authentication', 'enum', ['limit' => ['none', 'plain', 'basic', 'digest'], 'null' => true])
            ->addColumn('username', 'string', ['limit' => 250, 'null' => true])
            ->addColumn('password', 'string', ['limit' => 250, 'null' => true])
            ->addColumn('refreshRate', 'integer', ['default' => 86400])
            ->addColumn('clearRate', 'integer', ['default' => 0])
            ->addColumn('runsAfter', 'integer', ['default' => null, 'null' => true])
            ->addColumn('dataRoot', 'string', ['limit' => 250, 'null' => true])
            ->addColumn('lastSync', 'integer', ['default' => 0])
            ->addColumn('summarize', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('summarizeField', 'string', ['limit' => 250, 'null' => true])
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        $dataType = $this->table('datatype', ['id' => 'dataTypeId']);
        $dataType
            ->addColumn('dataType', 'string', ['limit' => 100])
            ->save();

        $dataSetColumnType = $this->table('datasetcolumntype', ['id' => 'dataSetColumnTypeId']);
        $dataSetColumnType
            ->addColumn('heading', 'string', ['limit' => 100])
            ->save();

        $dataSetColumn = $this->table('datasetcolumn', ['id' => 'dataSetColumnId']);
        $dataSetColumn
            ->addColumn('dataSetId', 'integer')
            ->addColumn('heading', 'string', ['limit' => 50])
            ->addColumn('dataTypeId', 'integer')
            ->addColumn('dataSetColumnTypeId', 'integer')
            ->addColumn('listContent', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('columnOrder', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('formula', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('remoteField', 'string', ['limit' => 250, 'null' => true])
            ->addForeignKey('dataSetId', 'dataset', 'dataSetId')
            ->addForeignKey('dataTypeId', 'datatype', 'dataTypeId')
            ->addForeignKey('dataSetColumnTypeId', 'datasetcolumntype', 'dataSetColumnTypeId')
            ->save();

        // Notifications
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
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        $linkNotificationDg = $this->table('lknotificationdg', ['id' => 'lkNotificationDgId']);
        $linkNotificationDg
            ->addColumn('notificationId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->addIndex(['notificationId', 'displayGroupId'], ['unique' => true])
            ->addForeignKey('notificationId', 'notification', 'notificationId')
            ->save();

        $linkNotificationGroup = $this->table('lknotificationgroup', ['id' => 'lkNotificationGroupId']);
        $linkNotificationGroup
            ->addColumn('notificationId', 'integer')
            ->addColumn('groupId', 'integer')
            ->addIndex(['notificationId', 'groupId'], ['unique' => true])
            ->addForeignKey('notificationId', 'notification', 'notificationId')
            ->addForeignKey('groupId', 'group', 'groupId')
            ->save();

        $linkNotificationUser = $this->table('lknotificationuser', ['id' => 'lkNotificationUserId']);
        $linkNotificationUser
            ->addColumn('notificationId', 'integer')
            ->addColumn('userId', 'integer')
            ->addColumn('read', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('readDt', 'integer')
            ->addColumn('emailDt', 'integer')
            ->addIndex(['notificationId', 'userId'], ['unique' => true])
            ->addForeignKey('notificationId', 'notification', 'notificationId')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();


        // Commands
        $command = $this->table('command', ['id' => 'commandId']);
        $command
            ->addColumn('command', 'string', ['limit' => 254])
            ->addColumn('code', 'string', ['limit' => 50])
            ->addColumn('description', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('userId', 'integer')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        $linkCommandDisplayProfile = $this->table('lkcommanddisplayprofile', ['id' => false, ['primaryKey' => ['commandId', 'displayProfileId']]]);
        $linkCommandDisplayProfile->addColumn('commandId', 'integer')
            ->addColumn('displayProfileId', 'integer')
            ->addColumn('commandString', 'string', ['limit' => 1000])
            ->addColumn('validationString', 'string', ['limit' => 1000])
            ->addForeignKey('commandId', 'command', 'commandId')
            ->addForeignKey('displayProfileId', 'displayprofile', 'displayProfileId')
            ->save();

        // Permissions
        $pages = $this->table('pages', ['id' => 'pageId']);
        $pages
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('title', 'string', ['limit' => 50])
            ->addColumn('asHome', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
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

        // Oauth
        //<editor-fold desc="OAUTH">

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
            ->addForeignKey('scope', 'oauth_access_tokens', 'id', ['delete' => 'CASCADE'])
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
            ->addForeignKey('userId', 'user', 'userId')
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
        //</editor-fold>

        $requiredFile = $this->table('requiredfile');
        $requiredFile->addColumn('requestKey', 'string', ['limit' => 10])
            ->addColumn('bytesRequested', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addColumn('complete' , 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();

        // Tasks
        $task = $this->table('task', ['id' => 'taskId']);
        $task
            ->addColumn('name', 'string', ['limit' => 254])
            ->addColumn('class', 'string', ['limit' => 254])
            ->addColumn('status', 'integer', ['default' => 2, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('pid', 'integer')
            ->addColumn('options', 'text')
            ->addColumn('schedule', 'string', ['limit' => 254])
            ->addColumn('lastRunDt', 'integer')
            ->addColumn('lastRunStartDt', 'integer', ['null' => true])
            ->addColumn('lastRunMessage', 'string', ['null' => true])
            ->addColumn('lastRunStatus', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('lastRunDuration', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('lastRunExitCode', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('isActive', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('runNow', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('configFile', 'string', ['limit' => 254])
            ->save();

        // Required Files
        $requiredFile = $this->table('requiredfile', ['id' => 'rfId']);
        $requiredFile
            ->addColumn('displayId', 'integer')
            ->addColumn('type', 'string', ['limit' => 1])
            ->addColumn('itemId', 'integer', ['null' => true])
            ->addColumn('bytesRequested', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addColumn('complete', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('path', 'string', ['null' => true, 'limit' => 255])
            ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => 0])
            ->addIndex(['displayId', 'type'])
            ->addForeignKey('displayId', 'display', 'displayId')
            ->save();

        // Tags
        $tag = $this->table('tag', ['id' => 'tagId']);
        $tag
            ->addColumn('tag', 'string', ['limit' => 50])
            ->save();

        // Tag Links
        $linkCampaignTag = $this->table('lktagcampaign', ['id' => 'lkTagCampaignId']);
        $linkCampaignTag
            ->addColumn('tagId', 'integer')
            ->addColumn('campaignId', 'integer')
            ->addIndex(['tagId', 'campaignId'], ['unique' => true])
            ->addForeignKey('tagId', 'tag', 'tagId')
            ->addForeignKey('campaignId', 'campaign', 'campaignId')
            ->save();

        $linkLayoutTag = $this->table('lktaglayout', ['id' => 'lkTagLayoutId']);
        $linkLayoutTag
            ->addColumn('tagId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addIndex(['tagId', 'layoutId'], ['unique' => true])
            ->addForeignKey('tagId', 'tag', 'tagId')
            ->addForeignKey('layoutId', 'layout', 'layoutId')
            ->save();

        $linkMediaTag = $this->table('lktagmedia', ['id' => 'lkTagMediaId']);
        $linkMediaTag
            ->addColumn('tagId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addIndex(['tagId', 'mediaId'], ['unique' => true])
            ->addForeignKey('tagId', 'tag', 'tagId')
            ->addForeignKey('mediaId', 'media', 'mediaId')
            ->save();

        $linkDisplayGroupTag = $this->table('lktagdisplaygroup', ['id' => 'lkTagDisplayGroupId']);
        $linkDisplayGroupTag
            ->addColumn('tagId', 'integer')
            ->addColumn('displayGroupId', 'integer')
            ->addIndex(['tagId', 'displayGroupId'], ['unique' => true])
            ->addForeignKey('tagId', 'tag', 'tagId')
            ->addForeignKey('displayGroupId', 'displaygroup', 'displayGroupId')
            ->save();

        // Transitions
        $transitions = $this->table('transition', ['id' => 'transitionId']);
        $transitions
            ->addColumn('transition', 'string', ['limit' => 254])
            ->addColumn('code', 'string', ['limit' => 254])
            ->addColumn('hasDuration', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('hasDirection', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('availableAsIn', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('availableAsOut', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->save();

        // Stats
        $stat = $this->table('stat', ['id' => 'statId']);
        $stat
            ->addColumn('type', 'string', ['limit' => 20])
            ->addColumn('statDate', 'datetime')
            ->addColumn('scheduleId', 'integer')
            ->addColumn('displayId', 'integer')
            ->addColumn('layoutId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addColumn('widgetId', 'integer')
            ->addColumn('start', 'datetime')
            ->addColumn('end', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('tag', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addIndex('statDate')
            ->addIndex(['displayId', 'end', 'type'])
            ->save();

        // Display Events
        $displayEvent = $this->table('displayevent', ['id' => 'displayEventId']);
        $displayEvent
            ->addColumn('eventDate', 'integer')
            ->addColumn('displayId', 'integer')
            ->addColumn('start', 'integer')
            ->addColumn('end', 'integer', ['null' => true])
            ->addIndex('eventDate')
            ->addIndex(['disaplyId', 'end'])
            ->save();

        // Log
        $log = $this->table('log', ['id' => 'logId']);
        $log
            ->addColumn('runNo', 'string', ['limit' => 10])
            ->addColumn('logDate', 'datetime')
            ->addColumn('channel', 'string', ['limit' => 20])
            ->addColumn('type', 'string', ['limit' => 254])
            ->addColumn('page', 'string', ['limit' => 50])
            ->addColumn('function', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('message', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
            ->addColumn('userId', 'integer', ['default' => 0])
            ->addColumn('displayId', 'integer', ['null' => true, 'default' => null])
            ->addIndex('logDate')
            ->save();

        // Audit Log
        $auditLog = $this->table('auditlog', ['id' => 'logId']);
        $auditLog->addColumn('logDate', 'integer')
            ->addColumn('userId', 'integer')
            ->addColumn('message', 'string', ['limit' => 255])
            ->addColumn('entity', 'string', ['limit' => 50])
            ->addColumn('entityId', 'integer')
            ->addColumn('objectAfter', 'text')
            ->save();

        // Bandwidth Tracking
        $bandwidthType = $this->table('bandwidthtype', ['id' => 'bandwidthTypeId']);
        $bandwidthType->addColumn('name', 'string', ['limit' => 25])
            ->save();

        $bandwidth = $this->table('bandwidth', ['id' => false, 'primaryKey' => ['displayId', 'type', 'month']]);
        $bandwidth->addColumn('displayId', 'integer')
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('month', 'integer')
            ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addForeignKey('type', 'bandwidthtype')
            ->save();

        // Blacklist
        $blacklist = $this->table('blacklist', ['id' => 'blacklistId']);
        $blacklist
            ->addColumn('mediaId', 'integer')
            ->addColumn('displayId', 'integer')
            ->addColumn('userId', 'integer', ['null' => true])
            ->addColumn('reportingDisplayId', 'integer', ['null' => true])
            ->addColumn('reason', 'text')
            ->addColumn('isIgnored', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addForeignKey('mediaId', 'media')
            ->addForeignKey('displayId', 'display')
            ->save();

        // Help
        $help = $this->table('help', ['id' => 'helpId']);
        $help
            ->addColumn('topic', 'string', ['limit' => 254])
            ->addColumn('category', 'string', ['limit' => 254, 'default' => 'General'])
            ->addColumn('link', 'string', ['limit' => 254])
            ->save();
    }

    private function addData()
    {

    }
}
