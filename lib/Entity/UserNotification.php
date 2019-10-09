<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (UserGroupNotification.php)
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
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * Set Read
     * @param int $readDt
     */
    public function setRead($readDt)
    {
        $this->read = 1;

        if ($this->readDt == 0)
            $this->readDt = $readDt;
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
        if ($this->emailDt == 0)
            $this->emailDt = $emailDt;
    }

    /**
     * Save
     */
    public function save()
    {
        $this->getStore()->update('UPDATE `lknotificationuser` SET `read` = :read, readDt = :readDt, emailDt = :emailDt WHERE notificationId = :notificationId AND userId = :userId', [
            'read' => $this->read,
            'readDt' => $this->readDt,
            'emailDt' => $this->emailDt,
            'notificationId' => $this->notificationId,
            'userId' => $this->userId
        ]);
    }
}