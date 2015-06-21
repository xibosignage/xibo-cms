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

use Xibo\Helper\ApplicationState;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;


class Timeline extends Base
{

    /**
     * Timeline in Grid mode
     */
    public function TimelineGrid()
    {
        $this->getUser()->setPref('timeLineView', 'grid');

        $response = $this->getState();
        $response->html = '';

        // Load the region and get the dimensions, applying the scale factor if necessary (only v1 layouts will have a scale factor != 1)
        $region = \Xibo\Factory\RegionFactory::loadByRegionId(Kit::GetParam('regionid', _GET, _INT));

        if (!$this->getUser()->checkEditable($region))
            trigger_error(__('You do not have permissions to edit this region'), E_USER_ERROR);

        // Set the theme module buttons
        $this->setModuleButtons($region->regionId, $region->playlists[0]->playlistId);

        $id = uniqid();
        Theme::Set('prepend', '<div class="row">' . Theme::RenderReturn('layout_designer_form_timeline') . '<div class="col-md-10">');
        Theme::Set('append', '</div></div>');
        Theme::Set('header_text', __('Media'));
        Theme::Set('id', $id);
        Theme::Set('form_fields', array());
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ApplicationState::Pager($id));
        Theme::Set('form_meta', '<input type="hidden" name="p" value="timeline">
            <input type="hidden" name="q" value="TimelineGridView">
            <input type="hidden" name="regionid" value="' . $region->regionId . '">');

        // Call to render the template
        $response->html = Theme::RenderReturn('grid_render');

        // Finish constructing the response
        $response->dialogClass = 'modal-big';
        $response->dialogTitle = __('Region Timeline');
        $response->dialogSize = true;
        $response->dialogWidth = '1000px';
        $response->dialogHeight = '550px';
        $response->focusInFirstInput = false;

        // Add some buttons
        $response->AddButton(__('Switch to List'), 'XiboSwapDialog("index.php?p=timeline&q=TimelineList&regionid=' . $region->regionId . '")');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Layout', 'RegionOptions') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }

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

            if ($this->getUser()->checkEditable($widget)) {
                $row['buttons'][] = array(
                    'id' => 'timeline_button_edit',
                    'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=EditForm&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                    'text' => __('Edit')
                );
            }

            if ($this->getUser()->checkDeleteable($widget)) {
                $row['buttons'][] = array(
                    'id' => 'timeline_button_delete',
                    'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=DeleteForm&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                    'text' => __('Remove'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'multiselectlink', 'value' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=DeleteMedia'),
                        array('name' => 'rowtitle', 'value' => $row['name']),
                        array('name' => 'regionid', 'value' => $region->regionId),
                        array('name' => 'lkid', 'value' => $widget->widgetId),
                        array('name' => 'options', 'value' => 'unassign')
                    )
                );
            }

            if ($this->getUser()->checkPermissionsModifyable($widget)) {
                $row['buttons'][] = array(
                    'id' => 'timeline_button_permissions',
                    'url' => 'index.php?p=user&q=permissionsForm&entity=Widget&objectId=' . $widget->widgetId . '"',
                    'text' => __('Permissions')
                );
            }

            if (count($this->getUser()->TransitionAuth('in')) > 0) {
                $row['buttons'][] = array(
                    'id' => 'timeline_button_trans_in',
                    'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=TransitionEditForm&type=in&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                    'text' => __('In Transition')
                );
            }

            if (count($this->getUser()->TransitionAuth('out')) > 0) {
                $row['buttons'][] = array(
                    'id' => 'timeline_button_trans_in',
                    'url' => 'index.php?p=module&mod=' . $tmpModule->getModuleType() . '&q=Exec&method=TransitionEditForm&type=out&regionId=' . $region->regionId . '&widgetId=' . $widget->widgetId . '"',
                    'text' => __('Out Transition')
                );
            }

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
