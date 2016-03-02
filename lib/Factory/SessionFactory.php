<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (SessionFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Session;
use Xibo\Exception\NotFoundException;

class SessionFactory extends BaseFactory
{
    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Session]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        try {
            $select = '
            SELECT session.userId, user.userName, isExpired, session.lastAccessed, remoteAddr AS remoteAddress, userAgent ';

            $body = '
              FROM `session`
                LEFT OUTER JOIN user ON user.userID = session.userID
             WHERE 1 = 1
            ';

            if ($this->getSanitizer()->getString('fromDt', $filterBy) != '') {
                $body .= ' AND session.LastAccessed < :lastAccessed ';
                $params['lastAccessed'] = $this->getDate()->getLocalDate($this->getSanitizer()->getDate('fromDt', $filterBy)->setTime(0, 0, 0));
            }

            if ($this->getSanitizer()->getString('type', $filterBy) == 'active') {
                $body .= ' AND IsExpired = 0 ';
            }

            if ($this->getSanitizer()->getString('type', $filterBy) == 'active') {
                $body .= ' AND IsExpired = 1 ';
            }

            if ($this->getSanitizer()->getString('type', $filterBy) == 'active') {
                $body .= ' AND session.userID IS NULL ';
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
                $entries[] = (new Session())->hydrate($row)->setContainer($this->getContainer());
            }

            // Paging
            if ($limit != '' && count($entries) > 0) {
                $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
                $this->_countLast = intval($results[0]['total']);
            }

            return $entries;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
}