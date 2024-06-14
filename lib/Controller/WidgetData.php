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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\WidgetDataFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Controller for managing Widget Data
 */
class WidgetData extends Base
{
    public function __construct(
        private readonly WidgetDataFactory $widgetDataFactory,
        private readonly WidgetFactory $widgetFactory
    ) {
    }

    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function get(Request $request, Response $response, int $widgetId): Response
    {
        $widget = $this->widgetFactory->getById($widgetId);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }


        $data = [];


        return $response->withJson($data);
    }

    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function add(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParams());
        $widgetId = $params->getInt('widgetId');

        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($widgetId);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Get the other params.
        $data = $this->widgetDataFactory->createEmpty();
        $data->widgetId = $widgetId;
        $data->data = $params->getArray('data');
        $data->displayOrder = $params->getInt('displayOrder');
        $data->save();

        // Successful
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added data for Widget'),
            'id' => $data->id,
            'data' => $data,
        ]);

        return $this->render($request, $response);
    }

    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        $data = $this->widgetDataFactory->getById($id);

        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($data->widgetId);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Get params and process the edit
        $params = $this->getSanitizer($request->getParams());
        $data->data = $params->getArray('data');
        $data->displayOrder = $params->getInt('displayOrder');
        $data->save();

        // Successful
        $this->getState()->hydrate([
            'message' => __('Edited data for Widget'),
            'id' => $data->id,
            'data' => $data,
        ]);

        return $this->render($request, $response);
    }

    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function delete(Request $request, Response $response, int $id): Response
    {
        $data = $this->widgetDataFactory->getById($id);

        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($data->widgetId);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Delete it.
        $data->delete();

        // Successful
        $this->getState()->hydrate(['message' => __('Deleted')]);

        return $this->render($request, $response);
    }

    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function setOrder(Request $request, Response $response, int $widgetId): Response
    {
        $params = $this->getSanitizer($request->getParams());

        // Check that we have permission to edit this widget
        $widget = $this->widgetFactory->getById($widgetId);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Expect an array of `id` in order.

        // Successful
        $this->getState()->hydrate([
            'message' => __('Edited data for Widget'),
            'id' => $widget->widgetId,
        ]);

        return $this->render($request, $response);
    }
}
