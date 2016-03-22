<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (Notification.php)
 */


namespace Xibo\Controller;
use Xibo\Entity\DisplayGroup;
use Xibo\Entity\UserGroup;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
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

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

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
     * @param DisplayGroupFactory $displayGroupFactory
     * @param UserGroupFactory $userGroupFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $notificationFactory, $displayGroupFactory, $userGroupFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->notificationFactory = $notificationFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->userGroupFactory = $userGroupFactory;
    }

    public function displayPage()
    {
        // Call to render the template
        $this->getState()->template = 'notification-page';
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
     *      in="formData",
     *      description="Filter by Notification Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="subject",
     *      in="formData",
     *      description="Filter by Subject",
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

        $notifications = $this->notificationFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($notifications as $notification) {
            /* @var \Xibo\Entity\Notification $notification */

            if ($this->isApi())
                return;

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
                    'text' => __('Delete')
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
            /* @var DisplayGroup $displayGroup */

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        foreach ($this->userGroupFactory->query(['`group`'], ['isUserSpecific' => -1]) as $userGroup) {
            /* @var UserGroup $userGroup */

            if ($userGroup->isUserSpecific == 1) {
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

        if ($notification->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'notification-form-edit';
        $this->getState()->setData([
            'notification' => $notification
        ]);
    }

    /**
     * Delete Notification Form
     * @param int $notificationId
     */
    public function deleteForm($notificationId)
    {
        $notification = $this->notificationFactory->getById($notificationId);

        if ($notification->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'notification-form-delete';
        $this->getState()->setData([
            'notification' => $notification
        ]);
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
        $notification->body = $this->getSanitizer()->getString('body');
        $notification->createdDt = $this->getDate()->getLocalDate('U');
        $notification->releaseDt = $this->getSanitizer()->getDate('releaseDt')->format('U');
        $notification->isEmail = $this->getSanitizer()->getCheckbox('isEmail');
        $notification->isInterrupt = $this->getSanitizer()->getCheckbox('$this->isInterrupt');
        $notification->userId = $this->getUser()->userId;
        $notification->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $notification->subject),
            'id' => $notification->notificationId,
            'data' => $notification
        ]);
    }
}