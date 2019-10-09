<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Notification.php)
 */


namespace Xibo\Entity;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Notification
 * @package Xibo\Entity
 *
 * @SWG\Definition()
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
    public $isEmail = 0;

    /**
     * @SWG\Property(
     *  description="Should the notification interrupt the CMS UI on navigate/login"
     * )
     * @var int
     */
    public $isInterrupt = 0;

    /**
     * @SWG\Property(
     *  description="Flag for system notification"
     * )
     * @var int
     */
    public $isSystem = 0;

    /**
     * @SWG\Property(
     *  description="The Owner User Id"
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(
     *  description="Attachment filename"
     * )
     * @var string
     */
    public $filename;

    /**
     * @SWG\Property(
     *  description="Attachment originalFileName"
     * )
     * @var string
     */
    public $originalFileName;

    /**
     * @SWG\Property(
     *  description="Additional email addresses to which a saved report will be sent"
     * )
     * @var string
     */
    public $nonusers;

    /**
     * @SWG\Property(
     *  description="User Group Notifications associated with this notification"
     * )
     * @var UserGroup[]
     */
    public $userGroups = [];

    /**
     * @SWG\Property(
     *  description="Display Groups associated with this notification"
     * )
     * @var DisplayGroup[]
     */
    public $displayGroups = [];

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($store, $log, $userGroupFactory, $displayGroupFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->userGroupFactory = $userGroupFactory;
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
     * @param UserGroup $userGroup
     */
    public function assignUserGroup($userGroup)
    {
        $this->load();

        if (!in_array($userGroup, $this->userGroups))
            $this->userGroups[] = $userGroup;
    }

    /**
     * Add Display Group
     * @param DisplayGroup $displayGroup
     */
    public function assignDisplayGroup($displayGroup)
    {
        $this->load();

        if (!in_array($displayGroup, $this->displayGroups))
            $this->displayGroups[] = $displayGroup;
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (empty($this->subject))
            throw new InvalidArgumentException(__('Please provide a subject'), 'subject');

        if (empty($this->body))
            throw new InvalidArgumentException(__('Please provide a body'), 'body');
    }

    /**
     * Load
     * @param array $options
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadUserGroups' => true,
            'loadDisplayGroups' => true,
        ], $options);

        if ($this->loaded || $this->notificationId == null)
            return;

        // Load the Display Groups and User Group Notifications
        if ($options['loadUserGroups'])
            $this->userGroups = $this->userGroupFactory->getByNotificationId($this->notificationId);

        if ($options['loadDisplayGroups'])
            $this->displayGroups = $this->displayGroupFactory->getByNotificationId($this->notificationId);

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
        $this->getStore()->update('DELETE FROM `lknotificationuser` WHERE `notificationId` = :notificationId', ['notificationId' => $this->notificationId]);

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
              (`subject`, `body`, `createDt`, `releaseDt`, `isEmail`, `isInterrupt`, `isSystem`, `userId`, `filename`, `originalFileName`, `nonusers`)
              VALUES (:subject, :body, :createDt, :releaseDt, :isEmail, :isInterrupt, :isSystem, :userId, :filename, :originalFileName, :nonusers)
        ', [
            'subject' => $this->subject,
            'body' => $this->body,
            'createDt' => $this->createdDt,
            'releaseDt' => $this->releaseDt,
            'isEmail' => $this->isEmail,
            'isInterrupt' => $this->isInterrupt,
            'isSystem' => $this->isSystem,
            'userId' => $this->userId,
            'filename' => $this->filename,
            'originalFileName' => $this->originalFileName,
            'nonusers' => $this->nonusers
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
                `isSystem` = :isSystem,
                `userId` = :userId,
                `filename` = :filename,
                `originalFileName` = :originalFileName,
                `nonusers` = :nonusers
              WHERE `notificationId` = :notificationId
        ', [
            'subject' => $this->subject,
            'body' => $this->body,
            'createDt' => $this->createdDt,
            'releaseDt' => $this->releaseDt,
            'isEmail' => $this->isEmail,
            'isInterrupt' => $this->isInterrupt,
            'isSystem' => $this->isSystem,
            'userId' => $this->userId,
            'filename' => $this->filename,
            'originalFileName' => $this->originalFileName,
            'nonusers' => $this->nonusers,
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

        $this->manageRealisedUserLinks();
    }

    /**
     * Manage the links in the User notification table
     */
    private function manageRealisedUserLinks()
    {
        // Delete links that no longer exist
        $this->getStore()->update('
            DELETE FROM `lknotificationuser`
             WHERE `notificationId` = :notificationId AND `userId` NOT IN (
                SELECT `userId`
                  FROM `lkusergroup`
                    INNER JOIN `lknotificationgroup`
                    ON `lknotificationgroup`.groupId = `lkusergroup`.groupId
                 WHERE `lknotificationgroup`.notificationId = :notificationId2
              ) AND userId <> 0
        ', [
            'notificationId' => $this->notificationId,
            'notificationId2' => $this->notificationId
        ]);

        // Pop in new links following from this adjustment
        $this->getStore()->update('
            INSERT INTO `lknotificationuser` (`notificationId`, `userId`, `read`, `readDt`, `emailDt`)
            SELECT DISTINCT :notificationId, `userId`, 0, 0, 0
              FROM `lkusergroup`
                INNER JOIN `lknotificationgroup`
                ON `lknotificationgroup`.groupId = `lkusergroup`.groupId
             WHERE `lknotificationgroup`.notificationId = :notificationId2
            ON DUPLICATE KEY UPDATE userId = `lknotificationuser`.userId
        ', [
            'notificationId' => $this->notificationId,
            'notificationId2' => $this->notificationId
        ]);

        if ($this->isSystem) {
            $this->getStore()->insert('
            INSERT INTO `lknotificationuser` (`notificationId`, `userId`, `read`, `readDt`, `emailDt`)
              VALUES (:notificationId, :userId, 0, 0, 0)
              ON DUPLICATE KEY UPDATE userId = `lknotificationuser`.userId
            ', [
                'notificationId' => $this->notificationId,
                'userId' => $this->userId
            ]);
        }
    }

    /**
     * Link User Groups
     */
    private function linkUserGroups()
    {
        foreach ($this->userGroups as $userGroup) {
            /* @var UserGroup $userGroup */
            $this->getStore()->update('INSERT INTO `lknotificationgroup` (notificationId, groupId) VALUES (:notificationId, :userGroupId) ON DUPLICATE KEY UPDATE groupId = groupId', [
                'notificationId' => $this->notificationId,
                'userGroupId' => $userGroup->groupId
            ]);
        }
    }

    /**
     * Unlink User Groups
     */
    private function unlinkUserGroups()
    {
        // Unlink any userGroup that is NOT in the collection
        $params = ['notificationId' => $this->notificationId];

        $sql = 'DELETE FROM `lknotificationgroup` WHERE notificationId = :notificationId AND groupId NOT IN (0';

        $i = 0;
        foreach ($this->userGroups as $userGroup) {
            /* @var UserGroup $userGroup */
            $i++;
            $sql .= ',:userGroupId' . $i;
            $params['userGroupId' . $i] = $userGroup->groupId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    /**
     * Link Display Groups
     */
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

    /**
     * Unlink Display Groups
     */
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