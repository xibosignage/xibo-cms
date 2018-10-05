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
use Xibo\Exception\NotFoundException;
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
        $application = $this->createEmpty();
        // Make and ID/Secret
        $application->secret = SecureKey::generate(254);
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
        $entries = array();
        $params = array();

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

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {

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

            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';


        if ($this->getSanitizer()->getString('clientId', $filterBy) != null) {
            $body .= ' AND `oauth_clients`.id = :clientId ';
            $params['clientId'] = $this->getSanitizer()->getString('clientId', $filterBy);
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != null) {
            $body .= ' AND `oauth_clients`.name = :name';
            $params['name'] = $this->getSanitizer()->getString('name', $filterBy);
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