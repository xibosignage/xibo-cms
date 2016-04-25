<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (NotificationFactory.php)
 */


namespace Xibo\Factory;

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
        $select = 'SELECT `notification`.notificationId,
            `notification`.subject,
            `notification`.createDt,
            `notification`.releaseDt,
            `notification`.body,
            `notification`.isEmail,
            `notification`.isInterrupt,
            `notification`.isSystem,
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