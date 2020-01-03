<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-13 Daniel Garner
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Util\RedirectUri;
use Slim\Views\Twig;
use Xibo\Entity\Application;
use Xibo\Entity\ApplicationScope;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\ApplicationFactory;
use Xibo\Factory\ApplicationRedirectUriFactory;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Session;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\ApiAccessTokenStorage;
use Xibo\Storage\ApiAuthCodeStorage;
use Xibo\Storage\ApiClientStorage;
use Xibo\Storage\ApiScopeStorage;
use Xibo\Storage\ApiSessionStorage;
use Xibo\Storage\StorageServiceInterface;

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

    /** @var  StorageServiceInterface */
    private $store;

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
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Session $session
     * @param StorageServiceInterface $store
     * @param ApplicationFactory $applicationFactory
     * @param ApplicationRedirectUriFactory $applicationRedirectUriFactory
     * @param UserFactory $userFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session, $store, $applicationFactory, $applicationRedirectUriFactory, $applicationScopeFactory, $userFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->session = $session;
        $this->store = $store;
        $this->applicationFactory = $applicationFactory;
        $this->applicationRedirectUriFactory = $applicationRedirectUriFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;
        $this->userFactory = $userFactory;
    }

    /**
     * Display Page
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'applications-page';

        return $this->render($request, $response);
    }

    /**
     * Display page grid
     */
    public function grid(Request $request, Response $response)
    {
        $this->getState()->template = 'grid';

        $applications = $this->applicationFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([], $request));

        foreach ($applications as $application) {
            /* @var Application $application */
            if ($this->isApi($request))
                return $response->write('Nope');

            // Include the buttons property
            $application->includeProperty('buttons');

            // Add an Edit button (edit form also exposes the secret - not possible to get through the API)
            $application->buttons = [];

            if ($application->userId == $this->getUser($request)->userId || $this->getUser($request)->getUserTypeId() == 1) {

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
     */
    public function authorizeRequest(Request $request, Response $response)
    {
        // Pull authorize params from our session
        if (!$authParams = $this->session->get('authParams'))
            throw new \InvalidArgumentException(__('Authorisation Parameters missing from session.'));

        // Get, show page
        $this->getState()->template = 'applications-authorize-page';
        $this->getState()->setData([
            'authParams' => $authParams
        ]);

        $this->render($request, $response);
    }

    /**
     * Authorize an oAuth request
     * @throws \League\OAuth2\Server\Exception\InvalidGrantException
     */
    public function authorize(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getParams());
        // Pull authorize params from our session
        if (!$authParams = $this->session->get('authParams'))
            throw new \InvalidArgumentException(__('Authorisation Parameters missing from session.'));

        // We are authorized
        if ($sanitizedQueryParams->getString('authorization') === 'Approve') {

            // Create a server
            $server = new AuthorizationServer();

            $server->setSessionStorage(new ApiSessionStorage($this->store));
            $server->setAccessTokenStorage(new ApiAccessTokenStorage($this->store));
            $server->setClientStorage(new ApiClientStorage($this->store));
            $server->setScopeStorage(new ApiScopeStorage($this->store));
            $server->setAuthCodeStorage(new ApiAuthCodeStorage($this->store));

            $authCodeGrant = new AuthCodeGrant();
            $server->addGrantType($authCodeGrant);

            // TODO: Add scopes element to $authParams based on the selections granted in the form

            // Authorize the request
            $redirectUri = $server->getGrantType('authorization_code')->newAuthorizeRequest('user', $this->getUser($request)->userId, $authParams);
        }
        else {
            $error = new \League\OAuth2\Server\Exception\AccessDeniedException();
            $error->redirectUri = $authParams['redirect_uri'];

            $redirectUri = RedirectUri::make($authParams['redirect_uri'], [
                'error' => $error->errorType,
                'message' => $error->getMessage()
            ]);
        }

        $this->getLog()->debug('Redirect URL is %s', $redirectUri);

        return $response->withRedirect($redirectUri, 302);
    }

    /**
     * Form to register a new application.
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
     * @param $clientId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser($request)->userId && $this->getUser($request)->getUserTypeId() != 1) {
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
     * @param $clientId
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser($request)->userId && $this->getUser($request)->getUserTypeId() != 1)
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
     * @throws InvalidArgumentException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $application = $this->applicationFactory->create();
        $application->name = $sanitizedParams->getString('name');

        if ($application->name == '' ) {
            throw new InvalidArgumentException(__('Please enter Application name'), 'name');
        }

        $application->userId = $this->getUser($request)->userId;
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
     * @throws InvalidArgumentException
     * @throws \Xibo\Exception\NotFoundException
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
     * @param $clientId
     * @throws \Xibo\Exception\XiboException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $this->getLog()->debug('Editing ' . $id);

        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser($request)->userId && $this->getUser($request)->getUserTypeId() != 1) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());;
        $client->name = $sanitizedParams->getString('name');
        $client->authCode = $sanitizedParams->getCheckbox('authCode');
        $client->clientCredentials = $sanitizedParams->getCheckbox('clientCredentials');

        if ($sanitizedParams->getCheckbox('resetKeys') == 1) {
            $client->resetKeys();
        }

        // Delete all the redirect urls and add them again
        $client->load();

        foreach ($client->redirectUris as $uri) {
            /* @var \Xibo\Entity\ApplicationRedirectUri $uri */
            $uri->delete();
        }

        $client->redirectUris = [];

        // Do we have a redirect?
        $redirectUris = $sanitizedParams->getArray('redirectUri');

        foreach ($redirectUris as $redirectUri) {
            if ($redirectUri == '')
                continue;

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
                /** @var ApplicationScope $existingScope */
                if ($scope->id == $existingScope->id) {
                    $found = true;
                    break;
                }
            }

            // Assign or unassign as necessary
            if ($checked && !$found)
                $client->assignScope($scope);
            else if (!$checked && $found)
                $client->unassignScope($scope);
        }

        // Change the ownership?
        if ($sanitizedParams->getInt('userId') !== null) {
            // Check we have permissions to view this user
            $user = $this->userFactory->getById($sanitizedParams->getInt('userId'));

            $this->getLog()->debug('Attempting to change ownership to ' . $user->userId . ' - ' . $user->userName);

            if (!$this->getUser()->checkViewable($user))
                throw new InvalidArgumentException('You do not have permission to assign this user', 'userId');

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
     * @param $clientId
     */
    public function delete(Request $request, Response $response, $id)
    {
        // Get the client
        $client = $this->applicationFactory->getById($id);

        if ($client->userId != $this->getUser($request)->userId && $this->getUser($request)->getUserTypeId() != 1) {
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
