<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\SubPlaylistItemsEvent;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class PlaylistDashboard
 * @package Xibo\Controller
 */
class PlaylistDashboard extends Base
{
    /** @var \Xibo\Factory\PlaylistFactory */
    private $playlistFactory;

    /** @var \Xibo\Factory\ModuleFactory */
    private $moduleFactory;

    /** @var \Xibo\Factory\WidgetFactory */
    private $widgetFactory;

    /** @var \Xibo\Factory\MediaFactory */
    private $mediaFactory;

    /** @var ContainerInterface */
    private $container;

    /**
     * PlaylistDashboard constructor.
     * @param $playlistFactory
     * @param $moduleFactory
     * @param $widgetFactory
     * @param \Xibo\Factory\MediaFactory $mediaFactory
     * @param ContainerInterface $container
     */
    public function __construct($playlistFactory, $moduleFactory, $widgetFactory, $mediaFactory, ContainerInterface $container)
    {
        $this->playlistFactory = $playlistFactory;
        $this->moduleFactory = $moduleFactory;
        $this->widgetFactory = $widgetFactory;
        $this->mediaFactory = $mediaFactory;
        $this->container = $container;
    }

    /**
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @return \Psr\Http\Message\ResponseInterface|\Slim\Http\Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        // Do we have a Playlist already in our User Preferences?
        $playlist = null;
        try {
            $playlistId = $this->getUser()->getOption('playlistDashboardSelectedPlaylistId');
            if ($playlistId->value != 0) {
                $playlist = $this->playlistFactory->getById($playlistId->value);
            }
        } catch (NotFoundException $notFoundException) {
            // this is fine, no need to throw errors here.
            $this->getLog()->debug(
                'Problem getting playlistDashboardSelectedPlaylistId user option. e = ' .
                $notFoundException->getMessage()
            );
        }

        $this->getState()->template = 'playlist-dashboard';
        $this->getState()->setData([
            'playlist' => $playlist,
            'validExtensions' => implode('|', $this->moduleFactory->getValidExtensions())
        ]);

        return $this->render($request, $response);
    }

    /**
     * Grid used for the Playlist drop down list
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Playlists
        $playlists = $this->playlistFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter([
            'name' => $this->getSanitizer($request->getParams())->getString('name'),
            'regionSpecific' => 0
        ], $sanitizedParams));

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->playlistFactory->countLast();
        $this->getState()->setData($playlists);

        return $this->render($request, $response);
    }

    /**
     * Show a particular playlist
     *  the output from this is very much like a form.
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function show(Request $request, Response $response, $id)
    {
        // Record this Playlist as the one we have currently selected.
        try {
            $this->getUser()->setOptionValue('playlistDashboardSelectedPlaylistId', $id);
            $this->getUser()->save();
        } catch (GeneralException $exception) {
            $this->getLog()->error('Problem setting playlistDashboardSelectedPlaylistId user option. e = ' . $exception->getMessage());
        }

        // Spots
        $spotsFound = 0;

        $playlist = $this->playlistFactory->getById($id);

        // Only edit permissions
        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        $this->getLog()->debug('show: testing to see if ' . $playlist->name . ' / ' . $playlist->playlistId
            . ' is the first playlist in any other ones.');

        // Work out the slot size of the first sub-playlist we are in.
        foreach ($this->playlistFactory->query(null, [
            'childId' => $playlist->playlistId,
            'depth' => 1,
            'disableUserCheck' => 1
        ]) as $parent) {
            // $parent is a playlist to which we belong.
            $this->getLog()->debug('show: This playlist is a sub-playlist in ' . $parent->name . '.');
            $parent->load();

            foreach ($parent->widgets as $parentWidget) {
                if ($parentWidget->type === 'subplaylist') {
                    $this->getLog()->debug('show: matched against a sub playlist widget ' . $parentWidget->widgetId . '.');

                    // Get the sub-playlist widgets
                    $event = new SubPlaylistItemsEvent($parentWidget);
                    $this->getDispatcher()->dispatch($event, SubPlaylistItemsEvent::$NAME);

                    foreach ($event->getItems() as $subPlaylistItem) {
                        $this->getLog()->debug('show: Assessing playlist ' . $subPlaylistItem->playlistId . ' on ' . $playlist->name);
                        if ($subPlaylistItem->playlistId == $playlist->playlistId) {
                            // Take the highest number of Spots we can find out of all the assignments.
                            $spotsFound = max($subPlaylistItem->spots ?? 0, $spotsFound);

                            // Assume this one isn't in the list more than one time.
                            break 2;
                        }
                    }

                    $this->getLog()->debug('show: no matching playlists found.');
                }
            }
        }

        // Load my Playlist and information about its widgets
        if ($spotsFound > 0) {
            // We are in a sub-playlist with spots, so now we load our widgets.
            $playlist->load();
            $user = $this->getUser();

            foreach ($playlist->widgets as $widget) {
                // Create a module for the widget and load in some extra data
                $module = $this->moduleFactory->getByType($widget->type);
                $widget->setUnmatchedProperty('name', $widget->getOptionValue('name', $module->name));
                $widget->setUnmatchedProperty('regionSpecific', $module->regionSpecific);
                $widget->setUnmatchedProperty('moduleIcon', $module->icon);

                // Check my permissions
                if ($module->regionSpecific == 0) {
                    $media = $this->mediaFactory->getById($widget->getPrimaryMediaId());
                    $widget->setUnmatchedProperty('viewble', $user->checkViewable($media));
                    $widget->setUnmatchedProperty('editable', $user->checkEditable($media));
                    $widget->setUnmatchedProperty('deletable', $user->checkDeleteable($media));
                } else {
                    $widget->setUnmatchedProperty('viewble', $user->checkViewable($widget));
                    $widget->setUnmatchedProperty('editable', $user->checkEditable($widget));
                    $widget->setUnmatchedProperty('deletable', $user->checkDeleteable($widget));
                }
            }
        }

        $this->getState()->template = 'playlist-dashboard-spots';
        $this->getState()->setData([
            'playlist' => $playlist,
            'spotsFound' => $spotsFound
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Playlist Widget Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function deletePlaylistWidgetForm(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);

        if (!$this->getUser()->checkDeleteable($widget)) {
            throw new AccessDeniedException();
        }

        // Pass to view
        $this->getState()->template = 'playlist-module-form-delete';
        $this->getState()->setData([
            'widget' => $widget,
        ]);

        return $this->render($request, $response);
    }
}
