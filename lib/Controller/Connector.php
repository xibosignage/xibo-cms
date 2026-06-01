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

use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\ConnectorDeletingEvent;
use Xibo\Event\ConnectorEnabledChangeEvent;
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
    public function __construct(
        private readonly ConnectorFactory $connectorFactory,
        private readonly WidgetFactory $widgetFactory,
    ) {
    }

    /**
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @return ResponseInterface|\Slim\Http\Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $connectors = $this->connectorFactory->query($request->getParams());

        foreach ($connectors as $connector) {
            // Instantiate and decorate the entity
            $this->decorateConnectorProperties($connector);
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', count($connectors))
            ->withJson($connectors);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param int|string $id
     * @return Response|ResponseInterface
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int|string $id): Response|ResponseInterface
    {
        // Is this an installed connector, or not.
        if (is_numeric($id)) {
            $connector = $this->connectorFactory->getById($id);
        } else {
            $connector = $this->connectorFactory->getUninstalledById($id);
        }

        $this->decorateConnectorProperties($connector);
        $interface = $this->connectorFactory->create($connector);

        return $response
            ->withStatus(200)
            ->withJson([
                'connector' => $connector,
                'providerSettings' => $interface->getProviderSettings(),
            ]);
    }

    /**
     * Get connector settings field definitions as JSON (used by the React UI)
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function editFormFields(Request $request, Response $response, $id): Response|ResponseInterface
    {
        if (is_numeric($id)) {
            $connector = $this->connectorFactory->getById($id);
        } else {
            $connector = $this->connectorFactory->getUninstalledById($id);
        }
        $interface = $this->connectorFactory->create($connector);

        return $response->withJson([
            'fields'              => $interface->getSettingsFields(),
            'settings'            => $connector->settings ?? [],
            'formSubtitle'        => $interface->getFormSubtitle(),
            'formDescriptionHtml' => $interface->getFormDescriptionHtml(),
            'formAlerts'          => $interface->getFormAlerts(),
            'enabledLabel'        => $interface->getEnabledLabel(),
            'enabledDescription'  => $interface->getEnabledDescription(),
            'enabledMessage'      => $interface->getEnabledMessage(),
        ]);
    }

    /**
     * Edit Connector Form Proxy
     *  this is a magic method used to call a connector method which returns some JSON data
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @param string $method
     * @return ResponseInterface|Response
     * @throws \Slim\Exception\HttpMethodNotAllowedException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function editFormProxy(
        Request $request,
        Response $response,
        int $id,
        string $method
    ): Response|ResponseInterface {
        $connector = $this->connectorFactory->getById($id);
        $interface = $this->connectorFactory->create($connector);

        if (method_exists($interface, $method)) {
            $ref = new \ReflectionMethod($interface, $method);
            $result = $ref->getNumberOfRequiredParameters() > 0
                ? $interface->{$method}($this->getSanitizer($request->getParams()))
                : $interface->{$method}();
            return $response->withJson($result);
        } else {
            throw new HttpMethodNotAllowedException($request);
        }
    }

    /**
     * Edit Connector
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
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
            // Others
            $this->getDispatcher()->dispatch(
                new ConnectorDeletingEvent($connector, $this->getConfig()),
                ConnectorDeletingEvent::$NAME
            );

            // Ourselves
            if (method_exists($interface, 'delete')) {
                $interface->delete($this->getConfig());
            }

            $connector->delete();

            // Successful
            return $response->withStatus(204);
        } else {
            // Core properties
            $connector->isEnabled = $params->getCheckbox('isEnabled');

            // Enabled state change.
            // Update ourselves, and any others that might be interested.
            if ($connector->hasPropertyChanged('isEnabled')) {
                // Others
                $this->getDispatcher()->dispatch(
                    new ConnectorEnabledChangeEvent($connector, $this->getConfig()),
                    ConnectorEnabledChangeEvent::$NAME
                );

                // Ourselves
                if ($connector->isEnabled && method_exists($interface, 'enable')) {
                    $interface->enable($this->getConfig());
                } else if (!$connector->isEnabled && method_exists($interface, 'disable')) {
                    $interface->disable($this->getConfig());
                }
            }

            $connector->settings = $interface->processSettingsForm($params, $connector->settings);
            $connector->save();

            // Successful
            return $response
                ->withStatus(200)
                ->withJson($connector);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface
     * @throws AccessDeniedException
     * @throws NotFoundException
     */
    public function connectorPreview(Request $request, Response $response): ResponseInterface
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

    /**
     * @param \Xibo\Entity\Connector $connector
     * @return void
     */
    private function decorateConnectorProperties(\Xibo\Entity\Connector $connector): void
    {
        try {
            $connector->decorate($this->connectorFactory->create($connector));
        } catch (NotFoundException) {
            $this->getLog()->info('Connector installed which is not found in this CMS. ' . $connector->className);
            $connector->setUnmatchedProperty('isHidden', 1);
        } catch (\Exception $e) {
            $this->getLog()->error('Incorrectly configured connector '
                . $connector->className . '. e=' . $e->getMessage());
            $connector->setUnmatchedProperty('isHidden', 1);
        }
    }
}
