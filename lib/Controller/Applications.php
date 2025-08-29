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
namespace Xibo\Controller;

use Carbon\Carbon;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\ApplicationScope;
use Xibo\Factory\ApplicationFactory;
use Xibo\Factory\ApplicationRedirectUriFactory;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Factory\ConnectorFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Session;
use Xibo\OAuth\AuthCodeRepository;
use Xibo\OAuth\RefreshTokenRepository;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Applications
 * @package Xibo\Controller
 */
class Applications extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var ApplicationFactory
     */
    private $applicationFactory;

    /**
     * @var ApplicationRedirectUriFactory
     */
    private $applicationRedirectUriFactory;

    /** @var  ApplicationScopeFactory */
    private $applicationScopeFactory;

    /** @var  UserFactory */
    private $userFactory;

    /** @var PoolInterface */
    private $pool;

    /** @var \Xibo\Factory\ConnectorFactory */
    private $connectorFactory;

    /**
     * Set common dependencies.
     * @param Session $session
     * @param ApplicationFactory $applicationFactory
     * @param ApplicationRedirectUriFactory $applicationRedirectUriFactory
     * @param $applicationScopeFactory
     * @param UserFactory $userFactory
     * @param $pool
     * @param \Xibo\Factory\ConnectorFactory $connectorFactory
     */
    public function __construct(
        $session,
        $applicationFactory,
        $applicationRedirectUriFactory,
        $applicationScopeFactory,
        $userFactory,
        $pool,
        ConnectorFactory $connectorFactory
    ) {
        $this->session = $session;
        $this->applicationFactory = $applicationFactory;
        $this->applicationRedirectUriFactory = $applicationRedirectUriFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;
        $this->userFactory = $userFactory;
        $this->pool = $pool;
        $this->connectorFactory = $connectorFactory;
    }

    /**
     * Display Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        // Load all connectors and output any javascript.
        $connectorJavaScript = [];
        foreach ($this->connectorFactory->query(['isVisible' => 1]) as $connector) {
            try {
                // Create a connector, add in platform settings and register it with the dispatcher.
                $connectorObject = $this->connectorFactory->create($connector);

                $settingsFormJavaScript = $connectorObject->getSettingsFormJavaScript();
                if (!empty($settingsFormJavaScript)) {
                    $connectorJavaScript[] = $settingsFormJavaScript;
                }
            } catch (\Exception $exception) {
                // Log and ignore.
                $this->getLog()->error('Incorrectly configured connector. e=' . $exception->getMessage());
            }
        }

        $this->getState()->template = 'applications-page';
        $this->getState()->setData([
            'connectorJavaScript' => $connectorJavaScript,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Display page grid
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response)
    {
        $this->getState()->template = 'grid';
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $applications = $this->applicationFactory->query(
            $this->gridRenderSort($sanitizedParams),
            $this->gridRenderFilter(
                ['name' => $sanitizedParams->getString('name')],
                $sanitizedParams
            )
        );

        foreach ($applications as $application) {
            if ($this->isApi($request)) {
                throw new AccessDeniedException();
            }

            // Include the buttons property
            $application->includeProperty('buttons');

            // Add an Edit button (edit form also exposes the secret - not possible to get through the API)
            $application->buttons = [];

            if ($application->userId == $this->getUser()->userId || $this->getUser()->getUserTypeId() == 1) {
                // Edit
                $application->buttons[] = [
                    'id' => 'application_edit_button',
                    'url' => $this->urlFor($request, 'application.edit.form', ['id' => $application->key]),
                    'text' => __('Edit')
                ];

                // Delete
                $application->buttons[] = [
                    'id' => 'application_delete_button',
                    'url' => $this->urlFor($request, 'application.delete.form', ['id' => $application->key]),
                    'text' => __('Delete')
                ];
            }
        }

        $this->getState()->setData($applications);
        $this->getState()->recordsTotal = $this->applicationFactory->countLast();

        return $this->render($request, $response);
    }

    /**
     * Display the Authorize form.
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function authorizeRequest(Request $request, Response $response)
    {
        // Pull authorize params from our session
        /** @var AuthorizationRequest $authParams */
        $authParams = $this->session->get('authParams');
        if (!$authParams) {
            throw new InvalidArgumentException(__('Authorisation Parameters missing from session.'), 'authParams');
        }

        if ($this->applicationFactory->checkAuthorised($authParams->getClient()->getIdentifier(), $this->getUser()->userId)) {
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
        $this->getState()->template = 'applications-authorize-page';
        $this->getState()->setData([
            'forceHide' => true,
            'authParams' => $authParams,
            'scopes' => $scopes,
            'application' => $client
        ]);

        return $this->render($request, $response);
    }

    /**
     * Authorize an oAuth request
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Exception
     */
    public function authorize(Request $request, Response $response)
    {
        // Pull authorize params from our session
        /** @var AuthorizationRequest $authRequest */
        $authRequest = $this->session->get('authParams');
        if (!$authRequest) {
            throw new InvalidArgumentException(__('Authorisation Parameters missing from session.'), 'authParams');
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
     * Form to register a new application.
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'applications-form-add';

        return $this->render($request, $response);
    }

    /**
     * Edit Application
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        // Load this clients details.
        $client->load();

        $scopes = $this->applicationScopeFactory->query();

        foreach ($scopes as $scope) {
            /** @var ApplicationScope $scope */
            $found = false;
            foreach ($client->scopes as $checked) {
                if ($checked->id == $scope->id) {
                    $found = true;
                    break;
                }
            }

            $scope->setUnmatchedProperty('selected', $found ? 1 : 0);
        }

        // Render the view
        $this->getState()->template = 'applications-form-edit';
        $this->getState()->setData([
            'client' => $client,
            'scopes' => $scopes,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Application Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'applications-form-delete';
        $this->getState()->setData([
            'client' => $client,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Register a new application with OAuth
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function add(Request $request, Response $response)
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
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $application->name),
            'data' => $application,
            'id' => $application->key
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Application
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $id)
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
            /** @var ApplicationScope $scope */
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

            $this->getLog()->debug('Attempting to change ownership to ' . $user->userId . ' - ' . $user->userName);

            if (!$this->getUser()->checkViewable($user)) {
                throw new InvalidArgumentException(__('You do not have permission to assign this user'), 'userId');
            }

            $client->userId = $user->userId;
        }

        $client->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $client->name),
            'data' => $client,
            'id' => $client->key
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete application
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function delete(Request $request, Response $response, $id)
    {
        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        $client->delete();
        $this->pool->getItem('C_' . $client->key)->clear();

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $client->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $userId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function revokeAccess(Request $request, Response $response, $id, $userId)
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

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Access to %s revoked'), $client->name)
        ]);

        return $this->render($request, $response);
    }
}
