<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApplicationFactory.php)
 */


namespace Xibo\Factory;


use League\OAuth2\Server\Util\SecureKey;
use Xibo\Entity\Application;
use Xibo\Entity\User;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ApplicationFactory
 * @package Xibo\Factory
 */
class ApplicationFactory extends BaseFactory
{
    /**
     * @var ApplicationRedirectUriFactory
     */
    private $applicationRedirectUriFactory;

    /** @var  ApplicationScopeFactory */
    private $applicationScopeFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param ApplicationRedirectUriFactory $applicationRedirectUriFactory
     * @param $applicationScopeFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $applicationRedirectUriFactory, $applicationScopeFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, null);

        $this->applicationRedirectUriFactory = $applicationRedirectUriFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;

        if ($this->applicationRedirectUriFactory == null)
            throw new \RuntimeException('Missing dependency: ApplicationRedirectUriFactory');
    }

    /**
     * @return Application
     */
    public function create()
    {
        $stripped = '';
        $application = $this->createEmpty();
        // Make and ID/Secret
        $bytes = openssl_random_pseudo_bytes(254, $strong);
        $stripped .= str_replace(['/', '+', '='], '', base64_encode($bytes));
        $application->secret = substr($stripped, 0, 254);
        // Assign this user
        $application->userId = $this->getUser()->userId;
        return $application;
    }

    /**
     * Create an empty application
     * @return Application
     */
    public function createEmpty()
    {
        if ($this->applicationRedirectUriFactory == null)
            throw new \RuntimeException('Missing dependency: ApplicationRedirectUriFactory');

        if ($this->applicationScopeFactory == null)
            throw new \RuntimeException('Missing dependency: ApplicationScopeFactory');

        return new Application($this->getStore(), $this->getLog(), $this->applicationRedirectUriFactory, $this->applicationScopeFactory);
    }

    /**
     * Get by ID
     * @param $clientId
     * @return Application
     * @throws NotFoundException
     */
    public function getById($clientId)
    {
        $client = $this->query(null, ['clientId' => $clientId]);

        if (count($client) <= 0)
            throw new NotFoundException();

        return $client[0];
    }

    /**
     * Get by Name
     * @param $name
     * @return Application
     * @throws NotFoundException
     */
    public function getByName($name)
    {
        $client = $this->query(null, ['name' => $name]);

        if (count($client) <= 0)
            throw new NotFoundException();

        return $client[0];
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getByUserId($userId)
    {
        return $this->query(null, ['userId' => $userId]);
    }

    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $entries = [];
        $params = [];

        $select = '
            SELECT `oauth_clients`.id AS `key`,
                `oauth_clients`.secret,
                `oauth_clients`.name,
                `user`.UserName AS owner,
                `oauth_clients`.authCode,
                `oauth_clients`.clientCredentials,
                `oauth_clients`.userId ';

        $body = '
              FROM `oauth_clients`
        ';
        
        $body .= " INNER JOIN `user` ON `user`.userId = `oauth_clients`.userId ";

        if ($sanitizedFilter->getInt('userId') !== null) {

            $select .= '
                , `oauth_auth_codes`.expire_time AS expires
            ';

            $body .= '
                INNER JOIN `oauth_sessions`
                ON `oauth_sessions`.client_id = `oauth_clients`.id
                    AND `oauth_sessions`.owner_id = :userId
                INNER JOIN `oauth_auth_codes`
                ON `oauth_auth_codes`.session_id = `oauth_sessions`.id
            ';

            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        $body .= ' WHERE 1 = 1 ';


        if ($sanitizedFilter->getString('clientId') != null) {
            $body .= ' AND `oauth_clients`.id = :clientId ';
            $params['clientId'] = $sanitizedFilter->getString('clientId');
        }

        if ($sanitizedFilter->getString('name') != null) {
            $body .= ' AND `oauth_clients`.name = :name';
            $params['name'] = $sanitizedFilter->getString('name');
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        // The final statements
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