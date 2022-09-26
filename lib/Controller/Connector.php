<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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
use Xibo\Factory\WidgetFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Connector controller to view, activate and install connectors.
 */
class Connector extends Base
{
    /** @var \Xibo\Factory\ConnectorFactory */
    private $connectorFactory;
    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    public function __construct(ConnectorFactory $connectorFactory, WidgetFactory $widgetFactory)
    {
        $this->connectorFactory = $connectorFactory;
        $this->widgetFactory = $widgetFactory;
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
        $params = $this->getSanitizer($request->getParams());

        $connectors = $this->connectorFactory->query($request->getParams());

        // Should we show uninstalled connectors?
        if ($params->getCheckbox('showUninstalled')) {
            $connectors = array_merge($connectors, $this->connectorFactory->getUninstalled());
        }

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
        $this->getState()->recordsTotal = count($connectors);
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
        // Is this an installed connector, or not.
        if (is_numeric($id)) {
            $connector = $this->connectorFactory->getById($id);
        } else {
            $connector = $this->connectorFactory->getUninstalledById($id);
        }
        $interface = $this->connectorFactory->create($connector);

        // Are some settings provided?
        $platformProvidesSettings = count($this->getConfig()->getConnectorSettings($interface->getSourceName())) > 0;

        $this->getState()->template = $interface->getSettingsFormTwig() ?: 'connector-form-edit';
        $this->getState()->setData([
            'connector' => $connector,
            'interface' => $interface,
            'platformProvidesSettings' => $platformProvidesSettings
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
        $params = $this->getSanitizer($request->getParams());
        if (is_numeric($id)) {
            $connector = $this->connectorFactory->getById($id);
        } else {
            $connector = $this->connectorFactory->getUninstalledById($id);

            // Null the connectorId so that we add this to the database.
            $connector->connectorId = null;
        }
        $interface = $this->connectorFactory->create($connector);

        // Is this an uninstallation request
        if ($params->getCheckbox('shouldUninstall')) {
            $connector->delete();

            // Successful
            $this->getState()->hydrate([
                'message' => sprintf(__('Uninstalled %s'), $interface->getTitle())
            ]);
        } else {
            // Core properties
            $connector->isEnabled = $params->getCheckbox('isEnabled');
            $connector->settings = $interface->processSettingsForm($params, $connector->settings);
            $connector->save();

            // Successful
            $this->getState()->hydrate([
                'message' => sprintf(__('Edited %s'), $interface->getTitle()),
                'id' => $id,
                'data' => $connector
            ]);
        }

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $token
     * @return \Psr\Http\Message\ResponseInterface
     * @throws AccessDeniedException
     */
    public function connectorPreview(Request $request, Response $response)
    {
        $params = $this->getSanitizer($request->getParams());
        $token = $params->getString('token');
        $isDebug = $params->getCheckbox('isDebug');

        if (empty($token)) {
            throw new AccessDeniedException();
        }

        // Dispatch an event to check the token
        $tokenEvent = new \Xibo\Event\XmdsConnectorTokenEvent();
        $tokenEvent->setToken($token);
        $this->getDispatcher()->dispatch($tokenEvent, \Xibo\Event\XmdsConnectorTokenEvent::$NAME);

        if (empty($tokenEvent->getWidgetId())) {
            throw new AccessDeniedException();
        }

        // Get the widget
        $widget = $this->widgetFactory->getById($tokenEvent->getWidgetId());

        // It has been found, so we raise an event here to see if any connector can provide a file for it.
        $event = new \Xibo\Event\XmdsConnectorFileEvent($widget, $isDebug);
        $this->getDispatcher()->dispatch($event, \Xibo\Event\XmdsConnectorFileEvent::$NAME);

        // What now?
        return $event->getResponse();
    }
}
