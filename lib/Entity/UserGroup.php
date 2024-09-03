<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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


namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class UserGroup
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class UserGroup
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Group ID")
     * @var int
     */
    public $groupId;

    /**
     * @SWG\Property(description="The group name")
     * @var string
     */
    public $group;

    /**
     * @SWG\Property(description="A flag indicating whether this is a user specific group or not")
     * @var int
     */
    public $isUserSpecific = 0;

    /**
     * @SWG\Property(description="A flag indicating the special everyone group")
     * @var int
     */
    public $isEveryone = 0;

    /**
     * @SWG\Property(description="Description of this User Group")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="This users library quota in bytes. 0 = unlimited")
     * @var int
     */
    public $libraryQuota;

    /**
     * @SWG\Property(description="Does this Group receive system notifications.")
     * @var int
     */
    public $isSystemNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive display notifications.")
     * @var int
     */
    public $isDisplayNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive DataSet notifications.")
     * @var int
     */
    public $isDataSetNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive Layout notifications.")
     * @var int
     */
    public $isLayoutNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive Library notifications.")
     * @var int
     */
    public $isLibraryNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive Report notifications.")
     * @var int
     */
    public $isReportNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive Schedule notifications.")
     * @var int
     */
    public $isScheduleNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive Custom notifications.")
     * @var int
     */
    public $isCustomNotification = 0;

    /**
     * @SWG\Property(description="Is this Group shown in the list of choices when onboarding a new user")
     * @var int
     */
    public $isShownForAddUser = 0;

    /**
     * @SWG\Property(description="Default Home page for new users")
     * @var string
     */
    public $defaultHomepageId;

    /**
     * @SWG\Property(description="Features this User Group has direct access to", @SWG\Items(type="string"))
     * @var array
     */
    public $features = [];

    // Users
    private $users = [];

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    private $assignedUserIds = [];
    private $unassignedUserIds = [];

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param UserGroupFactory $userGroupFactory
     * @param UserFactory $userFactory
     */
    public function __construct($store, $log, $dispatcher, $userGroupFactory, $userFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->userGroupFactory = $userGroupFactory;
        $this->userFactory = $userFactory;
    }

    /**
     *
     */
    public function __clone()
    {
        // Clear the groupId
        $this->groupId = null;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('ID = %d, Group = %s, IsUserSpecific = %d', $this->groupId, $this->group, $this->isUserSpecific);
    }

    /**
     * Generate a unique hash for this User Group
     */
    private function hash()
    {
        return md5(json_encode($this));
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->groupId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return 0;
    }

    /**
     * Set the Owner of this Group
     * @param User $user
     */
    public function setOwner($user)
    {
        $this->load();

        $this->isUserSpecific = 1;
        $this->isEveryone = 0;
        $this->assignUser($user);
    }

    /**
     * Assign User
     * @param User $user
     */
    public function assignUser($user)
    {
        $this->load();

        if (!in_array($user, $this->users)) {
            $this->users[] = $user;
            $this->assignedUserIds[] = $user->userId;
        }
    }

    /**
     * Unassign User
     * @param User $user
     */
    public function unassignUser($user)
    {
        $this->load();
        $this->unassignedUserIds[] = $user->userId;
        $this->users = array_udiff($this->users, [$user], function ($a, $b) {
            /**
             * @var User $a
             * @var User $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (!v::stringType()->length(1, 50)->validate($this->group)) {
            throw new InvalidArgumentException(__('User Group Name cannot be empty.') . $this, 'name');
        }

        if ($this->libraryQuota !== null && !v::intType()->validate($this->libraryQuota)) {
            throw new InvalidArgumentException(__('Library Quota must be a whole number.'), 'libraryQuota');
        }

        try {
            $group = $this->userGroupFactory->getByName($this->group, $this->isUserSpecific);

            if ($this->groupId == null || $this->groupId != $group->groupId) {
                throw new DuplicateEntityException(
                    __('There is already a group with this name. Please choose another.')
                );
            }
        } catch (NotFoundException $e) {

        }
    }

    /**
     * Load this User Group
     * @param array $options
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadUsers' => true
        ], $options);

        if ($this->loaded || $this->groupId == 0) {
            return;
        }

        if ($options['loadUsers']) {
            // Load all assigned users
            $this->users = $this->userFactory->getByGroupId($this->groupId);
        }

        // Set the hash
        $this->hash = $this->hash();
        $this->loaded = true;
    }

    /**
     * Save the group
     * @param array $options
     * @throws DuplicateEntityException
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'linkUsers' => true
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->groupId == null || $this->groupId == 0) {
            $this->add();
            $this->audit($this->groupId, 'User Group added', ['group' => $this->group]);
        } else if ($this->hash() != $this->hash) {
            $this->edit();
            $this->audit($this->groupId, 'User Group edited');
        }

        if ($options['linkUsers']) {
            $this->linkUsers();
            $this->unlinkUsers();

            if (count($this->assignedUserIds) > 0) {
                $this->audit($this->groupId, 'Users assigned', ['userIds' => implode(',', $this->assignedUserIds)]);
            }

            if (count($this->unassignedUserIds) > 0) {
                $this->audit($this->groupId, 'Users unassigned', ['userIds' => implode(',', $this->unassignedUserIds)]);
            }
        }
    }

    /**
     * Save features
     * @return $this
     */
    public function saveFeatures()
    {
        $this->getStore()->update('
            UPDATE `group` SET features = :features WHERE groupId = :groupId
        ', [
            'groupId' => $this->groupId,
            'features' => json_encode($this->features)
        ]);

        $this->audit($this->groupId, 'User Group feature access modified', [
            'features' => json_encode($this->features)
        ]);

        return $this;
    }

    /**
     * Delete this Group
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if ($this->hash == null) {
            $this->load();
        }

        // Unlink users
        $this->removeAssignments();

        $this->getStore()->update('DELETE FROM `permission` WHERE groupId = :groupId', ['groupId' => $this->groupId]);
        $this->getStore()->update('DELETE FROM `group` WHERE groupId = :groupId', ['groupId' => $this->groupId]);

        $this->audit($this->groupId, 'User group deleted.', false);
    }

    /**
     * Remove all assignments
     */
    private function removeAssignments()
    {
        // Delete Notifications
        // NB: notifications aren't modelled as child objects because there could be many thousands of notifications on each
        // usergroup. We consider the notification to be the parent here and it manages the assignments.
        // This does mean that we might end up with an empty notification (not assigned to anything)
        $this->getStore()->update('DELETE FROM `lknotificationuser` WHERE `userId` IN (SELECT `userId` FROM `lkusergroup` WHERE `groupId` = :groupId) ', ['groupId' => $this->groupId]);
        $this->getStore()->update('DELETE FROM `lknotificationgroup` WHERE `groupId` = :groupId', ['groupId' => $this->groupId]);

        // Remove user assignments
        $this->users = [];
        $this->unlinkUsers();
    }

    /**
     * Add
     */
    private function add()
    {
        $this->groupId = $this->getStore()->insert('
            INSERT INTO `group` (
                 `group`, 
                 `IsUserSpecific`,
                 `description`,
                 `libraryQuota`, 
                 `isSystemNotification`, 
                 `isDisplayNotification`,
                 `isDataSetNotification`,
                 `isLayoutNotification`,
                 `isLibraryNotification`,
                 `isReportNotification`,
                 `isScheduleNotification`,
                 `isCustomNotification`,
                 `isShownForAddUser`,
                 `defaultHomepageId`
              )
              VALUES (
                 :group, 
                 :isUserSpecific, 
                 :description, 
                 :libraryQuota, 
                 :isSystemNotification, 
                 :isDisplayNotification,
                 :isDataSetNotification,
                 :isLayoutNotification,
                 :isLibraryNotification,
                 :isReportNotification,
                 :isScheduleNotification,
                 :isCustomNotification,
                 :isShownForAddUser,
                 :defaultHomepageId
              )
        ', [
            'group' => $this->group,
            'isUserSpecific' => $this->isUserSpecific,
            'description' => $this->description,
            'libraryQuota' => $this->libraryQuota,
            'isSystemNotification' => $this->isSystemNotification,
            'isDisplayNotification' => $this->isDisplayNotification,
            'isDataSetNotification' => $this->isDataSetNotification,
            'isLayoutNotification' => $this->isLayoutNotification,
            'isLibraryNotification' => $this->isLibraryNotification,
            'isReportNotification' => $this->isReportNotification,
            'isScheduleNotification' => $this->isScheduleNotification,
            'isCustomNotification' => $this->isCustomNotification,
            'isShownForAddUser' => $this->isShownForAddUser,
            'defaultHomepageId' => $this->defaultHomepageId
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $this->getStore()->update('
          UPDATE `group` SET 
            `group` = :group, 
            `description` = :description, 
            libraryQuota = :libraryQuota, 
            `isSystemNotification` = :isSystemNotification,
            `isDisplayNotification` = :isDisplayNotification,
            `isDataSetNotification` = :isDataSetNotification,
            `isLayoutNotification` = :isLayoutNotification,
            `isLibraryNotification` = :isLibraryNotification,
            `isReportNotification` = :isReportNotification,
            `isScheduleNotification` = :isScheduleNotification,
            `isCustomNotification` = :isCustomNotification,
            `isShownForAddUser` = :isShownForAddUser,
            `defaultHomepageId` = :defaultHomepageId
           WHERE groupId = :groupId
        ', [
            'groupId' => $this->groupId,
            'group' => $this->group,
            'description' => $this->description,
            'libraryQuota' => $this->libraryQuota,
            'isSystemNotification' => $this->isSystemNotification,
            'isDisplayNotification' => $this->isDisplayNotification,
            'isDataSetNotification' => $this->isDataSetNotification,
            'isLayoutNotification' => $this->isLayoutNotification,
            'isLibraryNotification' => $this->isLibraryNotification,
            'isReportNotification' => $this->isReportNotification,
            'isScheduleNotification' => $this->isScheduleNotification,
            'isCustomNotification' => $this->isCustomNotification,
            'isShownForAddUser' => $this->isShownForAddUser,
            'defaultHomepageId' => $this->defaultHomepageId
        ]);
    }

    /**
     * Link Users
     */
    private function linkUsers()
    {
        $insert = $this->getStore()->getConnection()->prepare(
            'INSERT INTO `lkusergroup` (groupId, userId)
                    VALUES (:groupId, :userId) ON DUPLICATE KEY UPDATE groupId = groupId'
        );

        foreach ($this->users as $user) {
            /* @var User $user */
            $this->getLog()->debug('Linking %s to %s', $user->userName, $this->group);

            $insert->execute([
                'groupId' => $this->groupId,
                'userId' => $user->userId
            ]);
        }
    }

    /**
     * Unlink Users
     */
    private function unlinkUsers()
    {
        $params = ['groupId' => $this->groupId];

        $sql = 'DELETE FROM `lkusergroup` WHERE groupId = :groupId AND userId NOT IN (0';

        $i = 0;
        foreach ($this->users as $user) {
            /* @var User $user */
            $i++;
            $sql .= ',:userId' . $i;
            $params['userId' . $i] = $user->userId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }
}
