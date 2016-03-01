<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (HelpFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Help;
use Xibo\Exception\NotFoundException;

class HelpFactory extends BaseFactory
{
    /**
     * @param int $helpId
     * @return Help
     * @throws NotFoundException
     */
    public function getById($helpId)
    {
        $help = $this->query(null, ['helpId' => $helpId]);

        if (count($help) <= 0)
            throw new NotFoundException();

        return $help[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $select = 'SELECT `helpId`, `topic`, `category`, `link` ';

        $body = '
          FROM `help`
         WHERE 1 = 1
        ';

        if ($this->getSanitizer()->getInt('helpId', $filterBy) !== null) {
            $body .= ' AND help.helpId = :helpId ';
            $params['helpId'] = $this->getSanitizer()->getInt('helpId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit .= ' LIMIT ' . intval($this->getSanitizer()->getInt('start')) . ', ' . $this->getSanitizer()->getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new Help())->hydrate($row)->setContainer($this->getContainer());
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}