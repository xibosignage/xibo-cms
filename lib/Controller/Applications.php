<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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
namespace Xibo\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ApplicationScope;
use Xibo\Factory\ApplicationFactory;
use Xibo\Factory\ApplicationRedirectUriFactory;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\Session;
use Xibo\OAuth\AuthCodeRepository;
use Xibo\OAuth\RefreshTokenRepository;
use Xibo\Support\Exception\AccessDeniedException;
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

    /**
     * Set common dependencies.
     * @param Session $session
     * @param ApplicationFactory $applicationFactory
     * @param ApplicationRedirectUriFactory $applicationRedirectUriFactory
     * @param $applicationScopeFactory
     * @param UserFactory $userFactory
     */
    public function __construct($session, $applicationFactory, $applicationRedirectUriFactory, $applicationScopeFactory, $userFactory)
    {
        $this->session = $session;
        $this->applicationFactory = $applicationFactory;
        $this->applicationRedirectUriFactory = $applicationRedirectUriFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;
        $this->userFactory = $userFactory;
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
        $this->getState()->template = 'applications-page';

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

        $applications = $this->applicationFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter([], $sanitizedParams));

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
                    'url' => $this->urlFor($request,'application.edit.form', ['id' => $application->key]),
                    'text' => __('Edit')
                ];

                // Delete
                $application->buttons[] = [
                    'id' => 'application_delete_button',
                    'url' => $this->urlFor($request,'application.delete.form', ['id' => $application->key]),
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
        if (!$authParams = $this->session->get('authParams')) {
            throw new InvalidArgumentException(__('Authorisation Parameters missing from session.'), 'authParams');
        }

        // Get, show page
        $this->getState()->template = 'applications-authorize-page';
        $this->getState()->setData([
            'authParams' => $authParams
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
            new \Xibo\OAuth\AccessTokenRepository($this->getLog()),
            $this->applicationScopeFactory,
            $privateKey,
            $encryptionKey
        );

        $server->enableGrantType(
            new AuthCodeGrant(
                new AuthCodeRepository(),
                new RefreshTokenRepository(),
                new \DateInterval('PT10M')
            ),
            new \DateInterval('PT1H')
        );

        // Default scope
        $server->setDefaultScope('all');

        // We are authorized
        if ($sanitizedQueryParams->getString('authorization') === 'Approve') {

            $authRequest->setAuthorizationApproved(true);

            // get oauth User Entity and set the UserId to the current web userId
            $authRequest->setUser($this->getUser());

            // Redirect back to the home page
            return $server->completeAuthorizationRequest($authRequest, $response);
        }
        else {
            $authRequest->setAuthorizationApproved(false);
            return $server->completeAuthorizationRequest($authRequest, $response);
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
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Services', 'Register')
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

            $scope->selected = $found ? 1 : 0;
        }

        // Render the view
        $this->getState()->template = 'applications-form-edit';
        $this->getState()->setData([
            'client' => $client,
            'scopes' => $scopes,
            'users' => $this->userFactory->query(),
            'help' => $this->getHelp()->link('Services', 'Register')
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

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'applications-form-delete';
        $this->getState()->setData([
            'client' => $client,
            'help' => $this->getHelp()->link('Services', 'Register')
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

        if ($application->name == '' ) {
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
     * Form to register a new application for Advertisement.
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addDoohForm(Request $request, Response $response)
    {
        $this->getState()->template = 'applications-form-add-dooh';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Services', 'Register')
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function addDooh(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());;
        $application = $this->applicationFactory->create();
        $application->name = $sanitizedParams->getString('name');
        $application->userId = $sanitizedParams->getInt('userId');

        if ($application->name == '' ) {
            throw new InvalidArgumentException(__('Please enter Application name'), 'name');
        }

        if ($application->userId == null ) {
            throw new InvalidArgumentException(__('Please select user'), 'userId');
        }

        if ($this->userFactory->getById($application->userId)->userTypeId != 4 ) {
            throw new InvalidArgumentException(__('Invalid user type'), 'userTypeId');
        }

        // The dooh application is always confidential, and always client credentials
        $application->clientCredentials = 1;
        $application->isConfidential = 1;

        // Add the all scope
        $application->assignScope($this->applicationScopeFactory->getById('all'));

        // Save
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

        $sanitizedParams = $this->getSanitizer($request->getParams());;
        $client->name = $sanitizedParams->getString('name');
        $client->authCode = $sanitizedParams->getCheckbox('authCode');
        $client->clientCredentials = $sanitizedParams->getCheckbox('clientCredentials');

        if ($sanitizedParams->getCheckbox('resetKeys') == 1) {
            $client->resetSecret();
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

        // API Scopes
        foreach ($this->applicationScopeFactory->query() as $scope) {
            /** @var ApplicationScope $scope */

            // See if this has been checked this time
            $checked = $sanitizedParams->getCheckbox('scope_' . $scope->id);

            // Does this scope already exist?
            $found = false;
            foreach ($client->scopes as $existingScope) {
                if ($scope->id == $existingScope->id) {
                    $found = true;
                    break;
                }
            }

            // Assign or unassign as necessary
            if ($checked && !$found) {
                $client->assignScope($scope);
            } else if (!$checked && $found) {
                $client->unassignScope($scope);
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

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $client->name)
        ]);

        return $this->render($request, $response);
    }
}
