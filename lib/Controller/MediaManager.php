<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2013 Daniel Garner
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
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class MediaManager
 * @package Xibo\Controller
 */
class MediaManager extends Base
{
    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var RegionFactory */
    private $regionFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param ModuleFactory $moduleFactory
     * @param LayoutFactory $layoutFactory
     * @param RegionFactory $regionFactory
     * @param PlaylistFactory $playlistFactory
     * @param WidgetFactory $widgetFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $moduleFactory, $layoutFactory, $regionFactory, $playlistFactory, $widgetFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
        $this->moduleFactory = $moduleFactory;
        $this->layoutFactory = $layoutFactory;
        $this->regionFactory = $regionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->widgetFactory = $widgetFactory;
    }

    public function displayPage()
    {
        $moduleFactory = $this->moduleFactory;
        
        $this->getState()->template .= 'media-manager-page';
        $this->getState()->setData([
            // Users we have permission to see
            'modules' => $this->moduleFactory->query(null, ['assignable' => 1, 'enabled' => 1]),
            'assignableModules' => array_map(function($element) use ($moduleFactory) { 
                    $module = $moduleFactory->createForInstall($element->class);
                    $module->setModule($element);
                    return $module;
                }, $moduleFactory->getAssignableModules())
        ]);
    }

    public function grid()
    {
        $this->getState()->template = 'grid';

        $rows = [];

        $widgets = $this->widgetFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'layout' => $this->getSanitizer()->getString('layout'),
            'region' => $this->getSanitizer()->getString('region'),
            'media' => $this->getSanitizer()->getString('media'),
            'type' => $this->getSanitizer()->getString('type'),
            'playlist' => $this->getSanitizer()->getString('playlist'),
            'showWidgetsFrom' => $this->getSanitizer()->getInt('showWidgetsFrom')
        ]));
        $widgetsCount = $this->widgetFactory->countLast();

        foreach ($widgets as $widget) {

            // Load the widget
            $widget->load();

            // Create a module
            $module = $this->moduleFactory->createWithWidget($widget);

            // Get a list of Layouts that this playlist uses
            $layouts = $this->layoutFactory->query(null, ['playlistId' => $widget->playlistId, 'showDrafts' => 1]);

            $layoutNames = array_map(function($layout) {
                return $layout->layout;
            }, $layouts);

            // Get a list of Regions that this playlists uses
            $regions = $this->regionFactory->getByPlaylistId($widget->playlistId);

            $regionNames = array_map(function($region) {
                return $region->name;
            }, $regions);

            // We are good to go
            $row = [
                'layout' => implode(',', $layoutNames),
                'region' => implode(',', $regionNames),
                'playlist' => $widget->playlist,
                'widget' => $module->getName(),
                'widgetId' => $widget->widgetId,
                'type' => $module->getModuleName(),
                'displayOrder' => $widget->displayOrder,
                'thumbnail' => '',
                'thumbnailUrl' => ''
            ];

            $row['buttons'] = [];

            // Check editable
            if (!$this->getUser()->checkEditable($widget)) {
                $rows[] = $row;
                continue;
            }

            // for widgets on Playlist not inside of a region
            $regionWidth = null;
            $regionHeight = null;

            // Get region dimensions
            foreach ($regions as $region) {
                $regionWidth = $region->width;
                $regionHeight = $region->height;
            }

            $row['buttons'][] = [
                'id' => 'WidgetEditForm',
                'class' => 'WidgetEditForm',
                'dataAttributes' => [
                    ['name' => 'region-width', 'value' => $regionWidth],
                    ['name' => 'region-height', 'value' => $regionHeight]
                ],
                'url' => $this->urlFor('module.widget.edit.form', ['id' => $widget->widgetId]),
                'text' => __('Edit')
            ];

            // Thumbnail URL
            $row['thumbnail'] = '';
            $row['thumbnailUrl'] = '';

            if ($module->getModule()->regionSpecific == 0) {

                if ($widget->type == 'image') {
                    $download = $this->urlFor('library.download', ['id' => $widget->getPrimaryMediaId()]) . '?preview=1';
                    $row['thumbnail'] = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56&cache=1" /></i></a>';
                    $row['thumbnailUrl'] = $download . '&width=100&height=56&cache=1';
                }

                // Add a replace button directly on the drop down menu
                $row['buttons'][] = [
                    'id' => 'MediaReplaceForm',
                    'url' => '#',
                    'text' => __('Replace'),
                    'dataAttributes' => [
                        ['name' => 'media-id', 'value' => $widget->getPrimaryMediaId()],
                        ['name' => 'widget-id', 'value' => $widget->widgetId],
                        ['name' => 'valid-extensions', 'value' => implode('|', $this->moduleFactory->getValidExtensions(['type' => $widget->type]))]
                    ],
                    'class' => 'MediaManagerReplaceButton'
                ];
            }


            $rows[] = $row;
        }

        $this->getState()->recordsTotal = $widgetsCount;
        $this->getState()->setData($rows);
    }
}
