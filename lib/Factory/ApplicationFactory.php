<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
     * @param User $user
     * @param ApplicationRedirectUriFactory $applicationRedirectUriFactory
     * @param ApplicationScopeFactory $applicationScopeFactory
     */
    public function __construct($user, $applicationRedirectUriFactory, $applicationScopeFactory)
    {
        $this->setAclDependencies($user, null);

        $this->applicationRedirectUriFactory = $applicationRedirectUriFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;

        if ($this->applicationRedirectUriFactory == null) {
            throw new \RuntimeException('Missing dependency: ApplicationRedirectUriFactory');
        }
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
        if ($this->applicationRedirectUriFactory == null) {
            throw new \RuntimeException('Missing dependency: ApplicationRedirectUriFactory');
        }

        if ($this->applicationScopeFactory == null) {
            throw new \RuntimeException('Missing dependency: ApplicationScopeFactory');
        }

        return new Application(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->applicationRedirectUriFactory,
            $this->applicationScopeFactory
        );
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

        if (count($client) <= 0) {
            throw new NotFoundException();
        }

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
        $client = $this->query(null, ['name' => $name, 'useRegexForName' => 1]);

        if (count($client) <= 0) {
            throw new NotFoundException();
        }

        return $client[0];
    }

    /**
     * @param int $userId
     * @return Application[]
     */
    public function getByUserId($userId)
    {
        return $this->query(null, ['userId' => $userId]);
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Application[]
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
                `oauth_clients`.userId, 
                `oauth_clients`.isConfidential,
                `oauth_clients`.description,
                `oauth_clients`.logo,
                `oauth_clients`.coverImage,
                `oauth_clients`.companyName,
                `oauth_clients`.termsUrl,
                `oauth_clients`.privacyUrl
            ';

        $body = ' FROM `oauth_clients` ';
        $body .= ' INNER JOIN `user` ON `user`.userId = `oauth_clients`.userId ';
        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getString('clientId') != null) {
            $body .= ' AND `oauth_clients`.id = :clientId ';
            $params['clientId'] = $sanitizedFilter->getString('clientId');
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `oauth_clients`.userId = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        // Filter by Application Name?
        if ($sanitizedFilter->getString('name') != null) {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'oauth_clients',
                'name',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['isConfidential', 'authCode', 'clientCredentials']
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

        if ($client->isConfidential() === true
            && password_verify($clientSecret, $client->getHash()) === false
        ) {
            $this->getLog()->debug('Client secret does not match');
            return false;
        }

        $this->getLog()->debug('Grant Type '. $grantType . ' being tested. Client is condifential = ' . $client->isConfidential());

        // Check to see if this grant_type is allowed for this client
        switch ($grantType) {
            case 'authorization_code':
            case 'refresh_token':
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

    /**
     * Insert approval record for provided clientId/userId pair with current date and IP address
     * @param $clientId
     * @param $userId
     * @param $approvedDate
     * @param $approvedIp
     */
    public function setApplicationApproved($clientId, $userId, $approvedDate, $approvedIp)
    {
        $this->getLog()->debug('Adding approved Access for Application ' . $clientId . ' for User ' . $userId);

        $this->getStore()->insert('
            INSERT INTO `oauth_lkclientuser` (`clientId`, `userId`, `approvedDate`, `approvedIp`)
              VALUES (:clientId, :userId, :approvedDate, :approvedIp)
            ON DUPLICATE KEY UPDATE clientId = clientId, userId = userId, approvedDate = :approvedDate, approvedIp = :approvedIp

        ', [
            'clientId' => $clientId,
            'userId' => $userId,
            'approvedDate' => $approvedDate,
            'approvedIp' => $approvedIp
        ]);
    }

    /**
     * Check if provided clientId and userId pair are still authorised
     * @param $clientId
     * @param $userId
     * @return bool
     */
    public function checkAuthorised($clientId, $userId): bool
    {
        $results = $this->getStore()->select('SELECT clientId, userId FROM `oauth_lkclientuser` WHERE clientId = :clientId AND userId = :userId', [
            'userId' => $userId,
            'clientId' => $clientId
        ]);

        if (count($results) <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Get applications authorised by specific user
     * @param $userId
     * @return array
     */
    public function getAuthorisedByUserId($userId): array
    {
        return $this->getStore()->select('SELECT oauth_clients.name, oauth_clients.id, approvedDate, approvedIp FROM `oauth_lkclientuser` INNER JOIN `oauth_clients` on `oauth_lkclientuser`.clientId = `oauth_clients`.id WHERE `oauth_lkclientuser`.userId = :userId', [
            'userId' => $userId
        ]);
    }

    /**
     * Remove provided clientId and userId pair from link table
     * @param $userId
     * @param $clientId
     */
    public function revokeAuthorised($userId, $clientId)
    {
        $this->getStore()->update('DELETE FROM `oauth_lkclientuser` WHERE clientId = :clientId AND userId = :userId', [
            'userId' => $userId,
            'clientId' => $clientId
        ]);
    }
}
