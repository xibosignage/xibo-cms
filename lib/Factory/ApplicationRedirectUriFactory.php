<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApplicationRedirectUriFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\ApplicationRedirectUri;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ApplicationRedirectUriFactory
 * @package Xibo\Factory
 */
class ApplicationRedirectUriFactory extends BaseFactory
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
     * Create Empty
     * @return ApplicationRedirectUri
     */
    public function create()
    {
        return new ApplicationRedirectUri($this->getStore(), $this->getLog());
    }

    /**
     * Get by ID
     * @param $id
     * @return ApplicationRedirectUri
     * @throws NotFoundException
     */
    public function getById($id)
    {
        $clientRedirectUri = $this->query(null, ['id' => $id]);

        if (count($clientRedirectUri) <= 0)
            throw new NotFoundException();

        return $clientRedirectUri[0];
    }

    /**
     * Get by Client Id
     * @param $clientId
     * @return array[ApplicationRedirectUri]
     * @throws NotFoundException
     */
    public function getByClientId($clientId)
    {
        return $this->query(null, ['clientId' => $clientId]);
    }

    /**
     * Query
     * @param null $sortOrder
     * @param array $filterBy
     * @return array
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();
        $params = array();

        $select = 'SELECT id, client_id AS clientId, redirect_uri AS redirectUri ';

        $body = ' FROM `oauth_client_redirect_uris` WHERE 1 = 1 ';

        if ($this->getSanitizer()->getString('clientId', $filterBy) != null) {
            $body .= ' AND `oauth_client_redirect_uris`.client_id = :clientId ';
            $params['clientId'] = $this->getSanitizer()->getString('clientId', $filterBy);
        }

        if ($this->getSanitizer()->getString('id', $filterBy) != null) {
            $body .= ' AND `oauth_client_redirect_uris`.client_id = :id ';
            $params['id'] = $this->getSanitizer()->getString('id', $filterBy);
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

        // The final statements
        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->create()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}