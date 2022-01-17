<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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
use Xibo\Factory\ConnectorFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Connector controller to view, active and install connectors.
 */
class Connector extends Base
{
    /** @var \Xibo\Factory\ConnectorFactory */
    private $connectorFactory;

    public function __construct(ConnectorFactory $connectorFactory)
    {
        $this->connectorFactory = $connectorFactory;
    }

    /**
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @return \Psr\Http\Message\ResponseInterface|\Slim\Http\Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response)
    {
        $connectors = $this->connectorFactory->query($request->getParams());
        foreach ($connectors as $connector) {
            // Instantiate and decorate the entity
            try {
                $connector->decorate($this->connectorFactory->create($connector));
            } catch (\Exception $e) {
                $this->getLog()->error('Incorrectly configured connector '
                    . $connector->className . '. e=' . $e->getMessage());
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->connectorFactory->countLast();
        $this->getState()->setData($connectors);

        return $this->render($request, $response);
    }

    /**
     * Edit Connector Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $connector = $this->connectorFactory->getById($id);
        $interface = $this->connectorFactory->create($connector);

        $this->getState()->template = $interface->getSettingsFormTwig() ?? 'connector-form-edit';
        $this->getState()->setData([
            'connector' => $connector,
            'interface' => $interface
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Connector
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $connector = $this->connectorFactory->getById($id);
        $interface = $this->connectorFactory->create($connector);

        // Core properties
        $params = $this->getSanitizer($request->getParams());
        $connector->isEnabled = $params->getCheckbox('isEnabled');
        $connector->settings = $interface->processSettingsForm($params, $connector->settings);
        $connector->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $interface->getTitle()),
            'id' => $id,
            'data' => $connector
        ]);

        return $this->render($request, $response);
    }
}
