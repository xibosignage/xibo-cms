<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Notification.php)
 */


namespace Xibo\Entity;

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
     *  description="User Group Notifications associated with this notification"
     * )
     * @var int
     */
    public $userGroupNotifications = [];

    /**
     * @SWG\Property(
     *  description="Display Groups associated with this notification"
     * )
     * @var DisplayGroup[]
     */
    public $displayGroups = [];
}