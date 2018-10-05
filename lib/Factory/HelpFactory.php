<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (HelpFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Help;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

class HelpFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return Help
     */
    public function createEmpty()
    {
        return new Help(
            $this->getStore(),
            $this->getLog()
        );
    }

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
    public function query($sortOrder = null, $filterBy = [])
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
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit .= ' LIMIT ' . intval($this->getSanitizer()->getInt('start')) . ', ' . $this->getSanitizer()->getInt('length', 10);
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