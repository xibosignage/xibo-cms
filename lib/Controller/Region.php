<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Region.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\Playlist;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

class Region extends Base
{
    /**
     * Timeline Form
     * @param int $regionId
     */
    public function timelineForm($regionId)
    {
        // Get a complex object of playlists and widgets
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        // Set the view we have requested
        $this->getSession()->set('timeLineView', Sanitize::getString('view'));

        // Load the region
        $region->load(['playlistIncludeRegionAssignments' => false]);

        // Loop through everything setting permissions
        foreach ($region->playlists as $playlist) {
            /* @var Playlist $playlist */

            foreach ($playlist->widgets as $widget) {
                /* @var Widget $widget */
                $widget->module = ModuleFactory::createWithWidget($widget, $region);
            }
        }

        // Pass to view
        $this->getState()->template = ($this->getSession()->get('timeLineView') == 'grid') ? 'region-form-grid' : 'region-form-timeline';
        $this->getState()->setData([
            'region' => $region,
            'modules' => ModuleFactory::getAssignableModules(),
            'transitions' => $this->transitionData(),
            'help' => Help::Link('Layout', 'RegionOptions')
        ]);
    }

    /**
     * Edit Form
     * @param int $regionId
     */
    public function editForm($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        $this->getState()->template = 'region-form-edit';
        $this->getState()->setData([
            'region' => $region,
            'layout' => LayoutFactory::getById($region->layoutId),
            'transitions' => $this->transitionData(),
            'help' => Help::Link('Region', 'Edit')
        ]);
    }

    /**
     * Delete Form
     * @param int $regionId
     */
    public function deleteForm($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkDeleteable($region))
            throw new AccessDeniedException();

        $this->getState()->template = 'region-form-delete';
        $this->getState()->setData([
            'region' => $region,
            'layout' => LayoutFactory::getById($region->layoutId),
            'help' => Help::Link('Region', 'Delete')
        ]);
    }

    /**
     * Add a region
     * @param int $layoutId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function add($layoutId)
    {
        $layout = LayoutFactory::getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Add a new region
        $region = RegionFactory::create($this->getUser()->userId, $layout->layout . '-' . (count($layout->regions) + 1), 250, 250, 50, 50);

        $layout->regions[] = $region;
        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $region->name),
            'id' => $region->regionId,
            'data' => $region
        ]);
    }

    /**
     * @param int $regionId
     */
    public function edit($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        $region->name = Sanitize::getString('name');
        $region->width = Sanitize::getDouble('width');
        $region->height = Sanitize::getDouble('height');
        $region->top = Sanitize::getDouble('top');
        $region->left = Sanitize::getDouble('left');
        $region->zIndex = Sanitize::getInt('zIndex');

        // Loop
        $region->setOptionValue('loop', Sanitize::getCheckbox('loop'));

        // Transitions
        $region->setOptionValue('transitionType', Sanitize::getString('transitionType'));
        $region->setOptionValue('transitionDuration', Sanitize::getInt('transitionDuration'));
        $region->setOptionValue('transitionDirection', Sanitize::getString('transitionDirection'));

        // Save
        $region->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $region->name),
            'id' => $region->regionId,
            'data' => $region
        ]);
    }

    /**
     * Delete a region
     * @param int $regionId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkDeleteable($region))
            throw new AccessDeniedException();

        $region->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $region->name)
        ]);
    }

    /**
     * Update Positions
     * @param int $layoutId
     */
    function positionAll($layoutId)
    {
        // Create the layout
        $layout = LayoutFactory::loadById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Pull in the regions and convert them to stdObjects
        $regions = Sanitize::getParam('regions', null);

        if ($regions == null)
            throw new \InvalidArgumentException(__('No regions present'));

        $regions = json_decode($regions);

        // Go through each region and update the region in the layout we have
        foreach ($regions as $newCoordinates) {
            $regionId = Sanitize::int($newCoordinates->regionid);

            // Load the region
            $region = $layout->getRegion($regionId);

            // Check Permissions
            if (!$this->getUser()->checkEditable($region))
                throw new AccessDeniedException();

            // New coordinates
            $region->top = Sanitize::double($newCoordinates->top);
            $region->left = Sanitize::double($newCoordinates->left);
            $region->width = Sanitize::double($newCoordinates->width);
            $region->height = Sanitize::double($newCoordinates->height);
            Log::debug('Set ' . $region);
        }

        // Mark the layout as having changed
        $layout->status = 0;
        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);
    }

    /**
     * Represents the Preview inside the Layout Designer
     * @param int $regionId
     */
    public function preview($regionId)
    {
        $seqGiven = Sanitize::getInt('seq', 1);
        $seq = Sanitize::getInt('seq', 1);
        $width = Sanitize::getDouble('width', 0);
        $height = Sanitize::getDouble('height', 0);
        $scaleOverride = Sanitize::getDouble('scale_override', 0);

        // Load our region
        try {
            $region = RegionFactory::getById($regionId);
            $region->load();

            // Get the first playlist we can find
            if (count($region->playlists) <= 0)
                throw new NotFoundException(__('No playlists to preview'));

            // TODO: implement playlists
            $playlist = $region->playlists[0];
            /* @var \Xibo\Entity\Playlist $playlist */

            // We want to load the widget in the given sequence
            if (count($playlist->widgets) <= 0) {
                // No media to preview
                throw new NotFoundException(__('No widgets to preview'));
            }

            Log::debug('There are %d widgets.', count($playlist->widgets));

            // Select the widget at the required sequence
            $widget = $playlist->getWidgetAt($seq);
            /* @var \Xibo\Entity\Widget $widget */
            $widget->load();

            // Otherwise, output a preview
            $module = ModuleFactory::createWithWidget($widget, $region);

            $this->getState()->html = $module->preview($width, $height, $scaleOverride);
            $this->getState()->extra['type'] = $widget->type;
            $this->getState()->extra['duration'] = $widget->duration;
            $this->getState()->extra['number_items'] = count($playlist->widgets);
            $this->getState()->extra['text'] = $seqGiven . ' / ' . count($playlist->widgets) . ' ' . $module->getName() . ' lasting ' . $widget->duration . ' seconds';
            $this->getState()->extra['current_item'] = $seqGiven;

        } catch (NotFoundException $e) {
            // Log it
            Log::info($e->getMessage());

            // No media to preview
            $this->getState()->extra['text'] = __('Empty Region');
        }
    }

    private function transitionData()
    {
        return [
            'in' => TransitionFactory::getEnabledByType('in'),
            'out' => TransitionFactory::getEnabledByType('out'),
            'compassPoints' => array(
                array('id' => 'N', 'name' => __('North')),
                array('id' => 'NE', 'name' => __('North East')),
                array('id' => 'E', 'name' => __('East')),
                array('id' => 'SE', 'name' => __('South East')),
                array('id' => 'S', 'name' => __('South')),
                array('id' => 'SW', 'name' => __('South West')),
                array('id' => 'W', 'name' => __('West')),
                array('id' => 'NW', 'name' => __('North West'))
            )
        ];
    }
}