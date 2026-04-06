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

use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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

    #[OA\Get(
        path: '/daypart',
        operationId: 'dayPartSearch',
        description: 'Search dayparts',
        summary: 'Daypart Search',
        tags: ['dayPart']
    )]
    #[OA\Parameter(
        name: 'dayPartId',
        description: 'The dayPart ID to Search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'name',
        description: 'The name of the dayPart to Search',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by dayPart name, ID, or description',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data such as exceptions',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'dayPartId',
                'name',
                'startTime',
                'endTime',
                'description',
            ]
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
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/DayPart')
        )
    )]
    /**
     *  Search
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());

        // Construct the SQL
        $daypartSortQuery = $this->gridRenderSort(
            $sanitizedParams,
            $this->isJson($request),
        );

        $daypartFilterQuery = $this->gridRenderFilter([
            'dayPartId' => $sanitizedParams->getInt('dayPartId'),
            'name' => $sanitizedParams->getString('name'),
            'keyword' => $sanitizedParams->getString('keyword'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'isAlways' => $sanitizedParams->getInt('isAlways'),
            'isCustom' => $sanitizedParams->getInt('isCustom'),
            'isRetired' => $sanitizedParams->getInt('isRetired')
        ], $sanitizedParams);

        $dayParts = $this->dayPartFactory->query($daypartSortQuery, $daypartFilterQuery);
        $embed = ($sanitizedParams->getString('embed') != null)
            ? explode(',', $sanitizedParams->getString('embed'))
            : [];

        foreach ($dayParts as $dayPart) {
            if (!in_array('exceptions', $embed)) {
                $dayPart->excludeProperty('exceptions');
            }
            $dayPart->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($dayPart));
        }

        $recordsTotal = $this->dayPartFactory->countLast();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $recordsTotal)
            ->withJson($dayParts);
    }

    #[OA\Get(
        path: '/daypart/{dayPartId}',
        operationId: 'dayPartSearchById',
        description: 'Get the DayPart object specified by the provided daypartId',
        summary: 'Daypart Search by ID',
        tags: ['dayPart']
    )]
    #[OA\Parameter(
        name: 'dayPartId',
        description: 'Numeric ID of the DayPart to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data such as exceptions',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/DayPart')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());
        $dayPart = $this->dayPartFactory->getById($id, false);

        $embed = ($sanitizedParams->getString('embed') != null)
            ? explode(',', $sanitizedParams->getString('embed'))
            : [];

        if (!in_array('exceptions', $embed)) {
            $dayPart->excludeProperty('exceptions');
        }
        $dayPart->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($dayPart));

        return $response
            ->withStatus(200)
            ->withJson($dayPart);
    }

    #[OA\Post(
        path: '/daypart',
        operationId: 'dayPartAdd',
        description: 'Add a Daypart',
        summary: 'Daypart Add',
        tags: ['dayPart']
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', description: 'The Daypart Name', type: 'string'),
                    new OA\Property(
                        property: 'description',
                        description: 'A description for the dayPart',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'startTime',
                        description: 'The start time for this day part',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'endTime',
                        description: 'The end time for this day part',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'exceptionDays',
                        description: 'String array of exception days',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    ),
                    new OA\Property(
                        property: 'exceptionStartTimes',
                        description: 'String array of exception start times to match the exception days',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    ),
                    new OA\Property(
                        property: 'exceptionEndTimes',
                        description: 'String array of exception end times to match the exception days',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    )
                ],
                required: ['name', 'startTime', 'endTime']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/DayPart'),
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ]
    )]
    /**
     * Add
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
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

    #[OA\Put(
        path: '/daypart/{dayPartId}',
        operationId: 'dayPartEdit',
        description: 'Edit a Daypart',
        summary: 'Daypart Edit',
        tags: ['dayPart']
    )]
    #[OA\Parameter(
        name: 'dayPartId',
        description: 'The Daypart Id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', description: 'The Daypart Name', type: 'string'),
                    new OA\Property(
                        property: 'description',
                        description: 'A description for the dayPart',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'startTime',
                        description: 'The start time for this day part',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'endTime',
                        description: 'The end time for this day part',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'exceptionDays',
                        description: 'String array of exception days',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    ),
                    new OA\Property(
                        property: 'exceptionStartTimes',
                        description: 'String array of exception start times to match the exception days',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    ),
                    new OA\Property(
                        property: 'exceptionEndTimes',
                        description: 'String array of exception end times to match the exception days',
                        items: new OA\Items(type: 'string'),
                        type: 'array'
                    )
                ],
                required: ['name', 'startTime', 'endTime']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/DayPart')
    )]
    /**
     * Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
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

    #[OA\Delete(
        path: '/daypart/{dayPartId}',
        operationId: 'dayPartDelete',
        description: 'Delete the provided dayPart',
        summary: 'Delete DayPart',
        tags: ['dayPart']
    )]
    #[OA\Parameter(
        name: 'dayPartId',
        description: 'The Daypart Id to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
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
