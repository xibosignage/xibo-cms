<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2021 Xibo Signage Ltd
 * (ApplicationScopeFactory.php)
 */


namespace Xibo\Factory;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Xibo\Entity\ApplicationScope;
use Xibo\Helper\SanitizerService;
use Xibo\OAuth\ScopeEntity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ApplicationScopeFactory
 * @package Xibo\Factory
 */
class ApplicationScopeFactory extends BaseFactory implements ScopeRepositoryInterface
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * Create Empty
     * @return ApplicationScope
     */
    public function create()
    {
        return new ApplicationScope($this->getStore(), $this->getLog());
    }

    /**
     * Get by ID
     * @param $id
     * @return ApplicationScope
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

        $select = 'SELECT `oauth_scopes`.id, `oauth_scopes`.description';

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
    public function finalizeScopes(array $scopes, $grantType, ClientEntityInterface $clientEntity, $userIdentifier = null)
    {
        $this->getLog()->debug('finalizeScopes: provided scopes count = ' . count($scopes));

        $finalScopes = [];

        // $clientEntity->scopes are the valid scopes for this client.
        // make sure all of the requested scopes are valid
        foreach ($scopes as $scope) {
            // See if we can find it
            $found = false;

            /** @var \Xibo\Entity\Application $clientEntity */
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
