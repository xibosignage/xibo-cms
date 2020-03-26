<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Xibo\Entity\Permission;
use Xibo\Factory\ActionFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Session;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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

    /** @var WidgetFactory */
    private $widgetFactory;

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
     * @var ActionFactory
     */
    private $actionFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Session $session
     * @param RegionFactory $regionFactory
     * @param WidgetFactory $widgetFactory
     * @param PermissionFactory $permissionFactory
     * @param TransitionFactory $transitionFactory
     * @param ModuleFactory $moduleFactory
     * @param LayoutFactory $layoutFactory
     * @param UserGroupFactory $userGroupFactory
     * @param Twig $view
     * @param ActionFactory $actionFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session, $regionFactory, $widgetFactory, $permissionFactory, $transitionFactory, $moduleFactory, $layoutFactory, $userGroupFactory, Twig $view, $actionFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->session = $session;
        $this->regionFactory = $regionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->permissionFactory = $permissionFactory;
        $this->transitionFactory = $transitionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->moduleFactory = $moduleFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->actionFactory = $actionFactory;
    }

    /**
     * Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $region = $this->regionFactory->getById($id);

        if (!$this->getUser()->checkEditable($region)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'region-form-edit';
        $this->getState()->setData([
            'region' => $region,
            'layout' => $this->layoutFactory->getById($region->layoutId),
            'transitions' => $this->transitionData(),
            'help' => $this->getHelp()->link('Region', 'Edit')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $region = $this->regionFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($region))
            throw new AccessDeniedException();

        $this->getState()->template = 'region-form-delete';
        $this->getState()->setData([
            'region' => $region,
            'layout' => $this->layoutFactory->getById($region->layoutId),
            'help' => $this->getHelp()->link('Region', 'Delete')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add a region
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
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
     */
    public function add(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException();
        }

        if (!$layout->isChild()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $layout->load([
            'loadPlaylists' => true,
            'loadTags' => false,
            'loadPermissions' => true,
            'loadCampaigns' => false
        ]);

        // Add a new region
        $region = $this->regionFactory->create(
            $this->getUser()->userId, $layout->layout . '-' . (count($layout->regions) + 1),
            $sanitizedParams->getInt('width', ['default' => 250]),
            $sanitizedParams->getInt('height', ['default' => 250]),
            $sanitizedParams->getInt('top', ['default' => 50]),
            $sanitizedParams->getInt('left', ['default' => 50])
        );

        $layout->regions[] = $region;
        $layout->save([
            'saveTags' => false
        ]);

        // Permissions
        if ($this->getConfig()->getSetting('INHERIT_PARENT_PERMISSIONS') == 1) {

            $this->getLog()->debug('Applying permissions from parent, there are ' . count($layout->permissions));

            // Apply permissions from the Parent
            foreach ($layout->permissions as $permission) {
                /* @var Permission $permission */
                $permission = $this->permissionFactory->create($permission->groupId, get_class($region), $region->getId(), $permission->view, $permission->edit, $permission->delete);
                $permission->save();
            }
        }
        else {
            $this->getLog()->debug('Applying default permissions');

            // Apply the default permissions
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($region), $region->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $region->name),
            'id' => $region->regionId,
            'data' => $region
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
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
     */
    public function edit(Request $request, Response $response, $id)
    {
        $region = $this->regionFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($region)) {
            throw new AccessDeniedException();
        }

        // Check that this Regions Layout is in an editable state
        $layout = $this->layoutFactory->getById($region->layoutId);

        if (!$layout->isChild()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Load before we save
        $region->load();

        $region->name = $sanitizedParams->getString('name');
        $region->width = $sanitizedParams->getDouble('width');
        $region->height = $sanitizedParams->getDouble('height');
        $region->top = $sanitizedParams->getDouble('top');
        $region->left = $sanitizedParams->getDouble('left');
        $region->zIndex = $sanitizedParams->getInt('zIndex');

        // Loop
        $region->setOptionValue('loop', $sanitizedParams->getCheckbox('loop'));

        // Transitions
        $region->setOptionValue('transitionType', $sanitizedParams->getString('transitionType'));
        $region->setOptionValue('transitionDuration', $sanitizedParams->getInt('transitionDuration'));
        $region->setOptionValue('transitionDirection', $sanitizedParams->getString('transitionDirection'));

        // Save
        $region->save();

        // Mark the layout as needing rebuild
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

        return $this->render($request, $response);
    }

    /**
     * Delete a region
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
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
    public function delete(Request $request, Response $response, $id)
    {
        $region = $this->regionFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($region)) {
            throw new AccessDeniedException();
        }

        // Check that this Regions Layout is in an editable state
        $layout = $this->layoutFactory->getById($region->layoutId);

        if (!$layout->isChild())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        $region->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $region->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Update Positions
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
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
    function positionAll(Request $request, Response $response, $id)
    {
        // Create the layout
        $layout = $this->layoutFactory->loadById($id);

        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException();
        }

        // Check that this Layout is a Draft
        if (!$layout->isChild()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Pull in the regions and convert them to stdObjects
        $regions = $request->getParam('regions', null);

        if ($regions == null) {
            throw new InvalidArgumentException(__('No regions present'));
        }
        $regions = json_decode($regions);

        // Go through each region and update the region in the layout we have
        foreach ($regions as $newCoordinates) {
            // TODO attempt to sanitize?
            // Check that the properties we are expecting do actually exist
            if (!property_exists($newCoordinates, 'regionid'))
                throw new InvalidArgumentException(__('Missing regionid property'));

            if (!property_exists($newCoordinates, 'top'))
                throw new InvalidArgumentException(__('Missing top property'));

            if (!property_exists($newCoordinates, 'left'))
                throw new InvalidArgumentException(__('Missing left property'));

            if (!property_exists($newCoordinates, 'width'))
                throw new InvalidArgumentException(__('Missing width property'));

            if (!property_exists($newCoordinates, 'height'))
                throw new InvalidArgumentException(__('Missing height property'));

            $regionId = $newCoordinates->regionid;

            // Load the region
            $region = $layout->getRegion($regionId);

            // Check Permissions
            if (!$this->getUser()->checkEditable($region)) {
                throw new AccessDeniedException();
            }

            // New coordinates
            $region->top = $newCoordinates->top;
            $region->left = $newCoordinates->left;
            $region->width = $newCoordinates->width;
            $region->height = $newCoordinates->height;
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

        return $this->render($request, $response);
    }

    /**
     * Represents the Preview inside the Layout Designer
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws ControllerNotImplemented
     */
    public function preview(Request $request, Response $response, $id)
    {
        $sanitizedQuery = $this->getSanitizer($request->getParams());

        $widgetId = $sanitizedQuery->getInt('widgetId', ['default' => null]);
        $seqGiven = $sanitizedQuery->getInt('seq', ['default' => 1]);
        $seq = $sanitizedQuery->getInt('seq', ['default' => 1]);
        $width = $sanitizedQuery->getDouble('width', ['default' => 0]);
        $height = $sanitizedQuery->getDouble('height', ['default' => 0]);
        $scaleOverride = $sanitizedQuery->getDouble('scale_override', ['default' => 0]);

        // Load our region
        try {
            $region = $this->regionFactory->getById($id);
            $region->load();

            if ($widgetId !== null) {
                // Single Widget Requested
                $widget = $this->widgetFactory->getById($widgetId);
                $widget->load();

                $countWidgets = 1;

            } else {

                // Get the first playlist we can find
                $playlist = $region->getPlaylist()->setModuleFactory($this->moduleFactory);

                // Expand this Playlist out to its individual Widgets
                $widgets = $playlist->expandWidgets();

                $countWidgets = count($widgets);

                // We want to load the widget in the given sequence
                if ($countWidgets <= 0) {
                    // No media to preview
                    throw new NotFoundException(__('No widgets to preview'));
                }

                $this->getLog()->debug('There are ' . $countWidgets . ' widgets.');

                // Select the widget at the required sequence
                $widget = $playlist->getWidgetAt($seq, $widgets);
                /* @var \Xibo\Entity\Widget $widget */
                $widget->load();
            }

            // Output a preview
            $module = $this->moduleFactory->createWithWidget($widget, $region);

            // We need to make a route parser
            $this->getState()->html = $module
                ->setPreview(
                    true,
                    RouteContext::fromRequest($request)->getRouteParser(),
                    $width, $height
                )
                ->preview($width, $height, $scaleOverride);

            $this->getState()->extra['empty'] = false;
            $this->getState()->extra['type'] = $widget->type;
            $this->getState()->extra['duration'] = $widget->calculatedDuration;
            $this->getState()->extra['number_items'] = $countWidgets;
            $this->getState()->extra['current_item'] = $seqGiven;
            $this->getState()->extra['moduleName'] = $module->getName();
            $this->getState()->extra['regionDuration'] = $region->duration;
            $this->getState()->extra['useDuration'] = $widget->useDuration;
            $this->getState()->extra['zIndex'] = $region->zIndex;
            $this->getState()->extra['tempId'] = $widget->tempId;

        } catch (NotFoundException $e) {
            // No media to preview
            $this->getState()->extra['empty'] = true;
            $this->getState()->extra['text'] = __('Empty Region');
        } catch (InvalidArgumentException $e) {
            $this->getState()->extra['empty'] = true;
            $this->getState()->extra['text'] = __('Please correct the error with this Widget');
        }

        return $this->render($request, $response);
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

    /**
     * Add a drawer

     * @SWG\Post(
     *  path="/region/drawer/{id}",
     *  operationId="regionDrawerAdd",
     *  tags={"layout"},
     *  summary="Add drawer Region",
     *  description="Add a drawer Region to a Layout",
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The Layout ID to add the Region to",
     *      type="integer",
     *      required=true
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function addDrawer(Request $request, Response $response, $id) :Response
    {
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException();
        }

        if (!$layout->isChild()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $layout->load([
            'loadPlaylists' => true,
            'loadTags' => false,
            'loadPermissions' => true,
            'loadCampaigns' => false
        ]);

        // Add a new region
        $drawer = $this->regionFactory->create(
            $this->getUser()->userId, $layout->layout . '-' . (count($layout->regions) + 1 . ' - drawer'),
            $sanitizedParams->getInt('width', ['default' => 250]),
            $sanitizedParams->getInt('height', ['default' => 250]),
            $sanitizedParams->getInt('top', ['default' => 50]),
            $sanitizedParams->getInt('left', ['default' => 50]),
            0,
            1
        );

        $layout->drawers[] = $drawer;
        $layout->save([
            'saveTags' => false
        ]);

        // Permissions
        if ($this->getConfig()->getSetting('INHERIT_PARENT_PERMISSIONS') == 1) {

            $this->getLog()->debug('Applying permissions from parent, there are ' . count($layout->permissions));

            // Apply permissions from the Parent
            foreach ($layout->permissions as $permission) {
                /* @var Permission $permission */
                $permission = $this->permissionFactory->create($permission->groupId, get_class($drawer), $drawer->getId(), $permission->view, $permission->edit, $permission->delete);
                $permission->save();
            }
        }
        else {
            $this->getLog()->debug('Applying default permissions');

            // Apply the default permissions
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($drawer), $drawer->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added drawer %s'), $drawer->name),
            'id' => $drawer->regionId,
            'data' => $drawer
        ]);

        return $this->render($request, $response);
    }
}