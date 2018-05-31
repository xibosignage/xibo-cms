<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Region.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\Permission;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\Session;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Region
 * @package Xibo\Controller
 */
class Region extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var TransitionFactory
     */
    private $transitionFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Session $session
     * @param RegionFactory $regionFactory
     * @param PermissionFactory $permissionFactory
     * @param TransitionFactory $transitionFactory
     * @param ModuleFactory $moduleFactory
     * @param LayoutFactory $layoutFactory
     * @param UserGroupFactory $userGroupFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session, $regionFactory, $permissionFactory,
                                $transitionFactory, $moduleFactory, $layoutFactory, $userGroupFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->session = $session;
        $this->regionFactory = $regionFactory;
        $this->permissionFactory = $permissionFactory;
        $this->transitionFactory = $transitionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->moduleFactory = $moduleFactory;
        $this->userGroupFactory = $userGroupFactory;
    }

    /**
     * Timeline Form
     * @param int $regionId
     */
    public function timelineForm($regionId)
    {
        // Get a complex object of playlists and widgets
        $region = $this->regionFactory->getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        // Set the view we have requested
        $this->session->set('timeLineView', $this->getSanitizer()->getString('view', $this->session->get('timeLineView')));

        // Load the region
        $region->load(['playlistIncludeRegionAssignments' => false]);

        // Loop through everything setting permissions
        foreach ($region->playlists as $playlist) {
            /* @var \Xibo\Entity\Playlist $playlist */

            foreach ($playlist->widgets as $widget) {
                /* @var Widget $widget */
                $widget->module = $this->moduleFactory->createWithWidget($widget, $region);
            }
        }

        // Pass to view
        $this->getState()->template = ($this->session->get('timeLineView') == 'grid') ? 'region-form-grid' : 'region-form-timeline';
        $this->getState()->setData([
            'region' => $region,
            'modules' => $this->moduleFactory->getAssignableModules(),
            'transitions' => $this->transitionData(),
            'help' => $this->getHelp()->link('Layout', 'RegionOptions')
        ]);
    }

    /**
     * Edit Form
     * @param int $regionId
     */
    public function editForm($regionId)
    {
        $region = $this->regionFactory->getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        $this->getState()->template = 'region-form-edit';
        $this->getState()->setData([
            'region' => $region,
            'layout' => $this->layoutFactory->getById($region->layoutId),
            'transitions' => $this->transitionData(),
            'help' => $this->getHelp()->link('Region', 'Edit')
        ]);
    }

    /**
     * Delete Form
     * @param int $regionId
     */
    public function deleteForm($regionId)
    {
        $region = $this->regionFactory->getById($regionId);

        if (!$this->getUser()->checkDeleteable($region))
            throw new AccessDeniedException();

        $this->getState()->template = 'region-form-delete';
        $this->getState()->setData([
            'region' => $region,
            'layout' => $this->layoutFactory->getById($region->layoutId),
            'help' => $this->getHelp()->link('Region', 'Delete')
        ]);
    }

    /**
     * Add a region
     * @param int $layoutId
     *
     * @SWG\Post(
     *  path="/region/{id}",
     *  operationId="regionAdd",
     *  tags={"layout"},
     *  summary="Add Region",
     *  description="Add a Region to a Layout",
     *  @SWG\Parameter(
     *      name="id",
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
     *
     * @throws XiboException
     */
    public function add($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $layout->load([
            'loadPlaylists' => true,
            'loadTags' => false,
            'loadPermissions' => true,
            'loadCampaigns' => false
        ]);

        // Add a new region
        $region = $this->regionFactory->create($this->getUser()->userId, $layout->layout . '-' . (count($layout->regions) + 1),
            $this->getSanitizer()->getInt('width', 250), $this->getSanitizer()->getInt('height', 250), $this->getSanitizer()->getInt('top', 50), $this->getSanitizer()->getInt('left', 50));

        $layout->regions[] = $region;
        $layout->save([
            'saveTags' => false
        ]);

        // Permissions
        if ($this->getConfig()->GetSetting('INHERIT_PARENT_PERMISSIONS') == 1) {

            $this->getLog()->debug('Applying permissions from parent, there are %d', count($layout->permissions));

            // Apply permissions from the Parent
            foreach ($layout->permissions as $permission) {
                /* @var Permission $permission */
                $permission = $this->permissionFactory->create($permission->groupId, get_class($region), $region->getId(), $permission->view, $permission->edit, $permission->delete);
                $permission->save();

                foreach ($region->playlists as $playlist) {
                    /* @var \Xibo\Entity\Playlist $playlist */
                    $permission = $this->permissionFactory->create($permission->groupId, get_class($playlist), $playlist->getId(), $permission->view, $permission->edit, $permission->delete);
                    $permission->save();
                }
            }
        }
        else {
            $this->getLog()->debug('Applying default permissions');

            // Apply the default permissions
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($region), $region->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            foreach ($region->playlists as $playlist) {
                /* @var \Xibo\Entity\Playlist $playlist */
                foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
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
     *  path="/region/{id}",
     *  operationId="regionEdit",
     *  tags={"layout"},
     *  summary="Edit Region",
     *  description="Edit Region",
     *  @SWG\Parameter(
     *      name="id",
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
     *
     * @throws XiboException
     */
    public function edit($regionId)
    {
        $region = $this->regionFactory->getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        // Load before we save
        $region->load();

        $region->name = $this->getSanitizer()->getString('name');
        $region->width = $this->getSanitizer()->getDouble('width');
        $region->height = $this->getSanitizer()->getDouble('height');
        $region->top = $this->getSanitizer()->getDouble('top');
        $region->left = $this->getSanitizer()->getDouble('left');
        $region->zIndex = $this->getSanitizer()->getInt('zIndex');

        // Loop
        $region->setOptionValue('loop', $this->getSanitizer()->getCheckbox('loop'));

        // Transitions
        $region->setOptionValue('transitionType', $this->getSanitizer()->getString('transitionType'));
        $region->setOptionValue('transitionDuration', $this->getSanitizer()->getInt('transitionDuration'));
        $region->setOptionValue('transitionDirection', $this->getSanitizer()->getString('transitionDirection'));

        // Save
        $region->save();

        // Mark the layout as needing rebuild
        $layout = $this->layoutFactory->getById($region->layoutId);
        $layout->load(\Xibo\Entity\Layout::$loadOptionsMinimum);

        $saveOptions = \Xibo\Entity\Layout::$saveOptionsMinimum;
        $saveOptions['setBuildRequired'] = true;

        $layout->save($saveOptions);

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
        $region = $this->regionFactory->getById($regionId);

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
     * @throws NotFoundException
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
     *      description="Array of regions and their new positions. Each array element should be json encoded and have regionId, top, left, width and height.",
     *      type="array",
     *      required=true,
     *      @SWG\Items(
     *          type="string"
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
        $layout = $this->layoutFactory->loadById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Pull in the regions and convert them to stdObjects
        $regions = $this->getSanitizer()->getParam('regions', null);

        if ($regions == null)
            throw new \InvalidArgumentException(__('No regions present'));

        $regions = json_decode($regions);

        // Go through each region and update the region in the layout we have
        foreach ($regions as $newCoordinates) {

            // Check that the properties we are expecting do actually exist
            if (!property_exists($newCoordinates, 'regionid'))
                throw new \InvalidArgumentException(__('Missing regionid property'));

            if (!property_exists($newCoordinates, 'top'))
                throw new \InvalidArgumentException(__('Missing top property'));

            if (!property_exists($newCoordinates, 'left'))
                throw new \InvalidArgumentException(__('Missing left property'));

            if (!property_exists($newCoordinates, 'width'))
                throw new \InvalidArgumentException(__('Missing width property'));

            if (!property_exists($newCoordinates, 'height'))
                throw new \InvalidArgumentException(__('Missing height property'));

            $regionId = $this->getSanitizer()->int($newCoordinates->regionid);

            // Load the region
            $region = $layout->getRegion($regionId);

            // Check Permissions
            if (!$this->getUser()->checkEditable($region))
                throw new AccessDeniedException();

            // New coordinates
            $region->top = $this->getSanitizer()->double($newCoordinates->top);
            $region->left = $this->getSanitizer()->double($newCoordinates->left);
            $region->width = $this->getSanitizer()->double($newCoordinates->width);
            $region->height = $this->getSanitizer()->double($newCoordinates->height);
            $this->getLog()->debug('Set ' . $region);
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
        $seqGiven = $this->getSanitizer()->getInt('seq', 1);
        $seq = $this->getSanitizer()->getInt('seq', 1);
        $width = $this->getSanitizer()->getDouble('width', 0);
        $height = $this->getSanitizer()->getDouble('height', 0);
        $scaleOverride = $this->getSanitizer()->getDouble('scale_override', 0);

        // Load our region
        try {
            $region = $this->regionFactory->getById($regionId);
            $region->load();

            // Get the first playlist we can find
            if (count($region->playlists) <= 0)
                throw new NotFoundException(__('No playlists to preview'));

            // TODO: implement playlists
            $playlist = $region->playlists[0];
            /* @var \Xibo\Entity\Playlist $playlist */

            $countWidgets = count($playlist->widgets);

            // We want to load the widget in the given sequence
            if ($countWidgets <= 0) {
                // No media to preview
                throw new NotFoundException(__('No widgets to preview'));
            }

            $this->getLog()->debug('There are %d widgets.', count($playlist->widgets));

            // Select the widget at the required sequence
            $widget = $playlist->getWidgetAt($seq);
            /* @var \Xibo\Entity\Widget $widget */
            $widget->load();

            // Otherwise, output a preview
            $module = $this->moduleFactory->createWithWidget($widget, $region);

            $this->getState()->extra['empty'] = false;
            $this->getState()->html = $module->preview($width, $height, $scaleOverride);
            $this->getState()->extra['type'] = $widget->type;
            $this->getState()->extra['duration'] = $widget->calculatedDuration;
            $this->getState()->extra['number_items'] = $countWidgets;
            $this->getState()->extra['current_item'] = $seqGiven;
            $this->getState()->extra['moduleName'] = $module->getName();
            $this->getState()->extra['regionDuration'] = $region->duration;
            $this->getState()->extra['useDuration'] = $widget->useDuration;
            $this->getState()->extra['zIndex'] = $region->zIndex;

        } catch (NotFoundException $e) {
            // No media to preview
            $this->getState()->extra['empty'] = true;
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
        $region = $this->regionFactory->getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        // Load the playlists
        $region->load(['loadWidgets' => false]);

        // Get our list of widget orders
        $playlists = $this->getSanitizer()->getParam('playlists', null);

        // Go through each one and move it
        foreach ($playlists as $playlistId => $position) {

            // Find this item in the existing list and add it to our new order
            foreach ($region->playlists as $playlist) {
                /* @var \Xibo\Entity\Widget $playlist */
                if ($playlist->getId() == $playlistId) {
                    $this->getLog()->debug('Setting Display Order ' . $position . ' on playlistId ' . $playlistId);
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

    /**
     * @return array
     */
    private function transitionData()
    {
        return [
            'in' => $this->transitionFactory->getEnabledByType('in'),
            'out' => $this->transitionFactory->getEnabledByType('out'),
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