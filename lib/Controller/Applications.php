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

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Util\RedirectUri;
use Xibo\Entity\Application;
use Xibo\Entity\ApplicationRedirectUri;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\ApplicationFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\ApiAccessTokenStorage;
use Xibo\Storage\ApiAuthCodeStorage;
use Xibo\Storage\ApiClientStorage;
use Xibo\Storage\ApiScopeStorage;
use Xibo\Storage\ApiSessionStorage;


class Applications extends Base
{
    /**
     * Display Page
     */
    public function displayPage()
    {
        $this->getState()->template = 'applications-page';
    }

    /**
     * Display page grid
     */
    public function grid()
    {
        $this->getState()->template = 'grid';

        $applications = ApplicationFactory::query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($applications as $application) {
            /* @var Application $application */
            if ($this->isApi())
                return;

            // Include the buttons property
            $application->includeProperty('buttons');

            // Add an Edit button (edit form also exposes the secret - not possible to get through the API)
            $application->buttons = [];

            if ($application->userId == $this->getUser()->userId || $this->getUser()->getUserTypeId() == 1) {

                // Edit
                $application->buttons[] = array(
                    'id' => 'application_edit_button',
                    'url' => $this->urlFor('application.edit.form', array('id' => $application->key)),
                    'text' => __('Edit')
                );
            }
        }

        $this->getState()->setData($applications);
        $this->getState()->recordsTotal = ApplicationFactory::countLast();
    }

    /**
     * Display the Authorize form.
     */
    public function authorizeRequest()
    {
        // Pull authorize params from our session
        if (!$authParams = $this->getSession()->get('authParams'))
            throw new \InvalidArgumentException(__('Authorisation Parameters missing from session.'));

        // Get, show page
        $this->getState()->template = 'applications-authorize-page';
        $this->getState()->setData([
            'authParams' => $authParams
        ]);
    }

    /**
     * Authorize an oAuth request
     * @throws \League\OAuth2\Server\Exception\InvalidGrantException
     */
    public function authorize()
    {
        // Pull authorize params from our session
        if (!$authParams = $this->getSession()->get('authParams'))
            throw new \InvalidArgumentException(__('Authorisation Parameters missing from session.'));

        // We are authorized
        if (Sanitize::getString('authorization') === 'Approve') {

            // Create a server
            $server = new AuthorizationServer();

            $server->setSessionStorage(new ApiSessionStorage());
            $server->setAccessTokenStorage(new ApiAccessTokenStorage());
            $server->setClientStorage(new ApiClientStorage());
            $server->setScopeStorage(new ApiScopeStorage());
            $server->setAuthCodeStorage(new ApiAuthCodeStorage());

            $authCodeGrant = new AuthCodeGrant();
            $server->addGrantType($authCodeGrant);

            // Authorize the request
            $redirectUri = $server->getGrantType('authorization_code')->newAuthorizeRequest('user', $this->getUser()->userId, $authParams);
        }
        else {
            $error = new \League\OAuth2\Server\Exception\AccessDeniedException();
            $error->redirectUri = $authParams['redirect_uri'];

            $redirectUri = RedirectUri::make($authParams['redirect_uri'], [
                'error' => $error->errorType,
                'message' => $error->getMessage()
            ]);
        }

        Log::debug('Redirect URL is %s', $redirectUri);

        $this->getApp()->redirect($redirectUri, 302);
    }

    /**
     * Form to register a new application.
     */
    public function addForm()
    {
        $this->getState()->template = 'applications-form-add';
        $this->getState()->setData([
            'help' => Help::Link('Services', 'Register')
        ]);
    }

    public function editForm($clientId)
    {
        // Get the client
        $client = ApplicationFactory::getById($clientId);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1)
            throw new AccessDeniedException();

        // Load this clients details.
        $client->load();

        // Render the view
        $this->getState()->template = 'applications-form-edit';
        $this->getState()->setData([
            'client' => $client,
            'help' => Help::Link('Services', 'Register')
        ]);
    }

    /**
     * Register a new application with OAuth
     */
    public function add()
    {
        $application = ApplicationFactory::create();
        $application->name = Sanitize::getString('name');
        $application->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $application->name),
            'data' => $application,
            'id' => $application->key
        ]);
    }

    public function edit($clientId)
    {
        // Get the client
        $client = ApplicationFactory::getById($clientId);

        if ($client->userId != $this->getUser()->userId && $this->getUser()->getUserTypeId() != 1)
            throw new AccessDeniedException();

        $client->name = Sanitize::getString('name');
        $client->authCode = Sanitize::getCheckbox('authCode');
        $client->clientCredentials = Sanitize::getCheckbox('clientCredentials');

        if (Sanitize::getCheckbox('resetKeys') == 1) {
            $client->resetKeys();
        }

        // Delete all the redirect urls and add them again
        $client->load();

        foreach ($client->redirectUris as $uri) {
            $uri->delete();
        }

        $client->redirectUris = [];

        // Do we have a redirect?
        $redirectUris = Sanitize::getStringArray('redirectUri');

        foreach ($redirectUris as $redirectUri) {
            if ($redirectUri == '')
                continue;

            $redirect = new ApplicationRedirectUri();
            $redirect->redirectUri = $redirectUri;
            $client->assignRedirectUri($redirect);
        }

        $client->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $client->name),
            'data' => $client,
            'id' => $client->key
        ]);
    }
}
