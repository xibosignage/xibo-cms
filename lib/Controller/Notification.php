<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Notification.php)
 */


namespace Xibo\Controller;

use Xibo\Entity\UserGroup;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\AttachmentUploadHandler;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\DisplayNotifyService;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param NotificationFactory $notificationFactory
     * @param UserNotificationFactory $userNotificationFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayNotifyService $displayNotifyService
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $notificationFactory, $userNotificationFactory, $displayGroupFactory, $userGroupFactory, $displayNotifyService)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->notificationFactory = $notificationFactory;
        $this->userNotificationFactory = $userNotificationFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    public function displayPage()
    {
        // Call to render the template
        $this->getState()->template = 'notification-page';
    }

    /**
     * Show a notification
     * @param int $notificationId
     */
    public function interrupt($notificationId)
    {
        $notification = $this->userNotificationFactory->getByNotificationId($notificationId);

        // Mark it as read
        $notification->setRead($this->getDate()->getLocalDate(null, 'U'));
        $notification->save();

        $this->getState()->template = 'notification-interrupt';
        $this->getState()->setData(['notification' => $notification]);
    }

    /**
     * Show a notification
     * @param int $notificationId
     */
    public function show($notificationId)
    {
        $notification = $this->userNotificationFactory->getByNotificationId($notificationId);

        // Mark it as read
        $notification->setRead($this->getDate()->getLocalDate(null, 'U'));
        $notification->save();

        $this->getState()->template = 'notification-form-show';
        $this->getState()->setData(['notification' => $notification]);
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
     */
    function grid()
    {
        $filter = [
            'notificationId' => $this->getSanitizer()->getInt('notificationId'),
            'subject' => $this->getSanitizer()->getString('subject')
        ];
        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];
        $notifications = $this->notificationFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($notifications as $notification) {
            /* @var \Xibo\Entity\Notification $notification */

            if (in_array('userGroups', $embed) || in_array('displayGroups', $embed)) {
                $notification->load([
                    'loadUserGroups' => in_array('userGroups', $embed),
                    'loadDisplayGroups' => in_array('displayGroups', $embed),
                ]);
            }

            if ($this->isApi())
                continue;

            $notification->includeProperty('buttons');

            // Default Layout
            $notification->buttons[] = array(
                'id' => 'notification_button_edit',
                'url' => $this->urlFor('notification.edit.form', ['id' => $notification->notificationId]),
                'text' => __('Edit')
            );

            if ($this->getUser()->checkDeleteable($notification)) {
                $notification->buttons[] = array(
                    'id' => 'notification_button_delete',
                    'url' => $this->urlFor('notification.delete.form', ['id' => $notification->notificationId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor('notification.delete', ['id' => $notification->notificationId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'notification_button_delete'],
                        ['name' => 'text', 'value' => __('Delete?')],
                        ['name' => 'rowtitle', 'value' => $notification->subject]
                    ]
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->notificationFactory->countLast();
        $this->getState()->setData($notifications);
    }

    /**
     * Add Notification Form
     */
    public function addForm()
    {
        $groups = array();
        $displays = array();
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
    }

    /**
     * Edit Notification Form
     * @param int $notificationId
     */
    public function editForm($notificationId)
    {
        $notification = $this->notificationFactory->getById($notificationId);
        $notification->load();

        // Adjust the dates
        $notification->createdDt = $this->getDate()->getLocalDate($notification->createdDt);
        $notification->releaseDt = $this->getDate()->getLocalDate($notification->releaseDt);

        if (!$this->getUser()->checkEditable($notification))
            throw new AccessDeniedException();

        $groups = array();
        $displays = array();
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
            'displayGroupIds' => array_map(function($element) {
                return $element->displayGroupId;
            }, $notification->displayGroups),
            'userGroupIds' => array_map(function($element) {
                return $element->groupId;
            }, $notification->userGroups)
        ]);
    }

    /**
     * Delete Notification Form
     * @param int $notificationId
     */
    public function deleteForm($notificationId)
    {
        $notification = $this->notificationFactory->getById($notificationId);

        if (!$this->getUser()->checkDeleteable($notification))
            throw new AccessDeniedException();

        $this->getState()->template = 'notification-form-delete';
        $this->getState()->setData([
            'notification' => $notification
        ]);
    }

    /**
     * Add attachment
     */
    public function addAttachment()
    {

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('notification.add'),
            'upload_url' => $this->urlFor('notification.add'),
            'image_versions' => array(),
            'accept_file_types' => '/\.jpg|.jpeg|.png|.bmp|.gif|.zip|.pdf/i'
        );

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: %s', json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        new AttachmentUploadHandler($options);
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
     *      name="isEmail",
     *      in="formData",
     *      description="Flag indicating whether this notification should be emailed.",
     *      type="integer",
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
     */
    public function add()
    {
        $notification = $this->notificationFactory->createEmpty();
        $notification->subject = $this->getSanitizer()->getString('subject');
        $notification->body = $this->getSanitizer()->getParam('body', '');
        $notification->createdDt = $this->getDate()->getLocalDate(null, 'U');
        $notification->releaseDt = $this->getSanitizer()->getDate('releaseDt');

        if ($notification->releaseDt !== null)
            $notification->releaseDt = $notification->releaseDt->format('U');
        else
            $notification->releaseDt = $notification->createdDt;

        $notification->isEmail = $this->getSanitizer()->getCheckbox('isEmail');
        $notification->isInterrupt = $this->getSanitizer()->getCheckbox('isInterrupt');
        $notification->userId = $this->getUser()->userId;
        $notification->nonusers = $this->getSanitizer()->getString('nonusers');

        // Displays and Users to link
        foreach ($this->getSanitizer()->getIntArray('displayGroupIds') as $displayGroupId) {
            $notification->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));

            // Notify (don't collect)
            $this->displayNotifyService->collectLater()->notifyByDisplayGroupId($displayGroupId);
        }

        foreach ($this->getSanitizer()->getIntArray('userGroupIds') as $userGroupId) {
            $notification->assignUserGroup($this->userGroupFactory->getById($userGroupId));
        }

        $notification->save();

        $attachedFilename = $this->getSanitizer()->getString('attachedFilename');
        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        $saveName = $notification->notificationId .'_' .$attachedFilename;

        if (!empty($attachedFilename)) {

            // Move the file into the library
            // Try to move the file first
            $from = $libraryFolder . 'temp/' . $attachedFilename;
            $to = $libraryFolder . 'attachment/' .  $saveName;

            $moved = rename($from, $to);

            if (!$moved) {
                $this->getLog()->info('Cannot move file: ' . $from . ' to ' . $to . ', will try and copy/delete instead.');

                // Copy
                $moved = copy($from, $to);

                // Delete
                if (!@unlink($from)) {
                    $this->getLog()->error('Cannot delete file: ' . $from . ' after copying to ' . $to);
                }
            }

            if (!$moved)
                throw new ConfigurationException(__('Problem moving uploaded file into the Attachment Folder'));
        }

        $notification->filename = $saveName;
        $notification->originalFileName = $attachedFilename;

        $notification->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $notification->subject),
            'id' => $notification->notificationId,
            'data' => $notification
        ]);
    }

    /**
     * Edit Notification
     * @param int $notificationId
     *
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
     *      name="isEmail",
     *      in="formData",
     *      description="Flag indicating whether this notification should be emailed.",
     *      type="integer",
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
     *
     * @throws XiboException
     */
    public function edit($notificationId)
    {
        $notification = $this->notificationFactory->getById($notificationId);
        $notification->load();

        // Check Permissions
        if (!$this->getUser()->checkEditable($notification))
            throw new AccessDeniedException();

        $notification->subject = $this->getSanitizer()->getString('subject');
        $notification->body = $this->getSanitizer()->getParam('body', '');
        $notification->createdDt = $this->getDate()->getLocalDate(null, 'U');
        $notification->releaseDt = $this->getSanitizer()->getDate('releaseDt')->format('U');
        $notification->isEmail = $this->getSanitizer()->getCheckbox('isEmail');
        $notification->isInterrupt = $this->getSanitizer()->getCheckbox('isInterrupt');
        $notification->userId = $this->getUser()->userId;
        $notification->nonusers = $this->getSanitizer()->getString('nonusers');

        // Clear existing assignments
        $notification->displayGroups = [];
        $notification->userGroups = [];

        // Displays and Users to link
        foreach ($this->getSanitizer()->getIntArray('displayGroupIds') as $displayGroupId) {
            $notification->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));

            // Notify (don't collect)
            $this->displayNotifyService->collectLater()->notifyByDisplayGroupId($displayGroupId);
        }

        foreach ($this->getSanitizer()->getIntArray('userGroupIds') as $userGroupId) {
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
    }

    /**
     * Delete Notification
     * @param int $notificationId
     *
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
     *
     * @throws XiboException
     */
    public function delete($notificationId)
    {
        $notification = $this->notificationFactory->getById($notificationId);

        if (!$this->getUser()->checkDeleteable($notification))
            throw new AccessDeniedException();

        $notification->delete();

        /*Delete the attachment*/
        if (!empty($notification->filename)) {
            // Library location
            $attachmentLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION'). 'attachment/';
            if (file_exists($attachmentLocation . $notification->filename))
                unlink($attachmentLocation . $notification->filename);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $notification->subject)
        ]);
    }

    public function exportAttachment($notificationId)
    {
        $notification = $this->notificationFactory->getById($notificationId);

        $fileName = $this->getConfig()->getSetting('LIBRARY_LOCATION'). 'attachment/'.$notification->filename;

        // Return the file with PHP
        $this->setNoOutput(true);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
        header('Content-Length: ' . filesize($fileName));

        // Disable any buffering to prevent OOM errors.
        ob_end_flush();
        readfile($fileName);
        exit;
    }
}