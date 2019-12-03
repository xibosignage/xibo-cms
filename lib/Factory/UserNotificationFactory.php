<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (NotificationFactory.php)
 */


namespace Xibo\Factory;

use Xibo\Entity\User;
use Xibo\Entity\UserNotification;
use Xibo\Exception\AccessDeniedException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserGroupNotificationFactory
 * @package Xibo\Factory
 */
class UserNotificationFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
    }

    /**
     * @return UserNotification
     */
    public function createEmpty()
    {
        return new UserNotification($this->getStore(), $this->getLog());
    }

    /**
     * Create User Notification
     * @param $subject
     * @param $body
     * @return UserNotification
     */
    public function create($subject, $body = '')
    {
        $notification = $this->createEmpty();
        $notification->subject = $subject;
        $notification->body = $body;
        $notification->userId = $this->getUser()->userId;
        $notification->releaseDt = time();

        return $notification;
    }

    /**
     * Get by NotificationId
     * @param int $notificationId
     * @return UserNotification
     * @throws AccessDeniedException
     */
    public function getByNotificationId($notificationId)
    {
        $notifications = $this->query(null, ['userId' => $this->getUser()->userId, 'notificationId' => $notificationId]);

        if (count($notifications) <= 0)
            throw new AccessDeniedException();

        return $notifications[0];
    }

    /**
     * Get my notifications
     * @param int $length
     * @return UserNotification[]
     */
    public function getMine($length = 5)
    {
        return $this->query(null, ['userId' => $this->getUser()->userId, 'start' => 0, 'length' => $length]);
    }

    /**
     * Get email notification queue
     * @return UserNotification[]
     */
    public function getEmailQueue()
    {
        return $this->query(null, ['isEmail' => 1, 'isEmailed' => 0]);
    }

    /**
     * Count My Unread
     * @return int
     */
    public function countMyUnread()
    {
        return $this->getStore()->select('
            SELECT COUNT(*) AS Cnt
              FROM `lknotificationuser`
                INNER JOIN `notification`
                ON `notification`.notificationId = `lknotificationuser`.notificationId
             WHERE `lknotificationuser`.`userId` = :userId
              AND `lknotificationuser`.`read` = 0
              AND `notification`.releaseDt < :now
          ', [
            'now' => time(), 'userId' => $this->getUser()->userId
        ])[0]['Cnt'];
    }

    /**
     * @param array[Optional] $sortOrder
     * @param array[Optional] $filterBy
     * @return array[UserNotification]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();

        if ($sortOrder == null)
            $sortOrder = ['releaseDt DESC'];

        $params = ['now' => time()];
        $select = 'SELECT `lknotificationuser`.lknotificationuserId,
            `lknotificationuser`.notificationId,
            `lknotificationuser`.userId,
            `lknotificationuser`.read,
            `lknotificationuser`.readDt,
            `lknotificationuser`.emailDt,
             `notification`.subject,
             `notification`.body,
             `notification`.releaseDt,
             `notification`.isInterrupt,
             `notification`.isSystem,
             `notification`.filename,
             `notification`.originalFileName,
             `notification`.nonusers,
             `user`.email
        ';

        $body = ' FROM `lknotificationuser`
                    INNER JOIN `notification`
                    ON `notification`.notificationId = `lknotificationuser`.notificationId
                    LEFT OUTER JOIN `user`
                    ON `user`.userId = `lknotificationuser`.userId
         ';

        $body .= ' WHERE `notification`.releaseDt < :now ';

        if ($this->getSanitizer()->getInt('notificationId', $filterBy) !== null) {
            $body .= ' AND `lknotificationuser`.notificationId = :notificationId ';
            $params['notificationId'] = $this->getSanitizer()->getInt('notificationId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND `lknotificationuser`.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('read', $filterBy) !== null) {
            $body .= ' AND `lknotificationuser`.read = :read ';
            $params['read'] = $this->getSanitizer()->getInt('read', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isEmail', $filterBy) !== null) {
            $body .= ' AND `notification`.isEmail = :isEmail  ';
            $params['isEmail'] = $this->getSanitizer()->getInt('isEmail', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isEmailed', $filterBy) !== null) {
            if ($this->getSanitizer()->getInt('isEmailed', $filterBy) == 0)
                $body .= ' AND `lknotificationuser`.emailDt = 0 ';
            else
                $body .= ' AND `lknotificationuser`.emailDt <> 0 ';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}