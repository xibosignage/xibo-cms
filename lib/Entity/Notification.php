<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Notification.php)
 */


namespace Xibo\Entity;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\UserGroupNotificationFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Notification
 * @package Xibo\Entity
 */
class Notification implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="The Notifcation ID"
     * )
     * @var int
     */
    public $notificationId;

    /**
     * @SWG\Property(
     *  description="Create Date as Unix Timestamp"
     * )
     * @var int
     */
    public $createdDt;

    /**
     * @SWG\Property(
     *  description="Release Date as Unix Timestamp"
     * )
     * @var int
     */
    public $releaseDt;

    /**
     * @SWG\Property(
     *  description="The subject line"
     * )
     * @var string
     */
    public $subject;

    /**
     * @SWG\Property(
     *  description="The HTML body of the notification"
     * )
     * @var string
     */
    public $body;

    /**
     * @SWG\Property(
     *  description="Should the notification be emailed"
     * )
     * @var int
     */
    public $isEmail;

    /**
     * @SWG\Property(
     *  description="Should the notification interrupt the CMS UI on navigate/login"
     * )
     * @var int
     */
    public $isInterrupt;

    /**
     * @SWG\Property(
     *  description="The Owner User Id"
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(
     *  description="User Group Notifications associated with this notification"
     * )
     * @var array[UserGroupNotification]
     */
    public $userGroupNotifications = [];

    /**
     * @SWG\Property(
     *  description="Display Groups associated with this notification"
     * )
     * @var DisplayGroup[]
     */
    public $displayGroups = [];

    /** @var  UserGroupNotificationFactory */
    private $userGroupNotificationFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param UserGroupNotificationFactory $userGroupNotificationFactory
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($store, $log, $userGroupNotificationFactory, $displayGroupFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->userGroupNotificationFactory = $userGroupNotificationFactory;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->notificationId;
    }

    /**
     * Get Owner
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Add User Group Notification
     * @param UserGroupNotification $userGroupNotification
     */
    public function addUserGroupNotification($userGroupNotification)
    {
        $this->load();

        $this->userGroupNotifications = $userGroupNotification;
    }

    /**
     * Add Display Group
     * @param DisplayGroup $displayGroup
     */
    public function addDisplayGroup($displayGroup)
    {
        $this->load();

        $this->displayGroups[] = $displayGroup;
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (empty($this->subject))
            throw new \InvalidArgumentException(__('Please provide a subject'));

        if (empty($this->body))
            throw new \InvalidArgumentException(__('Please provide a body'));
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->loaded || $this->notificationId == null)
            return;

        // Load the Display Groups and User Group Notifications



        $this->loaded = true;
    }

    /**
     * Save Notification
     */
    public function save()
    {
        $this->validate();

        if ($this->notificationId == null)
            $this->add();
        else
            $this->edit();

        $this->manageAssignments();
    }

    /**
     * Delete Notification
     */
    public function delete()
    {
        // Remove all links
        $this->getStore()->update('DELETE FROM `lknotificationgroup` WHERE `notificationId` = :notificationId', ['notificationId' => $this->notificationId]);

        $this->getStore()->update('DELETE FROM `lknotificationdg` WHERE `notificationId` = :notificationId', ['notificationId' => $this->notificationId]);

        // Remove the notification
        $this->getStore()->update('DELETE FROM `notification` WHERE `notificationId` = :notificationId', ['notificationId' => $this->notificationId]);
    }

    /**
     * Add to DB
     */
    private function add()
    {
        $this->notificationId = $this->getStore()->insert('
            INSERT INTO `notification`
              (`subject`, `body`, `createDt`, `releaseDt`, `isEmail`, `isInterrupt`, `userId`)
              VALUES (:subject, :body, :createDt, :releaseDt, :isEmail, :isInterrupt, :userId)
        ', [
            'subject' => $this->subject,
            'body' => $this->body,
            'createDt' => $this->createdDt,
            'releaseDt' => $this->releaseDt,
            'isEmail' => $this->isEmail,
            'isInterrupt' => $this->isInterrupt,
            'userId' => $this->userId
        ]);
    }

    /**
     * Update in DB
     */
    private function edit()
    {
        $this->getStore()->update('
            UPDATE `notification` SET `subject` = :subject,
                `body` = :body,
                `createDt` = :createDt,
                `releaseDt` = :releaseDt,
                `isEmail` = :isEmail,
                `isInterrupt` = :isInterrupt,
                `userId` = :userId
              WHERE `notificationId` = :notificationId
        ', [
            'subject' => $this->subject,
            'body' => $this->body,
            'createDt' => $this->createdDt,
            'releaseDt' => $this->releaseDt,
            'isEmail' => $this->isEmail,
            'isInterrupt' => $this->isInterrupt,
            'userId' => $this->userId,
            'notificationId' => $this->notificationId
        ]);
    }

    /**
     * Manage assignements in DB
     */
    private function manageAssignments()
    {
        $this->linkUserGroups();
        $this->unlinkUserGroups();

        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();
    }

    private function linkUserGroups()
    {
        foreach ($this->userGroupNotifications as $userGroup) {
            /* @var UserGroupNotification $userGroup */
            $this->getStore()->update('INSERT INTO `lknotificationgroup` (notificationId, groupId) VALUES (:notificationId, :userGroupId) ON DUPLICATE KEY UPDATE groupId = groupId', [
                'notificationId' => $this->notificationId,
                'userGroupId' => $userGroup->userGroupId
            ]);
        }
    }

    private function unlinkUserGroups()
    {
        // Unlink any userGroup that is NOT in the collection
        $params = ['notificationId' => $this->notificationId];

        $sql = 'DELETE FROM `lknotificationgroup` WHERE notificationId = :notificationId AND groupId NOT IN (0';

        $i = 0;
        foreach ($this->userGroupNotifications as $userGroup) {
            /* @var UserGroupNotification $userGroup */
            $i++;
            $sql .= ',:userGroupId' . $i;
            $params['userGroupId' . $i] = $userGroup->userGroupId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    private function linkDisplayGroups()
    {
        foreach ($this->displayGroups as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            $this->getStore()->update('INSERT INTO `lknotificationdg` (notificationId, displayGroupId) VALUES (:notificationId, :displayGroupId) ON DUPLICATE KEY UPDATE displayGroupId = displayGroupId', [
                'notificationId' => $this->notificationId,
                'displayGroupId' => $displayGroup->displayGroupId
            ]);
        }
    }

    private function unlinkDisplayGroups()
    {
        // Unlink any displayGroup that is NOT in the collection
        $params = ['notificationId' => $this->notificationId];

        $sql = 'DELETE FROM `lknotificationdg` WHERE notificationId = :notificationId AND displayGroupId NOT IN (0';

        $i = 0;
        foreach ($this->displayGroups as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            $i++;
            $sql .= ',:displayGroupId' . $i;
            $params['displayGroupId' . $i] = $displayGroup->displayGroupId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }
}