<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserGroup.php)
 */


namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

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
     * @SWG\Property(description="This users library quota in bytes. 0 = unlimited")
     * @var int
     */
    public $libraryQuota;

    // Users
    private $users = [];

    public function __clone()
    {
        // Clear the groupId
        $this->groupId = null;
    }

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

    public function getId()
    {
        return $this->groupId;
    }

    public function getOwnerId()
    {
        return 1;
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

        if (!in_array($user, $this->users))
            $this->users[] = $user;
    }

    /**
     * Unassign User
     * @param User $user
     */
    public function unassignUser($user)
    {
        $this->load();

        $this->users = array_udiff($this->users, [$user], function($a, $b) {
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
        if (!v::string()->length(1, 50)->validate($this->group))
            throw new \InvalidArgumentException(__('User Group Name cannot be empty.') . $this);

        if (!v::int()->validate($this->libraryQuota))
            throw new \InvalidArgumentException(__('Library Quota must be a whole number.'));

        try {
            $group = UserGroupFactory::getByName($this->group);

            if ($this->groupId == null || $this->groupId != $group->groupId)
                throw new \InvalidArgumentException(__('There is already a group with this name. Please choose another.'));
        }
        catch (NotFoundException $e) {

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

        if ($this->loaded || $this->groupId == 0)
            return;

        if ($options['loadUsers'])
            // Load all assigned users
            $this->users = UserFactory::getByGroupId($this->groupId);

        // Set the hash
        $this->hash = $this->hash();
        $this->loaded = true;
    }

    /**
     * Save the group
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->groupId == null || $this->groupId == 0)
            $this->add();
        else if ($this->hash() != $this->hash)
            $this->edit();

        $this->linkUsers();
        $this->unlinkUsers();
    }

    /**
     * Delete this Group
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load();

        // Unlink users
        $this->unlinkUsers();

        PDOConnect::update('DELETE FROM `permission` WHERE groupId = :groupId', ['groupId' => $this->groupId]);
        PDOConnect::update('DELETE FROM `group` WHERE groupId = :groupId', ['groupId' => $this->groupId]);
    }

    public function removeAssignments()
    {
        $this->unlinkUsers();
    }

    /**
     * Add
     */
    private function add()
    {
        $this->groupId = PDOConnect::insert('INSERT INTO `group` (`group`, IsUserSpecific, libraryQuota) VALUES (:group, :isUserSpecific, :libraryQuota)', [
            'group' => $this->group,
            'isUserSpecific' => $this->isUserSpecific,
            'libraryQuota' => $this->libraryQuota
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        PDOConnect::update('UPDATE `group` SET `group` = :group, libraryQuota = :libraryQuota WHERE groupId = :groupId', [
            'groupId' => $this->groupId,
            'group' => $this->group,
            'libraryQuota' => $this->libraryQuota
        ]);
    }

    /**
     * Link Users
     */
    private function linkUsers()
    {
        $insert = PDOConnect::init()->prepare('INSERT INTO `lkusergroup` (groupId, userId) VALUES (:groupId, :userId) ON DUPLICATE KEY UPDATE groupId = groupId');

        foreach ($this->users as $user) {
            /* @var User $user */
            Log::debug('Linking %s to %s', $user->userName, $this->group);

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

        Log::sql($sql, $params);

        PDOConnect::update($sql, $params);
    }
}