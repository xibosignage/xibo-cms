<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use RobThree\Auth\TwoFactorAuth;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Permission;
use Xibo\Event\LayoutOwnerChangeEvent;
use Xibo\Event\LayoutSharingChangeEvent;
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\ApplicationFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\SessionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserTypeFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\QuickChartQRProvider;
use Xibo\Helper\Random;
use Xibo\Service\MediaService;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class User
 * @package Xibo\Controller
 */
class User extends Base
{
    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var UserTypeFactory
     */
    private $userTypeFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var ApplicationFactory
     */
    private $applicationFactory;

    /** @var SessionFactory */
    private $sessionFactory;

    /** @var MediaService */
    private $mediaService;

    /**
     * Set common dependencies.
     * @param UserFactory $userFactory
     * @param UserTypeFactory $userTypeFactory
     * @param UserGroupFactory $userGroupFactory
     * @param PermissionFactory $permissionFactory
     * @param ApplicationFactory $applicationFactory
     * @param SessionFactory $sessionFactory
     * @param MediaService $mediaService
     */
    public function __construct(
        $userFactory,
        $userTypeFactory,
        $userGroupFactory,
        $permissionFactory,
        $applicationFactory,
        $sessionFactory,
        MediaService $mediaService
    ) {
        $this->userFactory = $userFactory;
        $this->userTypeFactory = $userTypeFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->permissionFactory = $permissionFactory;
        $this->applicationFactory = $applicationFactory;
        $this->sessionFactory = $sessionFactory;
        $this->mediaService = $mediaService;
    }

    private function getMediaService(\Xibo\Entity\User $user): MediaService
    {
        $this->mediaService->setUser($user);
        return $this->mediaService;
    }

    /**
     * Home Page
     * this redirects to the appropriate page for this user.
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function home(Request $request, Response $response)
    {
        // Should we show this user the welcome page?
        if ($this->getUser()->newUserWizard == 0) {
            return $response->withRedirect($this->urlFor($request, 'welcome.view'));
        }

        // User wizard seen, go to home page
        $this->getLog()->debug('Showing the homepage: ' . $this->getUser()->homePageId);

        try {
            $homepage = $this->userGroupFactory->getHomepageByName($this->getUser()->homePageId);
        } catch (NotFoundException $exception) {
            return $response->withRedirect($this->urlFor($request, 'icondashboard.view'));
        }

        if (!$this->getUser()->featureEnabled($homepage->feature)) {
            return $response->withRedirect($this->urlFor($request, 'icondashboard.view'));
        } else {
            return $response->withRedirect($this->urlFor($request, $homepage->homepage));
        }
    }

    /**
     * Welcome Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function welcome(Request $request, Response $response)
    {
        $this->getState()->template = 'welcome-page';

        // Mark the page as seen
        if ($this->getUser()->newUserWizard == 0) {
            $this->getUser()->newUserWizard = 1;
            $this->getUser()->save(['validate' => false]);
        }

        return $this->render($request, $response);
    }

    /**
     * Controls which pages are to be displayed
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'user-page';
        $this->getState()->setData([
            'userTypes' => $this->userTypeFactory->query()
        ]);

        return $this->render($request, $response);
    }

    /**
     * Me
     *
     * @SWG\Get(
     *  path="/user/me",
     *  operationId="userMe",
     *  tags={"user"},
     *  summary="Get Me",
     *  description="Get my details",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/User")
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function myDetails(Request $request, Response $response)
    {
        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'data' => $this->getUser()
        ]);

        return $this->render($request, $response);
    }

    /**
     * Prints the user information in a table based on a check box selection
     *
     * @SWG\Get(
     *  path="/user",
     *  operationId="userSearch",
     *  tags={"user"},
     *  summary="User Search",
     *  description="Search users",
     *  @SWG\Parameter(
     *      name="userId",
     *      in="query",
     *      description="Filter by User Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userName",
     *      in="query",
     *      description="Filter by User Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userTypeId",
     *      in="query",
     *      description="Filter by UserType Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="query",
     *      description="Filter by Retired",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/User")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function grid(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());

        // Filter our users?
        $filterBy = [
            'userId' => $sanitizedParams->getInt('userId'),
            'userTypeId' => $sanitizedParams->getInt('userTypeId'),
            'userName' => $sanitizedParams->getString('userName'),
            'firstName' => $sanitizedParams->getString('firstName'),
            'lastName' => $sanitizedParams->getString('lastName'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'retired' => $sanitizedParams->getInt('retired'),
            'logicalOperatorName' => $sanitizedParams->getString('logicalOperatorName'),
            'userGroupIdMembers' => $sanitizedParams->getInt('userGroupIdMembers'),
        ];

        // Load results into an array
        $users = $this->userFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter($filterBy, $sanitizedParams));

        foreach ($users as $user) {
            /* @var \Xibo\Entity\User $user */

            $user->setUnmatchedProperty('libraryQuotaFormatted', ByteFormatter::format($user->libraryQuota * 1024));

            $user->loggedIn = $this->sessionFactory->getActiveSessionsForUser($user->userId);
            $this->getLog()->debug('Logged in status for user ID ' . $user->userId . ' with name ' . $user->userName . ' is ' . $user->loggedIn);

            // Set some text for the display status
            $user->setUnmatchedProperty('twoFactorDescription', match ($user->twoFactorTypeId) {
                1 => __('Email'),
                2 => __('Google Authenticator'),
                default => __('Disabled'),
            });

            if ($this->isApi($request)) {
                continue;
            }

            $user->includeProperty('buttons');

            // Deal with the home page
            try {
                $user->setUnmatchedProperty(
                    'homePage',
                    $this->userGroupFactory->getHomepageByName($user->homePageId)->title
                );
            } catch (NotFoundException $exception) {
                $this->getLog()->error('User has homepage which does not exist. userId: ' . $user->userId . ', homepage: ' . $user->homePageId);
                $user->setUnmatchedProperty('homePage', __('Unknown homepage, please edit to update.'));
            }

            // Set the home folder
            $user->setUnmatchedProperty('homeFolder', $user->getUnmatchedProperty('homeFolder', '/'));

            // Super admins have some buttons
            if ($this->getUser()->featureEnabled('users.modify')
                && $this->getUser()->checkEditable($user)
            ) {
                // Edit
                $user->buttons[] = [
                    'id' => 'user_button_edit',
                    'url' => $this->urlFor($request,'user.edit.form', ['id' => $user->userId]),
                    'text' => __('Edit')
                ];
            }

            if ($this->getUser()->featureEnabled('users.modify')
                && $this->getUser()->checkDeleteable($user)
                && $user->userId != $this->getConfig()->getSetting('SYSTEM_USER')
                && $this->getUser()->userId !== $user->userId
                && ( ($this->getUser()->isGroupAdmin() && $user->userTypeId == 3) || $this->getUser()->isSuperAdmin() )
            ) {
                // Delete
                $user->buttons[] = [
                    'id' => 'user_button_delete',
                    'url' => $this->urlFor($request,'user.delete.form', ['id' => $user->userId]),
                    'text' => __('Delete')
                ];
            }

