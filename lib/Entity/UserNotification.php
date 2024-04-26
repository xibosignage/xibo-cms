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
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserGroupNotification
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class UserNotification implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="The User Id"
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(
     *  description="The Notification Id"
     * )
     * @var int
     */
    public $notificationId;

    /**
     * @SWG\Property(
     *  description="Release Date expressed as Unix Timestamp"
     * )
     * @var int
     */
    public $releaseDt;

    /**
     * @SWG\Property(
     *  description="Read Date expressed as Unix Timestamp"
     * )
     * @var int
     */
    public $readDt;

    /**
     * @SWG\Property(
     *  description="Email Date expressed as Unix Timestamp"
     * )
     * @var int
     */
    public $emailDt;

    /**
     * @SWG\Property(
     *  description="A flag indicating whether to show as read or not"
     * )
     * @var int
     */
    public $read;

    /**
     * @SWG\Property(
     *  description="The subject"
     * )
     * @var string
     */
    public $subject;

    /**
     * @SWG\Property(
     *  description="The body"
     * )
     * @var string
     */
    public $body;

    /**
     * @SWG\Property(
     *  description="Should the notification interrupt the CMS UI on navigate/login"
     * )
     * @var int
     */
    public $isInterrupt;

    /**
     * @SWG\Property(
     *  description="Flag for system notification"
     * )
     * @var int
     */
    public $isSystem;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $originalFileName;

    /**
     * @var string
     */
    public $nonusers;

    /**
     * @var string
     */
    public $type;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * Set Read
     * @param int $readDt
     */
    public function setRead($readDt)
    {
        $this->read = 1;

        if ($this->readDt == 0) {
            $this->readDt = $readDt;
        }
    }

    /**
     * Set unread
     */
    public function setUnread()
    {
        $this->read = 0;
    }

    /**
     * Set Emailed
     * @param int $emailDt
     */
    public function setEmailed($emailDt)
    {
        if ($this->emailDt == 0) {
            $this->emailDt = $emailDt;
        }
    }

    /**
     * Save
     */
    public function save()
    {
        $this->getStore()->update('
            UPDATE `lknotificationuser` 
               SET `read` = :read,
                   `readDt` = :readDt,
                   `emailDt` = :emailDt
            WHERE notificationId = :notificationId
                AND userId = :userId
        ', [
            'read' => $this->read,
            'readDt' => $this->readDt,
            'emailDt' => $this->emailDt,
            'notificationId' => $this->notificationId,
            'userId' => $this->userId
        ]);
    }

    /**
     * @return string
     */
    public function getTypeForGroup(): string
    {
        return  match ($this->type) {
            'dataset' => 'isDataSetNotification',
            'display' => 'isDisplayNotification',
            'layout' => 'isLayoutNotification',
            'library' => 'isLibraryNotification',
            'report' => 'isReportNotification',
            'schedule' => 'isScheduleNotification',
            default => 'isCustomNotification',
        };
    }
}