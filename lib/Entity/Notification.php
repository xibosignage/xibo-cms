<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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


namespace Xibo\Entity;
use OpenApi\Attributes as OA;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Notification
 * @package Xibo\Entity
 */
#[OA\Schema]
class Notification implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Notifcation ID')]
    public $notificationId;

    /**
     * @var int
     */
    #[OA\Property(description: 'Create Date as Unix Timestamp')]
    public $createDt;

    /**
     * @var int
     */
    #[OA\Property(description: 'Release Date as Unix Timestamp')]
    public $releaseDt;

    /**
     * @var string
     */
    #[OA\Property(description: 'The subject line')]
    public $subject;

    /**
     * @var string
     */
    #[OA\Property(description: 'The Notification type')]
    public $type;

    /**
     * @var string
     */
    #[OA\Property(description: 'The HTML body of the notification')]
    public $body;

    /**
     * @var int
     */
    #[OA\Property(description: 'Should the notification interrupt the CMS UI on navigate/login')]
    public $isInterrupt = 0;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag for system notification')]
    public $isSystem = 0;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Owner User Id')]
    public $userId;

    /**
     * @var string
     */
    #[OA\Property(description: 'Attachment filename')]
    public $filename;

    /**
     * @var string
     */
    #[OA\Property(description: 'Attachment originalFileName')]
    public $originalFileName;

    /**
     * @var string
     */
    #[OA\Property(description: 'Additional email addresses to which a saved report will be sent')]
    public $nonusers;

    /**
     * @var UserGroup[]
     */
    #[OA\Property(description: 'User Group Notifications associated with this notification')]
    public $userGroups = [];

    /**
     * @var DisplayGroup[]
     */
    #[OA\Property(description: 'Display Groups associated with this notification')]
    public $displayGroups = [];

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($store, $log, $dispatcher, $userGroupFactory, $displayGroupFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function assignUserGroup($userGroup)
    {
        $this->load();

        if (!in_array($userGroup, $this->userGroups)) {
            $this->userGroups[] = $userGroup;
        }
    }

    /**
     * Add Display Group
     * @param DisplayGroup $displayGroup
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function assignDisplayGroup($displayGroup)
    {
        $this->load();

        if (!in_array($displayGroup, $this->displayGroups)) {
            $this->displayGroups[] = $displayGroup;
        }
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (empty($this->subject)) {
            throw new InvalidArgumentException(__('Please provide a subject'), 'subject');
        }

        if (empty($this->body)) {
            throw new InvalidArgumentException(__('Please provide a body'), 'body');
        }
    }

    /**
     * Load
     * @param array $options
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadUserGroups' => true,
            'loadDisplayGroups' => true,
        ], $options);

        if ($this->loaded || $this->notificationId == null) {
            return;
        }

        // Load the Display Groups and User Group Notifications
        if ($options['loadUserGroups']) {
            $this->userGroups = $this->userGroupFactory->getByNotificationId($this->notificationId);
        }

        if ($options['loadDisplayGroups']) {
            $this->displayGroups = $this->displayGroupFactory->getByNotificationId($this->notificationId);
        }

        $this->loaded = true;
    }

    /**
     * Save Notification
     * @throws InvalidArgumentException
     */
    public function save(): void
    {
        $this->validate();

        $isNewRecord = false;
        if ($this->notificationId == null) {
            $isNewRecord = true;
            $this->add();
        } else {
            $this->edit();
        }

        $this->manageAssignments($isNewRecord);
    }

    /**
     * Delete Notification
     */
    public function delete()
    {
        // Remove all links
        $this->getStore()->update(
            'DELETE FROM `lknotificationuser` WHERE `notificationId` = :notificationId',
            ['notificationId' => $this->notificationId]
        );

        $this->getStore()->update(
            'DELETE FROM `lknotificationgroup` WHERE `notificationId` = :notificationId',
            ['notificationId' => $this->notificationId]
        );

        $this->getStore()->update(
            'DELETE FROM `lknotificationdg` WHERE `notificationId` = :notificationId',
            ['notificationId' => $this->notificationId]
        );

        // Remove the notification
        $this->getStore()->update(
            'DELETE FROM `notification` WHERE `notificationId` = :notificationId',
            ['notificationId' => $this->notificationId]
        );
    }

    /**
     * Add to DB
     */
    private function add()
    {
        $this->notificationId = $this->getStore()->insert('
            INSERT INTO `notification` (
                `subject`,
                `body`,
                `createDt`,
                `releaseDt`,
                `isInterrupt`,
                `isSystem`,
                `userId`,
                `filename`,
                `originalFileName`,
                `nonusers`,
                `type`
              )
              VALUES (
                :subject,
                :body,
                :createDt,
                :releaseDt,
                :isInterrupt,
                :isSystem,
                :userId,
                :filename,
                :originalFileName,
                :nonusers,
                :type
              )
        ', [
            'subject' => $this->subject,
            'body' => $this->body,
            'createDt' => $this->createDt,
            'releaseDt' => $this->releaseDt,
            'isInterrupt' => $this->isInterrupt,
            'isSystem' => $this->isSystem,
            'userId' => $this->userId,
            'filename' => $this->filename,
            'originalFileName' => $this->originalFileName,
            'nonusers' => $this->nonusers,
            'type' => $this->type ?? 'custom'
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
                `isInterrupt` = :isInterrupt,
                `isSystem` = :isSystem,
                `userId` = :userId,
                `filename` = :filename,
                `originalFileName` = :originalFileName,
                `nonusers` = :nonusers,
                `type` = :type
              WHERE `notificationId` = :notificationId
        ', [
            'subject' => $this->subject,
            'body' => $this->body,
            'createDt' => $this->createDt,
            'releaseDt' => $this->releaseDt,
            'isInterrupt' => $this->isInterrupt,
            'isSystem' => $this->isSystem,
            'userId' => $this->userId,
            'filename' => $this->filename,
            'originalFileName' => $this->originalFileName,
            'nonusers' => $this->nonusers,
            'type' => $this->type ?? 'custom',
            'notificationId' => $this->notificationId
        ]);
    }

    /**
     * Manage assignements in DB
     */
    private function manageAssignments(bool $isNewRecord): void
    {
        $this->linkUserGroups();

        // Only unlink if we're not new (otherwise there is no point as we can't have any links yet)
        if (!$isNewRecord) {
            $this->unlinkUserGroups();
        }

        $this->linkDisplayGroups();

        if (!$isNewRecord) {
            $this->unlinkDisplayGroups();
        }

        $this->manageRealisedUserLinks();
    }

    /**
     * Manage the links in the User notification table
     */
    private function manageRealisedUserLinks(bool $isNewRecord = false): void
    {
        if (!$isNewRecord) {
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
        }

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
