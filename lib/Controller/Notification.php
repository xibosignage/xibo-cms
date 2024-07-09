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

use Carbon\Carbon;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\UserGroup;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\AttachmentUploadHandler;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SendFile;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\MediaService;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;

/**
 * Class Notification
 * @package Xibo\Controller
 */
class Notification extends Base
{
    /** @var  NotificationFactory */
    private $notificationFactory;

    /** @var  UserNotificationFactory */
    private $userNotificationFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /** @var DisplayNotifyService */
    private $displayNotifyService;

    /**
     * Notification constructor.
     * @param NotificationFactory $notificationFactory
     * @param UserNotificationFactory $userNotificationFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayNotifyService $displayNotifyService
     */
    public function __construct(
        $notificationFactory,
        $userNotificationFactory,
        $displayGroupFactory,
        $userGroupFactory,
        $displayNotifyService
    ) {
        $this->notificationFactory = $notificationFactory;
        $this->userNotificationFactory = $userNotificationFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        // Call to render the template
        $this->getState()->template = 'notification-page';

        return $this->render($request, $response);
    }

    /**
     * Show a notification
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function interrupt(Request $request, Response $response, $id)
    {
        $notification = $this->userNotificationFactory->getByNotificationId($id);

        // Mark it as read
        $notification->setRead(Carbon::now()->format('U'));
        $notification->save();

        $this->getState()->template = 'notification-interrupt';
        $this->getState()->setData(['notification' => $notification]);

        return $this->render($request, $response);
    }

    /**
     * Show a notification
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function show(Request $request, Response $response, $id)
    {
        $params = $this->getSanitizer($request->getParams());
        $notification = $this->userNotificationFactory->getByNotificationId($id);

        // Mark it as read
        $notification->setRead(Carbon::now()->format('U'));
        $notification->save();

        if ($params->getCheckbox('multiSelect')) {
            return $response->withStatus(201);
        } else {
            $this->getState()->template = 'notification-form-show';
            $this->getState()->setData(['notification' => $notification]);

            return $this->render($request, $response);
        }
    }

    /**
     * @SWG\Get(
     *  path="/notification",
     *  operationId="notificationSearch",
     *  tags={"notification"},
     *  summary="Notification Search",
     *  description="Search this users Notifications",
     *  @SWG\Parameter(
     *      name="notificationId",
     *      in="query",
     *      description="Filter by Notification Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="subject",
     *      in="query",
     *      description="Filter by Subject",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data such as userGroups,displayGroups",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Notification")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response): Response|\Psr\Http\Message\ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'notificationId' => $sanitizedQueryParams->getInt('notificationId'),
            'subject' => $sanitizedQueryParams->getString('subject'),
            'read' => $sanitizedQueryParams->getInt('read'),
            'releaseDt' => $sanitizedQueryParams->getDate('releaseDt')?->format('U'),
            'type' => $sanitizedQueryParams->getString('type'),
        ];
        $embed = ($sanitizedQueryParams->getString('embed') != null)
            ? explode(',', $sanitizedQueryParams->getString('embed'))
            : [];

        $notifications = $this->notificationFactory->query(
            $this->gridRenderSort($sanitizedQueryParams),
            $this->gridRenderFilter($filter, $sanitizedQueryParams)
        );

        foreach ($notifications as $notification) {
            if (in_array('userGroups', $embed) || in_array('displayGroups', $embed)) {
                $notification->load([
                    'loadUserGroups' => in_array('userGroups', $embed),
                    'loadDisplayGroups' => in_array('displayGroups', $embed),
                ]);
            }

            if ($this->isApi($request)) {
                continue;
            }

            $notification->includeProperty('buttons');

            // View Notification
            $notification->buttons[] = [
                'id' => 'notification_button_view',
                'url' => $this->urlFor(
                    $request,
                    'notification.show',
                    ['id' => $notification->notificationId]
                ),
                'text' => __('View'),
                'multi-select' => true,
                'dataAttributes' => [
                    [
                        'name' => 'commit-url',
                        'value' => $this->urlFor(
                            $request,
                            'notification.show',
                            ['id' => $notification->notificationId, 'multiSelect' => true]
                        ),
                    ],
                    ['name' => 'commit-method', 'value' => 'get'],
                    ['name' => 'id', 'value' => 'notification_button_view'],
                    ['name' => 'text', 'value' => __('Mark as read?')],
                    ['name' => 'sort-group', 'value' => 1],
                    ['name' => 'rowtitle', 'value' => $notification->subject]
                ]
            ];


            // Edit Notification
            if ($this->getUser()->checkEditable($notification) &&
                $this->getUser()->featureEnabled('notification.modify')
            ) {
                $notification->buttons[] = [
                    'id' => 'notification_button_edit',
                    'url' => $this->urlFor(
                        $request,
                        'notification.edit.form',
                        ['id' => $notification->notificationId]
                    ),
                    'text' => __('Edit')
                ];
            }

            // Delete Notifications
            if ($this->getUser()->checkDeleteable($notification) &&
                $this->getUser()->featureEnabled('notification.modify')
            ) {
                $notification->buttons[] = ['divider' => true];

                $notification->buttons[] = [
                    'id' => 'notification_button_delete',
                    'url' => $this->urlFor(
                        $request,
                        'notification.delete.form',
                        ['id' => $notification->notificationId]
                    ),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'notification.delete',
                                ['id' => $notification->notificationId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'notification_button_delete'],
                        ['name' => 'text', 'value' => __('Delete?')],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'rowtitle', 'value' => $notification->subject]
                    ]
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->notificationFactory->countLast();
        $this->getState()->setData($notifications);

        return $this->render($request, $response);
    }

    /**
     * Add Notification Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function addForm(Request $request, Response $response)
    {
        $groups = [];
        $displays = [];
        $userGroups = [];
        $users = [];

        foreach ($this->displayGroupFactory->query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        foreach ($this->userGroupFactory->query(['`group`'], ['isUserSpecific' => -1]) as $userGroup) {
            /* @var UserGroup $userGroup */

