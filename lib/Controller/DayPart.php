<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2016 Spring Signage Ltd - http://www.springsignage.com
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

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class DayPart
 * @package Xibo\Controller
 */
class DayPart extends Base
{
    /** @var  DayPartFactory */
    private $dayPartFactory;

    /** @var DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DayPartFactory $dayPartFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dayPartFactory, $displayGroupFactory, $displayFactory, $layoutFactory, $mediaFactory, $scheduleFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dayPartFactory = $dayPartFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
    }

    /**
     * View Route
     */
    public function displayPage()
    {
        $this->getState()->template = 'daypart-page';
    }

    /**
     *  Search
     *
     * @SWG\Get(
     *  path="/daypart",
     *  operationId="dayPartSearch",
     *  tags={"dayPart"},
     *  summary="Daypart Search",
     *  description="Search dayparts",
     *  @SWG\Parameter(
     *      name="dayPartId",
     *      in="query",
     *      description="The dayPart ID to Search",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="query",
     *      description="The name of the dayPart to Search",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data such as exceptions",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/DayPart")
     *      )
     *  )
     * )
     */
    public function grid()
    {
        $filter = [
            'dayPartId' => $this->getSanitizer()->getInt('dayPartId'),
            'name' => $this->getSanitizer()->getString('name'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'isAlways' => $this->getSanitizer()->getInt('isAlways'),
            'isCustom' => $this->getSanitizer()->getInt('isCustom')
        ];

        $dayParts = $this->dayPartFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));
        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];
        
        foreach ($dayParts as $dayPart) {
            /* @var \Xibo\Entity\DayPart $dayPart */
            if (!in_array('exceptions', $embed)){
                $dayPart->excludeProperty('exceptions');
            }
            if ($this->isApi())
                continue;

            $dayPart->includeProperty('buttons');

            if ($dayPart->isCustom !== 1 && $dayPart->isAlways !== 1) {
                // CRUD
                $dayPart->buttons[] = array(
                    'id' => 'daypart_button_edit',
                    'url' => $this->urlFor('daypart.edit.form', ['id' => $dayPart->dayPartId]),
                    'text' => __('Edit')
                );

                if ($this->getUser()->checkDeleteable($dayPart)) {
                    $dayPart->buttons[] = array(
                        'id' => 'daypart_button_delete',
                        'url' => $this->urlFor('daypart.delete.form', ['id' => $dayPart->dayPartId]),
                        'text' => __('Delete'),
                        'multi-select' => true,
                        'dataAttributes' => array(
                            array('name' => 'commit-url', 'value' => $this->urlFor('daypart.delete', ['id' => $dayPart->dayPartId])),
                            array('name' => 'commit-method', 'value' => 'delete'),
                            array('name' => 'id', 'value' => 'daypart_button_delete'),
                            array('name' => 'text', 'value' => __('Delete')),
                            array('name' => 'rowtitle', 'value' => $dayPart->name)
                        )
                    );
                }
            }

            if ($this->getUser()->checkPermissionsModifyable($dayPart)) {

                if (count($dayPart->buttons) > 0)
                    $dayPart->buttons[] = ['divider' => true];

                // Edit Permissions
                $dayPart->buttons[] = array(
                    'id' => 'daypart_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'DayPart', 'id' => $dayPart->dayPartId]),
                    'text' => __('Permissions')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->dayPartFactory->countLast();
        $this->getState()->setData($dayParts);
    }

    /**
     * Add Daypart Form
     */
    public function addForm()
    {
        $this->getState()->template = 'daypart-form-add';
        $this->getState()->setData([
            'extra' => [
                'exceptions' => []
            ]
        ]);
    }

    /**
     * Edit Daypart
     * @param int $dayPartId
     */
    public function editForm($dayPartId)
    {
        $dayPart = $this->dayPartFactory->getById($dayPartId);

        if (!$this->getUser()->checkEditable($dayPart))
            throw new AccessDeniedException();

        if ($dayPart->isAlways === 1 || $dayPart->isCustom === 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'daypart-form-edit';
        $this->getState()->setData([
            'dayPart' => $dayPart,
            'extra' => [
                'exceptions' => $dayPart->exceptions
            ]
        ]);
    }

    /**
     * Delete Daypart
     * @param int $dayPartId
     */
    public function deleteForm($dayPartId)
    {
        $dayPart = $this->dayPartFactory->getById($dayPartId);

        if (!$this->getUser()->checkDeleteable($dayPart))
            throw new AccessDeniedException();

        if ($dayPart->isAlways === 1 || $dayPart->isCustom === 1)
            throw new AccessDeniedException();

        // Get a count of schedules for this day part
        $schedules = $this->scheduleFactory->getByDayPartId($dayPartId);

        $this->getState()->template = 'daypart-form-delete';
        $this->getState()->setData([
            'countSchedules' => count($schedules),
            'dayPart' => $dayPart
        ]);
    }

    /**
     * Add
     * @SWG\Post(
     *  path="/daypart",
     *  operationId="dayPartAdd",
     *  tags={"dayPart"},
     *  summary="Daypart Add",
     *  description="Add a Daypart",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Daypart Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description for the dayPart",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="startTime",
     *      in="formData",
     *      description="The start time for this day part",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="endTime",
     *      in="formData",
     *      description="The end time for this day part",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="exceptionDays",
     *      in="formData",
     *      description="String array of exception days",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Parameter(
     *      name="exceptionStartTimes",
     *      in="formData",
     *      description="String array of exception start times to match the exception days",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Parameter(
     *      name="exceptionEndTimes",
     *      in="formData",
     *      description="String array of exception end times to match the exception days",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DayPart"),
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
        $dayPart = $this->dayPartFactory->createEmpty();
        $this->handleCommonInputs($dayPart);

        $dayPart
            ->setScheduleFactory($this->scheduleFactory)
            ->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $dayPart->name),
            'id' => $dayPart->dayPartId,
            'data' => $dayPart
        ]);
    }

    /**
     * Edit
     * @param int $dayPartId
     *
     * @SWG\Put(
     *  path="/daypart/{dayPartId}",
     *  operationId="dayPartEdit",
     *  tags={"dayPart"},
     *  summary="Daypart Edit",
     *  description="Edit a Daypart",
     *  @SWG\Parameter(
     *      name="dayPartId",
     *      in="path",
     *      description="The Daypart Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Daypart Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description for the dayPart",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="startTime",
     *      in="formData",
     *      description="The start time for this day part",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="endTime",
     *      in="formData",
     *      description="The end time for this day part",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="exceptionDays",
     *      in="formData",
     *      description="String array of exception days",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Parameter(
     *      name="exceptionStartTimes",
     *      in="formData",
     *      description="String array of exception start times to match the exception days",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Parameter(
     *      name="exceptionEndTimes",
     *      in="formData",
     *      description="String array of exception end times to match the exception days",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DayPart")
     *  )
     * )
     *
     * @throws XiboException
     */
    public function edit($dayPartId)
    {
        $dayPart = $this->dayPartFactory->getById($dayPartId)
            ->setDateService($this->getDate())
            ->setChildObjectDependencies($this->displayGroupFactory, $this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory, $this->dayPartFactory)
            ->load();

        if (!$this->getUser()->checkEditable($dayPart))
            throw new AccessDeniedException();

        if ($dayPart->isAlways === 1 || $dayPart->isCustom === 1)
            throw new AccessDeniedException();

        $this->handleCommonInputs($dayPart);
        $dayPart
            ->setScheduleFactory($this->scheduleFactory)
            ->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $dayPart->name),
            'id' => $dayPart->dayPartId,
            'data' => $dayPart
        ]);
    }

    /**
     * Handle common inputs
     * @param $dayPart
     */
    private function handleCommonInputs($dayPart)
    {
        $dayPart->userId = $this->getUser()->userId;
        $dayPart->name = $this->getSanitizer()->getString('name');
        $dayPart->description = $this->getSanitizer()->getString('description');
        $dayPart->isRetired = $this->getSanitizer()->getCheckbox('isRetired');
        $dayPart->startTime = $this->getSanitizer()->getString('startTime');
        $dayPart->endTime = $this->getSanitizer()->getString('endTime');

        // Exceptions
        $exceptionDays = $this->getSanitizer()->getStringArray('exceptionDays');
        $exceptionStartTimes = $this->getSanitizer()->getStringArray('exceptionStartTimes');
        $exceptionEndTimes = $this->getSanitizer()->getStringArray('exceptionEndTimes');

        // Clear down existing exceptions
        $dayPart->exceptions = [];

        $i = -1;
        foreach ($exceptionDays as $exceptionDay) {
            // Pull the corrisponding start/end time out of the same position in the array
            $i++;

            $exceptionDayStartTime = isset($exceptionStartTimes[$i]) ? $exceptionStartTimes[$i] : '';
            $exceptionDayEndTime = isset($exceptionEndTimes[$i]) ? $exceptionEndTimes[$i] : '';

            if ($exceptionDay == '' || $exceptionDayStartTime == '' || $exceptionDayEndTime == '')
                continue;

            // Is this already set?
            $found = false;
            foreach ($dayPart->exceptions as $exception) {

                if ($exception['day'] == $exceptionDay) {
                    $exception['start'] = $exceptionDayStartTime;
                    $exception['end'] = $exceptionDayEndTime;

                    $found = true;
                    break;
                }
            }

            // Otherwise add it
            if (!$found) {
                $dayPart->exceptions[] = [
                    'day' => $exceptionDay,
                    'start' => $exceptionDayStartTime,
                    'end' => $exceptionDayEndTime
                ];
            }
        }
    }

    /**
     * Delete
     * @param int $dayPartId
     *
     * @SWG\Delete(
     *  path="/daypart/{dayPartId}",
     *  operationId="dayPartDelete",
     *  tags={"dayPart"},
     *  summary="Delete DayPart",
     *  description="Delete the provided dayPart",
     *  @SWG\Parameter(
     *      name="dayPartId",
     *      in="path",
     *      description="The Daypart Id to Delete",
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
    public function delete($dayPartId)
    {
        $dayPart = $this->dayPartFactory->getById($dayPartId);

        if (!$this->getUser()->checkDeleteable($dayPart))
            throw new AccessDeniedException();

        $dayPart
            ->setDateService($this->getDate())
            ->setScheduleFactory($this->scheduleFactory)
            ->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $dayPart->name)
        ]);
    }
}