            if ($this->getUser()->featureEnabled('folder.userHome')) {
                $user->buttons[] = [
                    'id' => 'user_button_set_home',
                    'url' => $this->urlFor($request, 'user.homeFolder.form', ['id' => $user->userId]),
                    'text' => __('Set Home Folder'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request, 'user.homeFolder', ['id' => $user->userId])],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'user_button_set_home'],
                        ['name' => 'text', 'value' => __('Set home folder')],
                        ['name' => 'rowtitle', 'value' => $user->userName],
                        ['name' => 'form-callback', 'value' => 'userHomeFolderMultiselectFormOpen']
                    ],
                ];
            }

            if ($this->getUser()->featureEnabled('users.modify')
                && $this->getUser()->checkPermissionsModifyable($user)
            ) {
                $user->buttons[] = ['divider' => true];

                // User Groups
                $user->buttons[] = array(
                    'id' => 'user_button_group_membership',
                    'url' => $this->urlFor($request,'user.membership.form', ['id' => $user->userId]),
                    'text' => __('User Groups')
                );
            }

            if ($this->getUser()->isSuperAdmin()) {
                $user->buttons[] = ['divider' => true];

                // Features
                $user->buttons[] = [
                    'id' => 'user_button_page_security',
                    'url' => $this->urlFor($request,'group.acl.form', ['id' => $user->groupId, 'userId' => $user->userId]),
                    'text' => __('Features'),
                    'title' => __('Turn Features on/off for this User')
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->userFactory->countLast();
        $this->getState()->setData($users);

        return $this->render($request, $response);
    }

    /**
     * Adds a user
     *
     * @SWG\Post(
     *  path="/user",
     *  operationId="userAdd",
     *  tags={"user"},
     *  summary="Add User",
     *  description="Add a new User",
     *  @SWG\Parameter(
     *      name="userName",
     *      in="formData",
     *      description="The User Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="email",
     *      in="formData",
     *      description="The user email address",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userTypeId",
     *      in="formData",
     *      description="The user type ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="homePageId",
     *      in="formData",
     *      description="The homepage to use for this User",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="libraryQuota",
     *      in="formData",
     *      description="The users library quota in kilobytes",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="password",
     *      in="formData",
     *      description="The users password",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="groupId",
     *      in="formData",
     *      description="The inital user group for this User",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="firstName",
     *      in="formData",
     *      description="The users first name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="lastName",
     *      in="formData",
     *      description="The users last name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="phone",
     *      in="formData",
     *      description="The users phone number",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref1",
     *      in="formData",
     *      description="Reference 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref2",
     *      in="formData",
     *      description="Reference 2",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref3",
     *      in="formData",
     *      description="Reference 3",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref4",
     *      in="formData",
     *      description="Reference 4",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref5",
     *      in="formData",
     *      description="Reference 5",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="newUserWizard",
     *      in="formData",
     *      description="Flag indicating whether to show the new user guide",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="hideNavigation",
     *      in="formData",
     *      description="Flag indicating whether to hide the navigation",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="isPasswordChangeRequired",
     *      in="formData",
     *      description="A flag indicating whether password change should be forced for this user",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/User"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function add(Request $request, Response $response)
    {
        // Only group admins or super admins can create Users.
        if (!$this->getUser()->isSuperAdmin() && !$this->getUser()->isGroupAdmin()) {
            throw new AccessDeniedException(__('Only super and group admins can create users'));
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Build a user entity and save it
        $user = $this->userFactory->create();
        $user->setChildAclDependencies($this->userGroupFactory);

        $user->userName = $sanitizedParams->getString('userName');
        $user->email = $sanitizedParams->getString('email');
        $user->homePageId = $sanitizedParams->getString('homePageId');
        $user->libraryQuota = $sanitizedParams->getInt('libraryQuota', ['default' => 0]);
        $user->setNewPassword($sanitizedParams->getString('password'));

        // Are user home folders enabled? If not, use the default.
        if ($this->getUser()->featureEnabled('folder.userHome')) {
            $user->homeFolderId = $sanitizedParams->getInt('homeFolderId');
        } else {
            $user->homeFolderId = 1;
        }

        if ($this->getUser()->isSuperAdmin()) {
            $user->userTypeId = $sanitizedParams->getInt('userTypeId');
            $user->isSystemNotification = $sanitizedParams->getCheckbox('isSystemNotification');
            $user->isDisplayNotification = $sanitizedParams->getCheckbox('isDisplayNotification');
        } else {
            $user->userTypeId = 3;
            $user->isSystemNotification = 0;
            $user->isDisplayNotification = 0;
        }

        $user->firstName = $sanitizedParams->getString('firstName');
        $user->lastName = $sanitizedParams->getString('lastName');
        $user->phone = $sanitizedParams->getString('phone');
        $user->ref1 = $sanitizedParams->getString('ref1');
        $user->ref2 = $sanitizedParams->getString('ref2');
        $user->ref3 = $sanitizedParams->getString('ref3');
        $user->ref4 = $sanitizedParams->getString('ref4');
        $user->ref5 = $sanitizedParams->getString('ref5');

        // Options
        $user->newUserWizard = $sanitizedParams->getCheckbox('newUserWizard');
        $user->setOptionValue('hideNavigation', $sanitizedParams->getCheckbox('hideNavigation'));
        $user->isPasswordChangeRequired = $sanitizedParams->getCheckbox('isPasswordChangeRequired');

        // Initial user group
        $group = $this->userGroupFactory->getById($sanitizedParams->getInt('groupId'));

        if ($group->isUserSpecific == 1) {
            throw new InvalidArgumentException(__('Invalid user group selected'), 'groupId');
        }

        // Save the user
        $user->save();

        // Assign the initial group
        $group->assignUser($user);
        $group->save(['validate' => false]);

        // Handle enabled features for the homepage.
        if (!empty($user->homePageId)) {
            $homepage = $this->userGroupFactory->getHomepageByName($user->homePageId);
            if (!empty($homepage->feature) && !$user->featureEnabled($homepage->feature)) {
                throw new InvalidArgumentException(__('User does not have the enabled Feature for this Dashboard'), 'homePageId');
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $user->userName),
            'id' => $user->userId,
            'data' => $user
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit a user
     *
     * @SWG\Put(
     *  path="/user/{userId}",
     *  operationId="userEdit",
     *  tags={"user"},
     *  summary="Edit User",
     *  description="Edit existing User",
     *  @SWG\Parameter(
     *      name="userId",
     *      in="path",
     *      description="The user ID to edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="userName",
     *      in="formData",
     *      description="The User Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="email",
     *      in="formData",
     *      description="The user email address",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userTypeId",
     *      in="formData",
     *      description="The user type ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="homePageId",
     *      in="formData",
     *      description="The homepage to use for this User",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="libraryQuota",
     *      in="formData",
     *      description="The users library quota in kilobytes",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="newPassword",
     *      in="formData",
     *      description="New User password",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retypeNewPassword",
     *      in="formData",
     *      description="Repeat the new User password",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Flag indicating whether to retire this user",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="firstName",
     *      in="formData",
     *      description="The users first name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="lastName",
     *      in="formData",
     *      description="The users last name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="phone",
     *      in="formData",
     *      description="The users phone number",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref1",
     *      in="formData",
     *      description="Reference 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref2",
     *      in="formData",
     *      description="Reference 2",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref3",
     *      in="formData",
     *      description="Reference 3",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref4",
     *      in="formData",
     *      description="Reference 4",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref5",
     *      in="formData",
     *      description="Reference 5",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="newUserWizard",
     *      in="formData",
     *      description="Flag indicating whether to show the new user guide",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="hideNavigation",
     *      in="formData",
     *      description="Flag indicating whether to hide the navigation",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="isPasswordChangeRequired",
     *      in="formData",
     *      description="A flag indicating whether password change should be forced for this user",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/User"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);

        if (!$this->getUser()->checkEditable($user)) {
            throw new AccessDeniedException();
        }

        $this->getLog()->debug('User Edit process started.');

        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Build a user entity and save it
        $user->setChildAclDependencies($this->userGroupFactory);
        $user->load();
        $user->userName = $sanitizedParams->getString('userName');
        $user->email = $sanitizedParams->getString('email');
        $user->homePageId = $sanitizedParams->getString('homePageId');
        $user->libraryQuota = $sanitizedParams->getInt('libraryQuota');
        $user->retired = $sanitizedParams->getCheckbox('retired');

        // Are user home folders enabled? Don't change unless they are.
        if ($this->getUser()->featureEnabled('folder.userHome')) {
            $user->homeFolderId = $sanitizedParams->getInt('homeFolderId');
        }

        if ($this->getUser()->isSuperAdmin()) {
            $user->userTypeId = $sanitizedParams->getInt('userTypeId');
            if ($user->retired === 1) {
                $user->isSystemNotification = 0;
                $user->isDisplayNotification = 0;
                $user->isDataSetNotification = 0;
                $user->isCustomNotification = 0;
                $user->isLayoutNotification = 0;
                $user->isLibraryNotification = 0;
                $user->isReportNotification = 0;
                $user->isScheduleNotification = 0;
            } else {
                $user->isSystemNotification = $sanitizedParams->getCheckbox('isSystemNotification');
                $user->isDisplayNotification = $sanitizedParams->getCheckbox('isDisplayNotification');
                $user->isDataSetNotification = $sanitizedParams->getCheckbox('isDataSetNotification');
                $user->isCustomNotification = $sanitizedParams->getCheckbox('isCustomNotification');
                $user->isLayoutNotification = $sanitizedParams->getCheckbox('isLayoutNotification');
                $user->isLibraryNotification = $sanitizedParams->getCheckbox('isLibraryNotification');
                $user->isReportNotification = $sanitizedParams->getCheckbox('isReportNotification');
                $user->isScheduleNotification = $sanitizedParams->getCheckbox('isScheduleNotification');
            }
        }

        $user->firstName = $sanitizedParams->getString('firstName');
        $user->lastName = $sanitizedParams->getString('lastName');
        $user->phone = $sanitizedParams->getString('phone');
        $user->ref1 = $sanitizedParams->getString('ref1');
        $user->ref2 = $sanitizedParams->getString('ref2');
        $user->ref3 = $sanitizedParams->getString('ref3');
        $user->ref4 = $sanitizedParams->getString('ref4');
        $user->ref5 = $sanitizedParams->getString('ref5');

        // Options
        $user->newUserWizard = $sanitizedParams->getCheckbox('newUserWizard');
        $user->setOptionValue('hideNavigation', $sanitizedParams->getCheckbox('hideNavigation'));
        $user->isPasswordChangeRequired = $sanitizedParams->getCheckbox('isPasswordChangeRequired');

        $this->getLog()->debug('Params read');

        // Handle enabled features for the homepage.
        $homepage = $this->userGroupFactory->getHomepageByName($user->homePageId);
        if (!empty($homepage->feature) && !$user->featureEnabled($homepage->feature)) {
            throw new InvalidArgumentException(
                __('User does not have the enabled Feature for this Dashboard'),
                'homePageId'
            );
        }

        $this->getLog()->debug('Homepage validated.');

        // If we are a super admin
        if ($this->getUser()->userTypeId == 1) {
            $newPassword = $sanitizedParams->getString('newPassword');
            $retypeNewPassword = $sanitizedParams->getString('retypeNewPassword');
            $disableTwoFactor = $sanitizedParams->getCheckbox('disableTwoFactor');

            if ($newPassword != null && $newPassword != '') {
                $this->getLog()->debug('New password provided, checking.');

                // Make sure they are the same
                if ($newPassword != $retypeNewPassword) {
                    throw new InvalidArgumentException(__('Passwords do not match'));
                }

                // Set the new password
                $user->setNewPassword($newPassword);
            }

            // super admin can clear the twoFactorTypeId and secret for the user.
            if ($disableTwoFactor) {
                $user->clearTwoFactor();
            }
        }

        $this->getLog()->debug('About to save.');

        // Save the user
        $user->save();

        $this->getLog()->debug('User saved, about to return.');

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $user->userName),
            'id' => $user->userId,
            'data' => $user
        ]);

        return $this->render($request, $response);
    }

    /**
     * Deletes a User
     *
     * @SWG\Delete(
     *  path="/user/{userId}",
     *  operationId="userDelete",
     *  tags={"user"},
     *  summary="User Delete",
     *  description="Delete user",
     *  @SWG\Parameter(
     *      name="userId",
     *      in="path",
     *      description="Id of the user to delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="deleteAllItems",
     *      in="formData",
     *      description="Flag indicating whether to delete all items owned by that user",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="reassignUserId",
     *      in="formData",
     *      description="Reassign all items owned by this user to the specified user ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/User")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function delete(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // System User
        if ($user->userId == $this->getConfig()->getSetting('SYSTEM_USER')) {
            throw new InvalidArgumentException(__('This User is set as System User and cannot be deleted.'), 'userId');
        }

        if (!$this->getUser()->checkDeleteable($user)) {
            throw new AccessDeniedException();
        }

        if ($this->getUser()->userId === $user->userId) {
            throw new InvalidArgumentException(__('Cannot delete your own User from the CMS.'));
        }

        if ($this->getUser()->isGroupAdmin() && $user->userTypeId !== 3) {
            throw new InvalidArgumentException(__('Group Admin cannot remove Super Admins or other Group Admins.'));
        }

        if ($sanitizedParams->getCheckbox('deleteAllItems') && $user->isSuperAdmin()) {
            throw new InvalidArgumentException(__('Cannot delete all items owned by a Super Admin, please reassign to a different User.'));
        }

        $user->setChildAclDependencies($this->userGroupFactory);

        if ($sanitizedParams->getCheckbox('deleteAllItems') != 1) {
            // Do we have a userId to reassign content to?
            if ($sanitizedParams->getInt('reassignUserId') != null) {
                // Reassign all content owned by this user to the provided user
                $this->getLog()->debug(sprintf('Reassigning content to new userId: %d', $sanitizedParams->getInt('reassignUserId')));
                $this->getDispatcher()->dispatch(
                    UserDeleteEvent::$NAME,
                    new UserDeleteEvent(
                        $user,
                        'reassignAll',
                        $this->userFactory->getSystemUser(),
                        $this->userFactory->getById($sanitizedParams->getInt('reassignUserId'))
                    )
                );
            } else {
                // Check to see if we have any child data that would prevent us from deleting
                /** @var UserDeleteEvent $countChildren */
                $countChildren = $this->getDispatcher()->dispatch(UserDeleteEvent::$NAME, new UserDeleteEvent($user, 'countChildren', $this->userFactory->getSystemUser()));

                if ($countChildren->getReturnValue() > 0) {
                    throw new InvalidArgumentException(sprintf(__('This user cannot be deleted as it has %d child items'), $countChildren->getReturnValue()));
                }
            }
        }

        $this->getDispatcher()->dispatch(UserDeleteEvent::$NAME, new UserDeleteEvent($user, 'delete', $this->userFactory->getSystemUser()));
        // Delete the user
        $user->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $user->userName),
            'id' => $user->userId
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @return \Psr\Http\Message\ResponseInterface|\Slim\Http\Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function homepages(Request $request, Response $response)
    {
        // Only group admins or super admins can create Users.
        if (!$this->getUser()->isSuperAdmin() && !$this->getUser()->isGroupAdmin()) {
            throw new AccessDeniedException(__('Only super and group admins can create users'));
        }

        // Get all homepages accessible for a user group
        $params = $this->getSanitizer($request->getParams());
        $userId = $params->getInt('userId');

        if ($userId !== null) {
            $homepages = [];
            $user = $this->userFactory->getById($userId)
                ->setChildAclDependencies($this->userGroupFactory);

            foreach ($this->userGroupFactory->getHomepages() as $homepage) {
                if (empty($homepage->feature) || $user->featureEnabled($homepage->feature)) {
                    $homepages[] = $homepage;
                }
            }
        } else {
            $userTypeId = $params->getInt('userTypeId', [
                'throw' => function () {
                    throw new NotFoundException();
                }
            ]);

            if ($userTypeId == 1) {
                $homepages = $this->userGroupFactory->getHomepages();
            } else {
                $groupId = $params->getInt('groupId', [
                    'throw' => function () {
                        throw new NotFoundException();
                    }
                ]);
                $group = $this->userGroupFactory->getById($groupId);

                $homepages = [];
                foreach ($this->userGroupFactory->getHomepages() as $homepage) {
                    if (empty($homepage->feature) || in_array($homepage->feature, $group->features)) {
                        $homepages[] = $homepage;
                    }
                }
            }
        }

        // Prepare output
        $this->getState()->template = 'grid';

        // Have we asked for a specific homepage?
        $homepageFilter = $params->getString('homepage');
        if ($homepageFilter !== null) {
            if (array_key_exists($homepageFilter, $homepages)) {
                $this->getState()->recordsTotal = 1;
                $this->getState()->setData([$homepages[$homepageFilter]]);
                return $this->render($request, $response);
            } else {
                throw new NotFoundException(__('Homepage not found'));
            }
        }

        $this->getState()->recordsTotal = count($homepages);
        $this->getState()->setData(array_values($homepages));

        return $this->render($request, $response);
    }

    /**
     * User Add Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function addForm(Request $request, Response $response)
    {
        // Only group admins or super admins can create Users.
        if (!$this->getUser()->isSuperAdmin() && !$this->getUser()->isGroupAdmin()) {
            throw new AccessDeniedException(__('Only super and group admins can create users'));
        }

        $defaultUserTypeId = 3;
        foreach ($this->userTypeFactory->query(null, [
            'userType' => $this->getConfig()->getSetting('defaultUsertype')
        ]) as $defaultUserType) {
            $defaultUserTypeId = $defaultUserType->userTypeId;
        }

        $this->getState()->template = 'user-form-add';
        $this->getState()->setData([
            'options' => [
                'userTypes' => ($this->getUser()->isSuperAdmin()) ? $this->userTypeFactory->getAllRoles() : $this->userTypeFactory->getNonAdminRoles(),
                'defaultGroupId' => $this->getConfig()->getSetting('DEFAULT_USERGROUP'),
                'defaultUserType' => $defaultUserTypeId
            ],
        ]);

        return $this->render($request, $response);
    }

    /**
     * User Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);
        $user->setChildAclDependencies($this->userGroupFactory);

        if (!$this->getUser()->checkEditable($user)) {
            throw new AccessDeniedException();
        }

        $homepage = [];
        try {
            $homepage = $this->userGroupFactory->getHomepageByName($user->homePageId);
        } catch (NotFoundException $notFoundException) {
            $this->getLog()->error(sprintf('User %d has non existing homepage %s', $user->userId, $user->homePageId));
        }

        $this->getState()->template = 'user-form-edit';
        $this->getState()->setData([
            'user' => $user,
            'options' => [
                'homepage' => $homepage,
                'userTypes' => ($this->getUser()->isSuperAdmin()) ? $this->userTypeFactory->getAllRoles() : $this->userTypeFactory->getNonAdminRoles()
            ],
        ]);

        return $this->render($request, $response);
    }

    /**
     * User Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($user)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'user-form-delete';
        $this->getState()->setData([
            'user' => $user,
            'users' => $this->userFactory->query(null, ['notUserId' => $id]),
        ]);

        return $this->render($request, $response);
    }

    /**
     * Change my password form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editProfileForm(Request $request, Response $response)
    {
        $user = $this->getUser();

        $this->getState()->template = 'user-form-edit-profile';
        $this->getState()->setData([
            'user' => $user,
            'data' => [
                'setup' => $this->urlFor($request,'user.setup.profile'),
                'generate' => $this->urlFor($request,'user.recovery.generate.profile'),
                'show' => $this->urlFor($request,'user.recovery.show.profile'),
            ]
        ]);

        return $this->render($request, $response);
    }

    /**
     * Change my Password
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \QRException
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function editProfile(Request $request, Response $response)
    {
        $user = $this->getUser();

        // get all other values from the form
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $oldPassword = $sanitizedParams->getString('password');
        $newPassword = $sanitizedParams->getString('newPassword');
        $retypeNewPassword = $sanitizedParams->getString('retypeNewPassword');
        $user->email = $sanitizedParams->getString('email');
        $user->twoFactorTypeId = $sanitizedParams->getInt('twoFactorTypeId');
        $code = $sanitizedParams->getString('code');
        $recoveryCodes = $sanitizedParams->getString('twoFactorRecoveryCodes');

        if ($recoveryCodes != null) {
            $user->twoFactorRecoveryCodes = json_decode($recoveryCodes);
        }

        // What situations do we need to check the old password is correct?
        if ($user->hasPropertyChanged('twoFactorTypeId')
            || ($user->hasPropertyChanged('email') && $user->twoFactorTypeId === 1)
            || ($user->hasPropertyChanged('email') && $user->getOriginalValue('twoFactorTypeId') === 1)
            || $newPassword != null
        ) {
            try {
                $user->checkPassword($oldPassword);
            } catch (AccessDeniedException $exception) {
                throw new InvalidArgumentException(__('Please enter your password'), 'password');
            }
        }

        // check if we have a new password provided, if so check if it was correctly entered
        if ($newPassword != $retypeNewPassword) {
            throw new InvalidArgumentException(__('Passwords do not match'), 'newPassword');
        }

        // check if we have saved secret, for google auth that is done on jQuery side
        if (!isset($user->twoFactorSecret) && $user->twoFactorTypeId === 1) {
            $this->tfaSetup($request, $response);
            $user->twoFactorSecret = $_SESSION['tfaSecret'];
            unset($_SESSION['tfaSecret']);
        }

        // if we are setting up email two factor auth, check if the email is entered on the form as well
        if ($user->twoFactorTypeId === 1 && $user->email == '') {
            throw new InvalidArgumentException(__('Please provide valid email address'), 'email');
        }

        // if we are setting up email two factor auth, check if the sending email address is entered in CMS Settings.
        if ($user->twoFactorTypeId === 1 && $this->getConfig()->getSetting('mail_from') == '') {
            throw new InvalidArgumentException(__('Please provide valid sending email address in CMS Settings on Network tab'), 'mail_from');
        }

        // if we have a new password provided, update the user record
        if ($newPassword != null && $newPassword == $retypeNewPassword) {
            $user->setNewPassword($newPassword, $oldPassword);
            $user->isPasswordChangeRequired = 0;
            $user->save([
                'passwordUpdate' => true
            ]);
        }

        // if we are setting up Google auth, we are expecting a code from the form, validate the code here
        // we want to show QR code and validate the access code also with the previous auth method was set to email
        if ($user->twoFactorTypeId === 2
            && ($user->twoFactorSecret === null || $user->getOriginalValue('twoFactorTypeId') === 1)
        ) {
            if (!isset($code)) {
                throw new InvalidArgumentException(__('Access Code is empty'), 'code');
            }

            $validation = $this->tfaValidate($code, $user);

            if (!$validation) {
                unset($_SESSION['tfaSecret']);
                throw new InvalidArgumentException(__('Access Code is incorrect'), 'code');
            }

            if ($validation) {
                // if access code is correct, we want to set the secret to our user - either from session for new 2FA setup or leave it as it is for user changing from email to google auth
                if (!isset($user->twoFactorSecret)) {
                    $secret = $_SESSION['tfaSecret'];
                } else {
                    $secret = $user->twoFactorSecret;
                }

                $user->twoFactorSecret = $secret;
                unset($_SESSION['tfaSecret']);
            }
        }

        // if the two factor type is set to Off, clear any saved secrets and set the twoFactorTypeId to 0 in database.
        if ($user->twoFactorTypeId == 0) {
            $user->clearTwoFactor();
        }

        $user->save();

        // Return
        $this->getState()->hydrate([
            'message' => __('User Profile Saved'),
            'id' => $user->userId,
            'data' => $user
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \QRException
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tfaSetup(Request $request, Response $response)
    {
        $user = $this->getUser();

        $issuerSettings = $this->getConfig()->getSetting('TWOFACTOR_ISSUER');
        $appName = $this->getConfig()->getThemeConfig('app_name');
        $quickChartUrl = $this->getConfig()->getSetting('QUICK_CHART_URL', 'https://quickchart.io');

        if ($issuerSettings !== '') {
            $issuer = $issuerSettings;
        } else {
            $issuer = $appName;
        }

        $tfa = new TwoFactorAuth($issuer, 6, 30, 'sha1', new QuickChartQRProvider($quickChartUrl));

        // create two factor secret and store it in user record
        if (!isset($user->twoFactorSecret)) {
            $secret = $tfa->createSecret();
            $_SESSION['tfaSecret'] = $secret;
        } else {
            $secret = $user->twoFactorSecret;
        }

        // generate the QR code to scan, we only show it at first set up and only for Google auth
        $qRUrl = $tfa->getQRCodeImageAsDataUri($user->userName, $secret, 150);

        $this->getState()->setData([
            'qRUrl' => $qRUrl
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param string $code The Code to validate
     * @param $user
     * @return bool
     * @throws \RobThree\Auth\TwoFactorAuthException
     */
    public function tfaValidate($code, $user)
    {
        $issuerSettings = $this->getConfig()->getSetting('TWOFACTOR_ISSUER');
        $appName = $this->getConfig()->getThemeConfig('app_name');

        if ($issuerSettings !== '') {
            $issuer = $issuerSettings;
        } else {
            $issuer = $appName;
        }

        $tfa = new TwoFactorAuth($issuer);

        if (isset($_SESSION['tfaSecret'])) {
            // validate the provided two factor code with secret for this user
            $result = $tfa->verifyCode($_SESSION['tfaSecret'], $code, 3);
        } elseif (isset($user->twoFactorSecret)) {
            $result = $tfa->verifyCode($user->twoFactorSecret, $code, 3);
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tfaRecoveryGenerate(Request $request, Response $response)
    {
        $user = $this->getUser();

        // clear any existing codes when we generate new ones
        $user->twoFactorRecoveryCodes = [];

        $count = 4;
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Random::generateString(50);
        }

        $user->twoFactorRecoveryCodes =  $codes;

        $this->getState()->setData([
            'codes' => json_encode($codes, JSON_PRETTY_PRINT)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tfaRecoveryShow(Request $request, Response $response)
    {
        $user = $this->getUser();

        $user->twoFactorRecoveryCodes = json_decode($user->twoFactorRecoveryCodes);

        if (isset($_GET["generatedCodes"]) && !empty($_GET["generatedCodes"])) {
            $generatedCodes = $_GET["generatedCodes"];
            $user->twoFactorRecoveryCodes = json_encode($generatedCodes);
        }

        $this->getState()->setData([
            'codes' => $user->twoFactorRecoveryCodes
        ]);

        return $this->render($request, $response);
    }

    /**
     * Force User Password Change
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function forceChangePasswordPage(Request $request, Response $response)
    {
        $user = $this->getUser();

        // if the flag to force change password is not set to 1 then redirect to the Homepage
        if ($user->isPasswordChangeRequired != 1) {
            $response->withRedirect('home');
        }

        $this->getState()->template = 'user-force-change-password-page';

        return $this->render($request, $response);
    }

    /**
     * Force change my Password
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function forceChangePassword(Request $request, Response $response)
    {
        // Save the user
        $user = $this->getUser();
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $newPassword = $sanitizedParams->getString('newPassword');
        $retypeNewPassword = $sanitizedParams->getString('retypeNewPassword');

        if ($newPassword == null || $retypeNewPassword == '')
            throw new InvalidArgumentException(__('Please enter the password'), 'password');

        if ($newPassword != $retypeNewPassword)
            throw new InvalidArgumentException(__('Passwords do not match'), 'password');

        // Make sure that the new password doesn't verify against the existing hash
        try {
            $user->checkPassword($newPassword);
            throw new InvalidArgumentException(__('Please choose a new password'), 'password');
        } catch (AccessDeniedException) {
            // This is good, they don't match.
        }

        $user->setNewPassword($newPassword);
        $user->save([
            'passwordUpdate' => true
        ]);

        $user->isPasswordChangeRequired = 0;
        $user->save();

        // Return
        $this->getState()->hydrate([
            'message' => __('Password Changed'),
            'id' => $user->userId,
            'data' => $user
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/user/permissions/{entity}/{objectId}",
     *  operationId="userPermissionsSearch",
     *  tags={"user"},
     *  summary="Permission Data",
     *  description="Permission data for the Entity and Object Provided.",
     *  @SWG\Parameter(
     *      name="entity",
     *      in="path",
     *      description="The Entity",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="objectId",
     *      in="path",
     *      description="The ID of the Object to return permissions for",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Permission")
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param string $entity
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function permissionsGrid(Request $request, Response $response, $entity, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Load our object
        $object = $this->parsePermissionsEntity($entity, $id);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object)) {
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));
        }

        // List of all Groups with a view / edit / delete check box
        $permissions = $this->permissionFactory->getAllByObjectId($this->getUser(), $object->permissionsClass(), $id, $this->gridRenderSort($sanitizedParams), $this->gridRenderFilter(['name' => $sanitizedParams->getString('name')], $sanitizedParams));

        $this->getState()->template = 'grid';
        $this->getState()->setData($permissions);
        $this->getState()->recordsTotal = $this->permissionFactory->countLast();

        return $this->render($request,  $response);
    }


    /**
     * @SWG\Get(
     *  path="/user/permissions/{entity}",
     *  operationId="userPermissionsMultiSearch",
     *  tags={"user"},
     *  summary="Permission Data",
     *  description="Permission data for the multiple Entities and Objects Provided.",
     *  @SWG\Parameter(
     *      name="entity",
     *      in="path",
     *      description="The Entity",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="ids",
     *      in="query",
     *      description="The IDs of the Objects to return permissions for",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Permission")
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param string $entities
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function permissionsMultiGrid(Request $request, Response $response, $entity)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check if the array of ids is passed
        if($sanitizedParams->getString('ids') == '') {
            throw new InvalidArgumentException(__('The array of ids is empty!'));
        }

        // Get array of ids
        $ids = explode(',', $sanitizedParams->getString('ids'));

        // Array of all the permissions
        $permissions = [];
        $newPermissions = [];
        $objects = [];

        // Load our objects
        for ($i=0; $i < count($ids); $i++) {
            $objectId = $ids[$i];

            $objects[$i] = $this->parsePermissionsEntity($entity, $objectId);

            // Does this user have permission to edit the permissions?!
            if (!$this->getUser()->checkPermissionsModifyable($objects[$i])) {
                throw new AccessDeniedException(__('You do not have permission to edit all the entities permissions.'));
            }

            // List of all Groups with a view / edit / delete check box
            $permissions = array_merge_recursive($permissions, $this->permissionFactory->getAllByObjectId($this->getUser(), $objects[$i]->permissionsClass(), $objectId, $this->gridRenderSort($sanitizedParams), $this->gridRenderFilter(['name' => $sanitizedParams->getString('name')], $sanitizedParams)));
        }

        // Change permissions structure to be grouped by user group
        foreach ($permissions as $permission) {

            if(!array_key_exists($permission->groupId, $newPermissions)) {
                $newPermissions[$permission->groupId] = [
                    "groupId" => $permission->groupId,
                    "group" => $permission->group,
                    "isUser" => $permission->isUser,
                    "entity" => $permission->entity,
                    "permissions" => [
                        $permission->objectId => [
                            "permissionId" => $permission->permissionId,
                            "view" => $permission->view,
                            "edit" => $permission->edit,
                            "delete" => $permission->delete
                        ]
                    ]
                ];
            } else {
                $newPermissions[$permission->groupId]["permissions"][] = [
                    "permissionId" => $permission->permissionId,
                    "view" => $permission->view,
                    "edit" => $permission->edit,
                    "delete" => $permission->delete
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($newPermissions);
        $this->getState()->recordsTotal = $this->permissionFactory->countLast();

        return $this->render($request,  $response);
    }

    /**
     * Permissions to users for the provided entity
     * @param Request $request
     * @param Response $response
     * @param $entity
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function permissionsForm(Request $request, Response $response, $entity, $id)
    {
        $requestEntity = $entity;

        // Load our object
        $object = $this->parsePermissionsEntity($entity, $id);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object)) {
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));
        }

        $currentPermissions = [];
        foreach ($this->permissionFactory->getAllByObjectId($this->getUser(), $object->permissionsClass(), $id, ['groupId'], ['setOnly' => 1]) as $permission) {
            /* @var Permission $permission */
            $currentPermissions[$permission->groupId] = [
                'view' => ($permission->view == null) ? 0 : $permission->view,
                'edit' => ($permission->edit == null) ? 0 : $permission->edit,
                'delete' => ($permission->delete == null) ? 0 : $permission->delete
            ];
        }

        $data = [
            'entity' => $requestEntity,
            'objectId' => $id,
            'permissions' => $currentPermissions,
            'canSetOwner' => $object->canChangeOwner(),
            'object' => $object,
            'objectNameOverride' => $this->getSanitizer($request->getParams())->getString('nameOverride'),
        ];

        $this->getState()->template = 'user-form-permissions';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }


    /**
     * Permissions to users for the provided entity
     * @param Request $request
     * @param Response $response
     * @param $entity
     * @param $ids
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function permissionsMultiForm(Request $request, Response $response, $entity)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check if the array of ids is passed
        if($sanitizedParams->getString('ids') == '') {
            throw new InvalidArgumentException(__('The array of ids is empty!'));
        }

        // Get array of ids
        $ids = $sanitizedParams->getString('ids');

        $data = [
            'entity' => $entity,
            'objectIds' => $ids,
        ];

        $this->getState()->template = 'user-form-multiple-permissions';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *  path="/user/permissions/{entity}/{objectId}",
     *  operationId="userPermissionsSet",
     *  tags={"user"},
     *  summary="Permission Set",
     *  description="Set Permissions to users/groups for the provided entity.",
     *  @SWG\Parameter(
     *      name="entity",
     *      in="path",
     *      description="The Entity",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="objectId",
     *      in="path",
     *      description="The ID of the Object to set permissions on",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="groupIds",
     *      in="formData",
     *      description="Array of permissions with groupId as the key",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="formData",
     *      description="Change the owner of this item. Leave empty to keep the current owner",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param string $entity
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function permissions(Request $request, Response $response, $entity, $id)
    {
        // Load our object
        $object = $this->parsePermissionsEntity($entity, $id);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object)) {
            throw new AccessDeniedException(__('This object is not shared with you with edit permission'));
        }

        if ($object->permissionsClass() === 'Xibo\Entity\Folder' && $object->getId() === 1) {
            throw new InvalidArgumentException(__('You cannot share the root folder'), 'id');
        }

        if ($object->permissionsClass() === 'Xibo\Entity\Region' && $object->type === 'canvas') {
            throw new InvalidArgumentException(
                __('You cannot share the Canvas on a Layout, share the layout instead.'),
                'type',
            );
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Get all current permissions
        $permissions = $this->permissionFactory->getAllByObjectId($this->getUser(), $object->permissionsClass(), $id);

        // Get the provided permissions
        $groupIds = $sanitizedParams->getArray('groupIds');

        // Run the update
        $this->updatePermissions($permissions, $groupIds);

        // Should we update the owner?
        if ($sanitizedParams->getInt('ownerId') != 0) {
            $ownerId = $sanitizedParams->getInt('ownerId');

            $this->getLog()->debug('Requesting update to a new Owner - id = ' . $ownerId);

            if ($object->canChangeOwner()) {
                $object->setOwner($ownerId);
                $object->save([
                    'notify' => false,
                    'manageDynamicDisplayLinks' => false,
                    'validate' => false,
                    'recalculateHash' => false
                ]);
            } else {
                throw new ConfigurationException(__('Cannot change owner on this Object'));
            }

            // Nasty handling for ownerId on the Layout
            // ideally we'd remove that column and rely on the campaign ownerId in 1.9 onward
            if ($object->permissionsClass() == 'Xibo\Entity\Campaign') {
                $this->getLog()->debug('Changing owner on child Layout');

                $this->getDispatcher()->dispatch(
                    new LayoutOwnerChangeEvent($object->getId(), $ownerId),
                    LayoutOwnerChangeEvent::$NAME,
                );
            }
        }

        if ($object->permissionsClass() === 'Xibo\Entity\Folder') {
            /** @var $object \Xibo\Entity\Folder */
            $object->managePermissions();
        } else if ($object->permissionsClass() === 'Xibo\Entity\Campaign') {
            // Update any Canvas Regions to have the same permissions.
            $event = new LayoutSharingChangeEvent($object->getId());
            $this->getDispatcher()->dispatch($event, LayoutSharingChangeEvent::$NAME);

            foreach ($event->getCanvasRegionIds() as $canvasRegionId) {
                $this->getLog()->debug('permissions: canvas region detected, cascading permissions');
                $permissions = $this->permissionFactory->getAllByObjectId(
                    $this->getUser(),
                    'Xibo\Entity\Region',
                    $canvasRegionId,
                );
                $this->updatePermissions($permissions, $groupIds);
            }
        } else if ($object->permissionsClass() === 'Xibo\Entity\Region') {
            /** @var $object \Xibo\Entity\Region */
            // The regions own playlist should always have the same permissions.
            $permissions = $this->permissionFactory->getAllByObjectId(
                $this->getUser(),
                'Xibo\Entity\Playlist',
                $object->getPlaylist()->playlistId
            );

            $this->updatePermissions($permissions, $groupIds);
        }

        // Return
        $this->getState()->hydrate([
            'httpCode' => 204,
            'message' => __('Share option Updated')
        ]);

        return $this->render($request, $response);
    }


    /**
     * @SWG\Post(
     *  path="/user/permissions/{entity}/multiple",
     *  operationId="userPermissionsMultiSet",
     *  tags={"user"},
     *  summary="Multiple Permission Set",
     *  description="Set Permissions to users/groups for multiple provided entities.",
     *  @SWG\Parameter(
     *      name="entity",
     *      in="path",
     *      description="The Entity type",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="ids",
     *      in="formData",
     *      description="Array of object IDs",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="groupIds",
     *      in="formData",
     *      description="Array of permissions with groupId as the key",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="formData",
     *      description="Change the owner of this item. Leave empty to keep the current owner",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param string $entity
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function permissionsMulti(Request $request, Response $response, $entity)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Get array of ids
        $ids = ($sanitizedParams->getString('ids') != '') ? explode(',', $sanitizedParams->getString('ids')) : [];

        // Check if the array of ids is passed
        if (count($ids) == 0) {
            throw new InvalidArgumentException(__('The array of ids is empty!'));
        }

        // Set permissions for all the object ids, one by one
        foreach ($ids as $id) {
            $this->permissions($request, $response, $entity, $id);
        }

        // Return
        $this->getState()->hydrate([
            'httpCode' => 204,
            'message' => __('Share option Updated')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Parse the Permissions Entity
     * @param string $entity
     * @param int $objectId
     * @throws InvalidArgumentException
     */
    private function parsePermissionsEntity($entity, $objectId)
    {
        if ($entity == '') {
            throw new InvalidArgumentException(__('Sharing requested without an entity'));
        }

        if ($objectId == 0) {
            throw new InvalidArgumentException(__('Sharing form requested without an object'));
        }

        /** @var ParsePermissionEntityEvent $event */
        $event = $this->getDispatcher()->dispatch(
            new ParsePermissionEntityEvent($entity, $objectId),
            ParsePermissionEntityEvent::$NAME . lcfirst($entity)
        );

        return $event->getObject();
    }

    /**
     * Updates a set of permissions from a set of groupIds
     * @param Permission[] $permissions
     * @param array $groupIds
     */
    private function updatePermissions($permissions, $groupIds)
    {
        $this->getLog()->debug(sprintf('Received Permissions Array to update: %s', var_export($groupIds, true)));

        // List of groupIds with view, edit and del assignments
        foreach ($permissions as $row) {
            // Check and see what permissions we have been provided for this selection
            // If all permissions are 0, then the record is deleted
            if (is_array($groupIds)) {
                if (array_key_exists($row->groupId, $groupIds)) {
                    if(array_key_exists('view', $groupIds[$row->groupId])) {
                        $row->view = $groupIds[$row->groupId]['view'];
                    }

                    if(array_key_exists('edit', $groupIds[$row->groupId])) {
                        $row->edit = $groupIds[$row->groupId]['edit'];
                    }

                    if(array_key_exists('delete', $groupIds[$row->groupId])) {
                        $row->delete = $groupIds[$row->groupId]['delete'];
                    }

                    $row->save();
                }
            }
        }
    }

    /**
     * User Applications
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function myApplications(Request $request, Response $response)
    {
        $this->getState()->template = 'user-applications-form';
        $this->getState()->setData([
            'applications' => $this->applicationFactory->getAuthorisedByUserId($this->getUser()->userId),
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *     path="/user/pref",
     *     operationId="userPrefGet",
     *     tags={"user"},
     *     summary="Retrieve User Preferences",
     *     description="User preferences for non-state information, such as Layout designer zoom levels",
     *     @SWG\Parameter(
     *      name="preference",
     *      in="query",
     *      description="An optional preference",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful response",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserOption")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function pref(Request $request, Response $response)
    {
        $requestedPreference =  $request->getQueryParam('preference');

        if (!empty($requestedPreference)) {
            try {
                $option = $this->getUser()->getOption($requestedPreference);
            } catch (NotFoundException $exception) {
                $option = [];
            }
            $this->getState()->setData($option);
        } else {
            $this->getState()->setData($this->getUser()->getUserOptions());
        }

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *     path="/user/pref",
     *     operationId="userPrefEdit",
     *     tags={"user"},
     *     summary="Save User Preferences",
     *     description="Save User preferences for non-state information, such as Layout designer zoom levels",
     *     @SWG\Parameter(
     *      name="preference",
     *      in="body",
     *      required=true,
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserOption")
     *      )
     *   ),
     *   @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function prefEdit(Request $request, Response $response)
    {
        $parsedRequest = $this->getSanitizer($request->getParsedBody());

        // Update this user preference with the preference array
        $i = 0;
        foreach ($parsedRequest->getArray('preference') as $pref) {
            $i++;

            $sanitizedPref = $this->getSanitizer($pref);

            $option = $sanitizedPref->getString('option');
            $value = $sanitizedPref->getString('value');

            $this->getUser()->setOptionValue($option, $value);
        }

        if ($i > 0) {
            $this->getUser()->save();
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => ($i == 1) ? __('Updated Preference') : __('Updated Preferences')
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function membershipForm(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);

        if (!$this->getUser()->checkEditable($user)) {
            throw new AccessDeniedException();
        }

        // Groups we are assigned to
        $groupsAssigned = $this->userGroupFactory->getByUserId($user->userId);

        $this->getState()->template = 'user-form-membership';
        $this->getState()->setData([
            'user' => $user,
            'extra' => [
                'userGroupsAssigned' => $groupsAssigned
            ],
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function assignUserGroup(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);

        if (!$this->getUser()->checkEditable($user)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Go through each ID to assign
        foreach ($sanitizedParams->getIntArray('userGroupId', ['default' => []]) as $userGroupId) {
            $userGroup = $this->userGroupFactory->getById($userGroupId);

            if (!$this->getUser()->checkEditable($userGroup)) {
                throw new AccessDeniedException(__('Access Denied to UserGroup'));
            }

            $userGroup->assignUser($user);
            $userGroup->save(['validate' => false]);
        }

        // Have we been provided with unassign id's as well?
        foreach ($sanitizedParams->getIntArray('unassignUserGroupId', ['default' => []]) as $userGroupId) {
            $userGroup = $this->userGroupFactory->getById($userGroupId);

            if (!$this->getUser()->checkEditable($userGroup)) {
                throw new AccessDeniedException(__('Access Denied to UserGroup'));
            }

            $userGroup->unassignUser($user);
            $userGroup->save(['validate' => false]);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s assigned to User Groups'), $user->userName),
            'id' => $user->userId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Update the User Welcome Tutorial to Seen
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function userWelcomeSetUnSeen(Request $request, Response $response)
    {
        $this->getUser()->newUserWizard = 0;
        $this->getUser()->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s has started the welcome tutorial'), $this->getUser()->userName)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Update the User Welcome Tutorial to Seen
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function userWelcomeSetSeen(Request $request, Response $response)
    {
        $this->getUser()->newUserWizard = 1;
        $this->getUser()->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s has seen the welcome tutorial'), $this->getUser()->userName)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Preferences Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function preferencesForm(Request $request, Response $response)
    {
        $this->getState()->template = 'user-form-preferences';

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *     path="/user/pref",
     *     operationId="userPrefEditFromForm",
     *     tags={"user"},
     *     summary="Save User Preferences",
     *     description="Save User preferences from the Preferences form.",
     *     @SWG\Parameter(
     *      name="navigationMenuPosition",
     *      in="formData",
     *      required=true,
     *      type="string"
     *   ),
     *     @SWG\Parameter(
     *      name="useLibraryDuration",
     *      in="formData",
     *      required=false,
     *      type="integer"
     *   ),
     *     @SWG\Parameter(
     *      name="showThumbnailColumn",
     *      in="formData",
     *      required=false,
     *      type="integer"
     *   ),
     *     @SWG\Parameter(
     *      name="rememberFolderTreeStateGlobally",
     *      in="formData",
     *      required=false,
     *      type="integer"
     *   ),
     *   @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function prefEditFromForm(Request $request, Response $response)
    {
        $parsedParams = $this->getSanitizer($request->getParams());

        $this->getUser()->setOptionValue('navigationMenuPosition', $parsedParams->getString('navigationMenuPosition', ['defaultOnEmptyString' => true]));
        $this->getUser()->setOptionValue('useLibraryDuration', $parsedParams->getCheckbox('useLibraryDuration'));
        $this->getUser()->setOptionValue('showThumbnailColumn', $parsedParams->getCheckbox('showThumbnailColumn'));
        $this->getUser()->setOptionValue('isAlwaysUseManualAddUserForm', $parsedParams->getCheckbox('isAlwaysUseManualAddUserForm'));
        $this->getUser()->setOptionValue('rememberFolderTreeStateGlobally', $parsedParams->getCheckbox('rememberFolderTreeStateGlobally'));

        // Clear auto submits?
        if ($parsedParams->getCheckbox('autoSubmitClearAll', ['checkboxReturnInteger' => false])) {
            $this->getUser()->removeOptionByPrefix('autoSubmit.');
        }

        $this->getUser()->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Updated Preferences')
        ]);

        return $this->render($request, $response);
    }

    /**
     * User Onboarding Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function onboardingForm(Request $request, Response $response)
    {
        // Only group admins or super admins can create Users.
        if (!$this->getUser()->isSuperAdmin() && !$this->getUser()->isGroupAdmin()) {
            throw new AccessDeniedException(__('Only super and group admins can create users'));
        }

        $this->getState()->template = 'user-form-onboarding';
        $this->getState()->setData([
            'groups' => $this->userGroupFactory->query(null, [
                'isShownForAddUser' => 1
            ])
        ]);

        return $this->render($request, $response);
    }

    /**
     * Set home folder form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function setHomeFolderForm(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);
        $user->setChildAclDependencies($this->userGroupFactory);

        if (!$this->getUser()->checkEditable($user)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'user-form-home-folder';
        $this->getState()->setData([
            'user' => $user
        ]);

        return $this->render($request, $response);
    }

    /**
     * Set home folder form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function setHomeFolder(Request $request, Response $response, $id)
    {
        $user = $this->userFactory->getById($id);
        $user->setChildAclDependencies($this->userGroupFactory);

        if (!$this->getUser()->checkEditable($user)) {
            throw new AccessDeniedException();
        }

        if (!$this->getUser()->featureEnabled('folder.userHome')) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Build a user entity and save it
        $user->setChildAclDependencies($this->userGroupFactory);
        $user->load();
        $user->homeFolderId = $sanitizedParams->getInt('homeFolderId');
        $user->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $user->userName),
            'id' => $user->userId,
            'data' => $user
        ]);

        return $this->render($request, $response);
    }
}
