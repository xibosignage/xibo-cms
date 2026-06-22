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
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Notification as NotificationEntity;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\AttachmentUploadHandler;
use Xibo\Helper\Environment;
use Xibo\Helper\SendFile;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\MediaService;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Notification
 * @package Xibo\Controller
 */
class Notification extends Base
{
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        private readonly UserNotificationFactory $userNotificationFactory,
        private readonly DisplayGroupFactory $displayGroupFactory,
        private readonly UserGroupFactory $userGroupFactory,
        private readonly DisplayNotifyService $displayNotifyService,
    ) {
    }

    /**
     * Show a notification (interrupt page)
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function interrupt(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $notification = $this->userNotificationFactory->getByNotificationId($id);

        // Mark it as read
        $notification->setRead(Carbon::now()->format('U'));
        $notification->save();

        return $response
            ->withStatus(200)
            ->withJson($notification);
    }

    /**
     * Show a notification (drawer)
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function show(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $notification = $this->userNotificationFactory->getByNotificationId($id);

        // Mark it as read
        $notification->setRead(Carbon::now()->format('U'));
        $notification->save();

        return $response
            ->withStatus(200)
            ->withJson($notification);
    }

    #[OA\Get(
        path: '/notification',
        operationId: 'notificationSearch',
        description: 'Search this users Notifications',
        summary: 'Notification Search',
        tags: ['notification']
    )]
    #[OA\Parameter(
        name: 'notificationId',
        description: 'Filter by Notification Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'subject',
        description: 'Filter by Subject',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data such as userGroups,displayGroups',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Column to sort by. Used together with sortDir.',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: ['subject', 'type', 'releaseDt', 'isInterrupt']
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Notification')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $notificationSortQuery = $this->gridRenderSort(
            $sanitizedQueryParams,
            $this->isJson($request),
            'subject'
        );

        $notificationFilterQuery = $this->getNotificationFilterQuery($sanitizedQueryParams);

        $embed = ($sanitizedQueryParams->getString('embed') != null)
            ? explode(',', $sanitizedQueryParams->getString('embed'))
            : [];

        $notifications = $this->notificationFactory->query(
            $notificationSortQuery,
            $notificationFilterQuery
        );

        foreach ($notifications as $notification) {
            if (in_array('userGroups', $embed) || in_array('displayGroups', $embed)) {
                $notification->load([
                    'loadUserGroups' => in_array('userGroups', $embed),
                    'loadDisplayGroups' => in_array('displayGroups', $embed),
                ]);
            }

            $notification->canEdit = $this->getUser()->checkEditable($notification)
                && $this->getUser()->featureEnabled('notification.modify');
            $notification->canDelete = $this->getUser()->checkDeleteable($notification)
                && $this->getUser()->featureEnabled('notification.modify');
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->notificationFactory->countLast())
            ->withJson($notifications);
    }

    #[OA\Get(
        path: '/notification/{notificationId}',
        operationId: 'notificationGetById',
        description: 'Get a single Notification by ID',
        summary: 'Notification Get By ID',
        tags: ['notification']
    )]
    #[OA\Parameter(
        name: 'notificationId',
        description: 'The Notification ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Notification')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws NotFoundException|InvalidArgumentException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $notification = $this->notificationFactory->getById($id, false);
        $notification->load();

        $notification->canEdit = $this->getUser()->checkEditable($notification)
            && $this->getUser()->featureEnabled('notification.modify');
        $notification->canDelete = $this->getUser()->checkDeleteable($notification)
            && $this->getUser()->featureEnabled('notification.modify');

        return $response->withStatus(200)->withJson($notification);
    }

    /**
     * Mark notifications as read for the current user.
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     */
    public function markAsRead(Request $request, Response $response): Response|ResponseInterface
    {
        $params = $this->getSanitizer($request->getParams());
        $notificationId = $params->getInt('notificationId');
        $readDt = Carbon::now()->format('U');

        if ($notificationId !== null) {
            $notification = $this->userNotificationFactory->getByNotificationId($notificationId);
            $notification->setRead($readDt);
            $notification->save();
        } else {
            $this->userNotificationFactory->markAllAsRead($readDt);
        }

        return $response->withStatus(204);
    }

    /**
     * Return unread interrupt notifications for the current user (SPA client-side interrupt mechanism).
     * Only returns notifications the user is an audience member of.
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     */
    public function getInterrupt(Request $request, Response $response): Response|ResponseInterface
    {
        $notifications = $this->userNotificationFactory->query(
            ['releaseDt ASC'],
            ['audienceId' => $this->getUser()->userId, 'isInterrupt' => 1, 'read' => 0]
        );

        return $response->withStatus(200)->withJson($notifications);
    }

    /**
     * Return released notifications visible to the current user (audience), sorted by releaseDt DESC.
     * Used by the notification badge and dropdown in the SPA.
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws NotFoundException
     */
    public function myNotifications(Request $request, Response $response): Response|ResponseInterface
    {
        $params = $this->getSanitizer($request->getQueryParams());

        $filterBy = [
            'audienceId'   => $this->getUser()->userId,
            'start'        => $params->getInt('start'),
            'length'       => $params->getInt('length'),
        ];

        $notifications = $this->userNotificationFactory->query(
            ['releaseDt DESC'],
            $filterBy
        );

        $systemNotifications = $this->buildSystemNotifications($request);

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->userNotificationFactory->countLast() + count($systemNotifications))
            ->withHeader('X-Unread-Count', $this->userNotificationFactory->countMyUnread() + count($systemNotifications))
            ->withJson(array_merge($systemNotifications, $notifications));
    }

    /**
     * Add attachment
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ConfigurationException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function addAttachment(Request $request, Response $response): Response|ResponseInterface
    {
        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        $options = [
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'accept_file_types' => '/\.(jpg|jpeg|png|bmp|gif|zip|pdf)$/i'
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

    #[OA\Post(
        path: '/notification',
        operationId: 'notificationAdd',
        description: 'Add a Notification',
        summary: 'Notification Add',
        tags: ['notification']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['subject', 'isInterrupt', 'displayGroupIds', 'userGroupIds'],
                properties: [
                    new OA\Property(property: 'subject', description: 'The Subject', type: 'string'),
                    new OA\Property(property: 'body', description: 'The Body', type: 'string'),
                    new OA\Property(
                        property: 'releaseDt',
                        description: 'ISO date representing the release date for this notification',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'isInterrupt',
                        description: 'Flag indication whether this notification should interrupt the web portal nativation/login', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'displayGroupIds',
                        description: 'The display group ids to assign this notification to',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'userGroupIds',
                        description: 'The user group ids to assign to this notification',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ],
        content: new OA\JsonContent(ref: '#/components/schemas/Notification')
    )]
    /**
     * Add Notification
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ConfigurationException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function add(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $notification = $this->notificationFactory->createEmpty();
        $notification->subject = $sanitizedParams->getString('subject');
        $notification->body = $sanitizedParams->getHtml('body');
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

        $this->assignAudienceFromParams($notification, $sanitizedParams);

        $notification->save();

        $this->handleAttachmentUpload($notification, $sanitizedParams);

        return $response->withStatus(201)->withJson($notification);
    }

    #[OA\Put(
        path: '/notification/{notificationId}',
        operationId: 'notificationEdit',
        description: 'Edit a Notification',
        summary: 'Notification Edit',
        tags: ['notification']
    )]
    #[OA\Parameter(
        name: 'notificationId',
        description: 'The NotificationId',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['subject', 'releaseDt', 'isInterrupt', 'displayGroupIds', 'userGroupIds'],
                properties: [
                    new OA\Property(property: 'subject', description: 'The Subject', type: 'string'),
                    new OA\Property(property: 'body', description: 'The Body', type: 'string'),
                    new OA\Property(
                        property: 'releaseDt',
                        description: 'ISO date representing the release date for this notification',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'isInterrupt',
                        description: 'Flag indication whether this notification should interrupt the web portal nativation/login', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'displayGroupIds',
                        description: 'The display group ids to assign this notification to',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'userGroupIds',
                        description: 'The user group ids to assign to this notification',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Notification')
    )]
    /**
     * Edit Notification
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $notification = $this->notificationFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $notification->load();

        if (!$this->getUser()->checkEditable($notification)) {
            throw new AccessDeniedException();
        }

        $notification->subject = $sanitizedParams->getString('subject');
        $notification->body = $sanitizedParams->getHtml('body');
        $notification->createDt = Carbon::now()->format('U');
        $notification->releaseDt = $sanitizedParams->getDate('releaseDt')?->format('U') ?? $notification->releaseDt;
        $notification->isInterrupt = $sanitizedParams->getCheckbox('isInterrupt');
        $notification->userId = $this->getUser()->userId;
        $notification->nonusers = $sanitizedParams->getString('nonusers');

        if ($sanitizedParams->getCheckbox('clearAttachment')) {
            $notification->filename = '';
            $notification->originalFileName = '';
        }

        // Capture existing assignments so the caller can re-save them without needing
        // view permission on groups originally added by another user. Mirrors Campaign::edit().
        $originalDisplayGroupIds = array_map(
            fn ($dg) => $dg->displayGroupId,
            $notification->displayGroups ?? []
        );
        $originalUserGroupIds = array_map(
            fn ($ug) => $ug->groupId,
            $notification->userGroups ?? []
        );

        $this->assignAudienceFromParams($notification, $sanitizedParams, $originalDisplayGroupIds, $originalUserGroupIds);

        $notification->save();

        $this->handleAttachmentUpload($notification, $sanitizedParams);

        return $response->withStatus(200)->withJson($notification);
    }

    #[OA\Delete(
        path: '/notification/{notificationId}',
        operationId: 'notificationDelete',
        description: 'Delete the provided notification',
        summary: 'Delete Notification',
        tags: ['notification']
    )]
    #[OA\Parameter(
        name: 'notificationId',
        description: 'The Notification Id to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete Notification
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $notification = $this->notificationFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($notification)) {
            throw new AccessDeniedException();
        }

        $notification->delete();

        // Remove the attachment
        if (!empty($notification->filename)) {
            $attachmentLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'attachment/';

            if (file_exists($attachmentLocation . $notification->filename)) {
                unlink($attachmentLocation . $notification->filename);
            }
        }

        return $response->withStatus(204);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function exportAttachment(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $notification = $this->userNotificationFactory->getByNotificationId($id);

        $fileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'attachment/' . $notification->filename;

        // Return the file with PHP
        $this->setNoOutput(true);

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $fileName
        ));
    }

    /**
     * Build system notifications for the current request context.
     * These are never stored in the database; they reflect live environment state.
     * @param Request $request
     * @return array
     */
    private function buildSystemNotifications(Request $request): array
    {
        $notifications = [];

        // TODO: Might need further updates for the SPA

        if (Environment::isDevMode()) {
            $notifications[] = $this->buildSystemNotification(__('CMS is running in DEV mode'));
        } elseif ($this->getUser()->userTypeId === 1) {
            if (file_exists(PROJECT_ROOT . '/web/install/index.php')) {
                $notifications[] = $this->buildSystemNotification(
                    __('Installation files should be removed — web/install/index.php still exists')
                );
            }
            if (!Environment::checkUrl($request->getUri())) {
                $notifications[] = $this->buildSystemNotification(
                    __('CMS URL configuration warning — /web/ should not appear in the URL')
                );
            }
        }

        return $notifications;
    }

    /**
     * Build system notification object
     */
    private function buildSystemNotification(string $subject): object
    {
        return (object) [
            'notificationId' => null,
            'subject'        => $subject,
            'body'           => '',
            'type'           => 'system',
            'isSystem'       => 1,
            'isInterrupt'    => 0,
            'read'           => 0,
            'readDt'         => null,
            'releaseDt'      => Carbon::now()->format('U'),
            'userId'         => $this->getUser()->userId,
        ];
    }

    /**
     * Get the notification filter query
     */
    private function getNotificationFilterQuery($sanitizedQueryParams): array
    {
        $filter = [
            'notificationId' => $sanitizedQueryParams->getInt('notificationId'),
            'subject' => $sanitizedQueryParams->getString('subject'),
            'read' => $sanitizedQueryParams->getInt('read'),
            'releaseDt' => $sanitizedQueryParams->getDate('releaseDt')?->format('U'),
            'type' => $sanitizedQueryParams->getString('type'),
        ];

        return $this->gridRenderFilter($filter, $sanitizedQueryParams);
    }

    /**
     * Assign display groups and user groups from request params onto a notification.
     * For edit operations, pass the pre-clear assignment arrays to allow re-saving
     * groups the caller no longer has view permission on (permission bypass pattern
     * from Campaign::edit()).
     *
     * @throws AccessDeniedException
     * @throws NotFoundException|InvalidArgumentException
     */
    private function assignAudienceFromParams(
        NotificationEntity $notification,
        SanitizerInterface $params,
        array $originalDisplayGroupIds = [],
        array $originalUserGroupIds = [],
    ): void {
        $notification->displayGroups = [];
        $notification->userGroups = [];

        foreach ($params->getIntArray('displayGroupIds', ['default' => []]) as $displayGroupId) {
            $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

            if (!$this->getUser()->checkViewable($displayGroup)
                && !in_array($displayGroupId, $originalDisplayGroupIds, true)
            ) {
                throw new AccessDeniedException(__('Access to one or more display groups denied'));
            }

            $notification->assignDisplayGroup($displayGroup);
            $this->displayNotifyService->collectLater()->notifyByDisplayGroupId($displayGroupId);
        }

        foreach ($params->getIntArray('userGroupIds', ['default' => []]) as $userGroupId) {
            $userGroup = $this->userGroupFactory->getById($userGroupId);

            if (!$this->getUser()->checkViewable($userGroup)
                && !in_array($userGroupId, $originalUserGroupIds, true)
            ) {
                throw new AccessDeniedException(__('Access to one or more user groups denied'));
            }

            $notification->assignUserGroup($userGroup);
        }
    }

    /**
     * Move an uploaded attachment from temp into the attachment folder and persist the filename.
     * No-ops when no attachedFilename was submitted.
     *
     * @throws ConfigurationException|InvalidArgumentException
     */
    private function handleAttachmentUpload(NotificationEntity $notification, SanitizerInterface $params): void
    {
        // basename() strips path components — defence against traversal sequences in the submitted name.
        $attachedFilename = basename(
            $params->getString('attachedFilename', ['defaultOnEmptyString' => true]) ?? ''
        );

        if (empty($attachedFilename)) {
            return;
        }

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $saveName = $notification->notificationId . '_' . $attachedFilename;
        $notification->filename = $saveName;
        $notification->originalFileName = $attachedFilename;

        $from = $libraryFolder . 'temp/' . $attachedFilename;
        $to = $libraryFolder . 'attachment/' . $saveName;

        $moved = rename($from, $to);

        if (!$moved) {
            $this->getLog()->info(
                'Cannot move file: ' . $from . ' to ' . $to . ', will try and copy/delete instead.'
            );

            $moved = copy($from, $to);

            if (!@unlink($from)) {
                $this->getLog()->error('Cannot delete file: ' . $from . ' after copying to ' . $to);
            }
        }

        if (!$moved) {
            throw new ConfigurationException(__('Problem moving uploaded file into the Attachment Folder'));
        }

        $notification->save();
    }
}
