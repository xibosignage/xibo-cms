<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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

use Xibo\Helper\Log;
use Xibo\Helper\Theme;


class Timeline extends Base
{
    /**
     * TimeLine Grid
     */
    public function TimelineGridView()
    {
        $user = $this->getUser();
        $response = $this->getState();

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $region = \Xibo\Factory\RegionFactory::loadByRegionId(Kit::GetParam('regionid', _POST, _INT));

        if (!$this->getUser()->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Columns
        $cols = array(
            array('name' => 'order', 'title' => __('Order')),
            array('name' => 'name', 'title' => __('Name')),
            array('name' => 'type', 'title' => __('Type')),
            array('name' => 'duration', 'title' => __('Duration')),
            array('name' => 'transition', 'title' => __('Transition'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();
        $i = 0;

        // Get the Widgets on this Timeline
        // TODO: Playlist logic
        $playlist = $region->playlists[0];
        /* @var \Xibo\Entity\Playlist $playlist */

        Log::debug(count($playlist->widgets) . ' widgets on ' . $region);

        foreach ($playlist->widgets as $widget) {
            /* @var \Xibo\Entity\Widget $widget */
            // Put this node vertically in the region time line
            if (!$this->getUser()->checkViewable($widget))
                // Skip over media assignments that we do not have permission to see
                continue;

            // Construct an object containing all the layouts, and pass to the theme
            $row = array();

            $i++;

            // Create a media module to handle all the complex stuff
            $tmpModule = null;
            try {
                $tmpModule = \Xibo\Factory\ModuleFactory::createWithWidget($widget, $region);
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }

            $mediaName = $tmpModule->getName();

            $row['order'] = $i;
            $row['name'] = $mediaName;
            $row['type'] = __($tmpModule->getModuleName());
            $row['duration'] = sprintf('%d seconds', $widget->duration);
            $row['transition'] = sprintf('%s / %s', $tmpModule->getTransition('in'), $tmpModule->getTransition('out'));



            $rows[] = $row;
        }

        // Store the table rows
        Theme::Set('table_rows', $rows);
        Theme::Set('gridId', \Kit::GetParam('gridId', _REQUEST, _STRING));

        // Initialise the theme and capture the output
        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);
        $response->initialSortColumn = 1;

    }

    /**
     * Re-orders a medias regions
     */
    function TimelineReorder()
    {
        $response = $this->getState();

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $playlists = \Xibo\Factory\PlaylistFactory::getByRegionId(Kit::GetParam('regionId', _GET, _INT));
        $playlist = $playlists[0];
        /* @var \Xibo\Entity\Playlist $playlist */

        if (!$this->getUser()->checkEditable($playlist))
            trigger_error(__('You do not have permissions to edit this playlist'), E_USER_ERROR);

        // Load the widgets
        $playlist->load();

        // Create a list of media
        $widgetList = \Kit::GetParam('widgetIds', _POST, _ARRAY_INT);
        if (count($widgetList) <= 0)
            trigger_error(__('No widgets to reorder'), E_USER_ERROR);

        Log::debug($playlist . ' reorder to ' . var_export($widgetList, true));

        // Go through each one and move it
        $i = 0;
        foreach ($widgetList as $widgetId) {
            $i++;
            // Find this item in the existing list and add it to our new order
            foreach ($playlist->widgets as $widget) {
                /* @var \Xibo\Entity\Widget $widget */
                Log::debug('Comparing ' . $widget . ' with ' . $widgetId);
                if ($widget->getId() == $widgetId) {
                    Log::debug('Setting Display Order ' . $i . ' on widgetId ' . $widgetId);
                    $widget->displayOrder = $i;
                    $widget->save();
                    break;
                }
            }
        }

        $response->SetFormSubmitResponse(__('Order Changed'));
        $response->keepOpen = true;

    }
}
