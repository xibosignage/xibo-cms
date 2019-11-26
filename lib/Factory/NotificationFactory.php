<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (NotificationFactory.php)
 */


namespace Xibo\Factory;

use Jenssegers\Date\Date;
use Xibo\Entity\Notification;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class NotificationFactory
 * @package Xibo\Factory
 */
class NotificationFactory extends BaseFactory
{
    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $userGroupFactory, $displayGroupFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->userGroupFactory = $userGroupFactory;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * @return Notification
     */
    public function createEmpty()
    {
        return new Notification($this->getStore(), $this->getLog(), $this->userGroupFactory, $this->displayGroupFactory);
    }

    /**
     * @param string $subject
     * @param string $body
     * @param Date $date
     * @param bool $isEmail
     * @param bool $addGroups
     * @return Notification
     */
    public function createSystemNotification($subject, $body, $date, $isEmail = true, $addGroups = true)
    {
        $userId = $this->getUser()->userId;

        $notification = $this->createEmpty();
        $notification->subject = $subject;
        $notification->body = $body;
        $notification->createdDt = $date->format('U');
        $notification->releaseDt = $date->format('U');
        $notification->isEmail = ($isEmail) ? 1 : 0;
        $notification->isInterrupt = 0;
        $notification->userId = $userId;
        $notification->isSystem = 1;

        if ($addGroups) {
            // Add the system notifications group - if there is one.
            foreach ($this->userGroupFactory->getSystemNotificationGroups() as $group) {
                /* @var \Xibo\Entity\UserGroup $group */
                $notification->assignUserGroup($group);
            }
        }

        return $notification;
    }

    /**
     * Get by Id
     * @param int $notificationId
     * @return Notification
     * @throws NotFoundException
     */
    public function getById($notificationId)
    {
        $notifications = $this->query(null, ['notificationId' => $notificationId]);

        if (count($notifications) <= 0)
            throw new NotFoundException();

        return $notifications[0];
    }

    /**
     * @param string $subject
     * @param int $fromDt
     * @param int $toDt
     * @return Notification[]
     */
    public function getBySubjectAndDate($subject, $fromDt, $toDt)
    {
        return $this->query(null, ['subject' => $subject, 'createFromDt' => $fromDt, 'createToDt' => $toDt]);
    }

    /**
     * @param array[Optional] $sortOrder
     * @param array[Optional] $filterBy
     * @return Notification[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();

        if ($sortOrder == null)
            $sortOrder = ['subject'];

        $params = array();
        $select = 'SELECT `notification`.notificationId,
            `notification`.subject,
            `notification`.createDt,
            `notification`.releaseDt,
            `notification`.body,
            `notification`.isEmail,
            `notification`.isInterrupt,
            `notification`.isSystem,
            `notification`.filename,
            `notification`.originalFileName,
            `notification`.nonusers,
            `notification`.userId ';

        $body = ' FROM `notification` ';

        $body .= ' WHERE 1 = 1 ';

        self::viewPermissionSql('Xibo\Entity\Notification', $body, $params, '`notification`.notificationId', '`notification`.userId');

        if ($this->getSanitizer()->getInt('notificationId', $filterBy) !== null) {
            $body .= ' AND `notification`.notificationId = :notificationId ';
            $params['notificationId'] = $this->getSanitizer()->getInt('notificationId', $filterBy);
        }

        if ($this->getSanitizer()->getString('subject', $filterBy) != null) {
            $body .= ' AND `notification`.subject = :subject ';
            $params['subject'] = $this->getSanitizer()->getString('subject', $filterBy);
        }

        if ($this->getSanitizer()->getInt('createFromDt', $filterBy) != null) {
            $body .= ' AND `notification`.createDt >= :createFromDt ';
            $params['createFromDt'] = $this->getSanitizer()->getInt('createFromDt', $filterBy);
        }

        if ($this->getSanitizer()->getInt('releaseDt', $filterBy) != null) {
            $body .= ' AND `notification`.releaseDt >= :releaseDt ';
            $params['releaseDt'] = $this->getSanitizer()->getInt('releaseDt', $filterBy);
        }

        if ($this->getSanitizer()->getInt('createToDt', $filterBy) != null) {
            $body .= ' AND `notification`.createDt < :createToDt ';
            $params['createToDt'] = $this->getSanitizer()->getInt('createToDt', $filterBy);
        }

        // User Id?
        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND `notification`.notificationId IN (
              SELECT notificationId 
                FROM `lknotificationuser`
               WHERE userId = :userId 
            )';
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        // Display Id?
        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $body .= ' AND `notification`.notificationId IN (
              SELECT notificationId 
                FROM `lknotificationdg`
                    INNER JOIN `lkdgdg`
                    ON `lkdgdg`.parentId = `lknotificationdg`.displayGroupId
                    INNER JOIN `lkdisplaydg`
                    ON `lkdisplaydg`.displayGroupId = `lkdgdg`.childId
               WHERE `lkdisplaydg`.displayId = :displayId 
            )';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
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