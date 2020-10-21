<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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


namespace Xibo\Factory;

use Xibo\Entity\Homepage;
use Xibo\Entity\User;
use Xibo\Entity\UserGroup;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class UserGroupFactory
 * @package Xibo\Factory
 */
class UserGroupFactory extends BaseFactory
{
    /** @var array */
    private $features = null;

    /** @var array */
    private $homepages = null;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
    }

    /**
     * Create Empty User Group Object
     * @return UserGroup
     */
    public function createEmpty()
    {
        return new UserGroup($this->getStore(), $this->getLog(), $this, $this->getUserFactory());
    }

    /**
     * Create User Group
     * @param $userGroup
     * @param $libraryQuota
     * @return UserGroup
     */
    public function create($userGroup, $libraryQuota)
    {
        $group = $this->createEmpty();
        $group->group = $userGroup;
        $group->libraryQuota = $libraryQuota;

        return $group;
    }

    /**
     * Get by Group Id
     * @param int $groupId
     * @return UserGroup
     * @throws NotFoundException
     */
    public function getById($groupId)
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'groupId' => $groupId, 'isUserSpecific' => -1]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get by Group Name
     * @param string $group
     * @param int $isUserSpecific
     * @return UserGroup
     * @throws NotFoundException
     */
    public function getByName($group, $isUserSpecific = 0)
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'exactGroup' => $group, 'isUserSpecific' => $isUserSpecific]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get Everyone Group
     * @return UserGroup
     * @throws NotFoundException
     */
    public function getEveryone()
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'isEveryone' => 1]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get isSystemNotification Group
     * @return UserGroup[]
     */
    public function getSystemNotificationGroups()
    {
        return $this->query(null, ['disableUserCheck' => 1, 'isSystemNotification' => 1, 'isUserSpecific' => -1]);
    }

    /**
     * Get isDisplayNotification Group
     * @param int|null $displayGroupId Optionally provide a displayGroupId to restrict to view permissions.
     * @return UserGroup[]
     */
    public function getDisplayNotificationGroups($displayGroupId = null)
    {
        return $this->query(null, [
            'disableUserCheck' => 1,
            'isDisplayNotification' => 1,
            'isUserSpecific' => -1,
            'displayGroupId' => $displayGroupId
        ]);
    }

    /**
     * Get by User Id
     * @param int $userId
     * @return \Xibo\Entity\UserGroup[]
     */
    public function getByUserId($userId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $userId, 'isUserSpecific' => 0]);
    }

    /**
     * Get User Groups assigned to Notifications
     * @param int $notificationId
     * @return array[UserGroup]
     */
    public function getByNotificationId($notificationId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'notificationId' => $notificationId, 'isUserSpecific' => -1]);
    }

    /**
     * Get by Display Group
     * @param int $displayGroupId
     * @return UserGroup[]
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return UserGroup[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];

        if ($sortOrder === null) {
            $sortOrder = ['`group`'];
        }

        $select = '
        SELECT 	`group`.group,
            `group`.groupId,
            `group`.isUserSpecific,
            `group`.isEveryone,
            `group`.libraryQuota,
            `group`.isSystemNotification,
            `group`.isDisplayNotification,
            `group`.features
        ';

        $body = '
          FROM `group`
         WHERE 1 = 1
        ';

        // Permissions
        if ($parsedFilter->getCheckbox('disableUserCheck') == 0) {
            // Normal users can only see their group
            if ($this->getUser()->userTypeId != 1) {
                $body .= '
                AND `group`.groupId IN (
                    SELECT `group`.groupId
                      FROM `lkusergroup`
                        INNER JOIN `group`
                        ON `group`.groupId = `lkusergroup`.groupId
                            AND `group`.isUserSpecific = 0
                     WHERE `lkusergroup`.userId = :currentUserId
                )
                ';
                $params['currentUserId'] = $this->getUser()->userId;
            }
        }

        // Filter by Group Id
        if ($parsedFilter->getInt('groupId') !== null) {
            $body .= ' AND `group`.groupId = :groupId ';
            $params['groupId'] = $parsedFilter->getInt('groupId');
        }

        // Filter by Group Name
        if ($parsedFilter->getString('group') != null) {
            $terms = explode(',', $parsedFilter->getString('group'));
            $this->nameFilter('group', 'group', $terms, $body, $params, ($parsedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($parsedFilter->getString('exactGroup') != null) {
            $body .= ' AND `group`.group = :exactGroup ';
            $params['exactGroup'] = $parsedFilter->getString('exactGroup');
        }

        // Filter by User Id
        if ($parsedFilter->getInt('userId') !== null) {
            $body .= ' AND `group`.groupId IN (SELECT groupId FROM `lkusergroup` WHERE userId = :userId) ';
            $params['userId'] = $parsedFilter->getInt('userId');
        }

        if ($parsedFilter->getInt('isUserSpecific') !== -1) {
            $body .= ' AND isUserSpecific = :isUserSpecific ';
            $params['isUserSpecific'] = $parsedFilter->getInt('isUserSpecific', ['default' => 0]);
        }

        // Always apply isEveryone=0 unless its been provided otherwise.
        $body .= ' AND isEveryone = :isEveryone ';
        $params['isEveryone'] = $parsedFilter->getInt('isEveryone', ['default' => 0]);

        if ($parsedFilter->getInt('isSystemNotification') !== null) {
            $body .= ' AND isSystemNotification = :isSystemNotification ';
            $params['isSystemNotification'] = $parsedFilter->getInt('isSystemNotification');
        }

        if ($parsedFilter->getInt('isDisplayNotification') !== null) {
            $body .= ' AND isDisplayNotification = :isDisplayNotification ';
            $params['isDisplayNotification'] = $parsedFilter->getInt('isDisplayNotification');
        }

        if ($parsedFilter->getInt('notificationId') !== null) {
            $body .= ' AND `group`.groupId IN (SELECT groupId FROM `lknotificationgroup` WHERE notificationId = :notificationId) ';
            $params['notificationId'] = $parsedFilter->getInt('notificationId');
        }

        if ($parsedFilter->getInt('displayGroupId') !== null) {
            $body .= ' 
                AND `group`.groupId IN (
                    SELECT DISTINCT `permission`.groupId
                      FROM `permission`
                        INNER JOIN `permissionentity`
                        ON `permissionentity`.entityId = permission.entityId
                            AND `permissionentity`.entity = \'Xibo\\Entity\\DisplayGroup\'
                     WHERE `permission`.objectId = :displayGroupId
                        AND `permission`.view = 1
                )
            ';
            $params['displayGroupId'] = $parsedFilter->getInt('displayGroupId');
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedFilter->getInt('start', ['default' => 0]) !== null && $parsedFilter->getInt('length', ['default' => 10]) !== null) {
            $limit = ' LIMIT ' . intval($parsedFilter->getInt('start', ['default' => 0]), 0) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $group = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'isUserSpecific', 'isEveryone', 'libraryQuota', 'isSystemNotification', 'isDisplayNotification'
                ]
            ]);

            // Parse the features JSON string stored in database
            $group->features = ($group->features === null) ? [] : json_decode($group->features, true);

            $entries[] = $group;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * @param \Xibo\Entity\User $user The User
     * @param bool $includeIsUser
     * @return array
     */
    public function getGroupFeaturesForUser($user, $includeIsUser = true)
    {
        $features = [];

        foreach ($this->getStore()->select('
                SELECT `group`.groupId, `group`.features 
                  FROM `group`
                    INNER JOIN `lkusergroup`
                    ON `lkusergroup`.groupId = `group`.groupId
                 WHERE `group`.groupId = :groupId
                    OR `group`.groupId IN (SELECT groupId FROM lkusergroup WHERE userId = :userId)
            ', [
                'userId' => $user->userId,
                'groupId' => $user->groupId
            ]) as $featureString
        ) {
            if (!$includeIsUser && $user->groupId == $featureString['groupId']) {
                continue;
            }

            $feature = ($featureString['features'] == null) ? [] : json_decode($featureString['features'], true);
            $features = array_merge($feature, $features);
        }

        return $features;
    }

    /**
     * @param string $group
     * @return array
     */
    public function getFeaturesByGroup(string $group)
    {
        $groupFeatures = [];
        foreach ($this->getFeatures() as $feature) {
            if ($feature['group'] === $group) {
                $groupFeatures[] = $feature;
            }
        }
        return $groupFeatures;
    }

    /**
     * Populate the core system features and homepages
     * @return array
     */
    public function getFeatures()
    {
        if ($this->features === null) {
            $this->features = [
                'schedule.view' => [
                    'feature' => 'schedule.view',
                    'group' => 'scheduling',
                    'title' => __('View the Calendar')
                ],
                'schedule.agenda' => [
                    'feature' => 'schedule.agenda',
                    'group' => 'scheduling',
                    'title' => __('When on the Calendar show the Agenda')
                ],
                'schedule.add' => [
                    'feature' => 'schedule.add',
                    'group' => 'scheduling',
                    'title' => __('Create new Scheduled Events')
                ],
                'schedule.modify' => [
                    'feature' => 'schedule.modify',
                    'group' => 'scheduling',
                    'title' => __('Edit and Delete existing Scheduled Events')
                ],
                'schedule.now' => [
                    'feature' => 'schedule.now',
                    'group' => 'scheduling',
                    'title' => __('Use Schedule Now to create short events which play straight away')
                ],
                'daypart.view' => [
                    'feature' => 'daypart.view',
                    'group' => 'scheduling',
                    'title' => __('View Day parts')
                ],
                'daypart.add' => [
                    'feature' => 'daypart.add',
                    'group' => 'scheduling',
                    'title' => __('Add Day parts')
                ],
                'daypart.modify' => [
                    'feature' => 'daypart.modify',
                    'group' => 'scheduling',
                    'title' => __('Modify Day parts')
                ],
                'library.view' => [
                    'feature' => 'library.view',
                    'group' => 'library',
                    'title' => __('View the Media Library')
                ],
                'library.add' => [
                    'feature' => 'library.add',
                    'group' => 'library',
                    'title' => __('Add to the Media Library')
                ],
                'library.modify' => [
                    'feature' => 'library.modify',
                    'group' => 'library',
                    'title' => __('Modify media in the Media Library')
                ],
                'dataset.view' => [
                    'feature' => 'dataset.view',
                    'group' => 'library',
                    'title' => __('View DataSets')
                ],
                'dataset.add' => [
                    'feature' => 'dataset.add',
                    'group' => 'library',
                    'title' => __('Add DataSets')
                ],
                'dataset.modify' => [
                    'feature' => 'dataset.modify',
                    'group' => 'library',
                    'title' => __('Modify DataSets')
                ],
                'dataset.data' => [
                    'feature' => 'dataset.data',
                    'group' => 'library',
                    'title' => __('Modify data held within DataSets')
                ],
                'layout.view' => [
                    'feature' => 'layout.view',
                    'group' => 'layout-design',
                    'title' => __('View Layouts')
                ],
                'layout.add' => [
                    'feature' => 'layout.add',
                    'group' => 'layout-design',
                    'title' => __('Create new Layouts')
                ],
                'layout.modify' => [
                    'feature' => 'layout.modify',
                    'group' => 'layout-design',
                    'title' => __('Modify existing Layouts')
                ],
                'layout.export' => [
                    'feature' => 'layout.export',
                    'group' => 'layout-design',
                    'title' => __('Export Layouts')
                ],
                'campaign.view' => [
                    'feature' => 'campaign.view',
                    'group' => 'campaigns',
                    'title' => __('View Campaigns')
                ],
                'campaign.add' => [
                    'feature' => 'campaign.add',
                    'group' => 'campaigns',
                    'title' => __('Add Campaigns')
                ],
                'campaign.modify' => [
                    'feature' => 'campaign.modify',
                    'group' => 'campaigns',
                    'title' => __('Modify Campaigns')
                ],
                'template.view' => [
                    'feature' => 'template.view',
                    'group' => 'layout-design',
                    'title' => __('View Templates')
                ],
                'template.add' => [
                    'feature' => 'template.add',
                    'group' => 'layout-design',
                    'title' => __('Create new Templates')
                ],
                'template.modify' => [
                    'feature' => 'template.modify',
                    'group' => 'layout-design',
                    'title' => __('Modify existing Templates')
                ],
                'resolution.view' => [
                    'feature' => 'resolution.view',
                    'group' => 'layout-design',
                    'title' => __('View Resolutions')
                ],
                'resolution.add' => [
                    'feature' => 'resolution.add',
                    'group' => 'layout-design',
                    'title' => __('Add Resolutions')
                ],
                'resolution.modify' => [
                    'feature' => 'resolution.modify',
                    'group' => 'layout-design',
                    'title' => __('Modify Resolutions')
                ],
                'tag.view' => [
                    'feature' => 'tag.view',
                    'group' => 'tagging',
                    'title' => __('Tag Management')
                ],
                'tag.tagging' => [
                    'feature' => 'tag.tagging',
                    'group' => 'tagging',
                    'title' => __('Add/Edit tags on items')
                ],
                'playlist.add' => [
                    'feature' => 'playlist.add',
                    'group' => 'playlist-design',
                    'title' => __('Create new Playlists independent of a Layout')
                ],
                'playlist.modify' => [
                    'feature' => 'playlist.modify',
                    'group' => 'playlist-design',
                    'title' => __('Modify existing Playlists independent of a Layouts')
                ],
                'user.profile' => [
                    'feature' => 'user.profile',
                    'group' => 'users',
                    'title' => __('Update their profile, including password and authentication preferences.')
                ],
                'drawer' => [
                    'feature' => 'drawer',
                    'group' => 'users',
                    'title' => __('Get Notifications appear in the navigation bar')
                ],
                'notification.centre' => [
                    'feature' => 'notification.centre',
                    'group' => 'users',
                    'title' => __('Access the Notification Centre to read old notifications')
                ],
                'application.view' => [
                    'feature' => 'application.view',
                    'group' => 'users',
                    'title' => __('Access to API applications')
                ],
                'notification.add' => [
                    'feature' => 'notification.add',
                    'group' => 'notifications',
                    'title' => __('Add new Notifications')
                ],
                'notification.modify' => [
                    'feature' => 'notification.modify',
                    'group' => 'notifications',
                    'title' => __('Edit Notifications')
                ],
                'users.view' => [
                    'feature' => 'users.view',
                    'group' => 'users-management',
                    'title' => __('View Users')
                ],
                'users.add' => [
                    'feature' => 'users.add',
                    'group' => 'users-management',
                    'title' => __('Add Users')
                ],
                'users.modify' => [
                    'feature' => 'users.modify',
                    'group' => 'users-management',
                    'title' => __('Modify Users')
                ],
                'usergroup.view' => [
                    'feature' => 'usergroup.view',
                    'group' => 'users-management',
                    'title' => __('View User Groups')
                ],
                'usergroup.add' => [
                    'feature' => 'usergroup.add',
                    'group' => 'users-management',
                    'title' => __('Add User Groups')
                ],
                'usergroup.modify' => [
                    'feature' => 'usergroup.modify',
                    'group' => 'users-management',
                    'title' => __('Modify User Groups')
                ],
                'dashboard.status' => [
                    'feature' => 'dashboard.status',
                    'group' => 'dashboards',
                    'title' => __('Status Dashboard showing key platform metrics, usually for an administrator.')
                ],
                'dashboard.media.manager' => [
                    'feature' => 'dashboard.media.manager',
                    'group' => 'dashboards',
                    'title' => __('Media Manager Dashboard showing all Widgets the user has access to modify.')
                ],
                'dashboard.playlist' => [
                    'feature' => 'dashboard.playlist',
                    'group' => 'dashboards',
                    'title' => __('Playlist Dashboard showing all Playlists configured in Layouts the user has access to modify.')
                ],
                'displays.view' => [
                    'feature' => 'displays.view',
                    'group' => 'displays',
                    'title' => __('View Displays')
                ],
                'displays.add' => [
                    'feature' => 'displays.add',
                    'group' => 'displays',
                    'title' => __('Add Displays')
                ],
                'displays.modify' => [
                    'feature' => 'displays.modify',
                    'group' => 'displays',
                    'title' => __('Modify Displays')
                ],
                'displaygroup.view' => [
                    'feature' => 'displaygroup.view',
                    'group' => 'displays',
                    'title' => __('View Display Groups')
                ],
                'displaygroup.add' => [
                    'feature' => 'displaygroup.add',
                    'group' => 'displays',
                    'title' => __('Add Display Groups')
                ],
                'displaygroup.modify' => [
                    'feature' => 'displaygroup.modify',
                    'group' => 'displays',
                    'title' => __('Modify Display Groups')
                ],
                'displayprofile.view' => [
                    'feature' => 'displayprofile.view',
                    'group' => 'displays',
                    'title' => __('View Display Settings Profiles')
                ],
                'displayprofile.add' => [
                    'feature' => 'displayprofile.add',
                    'group' => 'displays',
                    'title' => __('Add Display Settings Profiles')
                ],
                'displayprofile.modify' => [
                    'feature' => 'displayprofile.modify',
                    'group' => 'displays',
                    'title' => __('Modify Display Settings Profiles')
                ],
                'playersoftware.view' => [
                    'feature' => 'playersoftware.view',
                    'group' => 'displays',
                    'title' => __('View Player Software Versions')
                ],
                'command.view' => [
                    'feature' => 'command.view',
                    'group' => 'displays',
                    'title' => __('View Commands')
                ],
                'fault.view' => [
                    'feature' => 'fault.view',
                    'group' => 'troubleshooting',
                    'title' => __('Report Fault wizard for collection a report to send to the support team for analysis. (may contain sensitive data)')
                ],
                'log.view' => [
                    'feature' => 'log.view',
                    'group' => 'troubleshooting',
                    'title' => __('Debug and Error logging (may contain sensitive data)')
                ],
                'session.view' => [
                    'feature' => 'session.view',
                    'group' => 'troubleshooting',
                    'title' => __('User sessions')
                ],
                'auditlog.view' => [
                    'feature' => 'auditlog.view',
                    'group' => 'troubleshooting',
                    'title' => __('Audit trail of created/modified and removed items.')
                ],
                'module.view' => [
                    'feature' => 'module.view',
                    'group' => 'system',
                    'title' => __('Module management')
                ],
                'transition.view' => [
                    'feature' => 'transition.view',
                    'group' => 'system',
                    'title' => __('Transition management')
                ],
                'task.view' => [
                    'feature' => 'task.view',
                    'group' => 'system',
                    'title' => __('Task management')
                ],
                'help.view' => [
                    'feature' => 'help.view',
                    'group' => 'system',
                    'title' => __('Help Link Management')
                ],
                'report.view' => [
                    'feature' => 'report.view',
                    'group' => 'reporting',
                    'title' => __('Reporting')
                ],
                'report.scheduling' => [
                    'feature' => 'report.scheduling',
                    'group' => 'reporting',
                    'title' => __('Scheduled Reports')
                ],
                'report.saving' => [
                    'feature' => 'report.saving',
                    'group' => 'reporting',
                    'title' => __('Saved Reports')
                ],
                'displays.reporting' => [
                    'feature' => 'displays.reporting',
                    'group' => 'reporting',
                    'title' => __('Display Reporting which shows bandwidth usage and time connected.')
                ],
                'proof-of-play' => [
                    'feature' => 'proof-of-play',
                    'group' => 'reporting',
                    'title' => __('Proof of Play reporting, summary and distribution')
                ],
            ];
        }
        return $this->features;
    }

    /**
     * @param string $homepage The home page id
     * @return array|mixed
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getHomepageByName(string $homepage)
    {
        $homepages = $this->getHomepages();

        if (!array_key_exists($homepage, $homepages)) {
            throw new NotFoundException(sprintf(__('Homepage %s not found.'), $homepage));
        }

        return $homepages[$homepage];
    }

    /**
     * @return \Xibo\Entity\Homepage[]
     */
    public function getHomepages()
    {
        if ($this->homepages === null) {
            $this->homepages = [
                'statusdashboard.view' => new Homepage(
                    'statusdashboard.view',
                    'dashboard.status',
                    __('Status Dashboard'),
                    __('Status Dashboard showing key platform metrics, usually for an administrator.')
                ),
                'icondashboard.view' => new Homepage(
                    'icondashboard.view',
                    '',
                    __('Icon Dashboard'),
                    __('Icon Dashboard showing an easy access set of feature icons the user can access.')
                ),
                'mediamanager.view' => new Homepage(
                    'mediamanager.view',
                    'dashboard.media.manager',
                    __('Media Manager Dashboard'),
                    __('Media Manager Dashboard showing all Widgets the user has access to modify.')
                ),
                'playlistdashboard.view' => new Homepage(
                    'playlistdashboard.view',
                    'dashboard.playlist',
                    __('Playlist Dashboard'),
                    __('Playlist Dashboard showing all Playlists configured in Layouts the user has access to modify.')
                ),
            ];
        }

        return $this->homepages;
    }

    /**
     * @param string $feature
     * @param string $title
     * @return $this
     */
    public function registerCustomFeature(string $feature, string $title)
    {
        $this->getFeatures();

        if (!array_key_exists($feature, $this->features)) {
            $this->features[$feature] = [
                'feature' => $feature,
                'group' => 'custom',
                'title' => $title
            ];
        }
        return $this;
    }

    /**
     * @param string $homepage
     * @param string $title
     * @param string $description
     * @param string $feature
     * @return $this
     */
    public function registerCustomHomepage(string $homepage, string $title, string $description, string $feature)
    {
        $this->getHomepages();

        if (!array_key_exists($homepage, $this->homepages)) {
            $this->homepages[$homepage] = new Homepage(
                $homepage,
                $title,
                $description,
                $feature
            );
        }
        return $this;
    }
}