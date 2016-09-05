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
use Xibo\Factory\DayPartFactory;
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
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dayPartFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dayPartFactory = $dayPartFactory;
    }

    /**
     * View Route
     */
    public function displayPage()
    {
        $this->getState()->template = 'daypart-page';
    }

    /**
     * Search
     */
    public function grid()
    {
        $filter = [
            'dayPartId' => $this->getSanitizer()->getInt('dayPartId'),
            'name' => $this->getSanitizer()->getString('name')
        ];

        $dayParts = $this->dayPartFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($dayParts as $dayPart) {
            /* @var \Xibo\Entity\DayPart $dayPart */

            if ($this->isApi())
                break;

            $dayPart->includeProperty('buttons');

            // Default Layout
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

        if ($dayPart->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
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

        if ($dayPart->getOwnerId() != $this->getUser()->userId && $this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'daypart-form-delete';
        $this->getState()->setData([
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

        $dayPart->save();

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
     *  operationId="dayPartAdd",
     *  tags={"dayPart"},
     *  summary="Daypart Add",
     *  description="Add a Daypart",
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
     */
    public function edit($dayPartId)
    {
        $dayPart = $this->dayPartFactory->getById($dayPartId);

        if (!$this->getUser()->checkEditable($dayPart))
            throw new AccessDeniedException();

        $this->handleCommonInputs($dayPart);
        $dayPart->save();

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
        $exceptionDays = $this->getSanitizer()->getStringArray('exceptionDay');
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
     *  tags={"daypart"},
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
     */
    public function delete($dayPartId)
    {
        $dayPart = $this->dayPartFactory->getById($dayPartId);

        if (!$this->getUser()->checkDeleteable($dayPart))
            throw new AccessDeniedException();

        $dayPart->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $dayPart->name)
        ]);
    }
}