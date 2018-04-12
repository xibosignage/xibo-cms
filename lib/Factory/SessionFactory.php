<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (SessionFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Session;
use Xibo\Exception\NotFoundException;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class SessionFactory
 * @package Xibo\Factory
 */
class SessionFactory extends BaseFactory
{
    /**
     * @var DateServiceInterface
     */
    private $date;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param DateServiceInterface $dateService
     */
    public function __construct($store, $log, $sanitizerService, $dateService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);

        $this->date = $dateService;
    }

    /**
     * @return Session
     */
    public function createEmpty()
    {
        return new Session($this->getStore(), $this->getLog());
    }

    /**
     * @param $sessionId
     * @return Session
     * @throws NotFoundException
     */
    public function getById($sessionId)
    {
        $session = $this->query(null, ['sessionId' => $sessionId]);

        if (count($session) <= 0)
            throw new NotFoundException();

        return $session[0];
    }

    /**
     * @param int $userId
     * @return int loggedIn
     */
    public function getActiveSessionsForUser($userId)
    {
        $userSession = $this->query(null, ['userId' => $userId, 'type' => 'active']);

        return (count($userSession) > 0) ? 1 : 0;
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return Session[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();
        $params = array();

        $select = '
            SELECT `session`.session_id AS sessionId, 
                session.userId, 
                user.userName, 
                isExpired, 
                session.lastAccessed, 
                remoteAddr AS remoteAddress, 
                userAgent, 
                user.userId AS userId,
                `session`.session_expiration AS expiresAt
        ';

        $body = '
          FROM `session`
            LEFT OUTER JOIN user ON user.userID = session.userID
         WHERE 1 = 1
        ';

        if ($this->getSanitizer()->getString('sessionId', $filterBy) != null) {
            $body .= ' AND session.session_id = :sessionId ';
            $params['sessionId'] = $this->getSanitizer()->getString('sessionId', $filterBy);
        }

        if ($this->getSanitizer()->getString('fromDt', $filterBy) != null) {
            $body .= ' AND session.LastAccessed >= :lastAccessed ';
            $params['lastAccessed'] = $this->date->getLocalDate($this->getSanitizer()->getDate('fromDt', $filterBy)->setTime(0, 0, 0));
        }

        if ($this->getSanitizer()->getString('type', $filterBy) != null) {

            if ($this->getSanitizer()->getString('type', $filterBy) == 'active') {
                $body .= ' AND IsExpired = 0 ';
            }

            if ($this->getSanitizer()->getString('type', $filterBy) == 'expired') {
                $body .= ' AND IsExpired = 1 ';
            }

            if ($this->getSanitizer()->getString('type', $filterBy) == 'guest') {
                $body .= ' AND IFNULL(session.userID, 0) = 0 ';
            }
        }

        if ($this->getSanitizer()->getString('userId', $filterBy) != null) {
            $body .= ' AND user.userID = :userId ';
            $params['userId'] = $this->getSanitizer()->getString('userId', $filterBy);
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
            $entries[] = $this->createEmpty()->hydrate($row, ['stringProperties' => ['sessionId']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}