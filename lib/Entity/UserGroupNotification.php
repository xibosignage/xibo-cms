<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (UserGroupNotification.php)
 */


namespace Xibo\Entity;

/**
 * Class UserGroupNotification
 * @package Xibo\Entity
 */
class UserGroupNotification implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="The User Group Notification Id"
     * )
     * @var int
     */
    public $userGroupNotificationId;

    /**
     * @SWG\Property(
     *  description="The User Group Id"
     * )
     * @var int
     */
    public $userGroupId;

    /**
     * @SWG\Property(
     *  description="The Notification Id"
     * )
     * @var int
     */
    public $notificationId;

    /**
     * @SWG\Property(
     *  description="Read Date expressed as Unix Timestamp"
     * )
     * @var int
     */
    public $readDt;
}