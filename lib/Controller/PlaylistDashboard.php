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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\SubPlaylistItemsEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class PlaylistDashboard
 * @package Xibo\Controller
 */
class PlaylistDashboard extends Base
{
    public function __construct(
        private readonly PlaylistFactory $playlistFactory,
        private readonly ModuleFactory   $moduleFactory,
        private readonly MediaFactory    $mediaFactory,
    ) {
    }

    /**
     * Grid used for the Playlist drop down list
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Playlists
        $playlists = $this->playlistFactory->query(
            $this->gridRenderSort($sanitizedParams, $this->isJson($request)),
            $this->gridRenderFilter([
                'name' => $this->getSanitizer($request->getParams())->getString('name'),
                'regionSpecific' => 0
            ], $sanitizedParams)
        );

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->playlistFactory->countLast())
            ->withJson($playlists);
    }

    /**
     * Show a particular playlist
     *  the output from this is very much like a form.
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function show(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        // Record this Playlist as the one we have currently selected.
        try {
            $this->getUser()->setOptionValue('playlistDashboardSelectedPlaylistId', $id);
            $this->getUser()->save();
        } catch (GeneralException $exception) {
            $this->getLog()->error(
                'Problem setting playlistDashboardSelectedPlaylistId user option. e = ' . $exception->getMessage()
            );
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
                    $this->getLog()->debug(
                        'show: matched against a sub playlist widget ' . $parentWidget->widgetId . '.'
                    );

                    // Get the sub-playlist widgets
                    $event = new SubPlaylistItemsEvent($parentWidget);
                    $this->getDispatcher()->dispatch($event, SubPlaylistItemsEvent::$NAME);

                    foreach ($event->getItems() as $subPlaylistItem) {
                        $this->getLog()->debug(
                            'show: Assessing playlist ' . $subPlaylistItem->playlistId . ' on ' . $playlist->name
                        );
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

                // Build mediaFiles from all mediaIds, caching objects to avoid double-fetch below
                $mediaMap = [];
                $mediaFiles = [];
                foreach ($widget->mediaIds as $mediaId) {
                    try {
                        $media = $this->mediaFactory->getById($mediaId);
                        $mediaMap[$mediaId] = $media;
                        $mediaFiles[] = [
                            'widgetId' => $widget->widgetId,
                            'mediaId' => $mediaId,
                            'fileName' => $media->fileName,
                            'fileSize' => ByteFormatter::format($media->fileSize),
                        ];
                    } catch (NotFoundException $e) {
                        $this->getLog()->error(
                            sprintf(
                                'MediaId %d assigned to widgetId %d, missing' ,
                                $mediaId,
                                $widget->widgetId
                            )
                        );
                        // media record missing — skip
                    }
                }
                $widget->setUnmatchedProperty('mediaFiles', $mediaFiles);

                // Check my permissions
                if ($module->regionSpecific == 0) {
                    $media = $mediaMap[$widget->getPrimaryMediaId()]
                        ?? $this->mediaFactory->getById($widget->getPrimaryMediaId());
                    $widget->setUnmatchedProperty('viewable', $user->checkViewable($media));
                    $widget->setUnmatchedProperty('editable', $user->checkEditable($media));
                    $widget->setUnmatchedProperty('deletable', $user->checkDeleteable($media));
                } else {
                    $widget->setUnmatchedProperty('viewable', $user->checkViewable($widget));
                    $widget->setUnmatchedProperty('editable', $user->checkEditable($widget));
                    $widget->setUnmatchedProperty('deletable', $user->checkDeleteable($widget));
                }
            }
        }

        return $response
            ->withStatus(200)
            ->withJson([
                'playlist' => $playlist,
                'spotsFound' => $spotsFound,
            ]);
    }
}
