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
namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Permission;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class UserGroup
 * @package Xibo\Controller
 */
class UserGroup extends Base
{
    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * Set common dependencies.
     * @param UserGroupFactory $userGroupFactory
     * @param PermissionFactory $permissionFactory
     * @param UserFactory $userFactory
     */
    public function __construct($userGroupFactory, $permissionFactory, $userFactory)
    {
        $this->userGroupFactory = $userGroupFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userFactory = $userFactory;
    }

    /**
     * Display page logic
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'usergroup-page';

        return $this->render($request, $response);
    }

    /**
     * Group Grid
     * @SWG\Get(
     *  path="/group",
     *  operationId="userGroupSearch",
     *  tags={"usergroup"},
     *  summary="UserGroup Search",
     *  description="Search User Groups",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="query",
     *      description="Filter by UserGroup Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userGroup",
     *      in="query",
     *      description="Filter by UserGroup Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserGroup")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function grid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());
        $filterBy = [
            'groupId' => $sanitizedQueryParams->getInt('userGroupId'),
            'group' => $sanitizedQueryParams->getString('userGroup'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'logicalOperatorName' => $sanitizedQueryParams->getString('logicalOperatorName'),
            'isUserSpecific' => 0,
            'userIdMember' => $sanitizedQueryParams->getInt('userIdMember'),
        ];

        $groups = $this->userGroupFactory->query(
            $this->gridRenderSort($sanitizedQueryParams),
            $this->gridRenderFilter($filterBy, $sanitizedQueryParams)
        );

        foreach ($groups as $group) {
            /* @var \Xibo\Entity\UserGroup $group */

            $group->setUnmatchedProperty(
                'libraryQuotaFormatted',
                ByteFormatter::format($group->libraryQuota * 1024)
            );

            if ($this->isApi($request)) {
                continue;
            }

