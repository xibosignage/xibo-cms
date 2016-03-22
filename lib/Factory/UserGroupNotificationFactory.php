<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (NotificationFactory.php)
 */


namespace Xibo\Factory;

use Xibo\Entity\Notification;
use Xibo\Entity\User;
use Xibo\Entity\UserGroupNotification;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserGroupNotificationFactory
 * @package Xibo\Factory
 */
class UserGroupNotificationFactory extends BaseFactory
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
     * @return Notification
     */
    public function createEmpty()
    {
        return new UserGroupNotification($this->getStore(), $this->getLog());
    }

    /**
     * Get by NotificationId
     * @param int $notificationId
     * @return Notification
     * @throws NotFoundException
     */
    public function getByNotificationId($notificationId)
    {
        return $this->query(null, ['notificationId' => $notificationId]);
    }

    /**
     * Get by GroupId
     * @param int $groupId
     * @return Notification
     * @throws NotFoundException
     */
    public function getByGroupId($groupId)
    {
        return $this->query(null, ['groupId' => $groupId]);
    }

    /**
     * @param array[Optional] $sortOrder
     * @param array[Optional] $filterBy
     * @return array[Notification]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        if ($sortOrder == null)
            $sortOrder = ['subject'];

        $params = array();
        $select = 'SELECT `lknotificationgroup`.lknotificationgroupId,
            `lknotificationgroup`.notificationId,
            `lknotificationgroup`.groupId,
            `lknotificationgroup`.read,
            `lknotificationgroup`.readDt ';

        $body = ' FROM `lknotificationgroup` ';

        $body .= ' WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('notificationId', $filterBy) !== null) {
            $body .= ' AND `lknotificationgroup`.notificationId = :notificationId ';
            $params['notificationId'] = $this->getSanitizer()->getInt('notificationId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('groupId', $filterBy) != null) {
            $body .= ' AND `lknotificationgroup`.groupId = :groupId ';
            $params['groupId'] = $this->getSanitizer()->getInt('subject', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new UserGroupNotification($this->getStore(), $this->getLog()))->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}