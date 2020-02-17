<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

use RobThree\Auth\TwoFactorAuth;
use Xibo\Entity\Campaign;
use Xibo\Entity\Layout;
use Xibo\Entity\Media;
use Xibo\Entity\Permission;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ApplicationFactory;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PageFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\SessionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserTypeFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\QuickChartQRProvider;
use Xibo\Helper\Random;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var ApplicationFactory
     */
    private $applicationFactory;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var SessionFactory */
    private $sessionFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @var PlayerVersionFactory */
    private $playerVersionFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var DataSetFactory */
    private $dataSetFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param UserFactory $userFactory
     * @param UserTypeFactory $userTypeFactory
     * @param UserGroupFactory $userGroupFactory
     * @param PageFactory $pageFactory
     * @param PermissionFactory $permissionFactory
     * @param LayoutFactory $layoutFactory
     * @param ApplicationFactory $applicationFactory
     * @param CampaignFactory $campaignFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayFactory $displayFactory
     * @param SessionFactory $sessionFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param WidgetFactory $widgetFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param PlaylistFactory $playlistFactory
     * @param DataSetFactory $dataSetFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $userFactory,
                                $userTypeFactory, $userGroupFactory, $pageFactory, $permissionFactory,
                                $layoutFactory, $applicationFactory, $campaignFactory, $mediaFactory, $scheduleFactory, $displayFactory, $sessionFactory, $displayGroupFactory, $widgetFactory, $playerVersionFactory, $playlistFactory, $dataSetFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->userFactory = $userFactory;
        $this->userTypeFactory = $userTypeFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->pageFactory = $pageFactory;
        $this->permissionFactory = $permissionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->applicationFactory = $applicationFactory;
        $this->campaignFactory = $campaignFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayFactory = $displayFactory;
        $this->sessionFactory = $sessionFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->widgetFactory = $widgetFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->dataSetFactory = $dataSetFactory;
    }

    /**
     * Controls which pages are to be displayed
     */
    function displayPage()
    {
        $this->getState()->template = 'user-page';
        $this->getState()->setData([
            'userTypes' => $this->userTypeFactory->query()
        ]);
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
     */
    public function myDetails()
    {
        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'data' => $this->getUser()
        ]);
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
     */
    function grid()
    {
        // Filter our users?
        $filterBy = [
            'userId' => $this->getSanitizer()->getInt('userId'),
            'userTypeId' => $this->getSanitizer()->getInt('userTypeId'),
            'userName' => $this->getSanitizer()->getString('userName'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'retired' => $this->getSanitizer()->getInt('retired')
        ];

        // Load results into an array
        $users = $this->userFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filterBy));

        foreach ($users as $user) {
            /* @var \Xibo\Entity\User $user */

            $user->libraryQuotaFormatted = ByteFormatter::format($user->libraryQuota * 1024);

            $user->loggedIn = $this->sessionFactory->getActiveSessionsForUser($user->userId);
            $this->getLog()->debug('Logged in status for user ID ' . $user->userId . ' with name ' . $user->userName . ' is ' . $user->loggedIn);

            // Set some text for the display status
            switch ($user->twoFactorTypeId) {
                case 1:
                    $user->twoFactorDescription = __('Email');
                    break;

                case 2:
                    $user->twoFactorDescription = __('Google Authenticator');
                    break;

                default:
                    $user->twoFactorDescription = __('Disabled');
            }

            if ($this->isApi()) {
                continue;
            }

            $user->includeProperty('buttons');
            $user->homePage = __($user->homePage);

            // Super admins have some buttons
            if ($this->getUser()->checkEditable($user)) {
                // Edit
                $user->buttons[] = [
                    'id' => 'user_button_edit',
                    'url' => $this->getApp()->urlFor('user.edit.form', ['id' => $user->userId]),
                    'text' => __('Edit')
                ];
            }

            if ($this->getUser()->isSuperAdmin()) {
                // Delete
                $user->buttons[] = [
                    'id' => 'user_button_delete',
                    'url' => $this->getApp()->urlFor('user.delete.form', ['id' => $user->userId]),
                    'text' => __('Delete')
                ];
            }

            if ($this->getUser()->checkPermissionsModifyable($user)) {
                $user->buttons[] = ['divider' => true];

                // User Groups
                $user->buttons[] = array(
                    'id' => 'user_button_group_membership',
                    'url' => $this->urlFor('user.membership.form', ['id' => $user->userId]),
                    'text' => __('User Groups')
                );
            }

            if ($this->getUser()->isSuperAdmin()) {
                $user->buttons[] = ['divider' => true];

                // Page Security
                $user->buttons[] = [
                    'id' => 'user_button_page_security',
                    'url' => $this->urlFor('group.acl.form', ['id' => $user->groupId]),
                    'text' => __('Page Security')
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->userFactory->countLast();
        $this->getState()->setData($users);
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
     */
    public function add()
    {
        // Only group admins or super admins can create Users.
        if (!$this->getUser()->isSuperAdmin() && !$this->getUser()->isGroupAdmin())
            throw new AccessDeniedException(__('Only super and group admins can create users'));

        // Build a user entity and save it
        $user = $this->userFactory->create();
        $user->setChildAclDependencies($this->userGroupFactory, $this->pageFactory);

        $user->userName = $this->getSanitizer()->getUserName('userName');
        $user->email = $this->getSanitizer()->getString('email');
        $user->homePageId = $this->getSanitizer()->getInt('homePageId');
        $user->libraryQuota = $this->getSanitizer()->getInt('libraryQuota', 0);
        $user->setNewPassword($this->getSanitizer()->getString('password'));

        if ($this->getUser()->isSuperAdmin()) {
            $user->userTypeId = $this->getSanitizer()->getInt('userTypeId');
            $user->isSystemNotification = $this->getSanitizer()->getCheckbox('isSystemNotification');
            $user->isDisplayNotification = $this->getSanitizer()->getCheckbox('isDisplayNotification');
        } else {
            $user->userTypeId = 3;
            $user->isSystemNotification = 0;
            $user->isDisplayNotification = 0;
        }

        $user->firstName = $this->getSanitizer()->getString('firstName');
        $user->lastName = $this->getSanitizer()->getString('lastName');
        $user->phone = $this->getSanitizer()->getString('phone');
        $user->ref1 = $this->getSanitizer()->getString('ref1');
        $user->ref2 = $this->getSanitizer()->getString('ref2');
        $user->ref3 = $this->getSanitizer()->getString('ref3');
        $user->ref4 = $this->getSanitizer()->getString('ref4');
        $user->ref5 = $this->getSanitizer()->getString('ref5');

        // Options
        $user->newUserWizard = $this->getSanitizer()->getCheckbox('newUserWizard');
        $user->setOptionValue('hideNavigation', $this->getSanitizer()->getCheckbox('hideNavigation'));
        $user->isPasswordChangeRequired = $this->getSanitizer()->getCheckbox('isPasswordChangeRequired');

        // Initial user group
        $group = $this->userGroupFactory->getById($this->getSanitizer()->getInt('groupId'));

        if ($group->isUserSpecific == 1)
            throw new InvalidArgumentException(__('Invalid user group selected'), 'groupId');

        // Save the user
        $user->save();

        // Assign the initial group
        $group->assignUser($user);
        $group->save(['validate' => false]);

        // Test to see if the user group selected has permissions to see the homepage selected
        // Make sure the user has permission to access this page.
        if (!$user->checkViewable($this->pageFactory->getById($user->homePageId)))
            throw new InvalidArgumentException(__('User does not have permission for this homepage'), 'homePageId');

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $user->userName),
            'id' => $user->userId,
            'data' => $user
        ]);
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
     * @param $userId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function edit($userId)
    {
        $user = $this->userFactory->getById($userId);

        if (!$this->getUser()->checkEditable($user))
            throw new AccessDeniedException();

        // Build a user entity and save it
        $user->setChildAclDependencies($this->userGroupFactory, $this->pageFactory);
        $user->load();
        $user->userName = $this->getSanitizer()->getUserName('userName');
        $user->email = $this->getSanitizer()->getString('email');
        $user->homePageId = $this->getSanitizer()->getInt('homePageId');
        $user->libraryQuota = $this->getSanitizer()->getInt('libraryQuota');
        $user->retired = $this->getSanitizer()->getCheckbox('retired');

        if ($this->getUser()->isSuperAdmin()) {
            $user->userTypeId = $this->getSanitizer()->getInt('userTypeId');
            $user->isSystemNotification = $this->getSanitizer()->getCheckbox('isSystemNotification');
            $user->isDisplayNotification = $this->getSanitizer()->getCheckbox('isDisplayNotification');
        }

        $user->firstName = $this->getSanitizer()->getString('firstName');
        $user->lastName = $this->getSanitizer()->getString('lastName');
        $user->phone = $this->getSanitizer()->getString('phone');
        $user->ref1 = $this->getSanitizer()->getString('ref1');
        $user->ref2 = $this->getSanitizer()->getString('ref2');
        $user->ref3 = $this->getSanitizer()->getString('ref3');
        $user->ref4 = $this->getSanitizer()->getString('ref4');
        $user->ref5 = $this->getSanitizer()->getString('ref5');

        // Options
        $user->newUserWizard = $this->getSanitizer()->getCheckbox('newUserWizard');
        $user->setOptionValue('hideNavigation', $this->getSanitizer()->getCheckbox('hideNavigation'));
        $user->isPasswordChangeRequired = $this->getSanitizer()->getCheckbox('isPasswordChangeRequired');

        // Make sure the user has permission to access this page.
        if (!$user->checkViewable($this->pageFactory->getById($user->homePageId)))
            throw new \InvalidArgumentException(__('User does not have permission for this homepage'));

        // If we are a super admin
        if ($this->getUser()->userTypeId == 1) {
            $newPassword = $this->getSanitizer()->getString('newPassword');
            $retypeNewPassword = $this->getSanitizer()->getString('retypeNewPassword');
            $disableTwoFactor = $this->getSanitizer()->getCheckbox('disableTwoFactor');

            if ($newPassword != null && $newPassword != '') {
                // Make sure they are the same
                if ($newPassword != $retypeNewPassword)
                    throw new \InvalidArgumentException(__('Passwords do not match'));

                // Set the new password
                $user->setNewPassword($newPassword);
            }

            // super admin can clear the twoFactorTypeId and secret for the user.
            if ($disableTwoFactor) {
                $user->clearTwoFactor();
            }
        }

        // Save the user
        $user->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $user->userName),
            'id' => $user->userId,
            'data' => $user
        ]);
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
     * @param $userId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete($userId)
    {
        $user = $this->userFactory->getById($userId);

        if (!$this->getUser()->checkDeleteable($user))
            throw new AccessDeniedException();

        $user->setChildAclDependencies($this->userGroupFactory, $this->pageFactory);
        $user->setChildObjectDependencies($this->campaignFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory, $this->displayFactory, $this->displayGroupFactory, $this->widgetFactory, $this->playerVersionFactory, $this->playlistFactory, $this->dataSetFactory);

        if ($this->getSanitizer()->getCheckbox('deleteAllItems') != 1) {

            // Do we have a userId to reassign content to?
            if ($this->getSanitizer()->getInt('reassignUserId') != null) {
                // Reassign all content owned by this user to the provided user
                $this->getLog()->debug('Reassigning content to new userId: %d', $this->getSanitizer()->getInt('reassignUserId'));

                $user->reassignAllTo($this->userFactory->getById($this->getSanitizer()->getInt('reassignUserId')));
            } else {
                // Check to see if we have any child data that would prevent us from deleting
                $children = $user->countChildren();

                if ($children > 0)
                    throw new \InvalidArgumentException(sprintf(__('This user cannot be deleted as it has %d child items'), $children));
            }
        }

        // Delete the user
        $user->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $user->userName),
            'id' => $user->userId
        ]);
    }

    /**
     * User Add Form
     */
    public function addForm()
    {
        // Only group admins or super admins can create Users.
        if (!$this->getUser()->isSuperAdmin() && !$this->getUser()->isGroupAdmin())
            throw new AccessDeniedException(__('Only super and group admins can create users'));

        $defaultUserTypeId = 3;
        foreach ($this->userTypeFactory->query(null, ['userType' => $this->getConfig()->getSetting('defaultUsertype')] ) as $defaultUserType) {
            $defaultUserTypeId = $defaultUserType->userTypeId;
        }

        $this->getState()->template = 'user-form-add';
        $this->getState()->setData([
            'options' => [
                'homepage' => $this->pageFactory->query(null, ['asHome' => 1]),
                'groups' => $this->userGroupFactory->query(),
                'userTypes' => ($this->getUser()->isSuperAdmin()) ? $this->userTypeFactory->getAllRoles() : $this->userTypeFactory->getNonAdminRoles(),
                'defaultGroupId' => $this->getConfig()->getSetting('DEFAULT_USERGROUP'),
                'defaultUserType' => $defaultUserTypeId
            ],
            'help' => [
                'add' => $this->getHelp()->link('User', 'Add')
            ]
        ]);
    }

    /**
     * User Edit Form
     * @param $userId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($userId)
    {
        $user = $this->userFactory->getById($userId);
        $user->setChildAclDependencies($this->userGroupFactory, $this->pageFactory);

        if (!$this->getUser()->checkEditable($user))
            throw new AccessDeniedException();

        $this->getState()->template = 'user-form-edit';
        $this->getState()->setData([
            'user' => $user,
            'options' => [
                'homepage' => $this->pageFactory->getForHomepage(),
                'userTypes' => $this->userTypeFactory->query()
            ],
            'help' => [
                'edit' => $this->getHelp()->link('User', 'Edit')
            ]
        ]);
    }

    /**
     * User Delete Form
     * @param $userId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function deleteForm($userId)
    {
        $user = $this->userFactory->getById($userId);

        if (!$this->getUser()->checkDeleteable($user))
            throw new AccessDeniedException();

        $this->getState()->template = 'user-form-delete';
        $this->getState()->setData([
            'user' => $user,
            'users' => $this->userFactory->query(null, ['notUserId' => $userId]),
            'help' => [
                'delete' => $this->getHelp()->link('User', 'Delete')
            ]
        ]);
    }

    /**
     * Change my password form
     * @throws \RobThree\Auth\TwoFactorAuthException
     */
    public function editProfileForm()
    {
        $user = $this->getUser();

        $this->getState()->template = 'user-form-edit-profile';
        $this->getState()->setData([
            'user' => $user,
            'help' => [
                'editProfile' => $this->getHelp()->link('User', 'EditProfile')
            ],
            'data' => [
                'setup' => $this->urlFor('user.setup.profile'),
                'generate' => $this->urlFor('user.recovery.generate.profile'),
                'show' => $this->urlFor('user.recovery.show.profile'),
            ]
        ]);
    }

    /**
     * Change my Password
     * @throws InvalidArgumentException
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws XiboException
     */
    public function editProfile()
    {
        $user = $this->getUser();
        // Store current (before edit) value of twoFactorTypeId in a variable
        $oldTwoFactorTypeId = $user->twoFactorTypeId;

        // get all other values from the form
        $oldPassword = $this->getSanitizer()->getString('password');
        $newPassword = $this->getSanitizer()->getString('newPassword');
        $retypeNewPassword = $this->getSanitizer()->getString('retypeNewPassword');
        $user->email = $this->getSanitizer()->getString('email');
        $user->twoFactorTypeId = $this->getSanitizer()->getInt('twoFactorTypeId');
        $code = $this->getSanitizer()->getString('code');
        $recoveryCodes = $this->getSanitizer()->getStringArray('twoFactorRecoveryCodes');

        if ($recoveryCodes != null || $recoveryCodes != []) {
            $user->twoFactorRecoveryCodes = json_decode($this->getSanitizer()->getStringArray('twoFactorRecoveryCodes'));
        }

        // check if we have a new password provided, if so check if it was correctly entered
        if ($newPassword != $retypeNewPassword) {
            throw new InvalidArgumentException(__('Passwords do not match'), 'password');
        }

        // check if we have saved secret, for google auth that is done on jQuery side
        if (!isset($user->twoFactorSecret) && $user->twoFactorTypeId === 1) {
            $this->tfaSetup();
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
        if ($user->twoFactorTypeId === 2 && ($user->twoFactorSecret === null || $oldTwoFactorTypeId === 1)) {
            if (!isset($code)) {
                throw new InvalidArgumentException(__('Access Code is empty'), 'code');
            }

            $validation = $this->tfaValidate($code);

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
    }

    /**
     * @throws XiboException
     * @throws \RobThree\Auth\TwoFactorAuthException
     */
    public function tfaSetup()
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
    }

    /**
     * @param string $code The Code to validate
     * @return bool
     * @throws \RobThree\Auth\TwoFactorAuthException
     */
    public function tfaValidate($code)
    {
        $user = $this->getUser();
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
            $result = $tfa->verifyCode($_SESSION['tfaSecret'], $code, 2);
        } elseif (isset($user->twoFactorSecret)) {
            $result = $tfa->verifyCode($user->twoFactorSecret, $code, 2);
        } else {
            $result = false;
        }

        return $result;
    }

    public function tfaRecoveryGenerate()
    {
        $user = $this->getUser();

        // clear any existing codes when we generate new ones
        $user->twoFactorRecoveryCodes = [];

        $count = 4;
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->getSanitizer()->string(Random::generateString(50));
        }

        $user->twoFactorRecoveryCodes =  $codes;

        $this->getState()->setData([
            'codes' => json_encode($codes, JSON_PRETTY_PRINT)
        ]);

        return $codes;
    }

    public function tfaRecoveryShow()
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
    }

    /**
     * Force User Password Change
     */
    public function forceChangePasswordPage()
    {
        $user = $this->getUser();

        // if the flag to force change password is not set to 1 then redirect to the Homepage
        if ($user->isPasswordChangeRequired != 1) {
            $this->getApp()->redirectTo('home');
        }

        $this->getState()->template = 'user-force-change-password-page';
    }

    /**
     * Force change my Password
     * @throws InvalidArgumentException
     */
    public function forceChangePassword()
    {
        // Save the user
        $user = $this->getUser();
        $newPassword = $this->getSanitizer()->getString('newPassword');
        $retypeNewPassword = $this->getSanitizer()->getString('retypeNewPassword');

        if ($newPassword == null || $retypeNewPassword == '')
            throw new InvalidArgumentException(__('Please enter the password'), 'password');

        if ($newPassword != $retypeNewPassword)
            throw new InvalidArgumentException(__('Passwords do not match'), 'password');

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
     * @param string $entity
     * @param int $objectId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function permissionsGrid($entity, $objectId)
    {
        $entity = $this->parsePermissionsEntity($entity, $objectId);

        // Load our object
        $object = $entity->getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        // List of all Groups with a view / edit / delete check box
        $permissions = $this->permissionFactory->getAllByObjectId($this->getUser(), $object->permissionsClass(), $objectId, $this->gridRenderSort(), $this->gridRenderFilter(['name' => $this->getSanitizer()->getString('name')]));

        $this->getState()->template = 'grid';
        $this->getState()->setData($permissions);
        $this->getState()->recordsTotal = $this->permissionFactory->countLast();
    }

    /**
     * Permissions to users for the provided entity
     * @param $entity
     * @param $objectId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function permissionsForm($entity, $objectId)
    {
        $requestEntity = $entity;

        $entity = $this->parsePermissionsEntity($entity, $objectId);

        // Load our object
        $object = $entity->getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        $currentPermissions = [];
        foreach ($this->permissionFactory->getAllByObjectId($this->getUser(), $object->permissionsClass(), $objectId, ['groupId'], ['setOnly' => 1]) as $permission) {
            /* @var Permission $permission */
            $currentPermissions[$permission->groupId] = [
                'view' => ($permission->view == null) ? 0 : $permission->view,
                'edit' => ($permission->edit == null) ? 0 : $permission->edit,
                'delete' => ($permission->delete == null) ? 0 : $permission->delete
            ];
        }

        $data = [
            'entity' => $requestEntity,
            'objectId' => $objectId,
            'permissions' => $currentPermissions,
            'canSetOwner' => $object->canChangeOwner(),
            'owners' => $this->userFactory->query(),
            'object' => $object,
            'help' => [
                'permissions' => $this->getHelp()->link('Campaign', 'Permissions')
            ]
        ];

        $this->getState()->template = 'user-form-permissions';
        $this->getState()->setData($data);
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
     * @param string $entity
     * @param int $objectId
     * @throws XiboException
     */
    public function permissions($entity, $objectId)
    {
        $entity = $this->parsePermissionsEntity($entity, $objectId);

        // Load our object
        $object = $entity->getById($objectId);

        // Does this user have permission to edit the permissions?!
        if (!$this->getUser()->checkPermissionsModifyable($object))
            throw new AccessDeniedException(__('You do not have permission to edit these permissions.'));

        // Get all current permissions
        $permissions = $this->permissionFactory->getAllByObjectId($this->getUser(), $object->permissionsClass(), $objectId);

        // Get the provided permissions
        $groupIds = $this->getSanitizer()->getStringArray('groupIds');

        // Run the update
        $this->updatePermissions($permissions, $groupIds);

        // Should we update the owner?
        if ($this->getSanitizer()->getInt('ownerId') != 0) {

            $ownerId = $this->getSanitizer()->getInt('ownerId');

            $this->getLog()->debug('Requesting update to a new Owner - id = ' . $ownerId);

            if ($object->canChangeOwner()) {
                $object->setOwner($ownerId);
                $object->save(['notify' => false, 'manageDynamicDisplayLinks' => false]);
            } else {
                throw new ConfigurationException(__('Cannot change owner on this Object'));
            }

            // Nasty handling for ownerId on the Layout
            // ideally we'd remove that column and rely on the campaign ownerId in 1.9 onward
            if ($object->permissionsClass() == 'Xibo\Entity\Campaign') {
                $this->getLog()->debug('Changing owner on child Layout');

                foreach ($this->layoutFactory->getByCampaignId($object->getId(), true, true) as $layout) {
                    $layout->setOwner($ownerId, true);
                    $layout->save(['notify' => false]);
                }
            }
        }

        // Cascade permissions
        if ($object->permissionsClass() == 'Xibo\Entity\Campaign' && $this->getSanitizer()->getCheckbox('cascade') == 1) {
            /* @var Campaign $object */
            $this->getLog()->debug('Cascade permissions down');

            // Define a function that can be called for each layout we find
            $updatePermissionsOnLayout = function($layout) use ($object, $groupIds) {

                // Regions
                foreach ($layout->regions as $region) {
                    /* @var Region $region */
                    $this->updatePermissions($this->permissionFactory->getAllByObjectId($this->getUser(), get_class($region), $region->getId()), $groupIds);
                    // Playlists
                    /* @var Playlist $playlist */
                    $playlist = $region->regionPlaylist;
                    $this->updatePermissions($this->permissionFactory->getAllByObjectId($this->getUser(), get_class($playlist), $playlist->getId()), $groupIds);
                    // Widgets
                    foreach ($playlist->widgets as $widget) {
                        /* @var Widget $widget */
                        $this->updatePermissions($this->permissionFactory->getAllByObjectId($this->getUser(), get_class($widget), $widget->getId()), $groupIds);
                    }
                }
            };

            foreach ($this->layoutFactory->getByCampaignId($object->campaignId, true, true) as $layout) {
                /* @var Layout $layout */
                // Assign the same permissions to the Layout
                $this->updatePermissions($this->permissionFactory->getAllByObjectId($this->getUser(), get_class($object), $layout->campaignId), $groupIds);

                // Load the layout
                $layout->load();

                $updatePermissionsOnLayout($layout);
            }
        } else if ($object->permissionsClass() == 'Xibo\Entity\Region') {
            // We always cascade region permissions down to the Playlist
            $object->load(['loadPlaylists' => true]);

            $this->updatePermissions($this->permissionFactory->getAllByObjectId($this->getUser(), get_class($object->regionPlaylist), $object->regionPlaylist->getId()), $groupIds);
        } else if ($object->permissionsClass() == 'Xibo\Entity\Playlist' && $this->getSanitizer()->getCheckbox('cascade') == 1) {
            $object->load();

            // Push the permissions down to each Widget
            foreach ($object->widgets as $widget) {
                $this->updatePermissions($this->permissionFactory->getAllByObjectId($this->getUser(), get_class($widget), $widget->getId()), $groupIds);
            }
        } else if ($object->permissionsClass() == 'Xibo\Entity\Media') {
            // Are we a font?
            /** @var $object Media */
            if ($object->mediaType === 'font') {
                // Drop permissions (we need to reassess).
                $this->getApp()->container->get('\Xibo\Controller\Library')->setApp($this->getApp())->installFonts(['invalidateCache' => true]);
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpCode' => 204,
            'message' => __('Permissions Updated')
        ]);
    }

    /**
     * Parse the Permissions Entity
     * //TODO: this does some nasty service location via $app, if anyone has a better idea, please submit a PR
     * @param string $entity
     * @param int $objectId
     * @return string
     */
    private function parsePermissionsEntity($entity, $objectId)
    {
        if ($entity == '')
            throw new \InvalidArgumentException(__('Permissions requested without an entity'));

        if ($objectId == 0)
            throw new \InvalidArgumentException(__('Permissions form requested without an object'));

        // Check to see that we can resolve the entity
        $entity = lcfirst($entity) . 'Factory';

        if (!$this->getApp()->container->has($entity) || !method_exists($this->getApp()->container->get($entity), 'getById')) {
            $this->getLog()->error('Invalid Entity %s', $entity);
            throw new \InvalidArgumentException(__('Permissions form requested with an invalid entity'));
        }

        return $this->getApp()->container->get($entity);
    }

    /**
     * Updates a set of permissions from a set of groupIds
     * @param array[Permission] $permissions
     * @param array $groupIds
     */
    private function updatePermissions($permissions, $groupIds)
    {
        $this->getLog()->debug('Received Permissions Array to update: %s', var_export($groupIds, true));

        // List of groupIds with view, edit and del assignments
        foreach ($permissions as $row) {
            /* @var \Xibo\Entity\Permission $row */

            // Check and see what permissions we have been provided for this selection
            // If all permissions are 0, then the record is deleted
            if (array_key_exists($row->groupId, $groupIds)) {
                $row->view = (array_key_exists('view', $groupIds[$row->groupId]) ? $groupIds[$row->groupId]['view'] : 0);
                $row->edit = (array_key_exists('edit', $groupIds[$row->groupId]) ? $groupIds[$row->groupId]['edit'] : 0);
                $row->delete = (array_key_exists('delete', $groupIds[$row->groupId]) ? $groupIds[$row->groupId]['delete'] : 0);
                $row->save();
            }
        }
    }

    /**
     * User Applications
     */
    public function myApplications()
    {
        $this->getState()->template = 'user-applications-form';
        $this->getState()->setData([
            'applications' => $this->applicationFactory->getByUserId($this->getUser()->userId),
            'help' => $this->getHelp()->link('User', 'Applications')
        ]);
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
     */
    public function pref()
    {
        $requestedPreference = $this->getSanitizer()->getString('preference');

        if ($requestedPreference != '') {
            $this->getState()->setData($this->getUser()->getOption($requestedPreference));
        }
        else {
            $this->getState()->setData($this->getUser()->getUserOptions());
        }
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
     */
    public function prefEdit()
    {
        // Update this user preference with the preference array
        $i = 0;
        foreach ($this->getSanitizer()->getStringArray('preference') as $pref) {
            $i++;

            $option = $this->getSanitizer()->string($pref['option']);
            $value = $this->getSanitizer()->string($pref['value']);

            $this->getUser()->setOptionValue($option, $value);
        }

        if ($i > 0)
            $this->getUser()->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => ($i == 1) ? __('Updated Preference') : __('Updated Preferences')
        ]);
    }

    /**
     * @param $userId
     */
    public function membershipForm($userId)
    {
        $user = $this->userFactory->getById($userId);

        if (!$this->getUser()->checkEditable($user))
            throw new AccessDeniedException();

        // Groups we are assigned to
        $groupsAssigned = $this->userGroupFactory->getByUserId($user->userId);

        // All Groups
        $allGroups = $this->userGroupFactory->query();

        // The available users are all users except users already in assigned users
        $checkboxes = array();

        foreach ($allGroups as $group) {
            /* @var \Xibo\Entity\UserGroup $group */
            // Check to see if it exists in $usersAssigned
            $exists = false;
            foreach ($groupsAssigned as $groupAssigned) {
                /* @var \Xibo\Entity\UserGroup $groupAssigned */
                if ($groupAssigned->groupId == $group->groupId) {
                    $exists = true;
                    break;
                }
            }

            // Store this checkbox
            $checkbox = array(
                'id' => $group->groupId,
                'name' => $group->group,
                'value_checked' => (($exists) ? 'checked' : '')
            );

            $checkboxes[] = $checkbox;
        }

        $this->getState()->template = 'user-form-membership';
        $this->getState()->setData([
            'user' => $user,
            'checkboxes' => $checkboxes,
            'help' =>  $this->getHelp()->link('User', 'Members')
        ]);
    }

    /**
     * @param $userId
     */
    public function assignUserGroup($userId)
    {
        $user = $this->userFactory->getById($userId);

        if (!$this->getUser()->checkEditable($user))
            throw new AccessDeniedException();

        // Go through each ID to assign
        foreach ($this->getSanitizer()->getIntArray('userGroupId') as $userGroupId) {
            $userGroup = $this->userGroupFactory->getById($userGroupId);

            if (!$this->getUser()->checkEditable($userGroup))
                throw new AccessDeniedException(__('Access Denied to UserGroup'));

            $userGroup->assignUser($user);
            $userGroup->save(['validate' => false]);
        }

        // Have we been provided with unassign id's as well?
        foreach ($this->getSanitizer()->getIntArray('unassignUserGroupId') as $userGroupId) {
            $userGroup = $this->userGroupFactory->getById($userGroupId);

            if (!$this->getUser()->checkEditable($userGroup))
                throw new AccessDeniedException(__('Access Denied to UserGroup'));

            $userGroup->unassignUser($user);
            $userGroup->save(['validate' => false]);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s assigned to User Groups'), $user->userName),
            'id' => $user->userId
        ]);
    }

    /**
     * Update the User Welcome Tutorial to Seen
     */
    public function userWelcomeSetUnSeen()
    {
        $this->getUser()->newUserWizard = 0;
        $this->getUser()->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s has started the welcome tutorial'), $this->getUser()->userName)
        ]);
    }

    /**
     * Update the User Welcome Tutorial to Seen
     */
    public function userWelcomeSetSeen()
    {
        $this->getUser()->newUserWizard = 1;
        $this->getUser()->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s has seen the welcome tutorial'), $this->getUser()->userName)
        ]);
    }

    /**
     * Preferences Form
     */
    public function preferencesForm()
    {
        $this->getState()->template = 'user-form-preferences';
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
     *   @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     * @throws InvalidArgumentException
     * @throws XiboException
     */
    public function prefEditFromForm()
    {
        $this->getUser()->setOptionValue('navigationMenuPosition', $this->getSanitizer()->getString('navigationMenuPosition'));
        $this->getUser()->setOptionValue('useLibraryDuration', $this->getSanitizer()->getCheckbox('useLibraryDuration'));
        $this->getUser()->setOptionValue('showThumbnailColumn', $this->getSanitizer()->getCheckbox('showThumbnailColumn'));

        if ($this->getUser()->isSuperAdmin()) {
            $this->getUser()->showContentFrom = $this->getSanitizer()->getInt('showContentFrom');
        }

        if (!$this->getUser()->isSuperAdmin() && $this->getSanitizer()->getInt('showContentFrom') == 2) {
            throw new InvalidArgumentException(__('Option available only for Super Admins'), 'showContentFrom');
        }

        $this->getUser()->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Updated Preferences')
        ]);
    }
}
