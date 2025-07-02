<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Xibo\Entity\ApplicationScope;
use Xibo\OAuth\ScopeEntity;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ApplicationScopeFactory
 * @package Xibo\Factory
 */
class ApplicationScopeFactory extends BaseFactory implements ScopeRepositoryInterface
{
    /**
     * Create Empty
     * @return ApplicationScope
     */
    public function create()
    {
        return new ApplicationScope($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Get by ID
     * @param $id
     * @return ApplicationScope
     * @throws NotFoundException
     */
    public function getById($id)
    {
        $scope = $this->query(null, ['id' => $id]);

        if (count($scope) <= 0) {
            throw new NotFoundException();
        }

        return $scope[0];
    }

    /**
     * Get by Client Id
     * @param $clientId
     * @return ApplicationScope[]
     */
    public function getByClientId($clientId)
    {
        return $this->query(null, ['clientId' => $clientId]);
    }

    /**
     * Query
     * @param null $sortOrder
     * @param array $filterBy
     * @return ApplicationScope[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];
        $params = [];

        $select = 'SELECT `oauth_scopes`.id, `oauth_scopes`.description, `oauth_scopes`.useRegex';

        $body = '  FROM `oauth_scopes`';

        if ($sanitizedFilter->getString('clientId') != null) {
            $body .= ' INNER JOIN `oauth_client_scopes`
                ON `oauth_client_scopes`.scopeId = `oauth_scopes`.id ';
        }

        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getString('clientId') != null) {
            $body .= ' AND `oauth_client_scopes`.clientId = :clientId  ';
            $params['clientId'] = $sanitizedFilter->getString('clientId');
        }

        if ($sanitizedFilter->getString('id') != null) {
            $body .= ' AND `oauth_scopes`.id = :id ';
            $params['id'] = $sanitizedFilter->getString('id');
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
            $entries[] = $this->create()->hydrate($row, ['stringProperties' => ['id']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * {@inheritdoc}
     */
    public function getScopeEntityByIdentifier($scopeIdentifier)
    {
        $this->getLog()->debug('getScopeEntityByIdentifier: ' . $scopeIdentifier);

        try {
            $applicationScope = $this->getById($scopeIdentifier);
            $scope = new ScopeEntity();
            $scope->setIdentifier($applicationScope->getId());
            return $scope;
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ): array {
        /** @var \Xibo\Entity\Application $clientEntity */
        $countOfScopesRequested = count($scopes);
        $this->getLog()->debug('finalizeScopes: provided scopes count = ' . $countOfScopesRequested);

        // No scopes have been requested
        // in this case we should return all scopes configured for the application
        // this is to maintain backwards compatibility with older implementations which do not
        // request scopes.
        if ($countOfScopesRequested <= 0) {
            return $clientEntity->getScopes();
        }

        // Scopes have been provided
        $finalScopes = [];

        // The client entity contains the scopes which are valid for this client
        foreach ($scopes as $scope) {
            // See if we can find it
            $found = false;

            foreach ($clientEntity->getScopes() as $validScope) {
                if ($validScope->getIdentifier() === $scope->getIdentifier()) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $finalScopes[] = $scope;
            }
        }

        return $finalScopes;
    }
}
