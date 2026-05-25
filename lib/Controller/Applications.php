<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
namespace Xibo\Controller;

use Carbon\Carbon;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Application;
use Xibo\Factory\ApplicationFactory;
use Xibo\Factory\ApplicationRedirectUriFactory;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Session;
use Xibo\OAuth\AuthCodeRepository;
use Xibo\OAuth\RefreshTokenRepository;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Applications
 * @package Xibo\Controller
 */
class Applications extends Base
{
    public function __construct(
        private readonly Session $session,
        private readonly ApplicationFactory $applicationFactory,
        private readonly ApplicationRedirectUriFactory $applicationRedirectUriFactory,
        private readonly ApplicationScopeFactory $applicationScopeFactory,
        private readonly UserFactory $userFactory,
        private readonly PoolInterface $pool,
    ) {
    }

    /**
     * Search all available OAuth scopes
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     */
    public function scopeSearch(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $filterBy = [];

        if ($sanitizedParams->getString('clientId') !== null) {
            $filterBy['clientId'] = $sanitizedParams->getString('clientId');
        }

        $scopes = $this->applicationScopeFactory->query(null, $filterBy);

        return $response->withStatus(200)->withJson($scopes);
    }

    /**
     * Get a single application by id, with redirect URIs and scopes loaded
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getById(Request $request, Response $response, $id)
    {
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        $client->load();

        return $response->withStatus(200)->withJson($client);
    }

    /**
     * Display page grid
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        if ($this->isApi($request)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $applications = $this->applicationFactory->query(
            $this->gridRenderSort($sanitizedParams, $this->isJson($request)),
            $this->getApplicationFilters($sanitizedParams)
        );

        if ($sanitizedParams->getCheckbox('getScopesState')) {
            if (!empty($applications)) {
                $allScopes = $this->applicationScopeFactory->query();
                $clientIds = array_map(fn(Application $a) => $a->key, $applications);
                $clientScopesMap = $this->applicationScopeFactory->getByClientIds($clientIds);

                foreach ($applications as $application) {
                    $this->getScopesAndState($application, $allScopes, $clientScopesMap);
                }
            }
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->applicationFactory->countLast())
            ->withJson($applications);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, string $id): Response|ResponseInterface
    {
        if ($this->isApi($request)) {
            throw new AccessDeniedException();
        }

        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        $this->getScopesAndState($client);
        
        return $response
            ->withStatus(200)
            ->withJson($client);
    }

    /**
     * Display the Authorize form.
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function authorizeRequest(Request $request, Response $response): Response|ResponseInterface
    {
        // Pull authorize params from our session
        /** @var AuthorizationRequest $authParams */
        $authParams = $this->session->get('authParams');
        if (!$authParams) {
            throw new InvalidArgumentException(
                __('Authorisation Parameters missing from session.'),
                'authParams'
            );
        }

        if ($this->applicationFactory
            ->checkAuthorised($authParams->getClient()->getIdentifier(), $this->getUser()->userId)
        ) {
            return $this->authorize($request->withParsedBody(['authorization' => 'Approve']), $response);
        }

        $client = $this->applicationFactory->getClientEntity($authParams->getClient()->getIdentifier())->load();

        // Process any scopes.
        $scopes = [];
        $authScopes = $authParams->getScopes();

        // if we have scopes in the request, make sure we only add the valid ones.
        // the default scope is all, if it's not set on the Application, $scopes will still be empty here.
        if ($authScopes !== null) {
            $validScopes = $this->applicationScopeFactory->finalizeScopes(
                $authScopes,
                $authParams->getGrantTypeId(),
                $client
            );

            // get all the valid scopes by their ID, we need to do this to present more details on the authorize form.
            foreach ($validScopes as $scope) {
                $scopes[] = $this->applicationScopeFactory->getById($scope->getIdentifier());
            }

            if (count($scopes) <= 0) {
                throw new InvalidArgumentException(
                    __('This application has not requested access to anything.'),
                    'authParams'
                );
            }

            // update scopes in auth request in session to scopes we actually present for approval
            $authParams->setScopes($validScopes);
        }

