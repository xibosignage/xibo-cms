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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\WidgetDataFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Controller for managing Widget Data
 */
class WidgetData extends Base
{
    public function __construct(
        private readonly WidgetDataFactory $widgetDataFactory,
        private readonly WidgetFactory $widgetFactory,
        private readonly ModuleFactory $moduleFactory
    ) {
    }

    #[OA\Get(
        path: '/playlist/widget/data/{id}',
        operationId: 'getWidgetData',
        description: 'Return all of the fallback data currently assigned to this Widget',
        summary: 'Get data for Widget',
        tags: ['widget']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The Widget ID that this data should be added to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/WidgetData'))
    )]
    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function get(Request $request, Response $response, int $id): Response
    {
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        return $response->withJson($this->widgetDataFactory->getByWidgetId($widget->widgetId));
    }

    #[OA\Post(
        path: '/playlist/widget/data/{id}',
        operationId: 'addWidgetData',
        description: 'Add fallback data to a data Widget',
        summary: 'Add a data to a Widget',
        tags: ['widget'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The Widget ID that this data should be added to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        description: 'A JSON formatted string containing a single data item for this widget\'s data type', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'displayOrder',
                        description: 'Optional integer to say which position this data should appear if there is more than one data item', // phpcs:ignore
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        headers: [new OA\Header(
            header: 'Location',
            description: 'Location of the new record',
            schema: new OA\Schema(type: 'string')
        )]
    )]
    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function add(Request $request, Response $response, int $id): Response
    {
        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Get the other params.
        $params = $this->getSanitizer($request->getParams());
        $widgetData = $this->widgetDataFactory
            ->create(
                $widget->widgetId,
                $this->parseAndValidate($widget, $params->getArray('data')),
                $params->getInt('displayOrder', ['default' => 1]),
            )
            ->save();

        // Update the widget modified dt
        $widget->modifiedDt =

        // Successful
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added data for Widget'),
            'id' => $widgetData->id,
            'data' => $widgetData,
        ]);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/playlist/widget/data/{id}/{dataId}',
        operationId: 'editWidgetData',
        description: 'Edit fallback data on a data Widget',
        summary: 'Edit data on a Widget',
        tags: ['widget'],
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The Widget ID that this data is attached to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'dataId',
        description: 'The ID of the data to be edited',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        description: 'A JSON formatted string containing a single data item for this widget\'s data type', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'displayOrder',
                        description: 'Optional integer to say which position this data should appear if there is more than one data item', // phpcs:ignore
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function edit(Request $request, Response $response, int $id, int $dataId): Response
    {
        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Make sure this dataId is for this widget
        $widgetData = $this->widgetDataFactory->getById($dataId);

        if ($id !== $widgetData->widgetId) {
            throw new AccessDeniedException(__('This widget data does not belong to this widget'));
        }

        // Get params and process the edit
        $params = $this->getSanitizer($request->getParams());
        $widgetData->data = $this->parseAndValidate($widget, $params->getArray('data'));
        $widgetData->displayOrder = $params->getInt('displayOrder', ['default' => 1]);
        $widgetData->save();

        // Successful
        $this->getState()->hydrate([
            'message' => __('Edited data for Widget'),
            'id' => $widgetData->id,
            'data' => $widgetData,
            'httpStatus' => 204,
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/playlist/widget/data/{id}/{dataId}',
        operationId: 'deleteWidgetData',
        description: 'Delete fallback data on a data Widget',
        summary: 'Delete data on a Widget',
        tags: ['widget']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The Widget ID that this data is attached to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'dataId',
        description: 'The ID of the data to be deleted',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function delete(Request $request, Response $response, int $id, int $dataId): Response
    {
        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Make sure this dataId is for this widget
        $widgetData = $this->widgetDataFactory->getById($dataId);

        if ($id !== $widgetData->widgetId) {
            throw new AccessDeniedException(__('This widget data does not belong to this widget'));
        }

        // Delete it.
        $widgetData->delete();

        // Successful
        $this->getState()->hydrate(['message' => __('Deleted'), 'httpStatus' => 204]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/playlist/widget/data/{id}/order',
        operationId: 'orderWidgetData',
        description: 'Provide all data to be ordered on a widget',
        summary: 'Update the order of data on a Widget',
        tags: ['widget']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The Widget ID that this data is attached to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'dataId', description: 'Data ID', type: 'integer'),
                    new OA\Property(property: 'displayOrder', description: 'Desired display order', type: 'integer')
                ],
                type: 'object'
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function setOrder(Request $request, Response $response, int $id): Response
    {
        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Expect an array of `id` in order.
        $params = $this->getSanitizer($request->getParams());
        foreach ($params->getArray('order', ['default' => []]) as $item) {
            $itemParams = $this->getSanitizer($item);

            // Make sure this dataId is for this widget
            $widgetData = $this->widgetDataFactory->getById($itemParams->getInt('dataId'));
            $widgetData->displayOrder = $itemParams->getInt('displayOrder');

            if ($id !== $widgetData->widgetId) {
                throw new AccessDeniedException(__('This widget data does not belong to this widget'));
            }

            // Save it
            $widgetData->save();
        }

        // Successful
        $this->getState()->hydrate([
            'message' => __('Updated the display order for data on Widget'),
            'id' => $widget->widgetId,
            'httpStatus' => 204,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Parse and validate the data provided in params.
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function parseAndValidate(\Xibo\Entity\Widget $widget, array $item): array
    {
        // Check that this module is a data widget
        $module = $this->moduleFactory->getByType($widget->type);
        if (!$module->isDataProviderExpected()) {
            throw new InvalidArgumentException(__('This is not a data widget'));
        }

        if ($module->fallbackData !== 1) {
            throw new InvalidArgumentException(__('Fallback data is not expected for this Widget'));
        }

        // Parse out the data string we've been given and make sure it's valid according to this widget's datatype
        $data = [];
        $params = $this->getSanitizer($item);

        $dataType = $this->moduleFactory->getDataTypeById($module->dataType);
        foreach ($dataType->fields as $field) {
            if ($field->isRequired && !$params->hasParam($field->id)) {
                throw new InvalidArgumentException(sprintf(
                    'Data is missing a field called %s',
                    $field->title
                ));
            }

            $value = match ($field->type) {
                'number' => $params->getDouble($field->id),
                default => $params->getString($field->id),
            };
            $data[$field->id] = $value;
        }

        return $data;
    }
}
