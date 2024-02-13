<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 * @phpcs:disable Generic.Files.LineLength.TooLong
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
        $session = $this->table('session', ['id' => false, 'primary_key' => 'session_id']);
        $session
            ->addColumn('session_id', 'string', ['limit' => 160])
            ->addColumn('session_data', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
            ->addColumn('session_expiration', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
            ->addColumn('lastAccessed', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('userId', 'integer', ['null' => true, 'default' => null])
            ->addColumn('isExpired', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('userAgent', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('remoteAddr', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addIndex('userId')
            ->save();

        // Settings table
        $settings = $this->table('setting', ['id' => 'settingId']);
        $settings
            ->addColumn('setting', 'string', ['limit' => 50])
            ->addColumn('type', 'string', ['limit' => 50])
            ->addColumn('title', 'string', ['limit' => 254])
            ->addColumn('value', 'string', ['limit' => 1000])
            ->addColumn('default', 'string', ['limit' => 1000])
            ->addColumn('fieldType', 'string', ['limit' => 24])
            ->addColumn('helpText', 'text', ['default' => null, 'null' => true])
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
            ->insert([
                ['userTypeId' => 1, 'userType' => 'Super Admin'],
                ['userTypeId' => 2, 'userType' => 'Group Admin'],
                ['userTypeId' => 3, 'userType' => 'User'],
            ])
            ->save();

        // Start with the user table
        $user = $this->table('user', ['id' => 'userId']);
        $user
            ->addColumn('userTypeId', 'integer')
            ->addColumn('userName', 'string', ['limit' => 50])
            ->addColumn('userPassword', 'string', ['limit' => 255])
            ->addColumn('loggedIn', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('lastAccessed', 'datetime', ['null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true, 'default' => null])
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
            ->insert([
                'userId' => 1,
                'userTypeId' => 1,
                'userName' => 'xibo_admin',
                'userPassword' => '5f4dcc3b5aa765d61d8327deb882cf99',
                'loggedIn' => 0,
                'lastAccessed' => null,
                'homePageId' => 29
            ])
            ->save();

        $userOption = $this->table('useroption', ['id' => false, 'primary_key' => ['userId', 'option']]);
        $userOption
            ->addColumn('userId', 'integer')
            ->addColumn('option', 'string', ['limit' => 50])
            ->addColumn('value', 'text', ['default' => null, 'null' => true])
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
            ->insert([
                ['groupId' => 1, 'group' => 'Users', 'isUserSpecific' => 0, 'isEveryone' => 0, 'isSystemNotification' => 0],
                ['groupId' => 2, 'group' => 'Everyone', 'isUserSpecific' => 0, 'isEveryone' => 1, 'isSystemNotification' => 0],
                ['groupId' => 3, 'group' => 'xibo_admin', 'isUserSpecific' => 1, 'isEveryone' => 0, 'isSystemNotification' => 1],
                ['groupId' => 4, 'group' => 'System Notifications', 'isUserSpecific' => 0, 'isEveryone' => 0, 'isSystemNotification' => 1],
            ])
            ->save();

        // Link User and User Group
        $linkUserUserGroup = $this->table('lkusergroup', ['id' => 'lkUserGroupID']);
        $linkUserUserGroup
            ->addColumn('groupId', 'integer')
            ->addColumn('userId', 'integer')
            ->addIndex(['groupId', 'userId'], ['unique' => true])
            ->addForeignKey('groupId', 'group', 'groupId')
            ->addForeignKey('userId', 'user', 'userId')
            ->insert([
                'groupId' => 3,
                'userId' => 1
            ])
            ->save();


        // Display Profile
        $displayProfile = $this->table('displayprofile', ['id' => 'displayProfileId']);
        $displayProfile
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('type', 'string', ['limit' => 15])
            ->addColumn('config', 'text', ['default' => null, 'null' => true])
            ->addColumn('isDefault', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('userId', 'integer')
            ->addForeignKey('userId', 'user', 'userId')
            ->insert([
                ['name' => 'Windows', 'type' => 'windows', 'config' => '[]', 'userId' => 1, 'isDefault' => 1],
                ['name' => 'Android', 'type' => 'android', 'config' => '[]', 'userId' => 1, 'isDefault' => 1],
                ['name' => 'webOS', 'type' => 'lg', 'config' => '[]', 'userId' => 1, 'isDefault' => 1],
            ])
            ->save();

        // Display Table
        $display = $this->table('display', ['id' => 'displayId']);
        $display
            ->addColumn('display', 'string', ['limit' => 50])
            ->addColumn('auditingUntil', 'integer', ['default' => 0])
            ->addColumn('defaultLayoutId', 'integer')
            ->addColumn('license', 'string', ['limit' => 40, 'default' => null, 'null' => true])
            ->addColumn('licensed', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('loggedIn', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('lastAccessed', 'integer', ['limit' => 11, 'default' => null, 'null' => true])
            ->addColumn('inc_schedule', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('email_alert', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('alert_timeout', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('clientAddress', 'string', ['limit' => 50, 'default' => null, 'null' => true])
            ->addColumn('mediaInventoryStatus', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('macAddress', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('lastChanged', 'integer', ['default' => null, 'null' => true])
            ->addColumn('numberOfMacAddressChanges', 'integer', ['default' => 0])
            ->addColumn('lastWakeOnLanCommandSent', 'integer', ['default' => null, 'null' => true])
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
            ->addColumn('xmrChannel', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('xmrPubKey', 'text', ['default' => null, 'null' => true])
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
        $linkDisplayGroup = $this->table('lkdgdg', ['id' => false, ['primary_key' => ['parentId', 'childId', 'depth']]]);
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
            ->addColumn('previewEnabled', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('assignable', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
            ->addColumn('render_as', 'string', ['limit' => 10, 'default' => null, 'null' => true])
            ->addColumn('settings', 'text', ['default' => null, 'null' => true])
            ->addColumn('viewPath', 'string', ['limit' => 254, 'default' => '../modules'])
            ->addColumn('class', 'string', ['limit' => 254])
            ->addColumn('defaultDuration', 'integer')
            ->addColumn('installName', 'string', ['limit' => 254, 'default' => null, 'null' => true])
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
            ->addColumn('apiRef', 'string', ['limit' => 254, 'default' => null, 'null' => true])
            ->addColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
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
            ->insert([
                ['resolution' => '1080p HD Landscape', 'width' => 800, 'height' => 450, 'intended_width' => 1920, 'intended_height' => 1080, 'version' => 2, 'enabled' => 1, 'userId' => 1],
                ['resolution' => '720p HD Landscape', 'width' => 800, 'height' => 450, 'intended_width' => 1280, 'intended_height' => 720, 'version' => 2, 'enabled' => 1, 'userId' => 1],
                ['resolution' => '1080p HD Portrait', 'width' => 450, 'height' => 800, 'intended_width' => 1080, 'intended_height' => 1920, 'version' => 2, 'enabled' => 1, 'userId' => 1],
                ['resolution' => '720p HD Portrait', 'width' => 450, 'height' => 800, 'intended_width' => 720, 'intended_height' => 1280, 'version' => 2, 'enabled' => 1, 'userId' => 1],
                ['resolution' => '4k cinema', 'width' => 800, 'height' => 450, 'intended_width' => 4096, 'intended_height' => 2304, 'version' => 2, 'enabled' => 1, 'userId' => 1],
                ['resolution' => 'Common PC Monitor 4:3', 'width' => 800, 'height' => 600, 'intended_width' => 1024, 'intended_height' => 768, 'version' => 2, 'enabled' => 1, 'userId' => 1],
                ['resolution' => '4k UHD Landscape', 'width' => 450, 'height' => 800, 'intended_width' => 3840, 'intended_height' => 2160, 'version' => 2, 'enabled' => 1, 'userId' => 1],
                ['resolution' => '4k UHD Portrait', 'width' => 800, 'height' => 450, 'intended_width' => 2160, 'intended_height' => 3840, 'version' => 2, 'enabled' => 1, 'userId' => 1]
            ])
            ->save();

        // Layout
        $layout = $this->table('layout', ['id' => 'layoutId']);
        $layout
            ->addColumn('layout', 'string', ['limit' => 254])
            ->addColumn('userId', 'integer')
            ->addColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
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
            ->addColumn('top', 'decimal')
            ->addColumn('left', 'decimal')
            ->addColumn('zIndex', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('duration', 'integer', ['default' => 0])
            ->addForeignKey('ownerId', 'user', 'userId')
            ->addForeignKey('layoutId', 'layout', 'layoutId')
            ->save();

        $regionOption = $this->table('regionoption', ['id' => false, 'primary_key' => ['regionId', 'option']]);
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

        $linkRegionPlaylist = $this->table('lkregionplaylist', ['id' => false, 'primary_key' => 'regionId', 'playlistId', 'displayOrder']);
        $linkRegionPlaylist
            ->addColumn('regionId', 'integer')
            ->addColumn('playlistId', 'integer')
            ->addColumn('displayOrder', 'integer')
            // No point in adding the foreign keys here, we know they will be removed in a future migration (we drop the table)
            ->save();

        // Widget
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
        $widgetOption
            ->addColumn('widgetId', 'integer')
            ->addColumn('type', 'string', ['limit' => 50])
            ->addColumn('option', 'string', ['limit' => 254])
            ->addColumn('value', 'text', ['null' => true])
            ->addForeignKey('widgetId', 'widget', 'widgetId')
            ->save();

        $linkWidgetMedia = $this->table('lkwidgetmedia', ['id' => false, 'primary_key' => ['widgetId', 'mediaId']]);
        $linkWidgetMedia
            ->addColumn('widgetId', 'integer')
            ->addColumn('mediaId', 'integer')
            ->addForeignKey('widgetId', 'widget', 'widgetId')
            ->addForeignKey('mediaId', 'media', 'mediaId')
            ->save();

        $linkWidgetAudio = $this->table('lkwidgetaudio', ['id' => false, 'primary_key' => ['widgetId', 'mediaId']]);
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
            ->addColumn('exceptions', 'text', ['default' => null, 'null' => true])
            ->addForeignKey('userId', 'user', 'userId')
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
            ->addColumn('lastRecurrenceWatermark', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('syncTimezone', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('recurrence_type', 'enum', ['values' => ['Minute', 'Hour', 'Day', 'Week', 'Month', 'Year'], 'default' => null, 'null' => true])
            ->addColumn('recurrence_detail', 'integer', ['default' => null, 'null' => true])
            ->addColumn('recurrence_range', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('recurrenceRepeatsOn', 'string', ['limit' => 14, 'default' => null, 'null' => true])
            ->addIndex('campaignId')
            ->addForeignKey('userId', 'user', 'userId')
            ->save();

        $linkScheduleDisplayGroup = $this->table('lkscheduledisplaygroup', ['id' => false, 'primary_key' => ['eventId', 'displayGroupId']]);
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
            ->addColumn('lastDataEdit', 'integer', ['default' => 0])
            ->addColumn('code', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('isLookup', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('isRemote', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('method', 'enum', ['values' => ['GET', 'POST'], 'null' => true])
            ->addColumn('uri', 'string', ['limit' => 250, 'null' => true])
            ->addColumn('postData', 'text', ['null' => true])
            ->addColumn('authentication', 'enum', ['values' => ['none', 'plain', 'basic', 'digest'], 'null' => true])
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
            ->insert([
                ['dataTypeId' => 1, 'dataType' => 'String'],
                ['dataTypeId' => 2, 'dataType' => 'Number'],
                ['dataTypeId' => 3, 'dataType' => 'Date'],
                ['dataTypeId' => 4, 'dataType' => 'External Image'],
                ['dataTypeId' => 5, 'dataType' => 'Library Image'],
            ])
            ->save();

        $dataSetColumnType = $this->table('datasetcolumntype', ['id' => 'dataSetColumnTypeId']);
        $dataSetColumnType
            ->addColumn('dataSetColumnType', 'string', ['limit' => 100])
            ->insert([
                ['dataSetColumnTypeId' => 1, 'dataSetColumnType' => 'Value'],
                ['dataSetColumnTypeId' => 2, 'dataSetColumnType' => 'Formula'],
                ['dataSetColumnTypeId' => 3, 'dataSetColumnType' => 'Remote'],
            ])
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

        $linkCommandDisplayProfile = $this->table('lkcommanddisplayprofile', ['id' => false, 'primary_key' => ['commandId', 'displayProfileId']]);
        $linkCommandDisplayProfile->addColumn('commandId', 'integer')
            ->addColumn('displayProfileId', 'integer')
            ->addColumn('commandString', 'string', ['limit' => 1000])
            ->addColumn('validationString', 'string', ['limit' => 1000, 'null' => true])
            ->addForeignKey('commandId', 'command', 'commandId')
            ->addForeignKey('displayProfileId', 'displayprofile', 'displayProfileId')
            ->save();

        // Permissions
        $pages = $this->table('pages', ['id' => 'pageId']);
        $pages
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('title', 'string', ['limit' => 100])
            ->addColumn('asHome', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->insert([
                ['pageId' => 1, 'name' => 'dashboard', 'title' => 'Dashboard', 'asHome' => 1],
                ['pageId' => 2, 'name' => 'schedule', 'title' => 'Schedule', 'asHome' => 1],
                ['pageId' => 3, 'name' => 'mediamanager', 'title' => 'Media Dashboard','asHome' =>  1],
                ['pageId' => 4, 'name' => 'layout', 'title' => 'Layout', 'asHome' => 1],
                ['pageId' => 5, 'name' => 'library', 'title' => 'Library', 'asHome' => 1],
                ['pageId' => 6, 'name' => 'display', 'title' => 'Displays', 'asHome' => 1],
                ['pageId' => 7, 'name' => 'update', 'title' => 'Update', 'asHome' => 0],
                ['pageId' => 8, 'name' => 'admin', 'title' => 'Administration', 'asHome' => 0],
                ['pageId' => 9, 'name' => 'group', 'title' => 'User Groups','asHome' =>  1],
                ['pageId' => 10, 'name' => 'log', 'title' => 'Log', 'asHome' => 1],
                ['pageId' => 11, 'name' => 'user', 'title' => 'Users', 'asHome' => 1],
                ['pageId' => 12, 'name' => 'license', 'title' => 'Licence', 'asHome' => 1],
                ['pageId' => 13, 'name' => 'index', 'title' => 'Home', 'asHome' => 0],
                ['pageId' => 14, 'name' => 'module', 'title' => 'Modules', 'asHome' => 1],
                ['pageId' => 15, 'name' => 'template', 'title' => 'Templates', 'asHome' => 1],
                ['pageId' => 16, 'name' => 'fault', 'title' => 'Report Fault','asHome' =>  1],
                ['pageId' => 17, 'name' => 'stats', 'title' => 'Statistics', 'asHome' => 1],
                ['pageId' => 18, 'name' => 'manual', 'title' => 'Manual', 'asHome' => 0],
                ['pageId' => 19, 'name' => 'resolution', 'title' => 'Resolutions', 'asHome' => 1],
                ['pageId' => 20, 'name' => 'help', 'title' => 'Help Links','asHome' =>  1],
                ['pageId' => 21, 'name' => 'clock', 'title' => 'Clock', 'asHome' => 0],
                ['pageId' => 22, 'name' => 'displaygroup', 'title' => 'Display Groups','asHome' =>  1],
                ['pageId' => 23, 'name' => 'application', 'title' => 'Applications', 'asHome' => 1],
                ['pageId' => 24, 'name' => 'dataset', 'title' => 'DataSets', 'asHome' => 1],
                ['pageId' => 25, 'name' => 'campaign', 'title' => 'Campaigns', 'asHome' => 1],
                ['pageId' => 26, 'name' => 'transition', 'title' => 'Transitions', 'asHome' => 1],
                ['pageId' => 27, 'name' => 'sessions', 'title' => 'Sessions', 'asHome' => 1],
                ['pageId' => 28, 'name' => 'preview', 'title' => 'Preview', 'asHome' => 0],
                ['pageId' => 29, 'name' => 'statusdashboard', 'title' => 'Status Dashboard','asHome' =>  1],
                ['pageId' => 30, 'name' => 'displayprofile', 'title' => 'Display Profiles','asHome' =>  1],
                ['pageId' => 31, 'name' => 'audit', 'title' => 'Audit Trail','asHome' =>  0],
                ['pageId' => 32, 'name' => 'region', 'title' => 'Regions', 'asHome' => 0],
                ['pageId' => 33, 'name' => 'playlist', 'title' => 'Playlist', 'asHome' => 0],
                ['pageId' => 34, 'name' => 'maintenance', 'title' => 'Maintenance', 'asHome' => 0],
                ['pageId' => 35, 'name' => 'command', 'title' => 'Commands', 'asHome' => 1],
                ['pageId' => 36, 'name' => 'notification', 'title' => 'Notifications', 'asHome' => 0],
                ['pageId' => 37, 'name' => 'drawer', 'title' => 'Notification Drawer','asHome' =>  0],
                ['pageId' => 38, 'name' => 'daypart', 'title' => 'Dayparting', 'asHome' => 0],
                ['pageId' => 39, 'name' => 'task', 'title' => 'Tasks', 'asHome' => 1]
            ])
            ->save();

        $permissionEntity = $this->table('permissionentity', ['id' => 'entityId']);
        $permissionEntity->addColumn('entity', 'string', ['limit' => 50])
            ->addIndex('entity', ['unique' => true])
            ->insert([
                ['entityId' => 1, 'entity' => 'Xibo\Entity\Page'],
                ['entityId' => 2, 'entity' => 'Xibo\Entity\DisplayGroup'],
                ['entityId' => 3, 'entity' => 'Xibo\Entity\Media'],
                ['entityId' => 4, 'entity' => 'Xibo\Entity\Campaign'],
                ['entityId' => 5, 'entity' => 'Xibo\Entity\Widget'],
                ['entityId' => 7, 'entity' => 'Xibo\Entity\Region'],
                ['entityId' => 8, 'entity' => 'Xibo\Entity\Playlist'],
                ['entityId' => 9, 'entity' => 'Xibo\Entity\DataSet'],
                ['entityId' => 10, 'entity' => 'Xibo\Entity\Notification'],
                ['entityId' => 11, 'entity' => 'Xibo\Entity\DayPart'],
            ])
            ->save();

        $permission = $this->table('permission', ['id' => 'permissionId']);
        $permission->addColumn('entityId', 'integer')
            ->addColumn('groupId', 'integer')
            ->addColumn('objectId', 'integer')
            ->addColumn('view', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('edit', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('delete', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->insert([
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 1, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 13, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 4, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 5, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 3, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 33, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 28, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 32, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 2, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 29, 'view' => 1, 'edit' => 0, 'delete' => 0],
                ['entityId' => 1, 'groupId' => 1, 'objectId' => 11, 'view' => 1, 'edit' => 0, 'delete' => 0]
            ])
            ->save();

        // Oauth
        //<editor-fold desc="OAUTH">

        $oauthClients = $this->table('oauth_clients', ['id' => false, 'primary_key' => ['id']]);
        $oauthClients
            ->addColumn('id', 'string', ['limit' => 254])
            ->addColumn('secret', 'string', ['limit' => 254])
            ->addColumn('name', 'string', ['limit' => 254])
            ->addColumn('userId', 'integer')
            ->addColumn('authCode', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('clientCredentials', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addForeignKey('userId', 'user', 'userId')
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
            ->insert([
                ['id' => 'all', 'description' => 'All']
            ])
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

        $oauthClientScopes = $this->table('oauth_client_scopes');
        $oauthClientScopes
            ->addColumn('clientId', 'string', ['limit' => 254])
            ->addColumn('scopeId', 'string', ['limit' => 254])
            ->addIndex(['clientId', 'scopeId'], ['unique' => true])
            ->save();

        $oauthRouteScopes = $this->table('oauth_scope_routes');
        $oauthRouteScopes
            ->addColumn('scopeId', 'string', ['limit' => 254])
            ->addColumn('route', 'string', ['limit' => 1000])
            ->addColumn('method', 'string', ['limit' => 8])
            ->save();
        //</editor-fold>

        // Tasks
        $task = $this->table('task', ['id' => 'taskId']);
        $task
            ->addColumn('name', 'string', ['limit' => 254])
            ->addColumn('class', 'string', ['limit' => 254])
            ->addColumn('status', 'integer', ['default' => 2, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('pid', 'integer', ['default' => null, 'null' => true])
            ->addColumn('options', 'text', ['default' => null, 'null' => true])
            ->addColumn('schedule', 'string', ['limit' => 254])
            ->addColumn('lastRunDt', 'integer', ['default' => 0])
            ->addColumn('lastRunStartDt', 'integer', ['null' => true])
            ->addColumn('lastRunMessage', 'string', ['null' => true])
            ->addColumn('lastRunStatus', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('lastRunDuration', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('lastRunExitCode', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL])
            ->addColumn('isActive', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('runNow', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('configFile', 'string', ['limit' => 254])
            ->insert([
                ['name' => 'Daily Maintenance', 'class' => '\Xibo\XTR\MaintenanceDailyTask', 'status' => 2, 'options' => '[]', 'schedule' => '0 0 * * * *', 'isActive' => 1, 'configFile' => '/tasks/maintenance-daily.task'],
                ['name' => 'Regular Maintenance', 'class' => '\Xibo\XTR\MaintenanceRegularTask', 'status' => 2, 'options' => '[]', 'schedule' =>  '*/5 * * * * *', 'isActive' => 1, 'configFile' => '/tasks/maintenance-regular.task'],
                ['name' => 'Email Notifications', 'class' => '\Xibo\XTR\EmailNotificationsTask', 'status' => 2, 'options' => '[]', 'schedule' =>  '*/5 * * * * *', 'isActive' => 1, 'configFile' => '/tasks/email-notifications.task'],
                ['name' => 'Stats Archive', 'class' => '\Xibo\XTR\StatsArchiveTask', 'status' => 2, 'options' => '{"periodSizeInDays":"7","maxPeriods":"4", "archiveStats":"Off"}', 'schedule' =>  '0 0 * * Mon', 'isActive' => 1, 'configFile' => '/tasks/stats-archiver.task'],
                ['name' => 'Remove old Notifications', 'class' =>  '\Xibo\XTR\NotificationTidyTask', 'status' => 2, 'options' => '{"maxAgeDays":"7","systemOnly":"1","readOnly":"0"}',  'schedule' => '15 0 * * *', 'isActive' => 1, 'configFile' => '/tasks/notification-tidy.task'],
                ['name' => 'Fetch Remote DataSets', 'class' =>  '\Xibo\XTR\RemoteDataSetFetchTask', 'status' => 2, 'options' => '[]',  'schedule' => '30 * * * * *', 'isActive' => 1, 'configFile' => '/tasks/remote-dataset.task'],
                ['name' => 'Drop Player Cache', 'class' => '\Xibo\XTR\DropPlayerCacheTask', 'options' => '[]', 'schedule' => '0 0 1 1 *', 'isActive' => '0', 'configFile' => '/tasks/drop-player-cache.task'],
            ])
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
            ->insert([
                ['tag' => 'template'],
                ['tag' => 'background'],
                ['tag' => 'thumbnail'],
                ['tag' => 'imported'],
            ])
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
            ->insert([
                ['transition' => 'Fade In', 'code' => 'fadeIn', 'hasDuration' => 1, 'hasDirection' => 0, 'availableAsIn' => 1, 'availableAsOut' => 0],
                ['transition' => 'Fade Out', 'code' => 'fadeOut', 'hasDuration' => 1, 'hasDirection' => 0, 'availableAsIn' => 0, 'availableAsOut' => 1],
                ['transition' => 'Fly', 'code' => 'fly', 'hasDuration' => 1, 'hasDirection' => 1, 'availableAsIn' => 1, 'availableAsOut' => 1],
            ])
            ->save();

        // Stats
        $stat = $this->table('stat', ['id' => 'statId']);
        $stat
            ->addColumn('type', 'string', ['limit' => 20])
            ->addColumn('statDate', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('scheduleId', 'integer')
            ->addColumn('displayId', 'integer')
            ->addColumn('layoutId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('mediaId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('widgetId', 'integer', ['default' => null, 'null' => true])
            ->addColumn('start', 'datetime', ['null' => true, 'default' => null])
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
            ->addIndex(['displayId', 'end'])
            ->save();

        // Log
        $log = $this->table('log', ['id' => 'logId']);
        $log
            ->addColumn('runNo', 'string', ['limit' => 10])
            ->addColumn('logDate', 'datetime', ['null' => true, 'default' => null])
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
            ->addColumn('objectAfter', 'text', ['default' => null, 'null' => true])
            ->save();

        // Bandwidth Tracking
        $bandwidthType = $this->table('bandwidthtype', ['id' => 'bandwidthTypeId']);
        $bandwidthType
            ->addColumn('name', 'string', ['limit' => 25])
            ->insert([
                ['bandwidthTypeId' => 1, 'name' => 'Register'],
                ['bandwidthTypeId' => 2, 'name' => 'Required Files'],
                ['bandwidthTypeId' => 3, 'name' => 'Schedule'],
                ['bandwidthTypeId' => 4, 'name' => 'Get File'],
                ['bandwidthTypeId' => 5, 'name' => 'Get Resource'],
                ['bandwidthTypeId' => 6, 'name' => 'Media Inventory'],
                ['bandwidthTypeId' => 7, 'name' => 'Notify Status'],
                ['bandwidthTypeId' => 8, 'name' => 'Submit Stats'],
                ['bandwidthTypeId' => 9, 'name' => 'Submit Log'],
                ['bandwidthTypeId' => 10, 'name' => 'Report Fault'],
                ['bandwidthTypeId' => 11, 'name' => 'Screen Shot'],
            ])
            ->save();

        $bandwidth = $this->table('bandwidth', ['id' => false, 'primary_key' => ['displayId', 'type', 'month']]);
        $bandwidth->addColumn('displayId', 'integer')
            ->addColumn('type', 'integer')
            ->addColumn('month', 'integer')
            ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
            ->addForeignKey('type', 'bandwidthtype', 'bandwidthTypeId')
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
        // Add settings
        $this->execute('
INSERT INTO `setting` (`settingid`, `setting`, `value`, `fieldType`, `helptext`, `options`, `cat`, `userChange`, `title`, `validation`, `ordering`, `default`, `userSee`, `type`) VALUES
(3, \'defaultUsertype\', \'User\', \'dropdown\', \'Sets the default user type selected when creating a user.\r\n<br />\r\nWe recommend that this is set to "User"\', \'User|Group Admin|Super Admin\', \'users\', 1, \'Default User Type\', \'\', 10, \'User\', 1, \'string\'),
(7, \'userModule\', \'module_user_general.php\', \'dirselect\', \'This sets which user authentication module is currently being used.\', NULL, \'users\', 0, \'User Module\', \'\', 0, \'module_user_general.php\', 0, \'string\'),
(11, \'defaultTimezone\', \'Europe/London\', \'timezone\', \'Set the default timezone for the application\', \'Europe/London\', \'regional\', 1, \'Timezone\', \'\', 20, \'Europe/London\', 1, \'string\'),
(18, \'mail_to\', \'mail@yoursite.com\', \'email\', \'Errors will be mailed here\', NULL, \'maintenance\', 1, \'Admin email address\', \'\', 30, \'mail@yoursite.com\', 1, \'string\'),
(19, \'mail_from\', \'mail@yoursite.com\', \'email\', \'Mail will be sent from this address\', NULL, \'maintenance\', 1, \'Sending email address\', \'\', 40, \'mail@yoursite.com\', 1, \'string\'),
(30, \'audit\', \'Error\', \'dropdown\', \'Set the level of logging the CMS should record. In production systems "error" is recommended.\', \'Emergency|Alert|Critical|Error|Warning|Notice|Info|Debug\', \'troubleshooting\', 1, \'Log Level\', \'\', 20, \'error\', 1, \'word\'),
(33, \'LIBRARY_LOCATION\', \'\', \'text\', \'The fully qualified path to the CMS library location.\', NULL, \'configuration\', 1, \'Library Location\', \'required\', 10, \'\', 1, \'string\'),
(34, \'SERVER_KEY\', \'\', \'text\', NULL, NULL, \'configuration\', 1, \'CMS Secret Key\', \'required\', 20, \'\', 1, \'string\'),
(35, \'HELP_BASE\', \'https://xibosignage.com/manual/en/\', \'text\', NULL, NULL, \'general\', 1, \'Location of the Manual\', \'required\', 10, \'https://xibosignage.com/manual/\', 1, \'string\'),
(36, \'PHONE_HOME\', \'1\', \'checkbox\', \'Should the server send anonymous statistics back to the Xibo project?\', NULL, \'general\', 1, \'Allow usage tracking?\', \'\', 10, \'1\', 1, \'checkbox\'),
(37, \'PHONE_HOME_KEY\', \'\', \'text\', \'Key used to distinguish each Xibo instance. This is generated randomly based on the time you first installed Xibo, and is completely untraceable.\', NULL, \'general\', 0, \'Phone home key\', \'\', 20, \'\', 0, \'string\'),
(38, \'PHONE_HOME_URL\', \'https://xibo.org.uk/api/stats/track\', \'text\', \'The URL to connect to to PHONE_HOME (if enabled)\', NULL, \'network\', 0, \'Phone home URL\', \'\', 60, \'https://xibo.org.uk/api/stats/track\', 0, \'string\'),
(39, \'PHONE_HOME_DATE\', \'0\', \'text\', \'The last time we PHONED_HOME in seconds since the epoch\', NULL, \'general\', 0, \'Phone home time\', \'\', 30, \'0\', 0, \'int\'),
(40, \'SERVER_MODE\', \'Production\', \'dropdown\', \'This should only be set if you want to display the maximum allowed error messaging through the user interface. <br /> Useful for capturing critical php errors and environment issues.\', \'Production|Test\', \'troubleshooting\', 1, \'Server Mode\', \'\', 30, \'Production\', 1, \'word\'),
(41, \'MAINTENANCE_ENABLED\', \'Off\', \'dropdown\', \'Allow the maintenance script to run if it is called?\', \'Protected|On|Off\', \'maintenance\', 1, \'Enable Maintenance?\', \'\', 10, \'Off\', 1, \'word\'),
(42, \'MAINTENANCE_EMAIL_ALERTS\', \'On\', \'dropdown\', \'Global switch for email alerts to be sent\', \'On|Off\', \'maintenance\', 1, \'Enable Email Alerts?\', \'\', 20, \'On\', 1, \'word\'),
(43, \'MAINTENANCE_KEY\', \'changeme\', \'text\', \'String appended to the maintenance script to prevent malicious calls to the script.\', NULL, \'maintenance\', 1, \'Maintenance Key\', \'\', 50, \'changeme\', 1, \'string\'),
(44, \'MAINTENANCE_LOG_MAXAGE\', \'30\', \'number\', \'Maximum age for log entries. Set to 0 to keep logs indefinitely.\', NULL, \'maintenance\', 1, \'Max Log Age\', \'\', 60, \'30\', 1, \'int\'),
(45, \'MAINTENANCE_STAT_MAXAGE\', \'30\', \'number\', \'Maximum age for statistics entries. Set to 0 to keep statistics indefinitely.\', NULL, \'maintenance\', 1, \'Max Statistics Age\', \'\', 70, \'30\', 1, \'int\'),
(46, \'MAINTENANCE_ALERT_TOUT\', \'12\', \'number\', \'How long in minutes after the last time a client connects should we send an alert? Can be overridden on a per client basis.\', NULL, \'maintenance\', 1, \'Max Display Timeout\', \'\', 80, \'12\', 1, \'int\'),
(47, \'SHOW_DISPLAY_AS_VNCLINK\', \'\', \'text\', \'Turn the display name in display management into a VNC link using the IP address last collected. The %s is replaced with the IP address. Leave blank to disable.\', NULL, \'displays\', 1, \'Display a VNC Link?\', \'\', 30, \'\', 1, \'string\'),
(48, \'SHOW_DISPLAY_AS_VNC_TGT\', \'_top\', \'text\', \'If the display name is shown as a link in display management, what target should the link have? Set _top to open the link in the same window or _blank to open in a new window.\', NULL, \'displays\', 1, \'Open VNC Link in new window?\', \'\', 40, \'_top\', 1, \'string\'),
(49, \'MAINTENANCE_ALWAYS_ALERT\', \'Off\', \'dropdown\', \'Should Xibo send an email if a display is in an error state every time the maintenance script runs?\', \'On|Off\', \'maintenance\', 1, \'Send repeat Display Timeouts\', \'\', 80, \'Off\', 1, \'word\'),
(50, \'SCHEDULE_LOOKAHEAD\', \'On\', \'dropdown\', \'Should Xibo send future schedule information to clients?\', \'On|Off\', \'general\', 0, \'Send Schedule in advance?\', \'\', 40, \'On\', 1, \'word\'),
(51, \'REQUIRED_FILES_LOOKAHEAD\', \'172800\', \'number\', \'How many seconds in to the future should the calls to RequiredFiles look?\', NULL, \'general\', 1, \'Send files in advance?\', \'\', 50, \'172800\', 1, \'int\'),
(52, \'REGION_OPTIONS_COLOURING\', \'Media Colouring\', \'dropdown\', NULL, \'Media Colouring|Permissions Colouring\', \'permissions\', 1, \'How to colour Media on the Region Timeline\', \'\', 30, \'Media Colouring\', 1, \'string\'),
(53, \'LAYOUT_COPY_MEDIA_CHECKB\', \'Unchecked\', \'dropdown\', \'Default the checkbox for making duplicates of media when copying layouts\', \'Checked|Unchecked\', \'defaults\', 1, \'Default copy media when copying a layout?\', \'\', 20, \'Unchecked\', 1, \'word\'),
(54, \'MAX_LICENSED_DISPLAYS\', \'0\', \'number\', \'The maximum number of licensed clients for this server installation. 0 = unlimited\', NULL, \'displays\', 0, \'Number of display slots\', \'\', 50, \'0\', 0, \'int\'),
(55, \'LIBRARY_MEDIA_UPDATEINALL_CHECKB\', \'Checked\', \'dropdown\', \'Default the checkbox for updating media on all layouts when editing in the library\', \'Checked|Unchecked\', \'defaults\', 1, \'Default update media in all layouts\', \'\', 10, \'Unchecked\', 1, \'word\'),
(56, \'USER_PASSWORD_POLICY\', \'\', \'text\', \'Regular Expression for password complexity, leave blank for no policy.\', \'\', \'users\', 1, \'Password Policy Regular Expression\', \'\', 20, \'\', 1, \'string\'),
(57, \'USER_PASSWORD_ERROR\', \'\', \'text\', \'A text description of this password policy. Will be show to users when their password does not meet the required policy\', \'\', \'users\', 1, \'Description of Password Policy\', \'\', 30, \'\', 1, \'string\'),
(59, \'LIBRARY_SIZE_LIMIT_KB\', \'0\', \'number\', \'The Limit for the Library Size in KB\', NULL, \'network\', 0, \'Library Size Limit\', \'\', 50, \'0\', 1, \'int\'),
(60, \'MONTHLY_XMDS_TRANSFER_LIMIT_KB\', \'0\', \'number\', \'XMDS Transfer Limit in KB/month\', NULL, \'network\', 0, \'Monthly bandwidth Limit\', \'\', 40, \'0\', 1, \'int\'),
(61, \'DEFAULT_LANGUAGE\', \'en_GB\', \'text\', \'The default language to use\', NULL, \'regional\', 1, \'Default Language\', \'\', 10, \'en_GB\', 1, \'string\'),
(62, \'TRANSITION_CONFIG_LOCKED_CHECKB\', \'Unchecked\', \'dropdown\', \'Is the Transition config locked?\', \'Checked|Unchecked\', \'defaults\', 0, \'Allow modifications to the transition configuration?\', \'\', 40, \'Unchecked\', 1, \'word\'),
(63, \'GLOBAL_THEME_NAME\', \'default\', \'text\', \'The Theme to apply to all pages by default\', NULL, \'configuration\', 1, \'CMS Theme\', \'\', 30, \'default\', 1, \'word\'),
(64, \'DEFAULT_LAT\', \'51.504\', \'number\', \'The Latitude to apply for any Geo aware Previews\', NULL, \'displays\', 1, \'Default Latitude\', \'\', 10, \'51.504\', 1, \'double\'),
(65, \'DEFAULT_LONG\', \'-0.104\', \'number\', \'The Longitude to apply for any Geo aware Previews\', NULL, \'displays\', 1, \'Default Longitude\', \'\', 20, \'-0.104\', 1, \'double\'),
(66, \'SCHEDULE_WITH_VIEW_PERMISSION\', \'No\', \'dropdown\', \'Should users with View permissions on displays be allowed to schedule to them?\', \'Yes|No\', \'permissions\', 1, \'Schedule with view permissions?\', \'\', 40, \'No\', 1, \'word\'),
(67, \'SETTING_IMPORT_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'general\', 1, \'Allow Import?\', \'\', 80, \'1\', 1, \'checkbox\'),
(68, \'SETTING_LIBRARY_TIDY_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'general\', 1, \'Enable Library Tidy?\', \'\', 90, \'1\', 1, \'checkbox\'),
(69, \'SENDFILE_MODE\', \'Off\', \'dropdown\', \'When a user downloads a file from the library or previews a layout, should we attempt to use Apache X-Sendfile, Nginx X-Accel, or PHP (Off) to return the file from the library?\', \'Off|Apache|Nginx\', \'general\', 1, \'File download mode\', \'\', 60, \'Off\', 1, \'word\'),
(70, \'EMBEDDED_STATUS_WIDGET\', \'\', \'text\', \'HTML to embed in an iframe on the Status Dashboard\', NULL, \'general\', 0, \'Status Dashboard Widget\', \'\', 70, \'\', 1, \'htmlstring\'),
(71, \'PROXY_HOST\', \'\', \'text\', \'The Proxy URL\', NULL, \'network\', 1, \'Proxy URL\', \'\', 10, \'\', 1, \'string\'),
(72, \'PROXY_PORT\', \'0\', \'number\', \'The Proxy Port\', NULL, \'network\', 1, \'Proxy Port\', \'\', 20, \'0\', 1, \'int\'),
(73, \'PROXY_AUTH\', \'\', \'text\', \'The Authentication information for this proxy. username:password\', NULL, \'network\', 1, \'Proxy Credentials\', \'\', 30, \'\', 1, \'string\'),
(74, \'DATE_FORMAT\',  \'Y-m-d H:i\',  \'text\',  \'The Date Format to use when displaying dates in the CMS.\', NULL ,  \'regional\',  \'1\',  \'Date Format\',  \'required\',  30,  \'Y-m-d\',  \'1\',  \'string\'),
(75, \'DETECT_LANGUAGE\',  \'1\',  \'checkbox\',  \'Detect the browser language?\', NULL ,  \'regional\',  \'1\',  \'Detect Language\',  \'\',  40,  \'1\',  1,  \'checkbox\'),
(76, \'DEFAULTS_IMPORTED\', \'0\', \'text\', \'Has the default layout been imported?\', NULL, \'general\', 0, \'Defaults Imported?\', \'required\', 100, \'0\', 0, \'checkbox\'),
(77, \'FORCE_HTTPS\', \'0\', \'checkbox\', \'Force the portal into HTTPS?\', NULL, \'network\', 1, \'Force HTTPS?\', \'\', 70, \'0\', 1, \'checkbox\'),
(78, \'ISSUE_STS\', \'0\', \'checkbox\', \'Add STS to the response headers? Make sure you fully understand STS before turning it on as it will prevent access via HTTP after the first successful HTTPS connection.\', NULL, \'network\', 1, \'Enable STS?\', \'\', 80, \'0\', 1, \'checkbox\'),
(79, \'STS_TTL\', \'600\', \'text\', \'The Time to Live (maxage) of the STS header expressed in seconds.\', NULL, \'network\', 1, \'STS Time out\', \'\', 90, \'600\', 1, \'int\'),
(81, \'CALENDAR_TYPE\', \'Gregorian\', \'dropdown\', \'Which Calendar Type should the CMS use?\', \'Gregorian|Jalali\', \'regional\', 1, \'Calendar Type\', \'\', 50, \'Gregorian\', 1, \'string\'),
(82, \'DASHBOARD_LATEST_NEWS_ENABLED\', \'1\', \'checkbox\', \'Should the Dashboard show latest news? The address is provided by the theme.\', \'\', \'general\', 1, \'Enable Latest News?\', \'\', 110, \'1\', 1, \'checkbox\'),
(83, \'LIBRARY_MEDIA_DELETEOLDVER_CHECKB\',\'Checked\',\'dropdown\',\'Default the checkbox for Deleting Old Version of media when a new file is being uploaded to the library.\',\'Checked|Unchecked\',\'defaults\',1,\'Default for "Delete old version of Media" checkbox. Shown when Editing Library Media.\', \'\', 50, \'Unchecked\', 1, \'dropdown\'),
(84, \'PROXY_EXCEPTIONS\', \'\', \'text\', \'Hosts and Keywords that should not be loaded via the Proxy Specified. These should be comma separated.\', \'\', \'network\', 1, \'Proxy Exceptions\', \'\', 32, \'\', 1, \'text\'),
(85, \'INSTANCE_SUSPENDED\', \'0\', \'checkbox\', \'Is this instance suspended?\', NULL, \'general\', 0, \'Instance Suspended\', \'\', 120, \'0\', 0, \'checkbox\'),
(87, \'XMR_ADDRESS\', \'tcp://localhost:5555\', \'text\', \'Please enter the private address for XMR.\', NULL, \'displays\', 1, \'XMR Private Address\', \'\', 5, \'tcp:://localhost:5555\', 1, \'string\'),
(88, \'XMR_PUB_ADDRESS\', \'\', \'text\', \'Please enter the public address for XMR.\', NULL, \'displays\', 1, \'XMR Public Address\', \'\', 6, \'\', 1, \'string\'),
(89, \'CDN_URL\', \'\', \'text\', \'Content Delivery Network Address for serving file requests to Players\', \'\', \'network\', 0, \'CDN Address\', \'\', 33, \'\', 0, \'string\'),
(90, \'ELEVATE_LOG_UNTIL\', \'1463396415\', \'datetime\', \'Elevate the log level until this date.\', null, \'troubleshooting\', 1, \'Elevate Log Until\', \' \', 25, \'\', 1, \'datetime\'),
(91, \'RESTING_LOG_LEVEL\', \'Error\', \'dropdown\', \'Set the level of the resting log level. The CMS will revert to this log level after an elevated period ends. In production systems "error" is recommended.\', \'Emergency|Alert|Critical|Error\', \'troubleshooting\', 1, \'Resting Log Level\', \'\', 19, \'error\', 1, \'word\'),
(92, \'TASK_CONFIG_LOCKED_CHECKB\', \'Unchecked\', \'dropdown\', \'Is the task config locked? Useful for Service providers.\', \'Checked|Unchecked\', \'defaults\', 0, \'Lock Task Config\', \'\', 30, \'Unchecked\', 0, \'word\'),
(93, \'WHITELIST_LOAD_BALANCERS\', \'\', \'text\', \'If the CMS is behind a load balancer, what are the load balancer IP addresses, comma delimited.\', \'\', \'network\', 1, \'Whitelist Load Balancers\', \'\', 100, \'\', 1, \'string\'),
(94, \'DEFAULT_LAYOUT\', \'1\', \'text\', \'The default layout to assign for new displays and displays which have their current default deleted.\', \'1\', \'displays\', 1, \'Default Layout\', \'\', 4, \'\', 1, \'int\'),
(95, \'DISPLAY_PROFILE_STATS_DEFAULT\', \'0\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Default setting for Statistics Enabled?\', \'\', 70, \'0\', 1, \'checkbox\'),
(96, \'DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Enable the option to report the current layout status?\', \'\', 80, \'0\', 1, \'checkbox\'),
(97, \'DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED\', \'1\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Enable the option to set the screenshot interval?\', \'\', 90, \'0\', 1, \'checkbox\'),
(98, \'DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT\', \'200\', \'number\', \'The default size in pixels for the Display Screenshots\', NULL, \'displays\', 1, \'Display Screenshot Default Size\', \'\', 100, \'200\', 1, \'int\'),
(99, \'LATEST_NEWS_URL\', \'http://xibo.org.uk/feed\', \'text\', \'RSS/Atom Feed to be displayed on the Status Dashboard\', \'\', \'general\', 0, \'Latest News URL\', \'\', 111, \'\', 0, \'string\'),
(100, \'DISPLAY_LOCK_NAME_TO_DEVICENAME\', \'0\', \'checkbox\', NULL, NULL, \'displays\', 1, \'Lock the Display Name to the device name provided by the Player?\', \'\', 80, \'0\', 1, \'checkbox\');        
        ');

        // Add help
        $this->execute('
INSERT INTO `help` (`HelpID`, `Topic`, `Category`, `Link`) VALUES
(1, \'Layout\', \'General\', \'layouts.html\'),
(2, \'Content\', \'General\', \'media.html\'),
(4, \'Schedule\', \'General\', \'scheduling.html\'),
(5, \'Group\', \'General\', \'users_groups.html\'),
(6, \'Admin\', \'General\', \'cms_settings.html\'),
(7, \'Report\', \'General\', \'troubleshooting.html\'),
(8, \'Dashboard\', \'General\', \'tour.html\'),
(9, \'User\', \'General\', \'users.html\'),
(10, \'Display\', \'General\', \'displays.html\'),
(11, \'DisplayGroup\', \'General\', \'displays_groups.html\'),
(12, \'Layout\', \'Add\', \'layouts.html#Add_Layout\'),
(13, \'Layout\', \'Background\', \'layouts_designer.html#Background\'),
(14, \'Content\', \'Assign\', \'layouts_playlists.html#Assigning_Content\'),
(15, \'Layout\', \'RegionOptions\', \'layouts_regions.html\'),
(16, \'Content\', \'AddtoLibrary\', \'media_library.html\'),
(17, \'Display\', \'Edit\', \'displays.html#Display_Edit\'),
(18, \'Display\', \'Delete\', \'displays.html#Display_Delete\'),
(19, \'Displays\', \'Groups\', \'displays_groups.html#Group_Members\'),
(20, \'UserGroup\', \'Add\', \'users_groups.html#Adding_Group\'),
(21, \'User\', \'Add\', \'users_administration.html#Add_User\'),
(22, \'User\', \'Delete\', \'users_administration.html#Delete_User\'),
(23, \'Content\', \'Config\', \'cms_settings.html#Content\'),
(24, \'LayoutMedia\', \'Permissions\', \'users_permissions.html\'),
(25, \'Region\', \'Permissions\', \'users_permissions.html\'),
(26, \'Library\', \'Assign\', \'layouts_playlists.html#Add_From_Library\'),
(27, \'Media\', \'Delete\', \'media_library.html#Delete\'),
(28, \'DisplayGroup\', \'Add\', \'displays_groups.html#Add_Group\'),
(29, \'DisplayGroup\', \'Edit\', \'displays_groups.html#Edit_Group\'),
(30, \'DisplayGroup\', \'Delete\', \'displays_groups.html#Delete_Group\'),
(31, \'DisplayGroup\', \'Members\', \'displays_groups.html#Group_Members\'),
(32, \'DisplayGroup\', \'Permissions\', \'users_permissions.html\'),
(34, \'Schedule\', \'ScheduleNow\', \'scheduling_now.html\'),
(35, \'Layout\', \'Delete\', \'layouts.html#Delete_Layout\'),
(36, \'Layout\', \'Copy\', \'layouts.html#Copy_Layout\'),
(37, \'Schedule\', \'Edit\', \'scheduling_events.html#Edit\'),
(38, \'Schedule\', \'Add\', \'scheduling_events.html#Add\'),
(39, \'Layout\', \'Permissions\', \'users_permissions.html\'),
(40, \'Display\', \'MediaInventory\', \'displays.html#Media_Inventory\'),
(41, \'User\', \'ChangePassword\', \'users.html#Change_Password\'),
(42, \'Schedule\', \'Delete\', \'scheduling_events.html\'),
(43, \'Layout\', \'Edit\', \'layouts_designer.html#Edit_Layout\'),
(44, \'Media\', \'Permissions\', \'users_permissions.html\'),
(45, \'Display\', \'DefaultLayout\', \'displays.html#DefaultLayout\'),
(46, \'UserGroup\', \'Edit\', \'users_groups.html#Edit_Group\'),
(47, \'UserGroup\', \'Members\', \'users_groups.html#Group_Member\'),
(48, \'User\', \'PageSecurity\', \'users_permissions.html#Page_Security\'),
(49, \'User\', \'MenuSecurity\', \'users_permissions.html#Menu_Security\'),
(50, \'UserGroup\', \'Delete\', \'users_groups.html#Delete_Group\'),
(51, \'User\', \'Edit\', \'users_administration.html#Edit_User\'),
(52, \'User\', \'Applications\', \'users_administration.html#Users_MyApplications\'),
(53, \'User\', \'SetHomepage\', \'users_administration.html#Media_Dashboard\'),
(54, \'DataSet\', \'General\', \'media_datasets.html\'),
(55, \'DataSet\', \'Add\', \'media_datasets.html#Create_Dataset\'),
(56, \'DataSet\', \'Edit\', \'media_datasets.html#Edit_Dataset\'),
(57, \'DataSet\', \'Delete\', \'media_datasets.html#Delete_Dataset\'),
(58, \'DataSet\', \'AddColumn\', \'media_datasets.html#Dataset_Column\'),
(59, \'DataSet\', \'EditColumn\', \'media_datasets.html#Dataset_Column\'),
(60, \'DataSet\', \'DeleteColumn\', \'media_datasets.html#Dataset_Column\'),
(61, \'DataSet\', \'Data\', \'media_datasets.html#Dataset_Row\'),
(62, \'DataSet\', \'Permissions\', \'users_permissions.html\'),
(63, \'Fault\', \'General\', \'troubleshooting.html#Report_Fault\'),
(65, \'Stats\', \'General\', \'displays_metrics.html\'),
(66, \'Resolution\', \'General\', \'layouts_resolutions.html\'),
(67, \'Template\', \'General\', \'layouts_templates.html\'),
(68, \'Services\', \'Register\', \'#Registered_Applications\'),
(69, \'OAuth\', \'General\', \'api_oauth.html\'),
(70, \'Services\', \'Log\', \'api_oauth.html#oAuthLog\'),
(71, \'Module\', \'Edit\', \'media_modules.html\'),
(72, \'Module\', \'General\', \'media_modules.html\'),
(73, \'Campaign\', \'General\', \'layouts_campaigns.html\'),
(74, \'License\', \'General\', \'licence_information.html\'),
(75, \'DataSet\', \'ViewColumns\', \'media_datasets.html#Dataset_Column\'),
(76, \'Campaign\', \'Permissions\', \'users_permissions.html\'),
(77, \'Transition\', \'Edit\', \'layouts_transitions.html\'),
(78, \'User\', \'SetPassword\', \'users_administration.html#Set_Password\'),
(79, \'DataSet\', \'ImportCSV\', \'media_datasets.htmlmedia_datasets.html#Import_CSV\'),
(80, \'DisplayGroup\', \'FileAssociations\', \'displays_fileassociations.html\'),
(81, \'Statusdashboard\', \'General\', \'tour_status_dashboard.html\'),
(82, \'Displayprofile\', \'General\', \'displays_settings.html\'),
(83, \'DisplayProfile\', \'Edit\', \'displays_settings.html#edit\'),
(84, \'DisplayProfile\', \'Delete\', \'displays_settings.html#delete\');        
        ');

        // Add modules
        $this->execute('
INSERT INTO `module` (`ModuleID`, `Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`, `viewPath`, `class`, `defaultDuration`) VALUES
  (1, \'Image\', \'Image\', 1, 0, \'Upload Image files to assign to Layouts\', \'forms/image.gif\', 1, \'jpg,jpeg,png,bmp,gif\', 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\Image\', 10),
  (2, \'Video\', \'Video\', 1, 0, \'Upload Video files to assign to Layouts\', \'forms/video.gif\', 1, \'wmv,avi,mpg,mpeg,webm,mp4\', 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\Video\', 0),
  (4, \'PowerPoint\', \'PowerPoint\', 1, 0, \'Upload a PowerPoint file to assign to Layouts\', \'forms/powerpoint.gif\', 1, \'ppt,pps,pptx\', 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\PowerPoint\', 10),
  (5, \'Webpage\', \'Webpage\', 1, 1, \'Embed a Webpage\', \'forms/webpage.gif\', 1, NULL, 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\WebPage\', 60),
  (6, \'Ticker\', \'Ticker\', 1, 1, \'Display dynamic feed content\', \'forms/ticker.gif\', 1, NULL, 1, 1, NULL, \'[]\', \'../modules\', \'Xibo\\\\Widget\\\\Ticker\', 5),
  (7, \'Text\', \'Text\', 1, 1, \'Add Text directly to a Layout\', \'forms/text.gif\', 1, NULL, 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\Text\', 5),
  (8, \'Embedded\', \'Embedded\', 1, 1, \'Embed HTML and JavaScript\', \'forms/webpage.gif\', 1, NULL, 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\Embedded\', 60),
  (11, \'datasetview\', \'Data Set\', 1, 1, \'Organise and display DataSet data in a tabular format\', \'forms/datasetview.gif\', 1, NULL, 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\DataSetView\', 60),
  (12, \'shellcommand\', \'Shell Command\', 1, 1, \'Instruct a Display to execute a command using the operating system shell\', \'forms/shellcommand.gif\', 1, NULL, 1, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\ShellCommand\', 3),
  (13, \'localvideo\', \'Local Video\', 1, 1, \'Display Video that only exists on the Display by providing a local file path or URL\', \'forms/video.gif\', 1, NULL, 0, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\LocalVideo\', 60),
  (14, \'genericfile\', \'Generic File\', 1, 0, \'A generic file to be stored in the library\', \'forms/library.gif\', 1, \'apk,ipk,js,html,htm\', 0, 0, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\GenericFile\', 10),
  (15, \'clock\', \'Clock\', 1, 1, \'Assign a type of Clock or a Countdown\', \'forms/library.gif\', 1, NULL, 1, 1, \'html\', \'[]\', \'../modules\', \'Xibo\\\\Widget\\\\Clock\', 5),
  (16, \'font\', \'Font\', 1, 0, \'A font to use in other Modules\', \'forms/library.gif\', 1, \'ttf,otf,eot,svg,woff\', 0, 0, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\Font\', 10),
  (17, \'audio\', \'Audio\', 1, 0, \'Upload Audio files to assign to Layouts\', \'forms/video.gif\', 1, \'mp3,wav\', 0, 1, NULL, NULL, \'../modules\', \'Xibo\\\\Widget\\\\Audio\', 0),
  (18, \'pdf\', \'PDF\', 1, 0, \'Upload PDF files to assign to Layouts\', \'forms/pdf.gif\', 1, \'pdf\', 1, 1, \'html\', null, \'../modules\', \'Xibo\\\\Widget\\\\Pdf\', 60),
  (19, \'notificationview\', \'Notification\', 1, 1, \'Display messages created in the Notification Drawer of the CMS\', \'forms/library.gif\', 1, null, 1, 1, \'html\', null, \'../modules\', \'Xibo\\\\Widget\\\\NotificationView\', 10);        
        ');
    }
}
