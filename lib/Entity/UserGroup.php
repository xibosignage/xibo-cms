<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserGroup.php)
 */


namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\UserGroupFactory;
use Xibo\Storage\PDOConnect;

class UserGroup
{
    use EntityTrait;

    public $groupId;
    public $group;
    public $isUserSpecific = 0;
    public $isEveryone = 0;
    public $libraryQuota;

    // Users
    private $userIds = [];

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
     * @param int $userId
     */
    public function setOwner($userId)
    {
        $this->isUserSpecific = 1;
        $this->isEveryone = 0;
        $this->assignUser($userId);
    }

    /**
     * Assign User
     * @param int $userId
     */
    public function assignUser($userId)
    {
        if (!in_array($userId, $this->userIds))
            $this->userIds[] = $userId;
    }

    /**
     * Unassign User
     * @param int $userId
     */
    public function unassignUser($userId)
    {
        unset($this->userIds[$userId]);
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
     */
    public function load()
    {
        //TODO
        //$this->userIds = UserFactory::getByGroupId

        // Set the hash
        $this->hash = $this->hash();
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
        $insert = PDOConnect::init()->prepare('INSERT INTO `lkusergroup` (groupId, userId) VALUES (:groupId, :userId)');

        foreach ($this->userIds as $userId) {
            $insert->execute([
                'groupId' =>$this->groupId,
                'userId' => $userId
            ]);
        }
    }

    /**
     * Unlink Users
     */
    private function unlinkUsers()
    {
        foreach ($this->userIds as $userId) {
            PDOConnect::update('DELETE FROM `lkusergroup` WHERE groupId = :groupId AND userId = :userId', ['groupId' => $this->groupId, 'userId' => $userId]);
        }
    }
}