            // we only want to show certain buttons, depending on the user logged in
            if ($this->getUser()->featureEnabled('usergroup.modify')
                && $this->getUser()->checkEditable($group)
            ) {
                // Edit
                $group->buttons[] = array(
                    'id' => 'usergroup_button_edit',
                    'url' => $this->urlFor($request, 'group.edit.form', ['id' => $group->groupId]),
                    'text' => __('Edit')
                );

                if ($this->getUser()->isSuperAdmin()) {
                    // Delete
                    $group->buttons[] = array(
                        'id' => 'usergroup_button_delete',
                        'url' => $this->urlFor($request, 'group.delete.form', ['id' => $group->groupId]),
                        'text' => __('Delete')
                    );

                    $group->buttons[] = ['divider' => true];

                    // Copy
                    $group->buttons[] = array(
                        'id' => 'usergroup_button_copy',
                        'url' => $this->urlFor($request, 'group.copy.form', ['id' => $group->groupId]),
                        'text' => __('Copy')
                    );

                    $group->buttons[] = ['divider' => true];
                }

                // Members
                $group->buttons[] = array(
                    'id' => 'usergroup_button_members',
                    'url' => $this->urlFor($request, 'group.members.form', ['id' => $group->groupId]),
                    'text' => __('Members')
                );

                if ($this->getUser()->isSuperAdmin()) {
                    // Features
                    $group->buttons[] = ['divider' => true];
                    $group->buttons[] = array(
                        'id' => 'usergroup_button_page_security',
                        'url' => $this->urlFor($request, 'group.acl.form', ['id' => $group->groupId]),
                        'text' => __('Features'),
                        'title' => __('Turn Features on/off for this User')
                    );
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->userGroupFactory->countLast();
        $this->getState()->setData($groups);

        return $this->render($request, $response);
    }

    /**
     * Form to Add a Group
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'usergroup-form-add';
        return $this->render($request, $response);
    }

    /**
     * Form to Edit a Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function editForm(Request $request, Response $response, $id)
    {
        $group = $this->userGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'usergroup-form-edit';
        $this->getState()->setData([
            'group' => $group,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Shows the Delete Group Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        $group = $this->userGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'usergroup-form-delete';
        $this->getState()->setData([
            'group' => $group,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add User Group
     * @SWG\Post(
     *  path="/group",
     *  operationId="userGroupAdd",
     *  tags={"usergroup"},
     *  summary="UserGroup Add",
     *  description="Add User Group",
     *  @SWG\Parameter(
     *      name="group",
     *      in="formData",
     *      description="Name of the User Group",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="decription",
     *      in="formData",
     *      description="A description of the User Group",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="libraryQuota",
     *      in="formData",
     *      description="The quota that should be applied (KiB). Provide 0 for no quota",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isSystemNotification",
     *      in="formData",
     *      description="Flag (0, 1), should members of this Group receive system notifications?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDisplayNotification",
     *      in="formData",
     *      description="Flag (0, 1), should members of this Group receive Display notifications
     * for Displays they have permissions to see",
     *      type="integer",
     *      required=false
     *   ),
     * @SWG\Parameter(
     *        name="isDataSetNotification",
     *        in="formData",
     *        description="Flag (0, 1), should members of this Group receive DataSet notification emails?",
     *        type="integer",
     *        required=false
     *     ),
     * @SWG\Parameter(
     *        name="isLayoutNotification",
     *        in="formData",
     *        description="Flag (0, 1), should members of this Group receive Layout notification emails?",
     *        type="integer",
     *        required=false
     *     ),
     * @SWG\Parameter(
     *        name="isLibraryNotification",
     *        in="formData",
     *        description="Flag (0, 1), should members of this Group receive Library notification emails?",
     *        type="integer",
     *        required=false
     *     ),
     * @SWG\Parameter(
     *        name="isReportNotification",
     *        in="formData",
     *        description="Flag (0, 1), should members of this Group receive Report notification emails?",
     *        type="integer",
     *        required=false
     *     ),
     * @SWG\Parameter(
     *         name="isScheduleNotification",
     *         in="formData",
     *         description="Flag (0, 1), should members of this Group receive Schedule notification emails?",
     *         type="integer",
     *         required=false
     *      ),
     * @SWG\Parameter(
     *         name="isCustomNotification",
     *         in="formData",
     *         description="Flag (0, 1), should members of this Group receive Custom notification emails?",
     *         type="integer",
     *         required=false
     *      ),
     *  @SWG\Parameter(
     *      name="isShownForAddUser",
     *      in="formData",
     *      description="Flag (0, 1), should this Group be shown in the Add User onboarding form.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="defaultHomePageId",
     *      in="formData",
     *      description="If this user has been created via the onboarding form, this should be the default home page",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserGroup")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check permissions
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        // Build a user entity and save it
        $group = $this->userGroupFactory->createEmpty();
        $group->group = $sanitizedParams->getString('group');
        $group->description = $sanitizedParams->getString('description');
        $group->libraryQuota = $sanitizedParams->getInt('libraryQuota');

        if ($this->getUser()->userTypeId == 1) {
            $group->isSystemNotification = $sanitizedParams->getCheckbox('isSystemNotification');
            $group->isDisplayNotification = $sanitizedParams->getCheckbox('isDisplayNotification');
            $group->isDataSetNotification = $sanitizedParams->getCheckbox('isDataSetNotification');
            $group->isCustomNotification = $sanitizedParams->getCheckbox('isCustomNotification');
            $group->isLayoutNotification = $sanitizedParams->getCheckbox('isLayoutNotification');
            $group->isLibraryNotification = $sanitizedParams->getCheckbox('isLibraryNotification');
            $group->isReportNotification = $sanitizedParams->getCheckbox('isReportNotification');
            $group->isScheduleNotification = $sanitizedParams->getCheckbox('isScheduleNotification');
            $group->isShownForAddUser = $sanitizedParams->getCheckbox('isShownForAddUser');
            $group->defaultHomepageId = $sanitizedParams->getString('defaultHomepageId');
        }

        // Save
        $group->save();

        // icondashboard does not need features, otherwise assign the feature matching selected homepage.
        if ($group->defaultHomepageId !== 'icondashboard.view' && !empty($group->defaultHomepageId)) {
            $group->features[] = $this->userGroupFactory->getHomepageByName($group->defaultHomepageId)->feature;
            $group->saveFeatures();
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $group->group),
            'id' => $group->groupId,
            'data' => $group
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit User Group
     * @SWG\Put(
     *  path="/group/{userGroupId}",
     *  operationId="userGroupEdit",
     *  tags={"usergroup"},
     *  summary="UserGroup Edit",
     *  description="Edit User Group",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="path",
     *      description="ID of the User Group",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="group",
     *      in="formData",
     *      description="Name of the User Group",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="decription",
     *      in="formData",
     *      description="A description of the User Group",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="libraryQuota",
     *      in="formData",
     *      description="The quota that should be applied (KiB). Provide 0 for no quota",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isSystemNotification",
     *      in="formData",
     *      description="Flag (0, 1), should members of this Group receive system notifications?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDisplayNotification",
     *      in="formData",
     *      description="Flag (0, 1), should members of this Group receive Display notifications
     * for Displays they have permissions to see",
     *      type="integer",
     *      required=false
     *   ),
     * @SWG\Parameter(
     *       name="isDataSetNotification",
     *       in="formData",
     *       description="Flag (0, 1), should members of this Group receive DataSet notification emails?",
     *       type="integer",
     *       required=false
     *    ),
     * @SWG\Parameter(
     *       name="isLayoutNotification",
     *       in="formData",
     *       description="Flag (0, 1), should members of this Group receive Layout notification emails?",
     *       type="integer",
     *       required=false
     *    ),
     * @SWG\Parameter(
     *       name="isLibraryNotification",
     *       in="formData",
     *       description="Flag (0, 1), should members of this Group receive Library notification emails?",
     *       type="integer",
     *       required=false
     *    ),
     * @SWG\Parameter(
     *       name="isReportNotification",
     *       in="formData",
     *       description="Flag (0, 1), should members of this Group receive Report notification emails?",
     *       type="integer",
     *       required=false
     *    ),
     * @SWG\Parameter(
     *        name="isScheduleNotification",
     *        in="formData",
     *        description="Flag (0, 1), should members of this Group receive Schedule notification emails?",
     *        type="integer",
     *        required=false
     *     ),
     * @SWG\Parameter(
     *        name="isCustomNotification",
     *        in="formData",
     *        description="Flag (0, 1), should members of this Group receive Custom notification emails?",
     *        type="integer",
     *        required=false
     *     ),
     *  @SWG\Parameter(
     *      name="isShownForAddUser",
     *      in="formData",
     *      description="Flag (0, 1), should this Group be shown in the Add User onboarding form.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="defaultHomePageId",
     *      in="formData",
     *      description="If this user has been created via the onboarding form, this should be the default home page",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserGroup")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $id)
    {
        // Check permissions
        if (!$this->getUser()->isSuperAdmin() && !$this->getUser()->isGroupAdmin()) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $group = $this->userGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        $group->load();

        $group->group = $sanitizedParams->getString('group');
        $group->description = $sanitizedParams->getString('description');
        $group->libraryQuota = $sanitizedParams->getInt('libraryQuota');

        if ($this->getUser()->userTypeId == 1) {
            $group->isSystemNotification = $sanitizedParams->getCheckbox('isSystemNotification');
            $group->isDisplayNotification = $sanitizedParams->getCheckbox('isDisplayNotification');
            $group->isDataSetNotification = $sanitizedParams->getCheckbox('isDataSetNotification');
            $group->isCustomNotification = $sanitizedParams->getCheckbox('isCustomNotification');
            $group->isLayoutNotification = $sanitizedParams->getCheckbox('isLayoutNotification');
            $group->isLibraryNotification = $sanitizedParams->getCheckbox('isLibraryNotification');
            $group->isReportNotification = $sanitizedParams->getCheckbox('isReportNotification');
            $group->isScheduleNotification = $sanitizedParams->getCheckbox('isScheduleNotification');
            $group->isShownForAddUser = $sanitizedParams->getCheckbox('isShownForAddUser');
            $group->defaultHomepageId = $sanitizedParams->getString('defaultHomepageId');

            // if we have homepage set assign matching feature if it does not already exist
            if (!empty($group->defaultHomepageId)
                && !in_array(
                    $this->userGroupFactory->getHomepageByName($group->defaultHomepageId)->feature,
                    $group->features
                )
                && $group->defaultHomepageId !== 'icondashboard.view'
            ) {
                $group->features[] = $this->userGroupFactory->getHomepageByName($group->defaultHomepageId)->feature;
                $group->saveFeatures();
            }
        }

        // Save
        $group->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $group->group),
            'id' => $group->groupId,
            'data' => $group
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete User Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Delete(
     *  path="/group/{userGroupId}",
     *  operationId="userGroupDelete",
     *  tags={"usergroup"},
     *  summary="Delete User Group",
     *  description="Delete User Group",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="path",
     *      description="The user Group ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete(Request $request, Response $response, $id)
    {
        // Check permissions
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $group = $this->userGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        $group->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $group->group),
            'id' => $group->groupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * ACL Form for the provided GroupId
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param int|null $userId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function aclForm(Request $request, Response $response, $id, $userId = null)
    {
        // Check permissions to this function
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        // Get permissions for the group provided
        $group = $this->userGroupFactory->getById($id);
        $inheritedFeatures = ($userId !== null)
            ? $this->userGroupFactory->getGroupFeaturesForUser($this->userFactory->getById($userId), false)
            : [];

        $data = [
            'groupId' => $id,
            'group' => $group->group,
            'isUserSpecific' => $group->isUserSpecific,
            'features' => $group->features,
            'inheritedFeatures' => $inheritedFeatures,
            'userGroupFactory' => $this->userGroupFactory,
        ];

        $this->getState()->template = 'usergroup-form-acl';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * ACL update
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function acl(Request $request, Response $response, $id)
    {
        // Check permissions to this function
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        // Load the Group we are working on
        // Get the object
        if ($id == 0) {
            throw new InvalidArgumentException(__('Features form requested without a User Group'), 'id');
        }

        $features = $request->getParam('features', null);

        if (!is_array($features)) {
            $features = [];
        }

        $group = $this->userGroupFactory->getById($id);
        $group->features = $features;
        $group->saveFeatures();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Features updated for %s'), $group->group),
            'id' => $group->groupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Shows the Members of a Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function membersForm(Request $request, Response $response, $id)
    {
        $group = $this->userGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        // Users in group
        $usersAssigned = $this->userFactory->query(null, ['groupIds' => [$id]]);

        $this->getState()->template = 'usergroup-form-members';
        $this->getState()->setData([
            'group' => $group,
            'extra' => [
                'usersAssigned' => $usersAssigned
            ],
        ]);

        return $this->render($request, $response);
    }

    /**
     * Assign User to the User Group
     * @SWG\Post(
     *  path="/group/members/assign/{userGroupId}",
     *  operationId="userGroupAssign",
     *  tags={"usergroup"},
     *  summary="Assign User to User Group",
     *  description="Assign User to User Group",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="path",
     *      description="ID of the user group to which assign the user",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="userId",
     *      in="formData",
     *      description="Array of userIDs to assign",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserGroup")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function assignUser(Request $request, Response $response, $id)
    {
        $this->getLog()->debug(sprintf('Assign User for groupId %d', $id));
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $group = $this->userGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        // Load existing memberships.
        $group->load();
        $changesMade = false;

        // Parse updated assignments from form.
        $users = $sanitizedParams->getIntArray('userId', ['default' => []]);

        foreach ($users as $userId) {
            $this->getLog()->debug(sprintf('Assign User %d for groupId %d', $userId, $id));

            $user = $this->userFactory->getById($userId);

            if (!$this->getUser()->checkViewable($user)) {
                throw new AccessDeniedException(__('Access Denied to User'));
            }

            $group->assignUser($user);
            $changesMade = true;
        }

        // Check to see if unassign has been provided.
        $users = $sanitizedParams->getIntArray('unassignUserId', ['default' => []]);

        foreach ($users as $userId) {
            $this->getLog()->debug(sprintf('Unassign User %d for groupId %d', $userId, $id));

            $user = $this->userFactory->getById($userId);

            if (!$this->getUser()->checkViewable($user)) {
                throw new AccessDeniedException(__('Access Denied to User'));
            }

            $group->unassignUser($user);
            $changesMade = true;
        }

        if ($changesMade) {
            $group->save(['validate' => false]);
            $message = sprintf(__('Membership set for %s'), $group->group);
        } else {
            $message = sprintf(__('No changes for %s'), $group->group);
        }

        // Return
        $this->getState()->hydrate([
            'message' => $message,
            'id' => $group->groupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Unassign User to the User Group
     * @SWG\Post(
     *  path="/group/members/unassign/{userGroupId}",
     *  operationId="userGroupUnassign",
     *  tags={"usergroup"},
     *  summary="Unassign User from User Group",
     *  description="Unassign User from User Group",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="path",
     *      description="ID of the user group from which to unassign the user",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="userId",
     *      in="formData",
     *      description="Array of userIDs to unassign",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserGroup")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function unassignUser(Request $request, Response $response, $id)
    {
        $group = $this->userGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        $users = $sanitizedParams->getIntArray('userId');

        foreach ($users as $userId) {
            $group->unassignUser($this->userFactory->getById($userId));
        }

        $group->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Membership set for %s'), $group->group),
            'id' => $group->groupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Form to Copy Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function copyForm(Request $request, Response $response, $id)
    {
        $group = $this->userGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'usergroup-form-copy';
        $this->getState()->setData([
            'group' => $group
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *  path="/group/{userGroupId}/copy",
     *  operationId="userGroupCopy",
     *  tags={"usergroup"},
     *  summary="Copy User Group",
     *  description="Copy an user group, optionally copying the group members",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="path",
     *      description="The User Group ID to Copy",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="group",
     *      in="formData",
     *      description="The Group Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="copyMembers",
     *      in="formData",
     *      description="Flag indicating whether to copy group members",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyFeatures",
     *      in="formData",
     *      description="Flag indicating whether to copy group features",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/UserGroup"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function copy(Request $request, Response $response, $id)
    {
        $group = $this->userGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check we have permission to view this group
        if (!$this->getUser()->checkEditable($group)) {
            throw new AccessDeniedException();
        }

        // Clone the group
        $group->load([
            'loadUsers' => ($sanitizedParams->getCheckbox('copyMembers') == 1)
        ]);
        $newGroup = clone $group;
        $newGroup->group = $sanitizedParams->getString('group');
        $newGroup->save();

        // Save features?
        if ($sanitizedParams->getCheckbox('copyFeatures')) {
            $newGroup->saveFeatures();
        } else {
            $newGroup->features = [];
        }

        // Copy permissions
        foreach ($this->permissionFactory->getByGroupId('Page', $group->groupId) as $permission) {
            /* @var Permission $permission */
            $permission = clone $permission;
            $permission->groupId = $newGroup->groupId;
            $permission->save();
        }

        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied %s'), $group->group),
            'id' => $newGroup->groupId,
            'data' => $newGroup
        ]);

        return $this->render($request, $response);
    }
}
