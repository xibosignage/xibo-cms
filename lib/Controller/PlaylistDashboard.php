<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\XiboException;
use Xibo\Helper\XiboUploadHandler;

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

    /** @var \Xibo\Factory\LayoutFactory */
    private $layoutFactory;

    /** @var \Xibo\Factory\DisplayGroupFactory */
    private $displayGroupFactory;

    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $playlistFactory, $moduleFactory, $widgetFactory, $layoutFactory, $displayGroupFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
        $this->playlistFactory = $playlistFactory;
        $this->moduleFactory = $moduleFactory;
        $this->widgetFactory = $widgetFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    public function displayPage()
    {
        // Do we have a Playlist already in our User Preferences?
        $playlist = null;
        try {
            $playlistId = $this->getUser()->getOption('playlistDashboardSelectedPlaylistId');
            if ($playlistId->value != 0) {
                $playlist = $this->playlistFactory->getById($playlistId->value);
            }
        } catch (XiboException $exception) {
            $this->getLog()->error('Problem getting playlistDashboardSelectedPlaylistId user option. e = ' . $exception->getMessage());
        }

        $this->getState()->template = 'playlist-dashboard';
        $this->getState()->setData([
            'playlist' => $playlist,
            'validExtensions' => implode('|', $this->moduleFactory->getValidExtensions())
        ]);
    }

    /**
     * Grid used for the Playlist drop down list
     */
    public function grid()
    {
        // Playlists
        $playlists = $this->playlistFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'name' => $this->getSanitizer()->getString('name'),
            'regionSpecific' => 0
        ]));

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->playlistFactory->countLast();
        $this->getState()->setData($playlists);
    }

    /**
     * Show a particular playlist
     *  the output from this is very much like a form.
     * @param $playlistId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function show($playlistId)
    {
        // Record this Playlist as the one we have currently selected.
        try {
            $this->getUser()->setOptionValue('playlistDashboardSelectedPlaylistId', $playlistId);
            $this->getUser()->save();
        } catch (XiboException $exception) {
            $this->getLog()->error('Problem setting playlistDashboardSelectedPlaylistId user option. e = ' . $exception->getMessage());
        }

        // Spots
        $spotsFound = 0;

        $playlist = $this->playlistFactory->getById($playlistId);

        // Only edit permissions
        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException();
        }

        // Load my Playlist and information about its widgets
        $playlist->load();

        foreach ($playlist->widgets as $widget) {
            // Create a module for the widget and load in some extra data
            $widget->module = $this->moduleFactory->createWithWidget($widget);

            // Check my permissions
            if ($widget->module->getModule()->regionSpecific == 0) {
                $widget->viewble = $this->getUser()->checkViewable($widget->module->getMedia());
                $widget->editable = $this->getUser()->checkEditable($widget->module->getMedia());
                $widget->deletable = $this->getUser()->checkDeleteable($widget->module->getMedia());
            } else {
                $widget->viewble = $this->getUser()->checkViewable($widget);
                $widget->editable = $this->getUser()->checkEditable($widget);
                $widget->deletable = $this->getUser()->checkDeleteable($widget);
            }

        }

        // Work out the slot size of the first sub-playlist we are in.
        foreach ($this->playlistFactory->query(null, ['childId' => $playlist->playlistId, 'depth' => 1, 'disableUserCheck' => 1]) as $parent) {
            // $parent is a playlist to which we belong.
            $this->getLog()->debug('This playlist is a sub-playlist in ' . $parent->name . '.');
            $parent->load();

            foreach ($parent->widgets as $parentWidget) {
                if ($parentWidget->type === 'subplaylist') {
                    // Create a SubPlaylist widget so we can easily get the items we want.
                    $subPlaylist = $this->moduleFactory->createWithWidget($parentWidget);
                    $subPlaylistOptions = $subPlaylist->getSubPlaylistOptions($playlist->playlistId);

                    // This will be included?
                    $spotCount = isset($subPlaylistOptions['subPlaylistIdSpots']) ? intval($subPlaylistOptions['subPlaylistIdSpots']) : 0;

                    // Take the highest number of Spots we can find out of all the assignments.
                    $spotsFound = ($spotCount > $spotsFound) ? $spotCount : $spotsFound;

                    // Assume this one isn't in the list more than one time.
                    break;
                }
            }
        }

        $this->getState()->template = 'playlist-dashboard-spots';
        $this->getState()->setData([
            'playlist' => $playlist,
            'spotsFound' => $spotsFound
        ]);
    }

    /**
     * Delete Playlist Widget Form
     * @param int $widgetId
     * @throws XiboException
     */
    public function deletePlaylistWidgetForm($widgetId)
    {
        $module = $this->moduleFactory->createWithWidget($this->widgetFactory->loadByWidgetId($widgetId));

        if (!$this->getUser()->checkDeleteable($module->widget))
            throw new AccessDeniedException();

        // Set some dependencies that are used in the delete
        $module->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);

        // Pass to view
        $this->getState()->template = 'playlist-module-form-delete';
        $this->getState()->setData([
            'module' => $module,
            'help' => $this->getHelp()->link('Media', 'Delete')
        ]);
    }

    /**
     * Upload adding/replacing accordingly
     * @throws \Exception
     */
    public function upload()
    {
        $libraryFolder = $this->getConfig()->GetSetting('LIBRARY_LOCATION');

        // Get Valid Extensions
        $validExt = $this->moduleFactory->getValidExtensions();

        // pass in a library controller to handle the extra functions needed
        $libraryController = $this->getApp()->container->get('\Xibo\Controller\Library');

        $options = [
            'userId' => $this->getUser()->userId,
            'controller' => $libraryController,
            'oldMediaId' => $this->getSanitizer()->getInt('oldMediaId'),
            'widgetId' => $this->getSanitizer()->getInt('widgetId'),
            'updateInLayouts' => 1,
            'deleteOldRevisions' => 1,
            'allowMediaTypeChange' => 1,
            'playlistId' => $this->getSanitizer()->getInt('playlistId'),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('library.add'),
            'upload_url' => $this->urlFor('library.add'),
            'image_versions' => [],
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => ($this->getConfig()->GetSetting('LIBRARY_SIZE_LIMIT_KB') * 1024),
            'libraryQuotaFull' => false,
            'expires' => 0
        ];

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        new XiboUploadHandler($options);
    }
}