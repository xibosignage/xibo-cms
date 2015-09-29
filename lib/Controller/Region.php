<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Region.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\Layout;
use Xibo\Entity\Permission;
use Xibo\Entity\Playlist;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Helper\Config;
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
        $this->getSession()->set('timeLineView', Sanitize::getString('view', $this->getSession()->get('timeLineView')));

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
     *
     * @SWG\Post(
     *  path="/region/{layoutId}",
     *  operationId="regionAdd",
     *  tags={"layout"},
     *  summary="Add Region",
     *  description="Add a Region to a Layout",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID to add the Region to",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="width",
     *      in="formData",
     *      description="The Width, default 250",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="height",
     *      in="formData",
     *      description="The Height",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="top",
     *      in="formData",
     *      description="The Top Coordinate",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="left",
     *      in="formData",
     *      description="The Left Coordinate",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Region"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add($layoutId)
    {
        $layout = LayoutFactory::getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $layout->load([
            'loadPlaylists' => true,
            'loadTags' => false,
            'loadPermissions' => true,
            'loadCampaigns' => false
        ]);

        // Add a new region
        $region = RegionFactory::create($this->getUser()->userId, $layout->layout . '-' . (count($layout->regions) + 1),
            Sanitize::getInt('width', 250), Sanitize::getInt('height', 250), Sanitize::getInt('top', 50), Sanitize::getInt('left', 50));

        $layout->regions[] = $region;
        $layout->save([
            'saveTags' => false
        ]);

        // Permissions
        if (Config::GetSetting('INHERIT_PARENT_PERMISSIONS') == 1) {

            Log::debug('Applying permissions from parent, there are %d', count($layout->permissions));

            // Apply permissions from the Parent
            foreach ($layout->permissions as $permission) {
                /* @var Permission $permission */
                $permission = PermissionFactory::create($permission->groupId, get_class($region), $region->getId(), $permission->view, $permission->edit, $permission->delete);
                $permission->save();

                foreach ($region->playlists as $playlist) {
                    /* @var Playlist $playlist */
                    $permission = PermissionFactory::create($permission->groupId, get_class($playlist), $playlist->getId(), $permission->view, $permission->edit, $permission->delete);
                    $permission->save();
                }
            }
        }
        else {
            Log::debug('Applying default permissions');

            // Apply the default permissions
            foreach (PermissionFactory::createForNewEntity($this->getUser(), get_class($region), $region->getId(), Config::GetSetting('LAYOUT_DEFAULT')) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            foreach ($region->playlists as $playlist) {
                /* @var Playlist $playlist */
                foreach (PermissionFactory::createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), Config::GetSetting('LAYOUT_DEFAULT')) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
                }
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $region->name),
            'id' => $region->regionId,
            'data' => $region
        ]);
    }

    /**
     * @param int $regionId
     *
     * @SWG\Put(
     *  path="/region/{regionId}",
     *  operationId="regionEdit",
     *  tags={"layout"},
     *  summary="Edit Region",
     *  description="Edit Region",
     *  @SWG\Parameter(
     *      name="regionId",
     *      in="path",
     *      description="The Region ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="width",
     *      in="formData",
     *      description="The Width, default 250",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="height",
     *      in="formData",
     *      description="The Height",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="top",
     *      in="formData",
     *      description="The Top Coordinate",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="left",
     *      in="formData",
     *      description="The Left Coordinate",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="zIndex",
     *      in="formData",
     *      description="The Layer for this Region",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="transitionType",
     *      in="formData",
     *      description="The Transition Type. Must be a valid transition code as returned by /transition",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="transitionDuration",
     *      in="formData",
     *      description="The transition duration in milliseconds if required by the transition type",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="transitionDirection",
     *      in="formData",
     *      description="The transition direction if required by the transition type.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="loop",
     *      in="formData",
     *      description="Flag indicating whether this region should loop if there is only 1 media item in the timeline",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Region")
     *  )
     * )
     */
    public function edit($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        // Load before we save
        $region->load();

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

        // Mark the layout as needing rebuild
        $layout = LayoutFactory::getById($region->layoutId);
        $layout->load(Layout::$loadOptionsMinimum);
        $layout->setBuildRequired();
        $layout->save(Layout::$saveOptionsMinimum);

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
     *
     * @SWG\Delete(
     *  path="/region/{regionId}",
     *  operationId="regionDelete",
     *  tags={"layout"},
     *  summary="Region Delete",
     *  description="Delete an existing region",
     *  @SWG\Parameter(
     *      name="regionId",
     *      in="path",
     *      description="The Region ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkDeleteable($region))
            throw new AccessDeniedException();

        $region->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $region->name)
        ]);
    }

    /**
     * Update Positions
     * @param int $layoutId
     *
     * @SWG\Put(
     *  path="/region/position/all/{layoutId}",
     *  operationId="regionPositionAll",
     *  tags={"layout"},
     *  summary="Position Regions",
     *  description="Position all regions for a Layout",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="regions",
     *      in="formData",
     *      description="Array of regions and their new positions",
     *      type="array",
     *      required=true,
     *      @SWG\Schema(
     *          type="object",
     *          additionalProperties={[{"title":"regionId", "type":"integer"},
     *          additionalProperties={"title":"top", "type":"number"},
     *          additionalProperties={"title":"left", "type":"number"},
     *          additionalProperties={"title":"width", "type":"number"},
     *          additionalProperties={"title":"height", "type":"number"}]}
     *      )
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
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
            $this->getState()->extra['current_item'] = $seqGiven;
            $this->getState()->extra['moduleName'] = $module->getName();
            $this->getState()->extra['regionDuration'] = $region->duration;

        } catch (NotFoundException $e) {
            // Log it
            Log::info($e->getMessage());

            // No media to preview
            $this->getState()->extra['text'] = __('Empty Region');
        }
    }

    /**
     * Order a region and its playlists
     * *** COMMENTED OUT - NOT SURE HOW TO DOCUMENT ***
     * @param int $regionId
     *
     * SWG\Post(
     *  path="/region/order/{regionId}",
     *  operationId="regionOrder",
     *  tags={"region"},
     *  summary="Order Playlists",
     *  description="Set the order of Playlists in a Region",
     *  SWG\Parameter(
     *      name="regionId",
     *      in="path",
     *      description="The Region ID to Order",
     *      type="integer",
     *      required=true
     *   ),
     *  SWG\Parameter(
     *      name="playlists",
     *      in="formData",
     *      description="Array of playlistIds and positions",
     *      type="array",
     *      required=true,
     *      SWG\Items(
     *          ref="#/definitions/RegionPlaylistList"
     *      )
     *   ),
     *  SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      SWG\Schema(ref="#/definitions/Region")
     *  )
     * )
     */
    function order($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        // Load the playlists
        $region->load(['loadWidgets' => false]);

        // Get our list of widget orders
        $playlists = Sanitize::getParam('playlists', null);

        // Go through each one and move it
        foreach ($playlists as $playlistId => $position) {

            // Find this item in the existing list and add it to our new order
            foreach ($region->playlists as $playlist) {
                /* @var \Xibo\Entity\Widget $playlist */
                if ($playlist->getId() == $playlistId) {
                    Log::debug('Setting Display Order ' . $position . ' on playlistId ' . $playlistId);
                    $playlist->displayOrder = $position;
                    break;
                }
            }
        }

        $region->save();

        // Success
        $this->getState()->hydrate([
            'message' => __('Order Changed'),
            'data' => $region
        ]);
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