            if ($userGroup->isUserSpecific == 0) {
                $userGroups[] = $userGroup;
            } else {
                $users[] = $userGroup;
            }
        }

        $this->getState()->template = 'notification-form-add';
        $this->getState()->setData([
            'displays' => $displays,
            'displayGroups' => $groups,
            'users' => $users,
            'userGroups' => $userGroups,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Notification Form
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
        $notification = $this->notificationFactory->getById($id);
        $notification->load();

        // Adjust the dates
        $notification->createDt = Carbon::createFromTimestamp($notification->createDt)
            ->format(DateFormatHelper::getSystemFormat());
        $notification->releaseDt = Carbon::createFromTimestamp($notification->releaseDt)
            ->format(DateFormatHelper::getSystemFormat());

        if (!$this->getUser()->checkEditable($notification)) {
            throw new AccessDeniedException();
        }

        $groups = [];
        $displays = [];
        $userGroups = [];
        $users = [];

        foreach ($this->displayGroupFactory->query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        foreach ($this->userGroupFactory->query(['`group`'], ['isUserSpecific' => -1]) as $userGroup) {
            /* @var UserGroup $userGroup */

            if ($userGroup->isUserSpecific == 0) {
                $userGroups[] = $userGroup;
            } else {
                $users[] = $userGroup;
            }
        }

        $this->getState()->template = 'notification-form-edit';
        $this->getState()->setData([
            'notification' => $notification,
            'displays' => $displays,
            'displayGroups' => $groups,
            'users' => $users,
            'userGroups' => $userGroups,
            'displayGroupIds' => array_map(function ($element) {
                return $element->displayGroupId;
            }, $notification->displayGroups),
            'userGroupIds' => array_map(function ($element) {
                return $element->groupId;
            }, $notification->userGroups)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Notification Form
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
        $notification = $this->notificationFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($notification)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'notification-form-delete';
        $this->getState()->setData([
            'notification' => $notification
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add attachment
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|\Slim\Http\Response
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addAttachment(Request $request, Response $response)
    {
        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        $options = [
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'accept_file_types' => '/\.jpg|.jpeg|.png|.bmp|.gif|.zip|.pdf/i'
        ];

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        new AttachmentUploadHandler($libraryFolder . 'temp/', $this->getLog()->getLoggerInterface(), $options);

        // Explicitly set the Content-Type header to application/json
        $response = $response->withHeader('Content-Type', 'application/json');

        return $this->render($request, $response);
    }

    /**
     * Add Notification
     *
     * @SWG\Post(
     *  path="/notification",
     *  operationId="notificationAdd",
     *  tags={"notification"},
     *  summary="Notification Add",
     *  description="Add a Notification",
     *  @SWG\Parameter(
     *      name="subject",
     *      in="formData",
     *      description="The Subject",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="body",
     *      in="formData",
     *      description="The Body",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="releaseDt",
     *      in="formData",
     *      description="ISO date representing the release date for this notification",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isInterrupt",
     *      in="formData",
     *      description="Flag indication whether this notification should interrupt the web portal nativation/login",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="displayGroupIds",
     *      in="formData",
     *      description="The display group ids to assign this notification to",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Parameter(
     *      name="userGroupIds",
     *      in="formData",
     *      description="The user group ids to assign to this notification",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Notification"),
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
     * @throws ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $notification = $this->notificationFactory->createEmpty();
        $notification->subject = $sanitizedParams->getString('subject');
        $notification->body = $request->getParam('body', '');
        $notification->createDt = Carbon::now()->format('U');
        $notification->releaseDt = $sanitizedParams->getDate('releaseDt');

        if ($notification->releaseDt !== null) {
            $notification->releaseDt = $notification->releaseDt->format('U');
        } else {
            $notification->releaseDt = $notification->createDt;
        }

        $notification->isInterrupt = $sanitizedParams->getCheckbox('isInterrupt');
        $notification->userId = $this->getUser()->userId;
        $notification->nonusers = $sanitizedParams->getString('nonusers');
        $notification->type = 'custom';

        // Displays and Users to link
        foreach ($sanitizedParams->getIntArray('displayGroupIds', ['default' => [] ]) as $displayGroupId) {
            $notification->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));

            // Notify (don't collect)
            $this->displayNotifyService->collectLater()->notifyByDisplayGroupId($displayGroupId);
        }

        foreach ($sanitizedParams->getIntArray('userGroupIds', ['default' => [] ]) as $userGroupId) {
            $notification->assignUserGroup($this->userGroupFactory->getById($userGroupId));
        }

        $notification->save();

        $attachedFilename = $sanitizedParams->getString('attachedFilename', ['defaultOnEmptyString' => true]);
        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        if (!empty($attachedFilename)) {
            $saveName = $notification->notificationId .'_' .$attachedFilename;
            $notification->filename = $saveName;
            $notification->originalFileName = $attachedFilename;
            // Move the file into the library
            // Try to move the file first
            $from = $libraryFolder . 'temp/' . $attachedFilename;
            $to = $libraryFolder . 'attachment/' .  $saveName;

            $moved = rename($from, $to);

            if (!$moved) {
                $this->getLog()->info(
                    'Cannot move file: ' . $from . ' to ' . $to . ', will try and copy/delete instead.'
                );

                // Copy
                $moved = copy($from, $to);

                // Delete
                if (!@unlink($from)) {
                    $this->getLog()->error('Cannot delete file: ' . $from . ' after copying to ' . $to);
                }
            }

            if (!$moved) {
                throw new ConfigurationException(__('Problem moving uploaded file into the Attachment Folder'));
            }

            $notification->save();
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $notification->subject),
            'id' => $notification->notificationId,
            'data' => $notification
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Notification
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Put(
     *  path="/notification/{notificationId}",
     *  operationId="notificationEdit",
     *  tags={"notification"},
     *  summary="Notification Edit",
     *  description="Edit a Notification",
     *  @SWG\Parameter(
     *      name="notificationId",
     *      in="path",
     *      description="The NotificationId",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="subject",
     *      in="formData",
     *      description="The Subject",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="body",
     *      in="formData",
     *      description="The Body",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="releaseDt",
     *      in="formData",
     *      description="ISO date representing the release date for this notification",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="isInterrupt",
     *      in="formData",
     *      description="Flag indication whether this notification should interrupt the web portal nativation/login",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="displayGroupIds",
     *      in="formData",
     *      description="The display group ids to assign this notification to",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Parameter(
     *      name="userGroupIds",
     *      in="formData",
     *      description="The user group ids to assign to this notification",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Notification")
     *  )
     * )
     */
    public function edit(Request $request, Response $response, $id)
    {
        $notification = $this->notificationFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $notification->load();

        // Check Permissions
        if (!$this->getUser()->checkEditable($notification)) {
            throw new AccessDeniedException();
        }

        $notification->subject = $sanitizedParams->getString('subject');
        $notification->body = $request->getParam('body', '');
        $notification->createDt = Carbon::now()->format('U');
        $notification->releaseDt = $sanitizedParams->getDate('releaseDt')->format('U');
        $notification->isInterrupt = $sanitizedParams->getCheckbox('isInterrupt');
        $notification->userId = $this->getUser()->userId;
        $notification->nonusers = $sanitizedParams->getString('nonusers');

        // Clear existing assignments
        $notification->displayGroups = [];
        $notification->userGroups = [];

        // Displays and Users to link
        foreach ($sanitizedParams->getIntArray('displayGroupIds', ['default' => []]) as $displayGroupId) {
            $notification->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));

            // Notify (don't collect)
            $this->displayNotifyService->collectLater()->notifyByDisplayGroupId($displayGroupId);
        }

        foreach ($sanitizedParams->getIntArray('userGroupIds', ['default' => []]) as $userGroupId) {
            $notification->assignUserGroup($this->userGroupFactory->getById($userGroupId));
        }

        $notification->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Edited %s'), $notification->subject),
            'id' => $notification->notificationId,
            'data' => $notification
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Notification
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Delete(
     *  path="/notification/{notificationId}",
     *  operationId="notificationDelete",
     *  tags={"notification"},
     *  summary="Delete Notification",
     *  description="Delete the provided notification",
     *  @SWG\Parameter(
     *      name="notificationId",
     *      in="path",
     *      description="The Notification Id to Delete",
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
        $notification = $this->notificationFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($notification)) {
            throw new AccessDeniedException();
        }

        $notification->delete();

        /*Delete the attachment*/
        if (!empty($notification->filename)) {
            // Library location
            $attachmentLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION'). 'attachment/';
            if (file_exists($attachmentLocation . $notification->filename)) {
                unlink($attachmentLocation . $notification->filename);
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $notification->subject)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function exportAttachment(Request $request, Response $response, $id)
    {
        $notification = $this->notificationFactory->getById($id);

        $fileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'attachment/' . $notification->filename;

        // Return the file with PHP
        $this->setNoOutput(true);

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $fileName
        ));
    }
}
