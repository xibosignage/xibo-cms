<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserGroup.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;
use Respect\Validation\Validator as v;

class UserGroup
{
    use EntityTrait;

    public $groupId;
    public $group;
    public $isUserSpecific;
    public $isEveryone;

    private $users;

    public function __construct()
    {
        $this->users = [];
        $this->isEveryone = 0;
    }

    public function __toString()
    {
        return sprintf('ID = %d, Group = %s, IsUserSpecific = %d', $this->groupId, $this->group, $this->isUserSpecific);
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
        if (!in_array($userId, $this->users))
            $this->users[] = $userId;
    }

    /**
     * Unassign User
     * @param int $userId
     */
    public function unassignUser($userId)
    {
        unset($this->users[$userId]);
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (!v::string()->length(1, 50)->validate($this->group))
            throw new \InvalidArgumentException(__('User Group Name cannot be empty.') . $this);
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
        else
            $this->edit();

        $this->linkUsers();
    }

    public function delete()
    {
        //TODO: delete
        $this->unlinkUsers();
    }

    /**
     * Add
     */
    private function add()
    {
        $this->groupId = PDOConnect::insert('INSERT INTO `group` (`group`, IsUserSpecific) VALUES (:group, :isUserSpecific)', [
            'group' => $this->group,
            'isUserSpecific' => $this->isUserSpecific
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        PDOConnect::update('UPDATE `group` SET `group` = :group WHERE groupId = :groupId', [
            'groupId' => $this->groupId,
            'group' => $this->group
        ]);
    }

    /**
     * Link Users
     */
    private function linkUsers()
    {
        $insert = PDOConnect::init()->prepare('INSERT INTO `lkusergroup` (groupId, userId) VALUES (:groupId, :userId)');

        foreach ($this->users as $userId) {
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
        PDOConnect::update('DELETE FROM `lkusergroup` WHERE groupId = :groupId', ['groupId' => $this->groupId]);
    }
}