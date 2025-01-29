<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class DayPart
 * @package Xibo\Controller
 */
class DayPart extends Base
{
    /** @var  DayPartFactory */
    private $dayPartFactory;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /**
     * Set common dependencies.
     * @param DayPartFactory $dayPartFactory
     * @param ScheduleFactory $scheduleFactory
     * @param \Xibo\Service\DisplayNotifyServiceInterface $displayNotifyService
     */
    public function __construct($dayPartFactory, $scheduleFactory, DisplayNotifyServiceInterface $displayNotifyService)
    {
        $this->dayPartFactory = $dayPartFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    /**
     * View Route
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'daypart-page';
        
        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());
        
        $filter = [
            'dayPartId' => $sanitizedParams->getInt('dayPartId'),
            'name' => $sanitizedParams->getString('name'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'isAlways' => $sanitizedParams->getInt('isAlways'),
            'isCustom' => $sanitizedParams->getInt('isCustom'),
            'isRetired' => $sanitizedParams->getInt('isRetired')
        ];

        $dayParts = $this->dayPartFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter($filter, $sanitizedParams));
        $embed = ($sanitizedParams->getString('embed') != null) ? explode(',', $sanitizedParams->getString('embed')) : [];
        
        foreach ($dayParts as $dayPart) {
            /* @var \Xibo\Entity\DayPart $dayPart */
            if (!in_array('exceptions', $embed)){
                $dayPart->excludeProperty('exceptions');
            }
            if ($this->isApi($request))
                continue;

            $dayPart->includeProperty('buttons');

            if ($dayPart->isCustom !== 1
                && $dayPart->isAlways !== 1
                && $this->getUser()->featureEnabled('daypart.modify')
            ) {
                // CRUD
                $dayPart->buttons[] = array(
                    'id' => 'daypart_button_edit',
                    'url' => $this->urlFor($request,'daypart.edit.form', ['id' => $dayPart->dayPartId]),
                    'text' => __('Edit')
                );

                if ($this->getUser()->checkDeleteable($dayPart)) {
                    $dayPart->buttons[] = [
                        'id' => 'daypart_button_delete',
                        'url' => $this->urlFor($request,'daypart.delete.form', ['id' => $dayPart->dayPartId]),
                        'text' => __('Delete'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'daypart.delete', ['id' => $dayPart->dayPartId])],
                            ['name' => 'commit-method', 'value' => 'delete'],
                            ['name' => 'id', 'value' => 'daypart_button_delete'],
                            ['name' => 'text', 'value' => __('Delete')],
                            ['name' => 'sort-group', 'value' => 1],
                            ['name' => 'rowtitle', 'value' => $dayPart->name]
                        ]
                    ];
                }
            }

            if ($this->getUser()->checkPermissionsModifyable($dayPart)
                && $this->getUser()->featureEnabled('daypart.modify')
            ) {
                if (count($dayPart->buttons) > 0)
                    $dayPart->buttons[] = ['divider' => true];

                // Edit Permissions
                $dayPart->buttons[] = [
                    'id' => 'daypart_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'DayPart', 'id' => $dayPart->dayPartId]),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'user.permissions.multi', ['entity' => 'DayPart', 'id' => $dayPart->dayPartId])],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'daypart_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $dayPart->name],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        ['name' => 'custom-handler-url', 'value' => $this->urlFor($request,'user.permissions.multi.form', ['entity' => 'DayPart'])],
                        ['name' => 'content-id-name', 'value' => 'dayPartId']
                    ]
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->dayPartFactory->countLast();
        $this->getState()->setData($dayParts);

        return $this->render($request, $response);
    }

    /**
     * Add Daypart Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'daypart-form-add';
        $this->getState()->setData([
            'extra' => [
                'exceptions' => []
            ]
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Daypart
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
        $dayPart = $this->dayPartFactory->getById($id);

        if (!$this->getUser()->checkEditable($dayPart)) {
            throw new AccessDeniedException();
        }

        if ($dayPart->isAlways === 1 || $dayPart->isCustom === 1) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'daypart-form-edit';
        $this->getState()->setData([
            'dayPart' => $dayPart,
            'extra' => [
                'exceptions' => $dayPart->exceptions
            ]
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Daypart
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
        $dayPart = $this->dayPartFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($dayPart)) {
            throw new AccessDeniedException();
        }

        if ($dayPart->isAlways === 1 || $dayPart->isCustom === 1) {
            throw new AccessDeniedException();
        }

        // Get a count of schedules for this day part
        $schedules = $this->scheduleFactory->getByDayPartId($id);

        $this->getState()->template = 'daypart-form-delete';
        $this->getState()->setData([
            'countSchedules' => count($schedules),
            'dayPart' => $dayPart
        ]);

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response)
    {
        $dayPart = $this->dayPartFactory->createEmpty();
        $this->handleCommonInputs($dayPart, $request);

        $dayPart
            ->setScheduleFactory($this->scheduleFactory, $this->displayNotifyService)
            ->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $dayPart->name),
            'id' => $dayPart->dayPartId,
            'data' => $dayPart
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
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
     */
    public function edit(Request $request, Response $response, $id)
    {
        $dayPart = $this->dayPartFactory->getById($id)
            ->load();

        if (!$this->getUser()->checkEditable($dayPart)) {
            throw new AccessDeniedException();
        }

        if ($dayPart->isAlways === 1 || $dayPart->isCustom === 1) {
            throw new AccessDeniedException();
        }

        $this->handleCommonInputs($dayPart, $request);
        $dayPart
            ->setScheduleFactory($this->scheduleFactory, $this->displayNotifyService)
            ->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $dayPart->name),
            'id' => $dayPart->dayPartId,
            'data' => $dayPart
        ]);

        return $this->render($request, $response);
    }

    /**
     * Handle common inputs
     * @param \Xibo\Entity\DayPart $dayPart
     * @param Request $request
     */
    private function handleCommonInputs($dayPart, Request $request)
    {
        $dayPart->userId = $this->getUser()->userId;
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $dayPart->name = $sanitizedParams->getString('name');
        $dayPart->description = $sanitizedParams->getString('description');
        $dayPart->isRetired = $sanitizedParams->getCheckbox('isRetired');
        $dayPart->startTime = $sanitizedParams->getString('startTime');
        $dayPart->endTime = $sanitizedParams->getString('endTime');

        // Exceptions
        $exceptionDays = $sanitizedParams->getArray('exceptionDays', ['default' => []]);
        $exceptionStartTimes = $sanitizedParams->getArray('exceptionStartTimes', ['default' => []]);
        $exceptionEndTimes = $sanitizedParams->getArray('exceptionEndTimes', ['default' => []]);

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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
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
     */
    public function delete(Request $request, Response $response, $id)
    {
        $dayPart = $this->dayPartFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($dayPart)) {
            throw new AccessDeniedException();
        }

        if ($dayPart->isSystemDayPart()) {
            throw new InvalidArgumentException(__('Cannot Delete system specific DayParts'));
        }

        $dayPart
            ->setScheduleFactory($this->scheduleFactory, $this->displayNotifyService)
            ->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $dayPart->name)
        ]);

        return $this->render($request, $response);
    }
}