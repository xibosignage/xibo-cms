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
    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

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
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $moduleFactory, $layoutFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
        $this->moduleFactory = $moduleFactory;
        $this->layoutFactory = $layoutFactory;
    }

    public function displayPage()
    {
        $this->getState()->template .= 'media-manager-page';
        $this->getState()->setData([
            // Users we have permission to see
            'modules' => $this->moduleFactory->query(null, ['assignable' => 1])
        ]);
    }

    public function grid()
    {
        $this->getState()->template = 'grid';

        $filterLayout = $this->getSanitizer()->getString('layout');
        $filterRegion = $this->getSanitizer()->getString('region');
        $filterMedia = $this->getSanitizer()->getString('media');
        $filterType = $this->getSanitizer()->getString('type');

        $rows = array();

        foreach ($this->layoutFactory->query(null, ['layout' => $filterLayout]) as $layout) {
            /* @var \Xibo\Entity\Layout $layout */
            // We have edit permissions?
            if (!$this->getUser()->checkEditable($layout))
                continue;

            // Load the layout
            $layout->load();

            //get the regions
            foreach ($layout->regions as $region) {
                /* @var \Xibo\Entity\Region $region */

                // Do we have permission to edit?
                if (!$this->getUser()->checkEditable($region))
                    continue;

                if ($filterRegion != '' && !stristr($region->name, $filterRegion))
                    continue;

                // Playlists
                foreach($region->playlists as $playlist) {
                    /* @var \Xibo\Entity\Playlist $playlist */
                    if (!$this->getUser()->checkEditable($playlist))
                        continue;

                    // Get all the widgets in the playlist
                    foreach ($playlist->widgets as $widget) {
                        /* @var \Xibo\Entity\Widget $widget */

                        // Check we've not filtered this out
                        if ($filterMedia != '' && !stristr($widget->getOptionValue('name', $widget->type), $filterMedia))
                            continue;

                        if ($filterType != '' && $widget->type != strtolower($filterType))
                            continue;

                        // Check editable
                        if (!$this->getUser()->checkEditable($widget))
                            continue;

                        // Create a module
                        $module = $this->moduleFactory->createWithWidget($widget);

                        // We are good to go
                        $rows[] = [
                            'layout' => $layout,
                            'region' => $region->name,
                            'playlist' => $playlist->name,
                            'widget' => $module->getName(),
                            'type' => $module->getModuleName(),
                            'displayOrder' => $widget->displayOrder,
                            'buttons' => [
                                [
                                    'id' => 'WidgetEditForm',
                                    'url' => $this->urlFor('module.widget.edit.form', ['id' => $widget->widgetId]),
                                    'text' => __('Edit')
                                ]
                            ]
                        ];
                    }
                }
            }
        }

        $this->getState()->setData($rows);
    }
}
