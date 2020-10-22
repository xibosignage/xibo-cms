<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Factory;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Xibo\Entity\Application;
use Xibo\Entity\User;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ApplicationFactory
 * @package Xibo\Factory
 */
class ApplicationFactory extends BaseFactory implements ClientRepositoryInterface
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
     * @param SanitizerService $sanitizerService
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
        $application = $this->createEmpty();
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

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return array
     */
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

        $body = ' FROM `oauth_clients` ';
        $body .= ' INNER JOIN `user` ON `user`.userId = `oauth_clients`.userId ';
        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getString('clientId') != null) {
            $body .= ' AND `oauth_clients`.id = :clientId ';
            $params['clientId'] = $sanitizedFilter->getString('clientId');
        }

        if ($sanitizedFilter->getString('name') != null) {
            $body .= ' AND `oauth_clients`.name = :name';
            $params['name'] = $sanitizedFilter->getString('name');
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `oauth_clients`.userId = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
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
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['isConfidential']
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * @inheritDoc
     * @return Application
     */
    public function getClientEntity($clientIdentifier)
    {
        $this->getLog()->debug('getClientEntity for clientId: ' . $clientIdentifier);

        try {
            return $this->getById($clientIdentifier)->load();
        } catch (NotFoundException $e) {
            $this->getLog()->debug('getClientEntity: Unable to find ' . $clientIdentifier);
            return null;
        }
    }

    /** @inheritDoc */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        $this->getLog()->debug('validateClient for clientId: ' . $clientIdentifier . ' grant is ' . $grantType);

        $client = $this->getClientEntity($clientIdentifier);

        if ($client === null) {
            $this->getLog()->debug('Client does not exist');
            return false;
        }

        if (
            $client->isConfidential() === true
            && password_verify($clientSecret, $client->getHash()) === false
        ) {
            $this->getLog()->debug('Client secret does not match');
            return false;
        }

        $this->getLog()->debug('Grant Type '. $grantType . ' being tested. Client is condifential = ' . $client->isConfidential());

        // Check to see if this grant_type is allowed for this client
        switch ($grantType) {

            case 'authorization_code':
                if ($client->authCode != 1) {
                    return false;
                }

                break;

            case 'client_credentials':
            case 'mcaas':
                if ($client->clientCredentials != 1) {
                    return false;
                }

                break;

            default:
                return false;
        }

        $this->getLog()->debug('Grant Type is allowed.');

        return true;
    }
}