        // Reasert  the auth params.
        $this->session->set('authParams', $authParams);

        // Get, show page
        return $response
            ->withStatus(200)
            ->withJson([
                'forceHide' => true,
                'authParams' => $authParams,
                'scopes' => $scopes,
                'application' => $client
            ]);
    }

    /**
     * Authorize an oAuth request
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws \Exception
     */
    public function authorize(Request $request, Response $response): Response|ResponseInterface
    {
        // Pull authorize params from our session
        /** @var AuthorizationRequest $authRequest */
        $authRequest = $this->session->get('authParams');
        if (!$authRequest) {
            throw new InvalidArgumentException(
                __('Authorisation Parameters missing from session.'),
                'authParams'
            );
        }

        $sanitizedQueryParams = $this->getSanitizer($request->getParams());

        $apiKeyPaths = $this->getConfig()->getApiKeyDetails();
        $privateKey = $apiKeyPaths['privateKeyPath'];
        $encryptionKey = $apiKeyPaths['encryptionKey'];

        $server = new AuthorizationServer(
            $this->applicationFactory,
            new \Xibo\OAuth\AccessTokenRepository($this->getLog(), $this->pool, $this->applicationFactory),
            $this->applicationScopeFactory,
            $privateKey,
            $encryptionKey
        );

        $server->enableGrantType(
            new AuthCodeGrant(
                new AuthCodeRepository(),
                new RefreshTokenRepository($this->getLog(), $this->pool),
                new \DateInterval('PT10M')
            ),
            new \DateInterval('PT1H')
        );

        // get oauth User Entity and set the UserId to the current web userId
        $authRequest->setUser($this->getUser());

        // We are authorized
        if ($sanitizedQueryParams->getString('authorization') === 'Approve') {
            $authRequest->setAuthorizationApproved(true);

            $this->applicationFactory->setApplicationApproved(
                $authRequest->getClient()->getIdentifier(),
                $authRequest->getUser()->getIdentifier(),
                Carbon::now()->format(DateFormatHelper::getSystemFormat()),
                $request->getAttribute('ip_address')
            );

            $this->getLog()->audit(
                'Auth',
                0,
                'Application access approved',
                [
                    'Application identifier ends with' => substr($authRequest->getClient()->getIdentifier(), -8),
                    'Application Name' => $authRequest->getClient()->getName()
                ]
            );
        } else {
            $authRequest->setAuthorizationApproved(false);
        }

        // Redirect back to the specified redirect url
        try {
            return $server->completeAuthorizationRequest($authRequest, $response);
        } catch (OAuthServerException $exception) {
            if ($exception->hasRedirect()) {
                return $response->withRedirect($exception->getRedirectUri());
            } else {
                throw $exception;
            }
        }
    }

    /**
     * Register a new application with OAuth
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function add(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $application = $this->applicationFactory->create();
        $application->name = $sanitizedParams->getString('name');

        if ($application->name == '') {
            throw new InvalidArgumentException(__('Please enter Application name'), 'name');
        }

        $application->userId = $this->getUser()->userId;
        $application->save();

        // Return
        return $response
            ->withStatus(201)
            ->withJson($application);
    }

    /**
     * Edit Application
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $this->getLog()->debug('Editing ' . $id);

        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        $client->name = $sanitizedParams->getString('name');
        $client->authCode = $sanitizedParams->getCheckbox('authCode');
        $client->clientCredentials = $sanitizedParams->getCheckbox('clientCredentials');
        $client->isConfidential = $sanitizedParams->getCheckbox('isConfidential');

        if ($sanitizedParams->getCheckbox('resetKeys') == 1) {
            $client->resetSecret();
            $this->pool->getItem('C_' . $client->key)->clear();
        }

        if ($client->authCode === 1) {
            $client->description = $sanitizedParams->getString('description');
            $client->logo = $sanitizedParams->getString('logo');
            $client->coverImage = $sanitizedParams->getString('coverImage');
            $client->companyName = $sanitizedParams->getString('companyName');
            $client->termsUrl = $sanitizedParams->getString('termsUrl');
            $client->privacyUrl = $sanitizedParams->getString('privacyUrl');
        }

        // Delete all the redirect urls and add them again
        $client->load();

        foreach ($client->redirectUris as $uri) {
            $uri->delete();
        }

        $client->redirectUris = [];

        // Do we have a redirect?
        $redirectUris = $sanitizedParams->getArray('redirectUri');

        foreach ($redirectUris as $redirectUri) {
            if ($redirectUri == '') {
                continue;
            }

            $redirect = $this->applicationRedirectUriFactory->create();
            $redirect->redirectUri = $redirectUri;
            $client->assignRedirectUri($redirect);
        }

        // clear scopes
        $client->scopes = [];

        // API Scopes
        foreach ($this->applicationScopeFactory->query() as $scope) {
            // See if this has been checked this time
            $checked = $sanitizedParams->getCheckbox('scope_' . $scope->id);

            // Assign scopes
            if ($checked) {
                $client->assignScope($scope);
            }
        }

        // Change the ownership?
        if ($sanitizedParams->getInt('userId') !== null) {
            // Check we have permissions to view this user
            $user = $this->userFactory->getById($sanitizedParams->getInt('userId'));

            $this->getLog()->debug(
                'Attempting to change ownership to ' . $user->userId . ' - ' . $user->userName
            );

            if (!$this->getUser()->checkViewable($user)) {
                throw new InvalidArgumentException(
                    __('You do not have permission to assign this user'),
                    'userId'
                );
            }

            $client->userId = $user->userId;
        }

        $client->save();

        // Return
        return $response
            ->withStatus(200)
            ->withJson($client);
    }

    /**
     * Delete application
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function delete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        $client->delete();
        $this->pool->getItem('C_' . $client->key)->clear();

        return $response->withStatus(204);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $userId
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function revokeAccess(Request $request, Response $response, $id, $userId): Response|ResponseInterface
    {
        if ($userId === null) {
            throw new InvalidArgumentException(__('No User ID provided'));
        }

        if (empty($id)) {
            throw new InvalidArgumentException(__('No Client id provided'));
        }

        $client = $this->applicationFactory->getClientEntity($id);

        if ($this->getUser()->userId != $userId) {
            throw new InvalidArgumentException(__('Access denied: You do not own this authorization.'));
        }

        // remove record in lk table
        $this->applicationFactory->revokeAuthorised($userId, $client->key);
        // clear cache for this clientId/userId pair, this is how we know the application is no longer approved
        $this->pool->getItem('C_' . $client->key . '/' . $userId)->clear();

        $this->getLog()->audit(
            'Auth',
            0,
            'Application access revoked',
            [
                'Application identifier ends with' => substr($client->key, -8),
                'Application Name' => $client->getName()
            ]
        );

        return $response->withStatus(204);
    }

    /**
     * @param SanitizerInterface $params
     * @return array
     */
    private function getApplicationFilters(SanitizerInterface $params): array
    {
        $filter = [
            'name'    => $params->getString('name'),
            'keyword' => $params->getString('keyword'),
        ];

        if (!$this->getUser()->isSuperAdmin()) {
            $filter['userId'] = $this->getUser()->userId;
        }

        return $this->gridRenderFilter($filter, $params);
    }

    /**
     * @param Application $client
     * @param array|null $allScopes
     * @param array|null $clientScopesMap
     * @return void
     */
    private function getScopesAndState(
        Application $client,
        ?array $allScopes = null,
        ?array $clientScopesMap = null
    ): void {
        if ($allScopes === null || $clientScopesMap === null) {
            $client->load();
            $clientScopes = $client->scopes;
            $allScopes = $this->applicationScopeFactory->query();
        } else {
            $clientScopes = $clientScopesMap[$client->key] ?? [];
        }

        $assignedIds = [];
        foreach ($clientScopes as $cs) {
            $assignedIds[$cs->id] = true;
        }

        $scopesState = [];
        foreach ($allScopes as $scope) {
            $copy = clone $scope;
            $copy->setUnmatchedProperty('selected', isset($assignedIds[$scope->id]) ? 1 : 0);
            $scopesState[] = $copy;
        }

        $client->setUnmatchedProperty('scopesState', $scopesState);
    }
}
