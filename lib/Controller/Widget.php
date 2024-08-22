<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Carbon\Carbon;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\DataSetDataTypeRequestEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\WidgetAddEvent;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Event\WidgetEditOptionRequestEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\WidgetAudioFactory;
use Xibo\Factory\WidgetDataFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Render\WidgetDownloader;

/**
 * Controller for managing Widgets on Playlists/Layouts
 */
class Widget extends Base
{
    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var \Xibo\Factory\ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var PermissionFactory */
    private $permissionFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @var TransitionFactory */
    private $transitionFactory;

    /** @var RegionFactory */
    private $regionFactory;

    /** @var WidgetAudioFactory */
    protected $widgetAudioFactory;

    /**
     * Set common dependencies.
     * @param ModuleFactory $moduleFactory
     * @param \Xibo\Factory\ModuleTemplateFactory $moduleTemplateFactory
     * @param PlaylistFactory $playlistFactory
     * @param MediaFactory $mediaFactory
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     * @param TransitionFactory $transitionFactory
     * @param RegionFactory $regionFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     */
    public function __construct(
        $moduleFactory,
        $moduleTemplateFactory,
        $playlistFactory,
        $mediaFactory,
        $permissionFactory,
        $widgetFactory,
        $transitionFactory,
        $regionFactory,
        $widgetAudioFactory,
        private readonly WidgetDataFactory $widgetDataFactory
    ) {
        $this->moduleFactory = $moduleFactory;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->playlistFactory = $playlistFactory;
        $this->mediaFactory = $mediaFactory;
        $this->permissionFactory = $permissionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->transitionFactory = $transitionFactory;
        $this->regionFactory = $regionFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
    }

    // phpcs:disable
    /**
     * Add Widget
     *
     * @SWG\Post(
     *  path="/playlist/widget/{type}/{playlistId}",
     *  operationId="addWidget",
     *  tags={"widget"},
     *  summary="Add a Widget to a Playlist",
     *  description="Add a new Widget to a Playlist",
     *  @SWG\Parameter(
     *      name="type",
     *      in="path",
     *      description="The type of the Widget e.g. text. Media based Widgets like Image are added via POST /playlist/library/assign/{playlistId} call.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The Playlist ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="displayOrder",
     *      in="formData",
     *      description="Optional integer to say which position this assignment should occupy in the list. If more than one media item is being added, this will be the position of the first one.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="templateId",
     *      in="formData",
     *      description="If the module type provided has a dataType then provide the templateId to use.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     *
     * @param Request $request
     * @param Response $response
     * @param string $type
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    // phpcs:enable
    public function addWidget(Request $request, Response $response, $type, $id)
    {
        $params = $this->getSanitizer($request->getParams());

        $playlist = $this->playlistFactory->getById($id);
        if (!$this->getUser()->checkEditable($playlist)) {
            throw new AccessDeniedException(__('This Playlist is not shared with you with edit permission'));
        }

        // Check we have a permission factory
        if ($this->permissionFactory == null) {
            throw new ConfigurationException(
                __('Sorry there is an error with this request, cannot set inherited permissions')
            );
        }

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Load some information about this playlist
        // loadWidgets = true to keep the ordering correct
        $playlist->load([
            'playlistIncludeRegionAssignments' => false,
            'loadWidgets' => true,
            'loadTags' => false
        ]);

        // Make sure this module type is supported
        $module = $this->moduleFactory->getByType($type);
        if ($module->enabled == 0) {
            throw new NotFoundException(__('No module enabled of that type.'));
        }

        // Make sure it isn't a file based widget (which must be assigned not created)
        if ($module->regionSpecific != 1) {
            throw new InvalidArgumentException(
                __('Sorry but a file based Widget must be assigned not created'),
                'type'
            );
        }

        // If we're adding a canvas widget, then make sure we don't already have one and that we're on a region
        if ($module->type === 'global') {
            if (!$playlist->isRegionPlaylist()) {
                throw new InvalidArgumentException(__('Canvas Widgets can only be added to a Zone'), 'regionId');
            }

            foreach ($playlist->widgets as $widget) {
                if ($widget->type === 'global') {
                    throw new InvalidArgumentException(__('Only one Canvas Widget allowed per Playlist'), 'type');
                }
            }
        }

        // Grab a widget, set the type and default duration
        $widget = $this->widgetFactory->create(
            $this->getUser()->userId,
            $playlist->playlistId,
            $module->type,
            $module->defaultDuration,
            $module->schemaVersion
        );

        // Default status setting
        $widget->setOptionValue(
            'enableStat',
            'attrib',
            $this->getConfig()->getSetting('WIDGET_STATS_ENABLED_DEFAULT')
        );

        // Get the template
        if ($module->isTemplateExpected()) {
            $templateId = $params->getString('templateId', [
                'throw' => function () {
                    throw new InvalidArgumentException(__('Please select a template'), 'templateId');
                }
            ]);
            if ($templateId !== 'elements') {
                // Check it.
                $template = $this->moduleTemplateFactory->getByDataTypeAndId($module->dataType, $templateId);

                // Make sure its static
                if ($template->type !== 'static') {
                    throw new InvalidArgumentException(
                        __('Expecting a static template'),
                        'templateId'
                    );
                }
            }

            // Set it
            $widget->setOptionValue('templateId', 'attrib', $templateId);
        }

        // Assign this module to this Playlist in the appropriate place (which could be null)
        $displayOrder = $params->getInt('displayOrder');
        $playlist->assignWidget($widget, $displayOrder);

        if ($playlist->isRegionPlaylist() && count($playlist->widgets) >= 2) {
            // Convert this region to a `playlist` (if it is a zone)
            $widgetRegion = $this->regionFactory->getById($playlist->regionId);
            if ($widgetRegion->type === 'zone') {
                $widgetRegion->type = 'playlist';
                $widgetRegion->save();
            }
        }

        // Dispatch the Edit Event
        $this->getDispatcher()->dispatch(new WidgetAddEvent($module, $widget));

        // Save the widget
        $widget->calculateDuration($module)->save();

        // Module add will have saved our widget with the correct playlistId and displayOrder
        // if we have provided a displayOrder, then we ought to also save the Playlist so that new orders for those
        // existing Widgets are also saved.
        if ($displayOrder !== null) {
            $playlist->save();
        }

        // Successful
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $module->name),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Widget Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function editWidgetForm(Request $request, Response $response, $id)
    {
        // Load the widget
        $widget = $this->widgetFactory->loadByWidgetId($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Get a module for this widget
        $module = $this->moduleFactory->getByType($widget->type);

        // Media file?
        $media = null;
        if ($module->regionSpecific == 0) {
            try {
                $media = $this->mediaFactory->getById($widget->getPrimaryMediaId());
            } catch (NotFoundException $e) {
                $this->getLog()->error('Library Widget does not have a Media Id. widgetId: ' . $id);
            }
        }

        // Decorate the module properties with our current widgets data
        $module->decorateProperties($widget);

        // Do we have a static template assigned to this widget?
        //  we don't worry about elements here, the layout editor manages those for us.
        $template = null;
        $templateId = $widget->getOptionValue('templateId', null);
        if ($module->isTemplateExpected() && !empty($templateId) && $templateId !== 'elements') {
            $template = $this->moduleTemplateFactory->getByDataTypeAndId($module->dataType, $templateId);

            // Decorate the template with any properties saved in the widget
            $template->decorateProperties($widget);
        }

        // Pass to view
        $this->getState()->template = '';
        $this->getState()->setData([
            'module' => $module,
            'template' => $template,
            'media' => $media,
            'mediaEditable' => $media === null ? false : $this->getUser()->checkEditable($media),
            'commonProperties' => [
                'name' => $widget->getOptionValue('name', null),
                'enableStat' => $widget->getOptionValue('enableStat', null),
                'isRepeatData' => $widget->getOptionValue('isRepeatData', null),
                'showFallback' => $widget->getOptionValue('showFallback', null),
                'duration' => $widget->duration,
                'useDuration' => $widget->useDuration
            ],
        ]);

        return $this->render($request, $response);
    }

    // phpcs:disable
    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{id}",
     *  operationId="editWidget",
     *  tags={"widget"},
     *  summary="Edit a Widget",
     *  description="Edit a widget providing new properties to set on it",
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The ID of the Widget",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Set a duration on this widget, if unchecked the default or library duration will be used.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="Duration to use on this widget",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="An optional name for this widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="Should stats be enabled? On|Off|Inherit ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isRepeatData",
     *      in="formData",
     *      description="If this widget requires data, should that data be repeated to meet the number of items requested?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showFallback",
     *      in="formData",
     *      description="If this widget requires data and allows fallback data how should that data be returned? (never, always, empty, error)",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="properties",
     *      in="formData",
     *      description="Add an additional parameter for each of the properties required this module and its template. Use the moduleProperties and moduleTemplateProperties calls to get a list of properties needed",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    // phpcs:enable
    public function editWidget(Request $request, Response $response, $id)
    {
        $params = $this->getSanitizer($request->getParams());
        $widget = $this->widgetFactory->loadByWidgetId($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $module = $this->moduleFactory->getByType($widget->type);

        // Handle common parameters.
        $widget->useDuration = $params->getCheckbox('useDuration');
        $widget->duration = $params->getInt('duration', ['default' => $module->defaultDuration]);
        $widget->setOptionValue('name', 'attrib', $params->getString('name'));
        $widget->setOptionValue('enableStat', 'attrib', $params->getString('enableStat'));

        // Handle special common properties for widgets with data
        if ($module->isDataProviderExpected()) {
            $widget->setOptionValue('isRepeatData', 'attrib', $params->getCheckbox('isRepeatData'));

            if ($module->fallbackData === 1) {
                $widget->setOptionValue('showFallback', 'attrib', $params->getString('showFallback'));
            }
        }

        // Validate common parameters if we don't have a validator present.
        $widgetValidators = $module->getWidgetValidators();
        if (count($widgetValidators) <= 0 && $widget->duration <= 0) {
            throw new InvalidArgumentException(__('Duration needs to be a positive value'), 'duration');
        }

        // Set maximum duration - we do this regardless of the validator.
        if ($widget->duration > 526000) {
            throw new InvalidArgumentException(__('Duration must be lower than 526000'), 'duration');
        }

        // Save the template if provided
        $templateId = $params->getString('templateId');
        $template = null;
        if (!empty($templateId) && $templateId !== 'elements') {
            // We're allowed to change between static templates, but not between elements and static templates.
            // We can't change away from elements
            if ($widget->getOptionValue('templateId', null) === 'elements') {
                throw new InvalidArgumentException(
                    __('This widget uses elements and can not be changed to a static template'),
                    'templateId'
                );
            }

            // We must be a static
            $template = $this->moduleTemplateFactory->getByDataTypeAndId($module->dataType, $templateId);

            // Make sure its static
            if ($template->type !== 'static') {
                throw new InvalidArgumentException(
                    __('You can only change to another template of the same type'),
                    'templateId'
                );
            }

            // Set it
            $widget->setOptionValue('templateId', 'attrib', $templateId);
        } else if ($templateId === 'elements') {
            // If it was empty to start with and now its elements, we should set it.
            $widget->setOptionValue('templateId', 'attrib', $templateId);
        }

        // If we did not set the template in this save, then pull it out so that we can save its properties
        // don't do this for elements.
        $existingTemplateId = $widget->getOptionValue('templateId', null);
        if ($template === null && $existingTemplateId !== null && $existingTemplateId !== 'elements') {
            $template = $this->moduleTemplateFactory->getByDataTypeAndId($module->dataType, $existingTemplateId);
        }

        // We're expecting all of our properties to be supplied for editing.
        foreach ($module->properties as $property) {
            if ($property->type === 'message') {
                continue;
            }
            $property->setValueByType($params);
        }

        // Once they are set, validate them.
        $module->validateProperties('save', ['duration' => $widget->duration]);

        // Assert these properties on the widget.
        $widget->applyProperties($module->properties);

        // Assert the template properties
        if ($template !== null) {
            foreach ($template->properties as $property) {
                if ($property->type === 'message') {
                    continue;
                }
                $property->setValueByType($params);
            }

            $template->validateProperties('save', ['duration' => $widget->duration]);

            $widget->applyProperties($template->properties);
        }

        // Check to see if the media we've assigned exists.
        foreach ($widget->mediaIds as $mediaId) {
            try {
                $this->mediaFactory->getById($mediaId);
            } catch (NotFoundException) {
                throw new InvalidArgumentException(sprintf(
                    __('Your library reference %d does not exist.'),
                    $mediaId
                ), 'libraryRef');
            }
        }

        // TODO: remove media which is no longer referenced, without removing primary media and/or media in elements

        // If we have a validator interface, then use it now
        foreach ($widgetValidators as $widgetValidator) {
            $widgetValidator->validate($module, $widget, 'save');
        }

        // We've reached the end, so save
        $widget->calculateDuration($module)->save();

        // Successful
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $module->name),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Widget Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteWidgetForm(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);

        if (!$this->getUser()->checkDeleteable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with delete permission'));
        }

        $error = false;
        $module = null;
        try {
            $module = $this->moduleFactory->getByType($widget->type);
        } catch (NotFoundException $notFoundException) {
            $error = true;
        }

        // Pass to view
        $this->getState()->template = 'module-form-delete';
        $this->getState()->setData([
            'widgetId' => $id,
            'module' => $module,
            'error' => $error,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete a Widget
     * @SWG\Delete(
     *  path="/playlist/widget/{widgetId}",
     *  operationId="WidgetDelete",
     *  tags={"widget"},
     *  summary="Delete a Widget",
     *  description="Deleted a specified widget",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The widget ID to delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *  )
     *)
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteWidget(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);

        if (!$this->getUser()->checkDeleteable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with delete permission'));
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Delete clears these, so cache them.
        $widgetMedia = $widget->mediaIds;

        // Call Widget Delete
        $widget->delete();

        // Delete Media?
        if ($sanitizedParams->getCheckbox('deleteMedia') == 1) {
            foreach ($widgetMedia as $mediaId) {
                $media = $this->mediaFactory->getById($mediaId);

                // Check we have permissions to delete
                if (!$this->getUser()->checkDeleteable($media)) {
                    throw new AccessDeniedException();
                }

                $this->getDispatcher()->dispatch(new MediaDeleteEvent($media), MediaDeleteEvent::$NAME);
                $media->delete();
            }
        }

        // Module name for the message
        try {
            $module = $this->moduleFactory->getByType($widget->type);
            $message = sprintf(__('Deleted %s'), $module->name);
        } catch (NotFoundException $notFoundException) {
            $message = __('Deleted Widget');
        }

        // Successful
        $this->getState()->hydrate(['message' => $message]);

        return $this->render($request, $response);
    }

    /**
     * Edit Widget Transition Form
     * @param Request $request
     * @param Response $response
     * @param string $type
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editWidgetTransitionForm(Request $request, Response $response, $type, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Pass to view
        $this->getState()->template = 'module-form-transition';
        $this->getState()->setData([
            'type' => $type,
            'widget' => $widget,
            'module' => $this->moduleFactory->getByType($widget->type),
            'transitions' => [
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
            ],
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Widget transition
     * @SWG\Put(
     *  path="/playlist/widget/transition/{type}/{widgetId}",
     *  operationId="WidgetEditTransition",
     *  tags={"widget"},
     *  summary="Adds In/Out transition",
     *  description="Adds In/Out transition to a specified widget",
     *  @SWG\Parameter(
     *      name="type",
     *      in="path",
     *      description="Transition type, available options: in, out",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The widget ID to add the transition to",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="transitionType",
     *      in="formData",
     *      description="Type of a transition, available Options: fly, fadeIn, fadeOut",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="transitionDuration",
     *      in="formData",
     *      description="Duration of this transition in milliseconds",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="transitionDirection",
     *      in="formData",
     *      description="The direction for this transition, only appropriate for transitions that move, such as fly. Available options: N, NE, E, SE, S, SW, W, NW",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *   )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $type
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function editWidgetTransition(Request $request, Response $response, $type, $id)
    {
        $widget = $this->widgetFactory->getById($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $widget->load();
        $sanitizedParams = $this->getSanitizer($request->getParams());

        switch ($type) {
            case 'in':
                $widget->setOptionValue('transIn', 'attrib', $sanitizedParams->getString('transitionType'));
                $widget->setOptionValue('transInDuration', 'attrib', $sanitizedParams->getInt('transitionDuration'));
                $widget->setOptionValue(
                    'transInDirection',
                    'attrib',
                    $sanitizedParams->getString('transitionDirection')
                );

                break;

            case 'out':
                $widget->setOptionValue('transOut', 'attrib', $sanitizedParams->getString('transitionType'));
                $widget->setOptionValue('transOutDuration', 'attrib', $sanitizedParams->getInt('transitionDuration'));
                $widget->setOptionValue(
                    'transOutDirection',
                    'attrib',
                    $sanitizedParams->getString('transitionDirection')
                );

                break;

            default:
                throw new InvalidArgumentException(__('Unknown transition type'), 'type');
        }

        $widget->save();

        // Successful
        $this->getState()->hydrate([
            'message' => __('Edited Transition'),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Widget Audio Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetAudioForm(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Are we allowed to do this?
        if ($widget->type === 'subplaylist') {
            throw new InvalidArgumentException(
                __('Audio cannot be attached to a Sub-Playlist Widget. Please attach it to the Widgets inside the Playlist'),
                'type'
            );
        }

        $audioAvailable = true;
        if ($widget->countAudio() > 0) {
            $audio = $this->mediaFactory->getById($widget->getAudioIds()[0]);

            $this->getLog()->debug('Found audio: ' . $audio->mediaId . ', isEdited = '
                . $audio->isEdited . ', retired = ' . $audio->retired);

            $audioAvailable = ($audio->isEdited == 0 && $audio->retired == 0);
        }

        // Pass to view
        $this->getState()->template = 'module-form-audio';
        $this->getState()->setData([
            'widget' => $widget,
            'module' => $this->moduleFactory->getByType($widget->type),
            'media' => $this->mediaFactory->getByMediaType('audio'),
            'isAudioAvailable' => $audioAvailable
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit an Audio Widget
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/audio",
     *  operationId="WidgetAssignedAudioEdit",
     *  tags={"widget"},
     *  summary="Parameters for edting/adding audio file to a specific widget",
     *  description="Parameters for edting/adding audio file to a specific widget",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of a widget to which you want to add audio or edit existing audio",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="formData",
     *      description="Id of a audio file in CMS library you wish to add to a widget",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="volume",
     *      in="formData",
     *      description="Volume percentage(0-100) for this audio to play at",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="loop",
     *      in="formData",
     *      description="Flag (0, 1) Should the audio loop if it finishes before the widget has finished?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetAudio(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Are we allowed to do this?
        if ($widget->type === 'subplaylist') {
            throw new InvalidArgumentException(
                __('Audio cannot be attached to a Sub-Playlist Widget. Please attach it to the Widgets inside the Playlist'),
                'type'
            );
        }

        $widget->load();

        // Pull in the parameters we are expecting from the form.
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $mediaId = $sanitizedParams->getInt('mediaId');
        $volume = $sanitizedParams->getInt('volume', ['default' => 100]);
        $loop = $sanitizedParams->getCheckbox('loop');

        // Remove existing audio records.
        foreach ($widget->audio as $audio) {
            $widget->unassignAudio($audio);
        }

        if ($mediaId != 0) {
            $widgetAudio = $this->widgetAudioFactory->createEmpty();
            $widgetAudio->mediaId = $mediaId;
            $widgetAudio->volume = $volume;
            $widgetAudio->loop = $loop;

            $widget->assignAudio($widgetAudio);
        }

        $widget->save();

        // Successful
        $this->getState()->hydrate([
            'message' => __('Edited Audio'),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete an Assigned Audio Widget
     * @SWG\Delete(
     *  path="/playlist/widget/{widgetId}/audio",
     *  operationId="WidgetAudioDelete",
     *  tags={"widget"},
     *  summary="Delete assigned audio widget",
     *  description="Delete assigned audio widget from specified widget ID",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of a widget from which you want to delete the audio from",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *  )
     *)
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetAudioDelete(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);

        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $widget->load();

        foreach ($widget->audio as $audio) {
            $widget->unassignAudio($audio);
        }

        $widget->save();

        // Successful
        $this->getState()->hydrate([
            'message' => __('Removed Audio'),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get Data
     * @param Request $request
     * @param Response $response
     * @param $regionId
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getData(Request $request, Response $response, $regionId, $id)
    {
        $region = $this->regionFactory->getById($regionId);
        if (!$this->getUser()->checkViewable($region)) {
            throw new AccessDeniedException(__('This Region is not shared with you'));
        }

        $widget = $this->widgetFactory->loadByWidgetId($id);
        if (!$this->getUser()->checkViewable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you'));
        }

        $module = $this->moduleFactory->getByType($widget->type);

        // This is always a preview
        // We only return data if a data provider is expected.
        if (!$module->isDataProviderExpected()) {
            return $response->withJson([]);
        }

        // Populate the widget with its properties.
        $widget->load();
        $module->decorateProperties($widget, true);

        $dataProvider = $module->createDataProvider($widget);
        $dataProvider->setMediaFactory($this->mediaFactory);
        $dataProvider->setDisplayProperties(
            $this->getConfig()->getSetting('DEFAULT_LAT'),
            $this->getConfig()->getSetting('DEFAULT_LONG')
        );
        $dataProvider->setIsPreview(true);

        $widgetInterface = $module->getWidgetProviderOrNull();
        $widgetDataProviderCache = $this->moduleFactory->createWidgetDataProviderCache();
        $cacheKey = $this->moduleFactory->determineCacheKey(
            $module,
            $widget,
            0,
            $dataProvider,
            $widgetInterface
        );

        $this->getLog()->debug('getData: cacheKey is ' . $cacheKey);

        // Get the data modified date
        $dataModifiedDt = null;
        if ($widgetInterface !== null) {
            $dataModifiedDt = $widgetInterface->getDataModifiedDt($dataProvider);

            if ($dataModifiedDt !== null) {
                $this->getLog()->debug('getData: data modifiedDt is ' . $dataModifiedDt->toAtomString());
            }
        }

        // Will we use fallback data if available?
        $showFallback = $widget->getOptionValue('showFallback', 'never');
        if ($showFallback !== 'never') {
            // What data type are we dealing with?
            try {
                $dataTypeFields = [];
                foreach ($this->moduleFactory->getDataTypeById($module->dataType)->fields as $field) {
                    $dataTypeFields[$field->id] = $field->type;
                }

                // Potentially we will, so get the modifiedDt of this fallback data.
                $fallbackModifiedDt = $this->widgetDataFactory->getModifiedDtForWidget($widget->widgetId);

                if ($fallbackModifiedDt !== null) {
                    $this->getLog()->debug('getData: fallback modifiedDt is ' . $fallbackModifiedDt->toAtomString());

                    $dataModifiedDt = max($dataModifiedDt, $fallbackModifiedDt);
                }
            } catch (NotFoundException) {
                $this->getLog()->info('getData: widget will fallback set where the module does not support it');
                $dataTypeFields = null;
            }
        } else {
            $dataTypeFields = null;
        }

        // Use the cache if we can.
        if (!$widgetDataProviderCache->decorateWithCache($dataProvider, $cacheKey, $dataModifiedDt)
            || $widgetDataProviderCache->isCacheMissOrOld()
        ) {
            $this->getLog()->debug('getData: Pulling fresh data');

            $dataProvider->clearData();
            $dataProvider->clearMeta();
            $dataProvider->addOrUpdateMeta('showFallback', $showFallback);

            try {
                if ($widgetInterface !== null) {
                    $widgetInterface->fetchData($dataProvider);
                } else {
                    $dataProvider->setIsUseEvent();
                }

                if ($dataProvider->isUseEvent()) {
                    $this->getDispatcher()->dispatch(
                        new WidgetDataRequestEvent($dataProvider),
                        WidgetDataRequestEvent::$NAME
                    );
                }

                // Before caching images, check to see if the data provider is handled
                $isFallback = false;
                if ($showFallback !== 'never'
                    && $dataTypeFields !== null
                    && (
                        count($dataProvider->getErrors()) > 0
                        || count($dataProvider->getData()) <= 0
                        || $showFallback === 'always'
                    )
                ) {
                    // Error or no data.
                    $this->getLog()->debug('getData: eligible for fallback data');

                    // Pull in the fallback data
                    foreach ($this->widgetDataFactory->getByWidgetId($dataProvider->getWidgetId()) as $item) {
                        // Handle any special data types in the fallback data
                        foreach ($item->data as $itemId => $itemData) {
                            if (!empty($itemData)
                                && array_key_exists($itemId, $dataTypeFields)
                                && $dataTypeFields[$itemId] === 'image'
                            ) {
                                $item->data[$itemId] = $dataProvider->addLibraryFile($itemData);
                            }
                        }

                        $dataProvider->addItem($item->data);

                        // Indicate we've been handled by fallback data
                        $isFallback = true;
                    }

                    if ($isFallback) {
                        $dataProvider->addOrUpdateMeta('includesFallback', true);
                    }
                }

                // Remove fallback data from the cache if no-longer needed
                if (!$isFallback) {
                    $dataProvider->addOrUpdateMeta('includesFallback', false);
                }

                // Do we have images?
                $media = $dataProvider->getImages();
                if (count($media) > 0) {
                    // Process the downloads.
                    $this->mediaFactory->processDownloads(function ($media) use ($widget) {
                        // Success
                        // We don't need to do anything else, references to mediaId will be built when we decorate
                        // the HTML.
                        // Nothing is linked to a display when in preview mode.
                        $this->getLog()->debug('getData: Successfully downloaded ' . $media->mediaId);
                    });
                }

                // Save to cache
                if ($dataProvider->isHandled() || $isFallback) {
                    $widgetDataProviderCache->saveToCache($dataProvider);
                } else {
                    // Unhandled data provider.
                    $this->getLog()->debug('getData: unhandled data provider and no fallback data');

                    $message = null;
                    foreach ($dataProvider->getErrors() as $error) {
                        $message .= $error . PHP_EOL;
                    }
                    throw new ConfigurationException($message ?? __('No data providers configured'));
                }
            } finally {
                $widgetDataProviderCache->finaliseCache();
            }
        } else {
            $this->getLog()->debug('getData: Returning cache');
        }

        // Add permissions needed to see linked media
        $media = $widgetDataProviderCache->getCachedMediaIds();
        $this->getLog()->debug('getData: linking ' . count($media) . ' images');

        foreach ($media as $mediaId) {
            // We link these module images to the user.
            foreach ($this->permissionFactory->getAllByObjectId(
                $this->getUser(),
                'Xibo\Entity\Media',
                $mediaId,
            ) as $permission) {
                $permission->view = 1;
                $permission->save();
            }
        }

        // Decorate for output.
        $data = $widgetDataProviderCache->decorateForPreview(
            $dataProvider->getData(),
            function (string $route, array $data, array $params = []) use ($request) {
                return $this->urlFor($request, $route, $data, $params);
            }
        );

        return $response->withJson([
            'data' => $data,
            'meta' => $dataProvider->getMeta(),
        ]);
    }

    /**
     * Get Resource
     * @param Request $request
     * @param Response $response
     * @param $regionId
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getResource(Request $request, Response $response, $regionId, $id)
    {
        $this->setNoOutput();

        $region = $this->regionFactory->getById($regionId);
        if (!$this->getUser()->checkViewable($region)) {
            throw new AccessDeniedException(__('This Region is not shared with you'));
        }

        $widget = $this->widgetFactory->loadByWidgetId($id);
        if (!$this->getUser()->checkViewable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you'));
        }

        $module = $this->moduleFactory->getByType($widget->type);

        // 3 options
        // ---------
        // download a file
        // render a canvas
        // render a widget in a region

        // Download a file
        // ---------------
        // anything that calls this and does not produce some HTML should output its associated
        // file.
        if ($module->regionSpecific == 0 && $module->renderAs != 'html') {
            // Pull out the media
            $media = $this->mediaFactory->getById($widget->getPrimaryMediaId());

            // Create a downloader to deal with this.
            $downloader = new WidgetDownloader(
                $this->getConfig()->getSetting('LIBRARY_LOCATION'),
                $this->getConfig()->getSetting('SENDFILE_MODE'),
                $this->getConfig()->getSetting('DEFAULT_RESIZE_LIMIT', 6000)
            );
            $downloader->useLogger($this->getLog()->getLoggerInterface());
            return $this->render($request, $downloader->download($media, $response));
        }

        if ($region->type === 'canvas') {
            // Render a canvas
            // ---------------
            // A canvas plays all widgets in the region at once.
            // none of them will be anything other than elements
            $widgets = $region->getPlaylist()->widgets;
        } else {
            // Render a widget in a region
            // ---------------------------
            // We have a widget
            $widgets = [$widget];
        }

        // Templates
        $templates = $this->widgetFactory->getTemplatesForWidgets($module, $widgets);

        // Create a renderer to deal with this.
        try {
            $renderer = $this->moduleFactory->createWidgetHtmlRenderer();
            $resource = $renderer->renderOrCache(
                $region,
                $widgets,
                $templates
            );

            if (!empty($resource)) {
                $resource = $renderer->decorateForPreview(
                    $region,
                    $resource,
                    function (string $route, array $data, array $params = []) use ($request) {
                        return $this->urlFor($request, $route, $data, $params);
                    },
                    $request,
                );

                $response->getBody()->write($resource);
            }
        } catch (\Exception $e) {
            $this->getLog()->error('Failed to render widget, e: ' . $e->getMessage());
            throw new ConfigurationException(__('Problem rendering widget'));
        }

        return $this->render($request, $response);
    }

    /**
     * Widget Expiry Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function widgetExpiryForm(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->loadByWidgetId($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Parse out the dates
        $fromDt = $widget->fromDt === \Xibo\Entity\Widget::$DATE_MIN
            ? ''
            : Carbon::createFromTimestamp($widget->fromDt)->format(DateFormatHelper::getSystemFormat());

        $toDt = $widget->toDt === \Xibo\Entity\Widget::$DATE_MAX
            ? ''
            : Carbon::createFromTimestamp($widget->toDt)->format(DateFormatHelper::getSystemFormat());

        // Pass to view
        $this->getState()->template = 'module-form-expiry';
        $this->getState()->setData([
            'module' => $this->moduleFactory->getByType($widget->type),
            'fromDt' => $fromDt,
            'toDt' => $toDt,
            'deleteOnExpiry' => $widget->getOptionValue('deleteOnExpiry', 0)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit an Expiry Widget
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/expiry",
     *  operationId="WidgetAssignedExpiryEdit",
     *  tags={"widget"},
     *  summary="Set Widget From/To Dates",
     *  description="Control when this Widget is active on this Playlist",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of a widget to which you want to add audio or edit existing audio",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The From Date in Y-m-d H::i:s format",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The To Date in Y-m-d H::i:s format",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="deleteOnExpiry",
     *      in="formData",
     *      description="Delete this Widget when it expires?",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetExpiry(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $widget->load();

        // Pull in the parameters we are expecting from the form.
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $fromDt = $sanitizedParams->getDate('fromDt');
        $toDt = $sanitizedParams->getDate('toDt');

        if ($fromDt !== null) {
            $widget->fromDt = $fromDt->format('U');
        } else {
            $widget->fromDt = \Xibo\Entity\Widget::$DATE_MIN;
        }

        if ($toDt !== null) {
            $widget->toDt = $toDt->format('U');
        } else {
            $widget->toDt = \Xibo\Entity\Widget::$DATE_MAX;
        }

        // Delete on expiry?
        $widget->setOptionValue('deleteOnExpiry', 'attrib', ($sanitizedParams->getCheckbox('deleteOnExpiry') ? 1 : 0));

        // Save
        $widget->save([
            'saveWidgetOptions' => true,
            'saveWidgetAudio' => false,
            'saveWidgetMedia' => false,
            'notify' => true,
            'notifyPlaylists' => true,
            'notifyDisplays' => false,
            'audit' => true
        ]);

        if ($this->isApi($request)) {
            $widget->createdDt = Carbon::createFromTimestamp($widget->createdDt)
                ->format(DateFormatHelper::getSystemFormat());

            $widget->modifiedDt = Carbon::createFromTimestamp($widget->modifiedDt)
                ->format(DateFormatHelper::getSystemFormat());

            $widget->fromDt = Carbon::createFromTimestamp($widget->fromDt)
                ->format(DateFormatHelper::getSystemFormat());

            $widget->toDt = Carbon::createFromTimestamp($widget->toDt)
                ->format(DateFormatHelper::getSystemFormat());
        }

        // Successful
        $this->getState()->hydrate([
            'message' => __('Edited Expiry'),
            'id' => $widget->widgetId,
            'data' => $widget
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/region",
     *  operationId="WidgetAssignedRegionSet",
     *  tags={"widget"},
     *  summary="Set Widget Region",
     *  description="Set the Region this Widget is intended for - only suitable for Drawer Widgets",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of the Widget to set region on",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="targetRegionId",
     *      in="formData",
     *      description="The target regionId",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function widgetSetRegion(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isRegionPlaylist() || !$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Make sure we are on a Drawer Widget
        $region = $this->regionFactory->getById($playlist->regionId);
        if ($region->isDrawer !== 1) {
            throw new InvalidArgumentException(
                __('You can only set a target region on a Widget in the drawer.'),
                'widgetId'
            );
        }

        // Store the target regionId
        $widget->load();
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $widget->setOptionValue('targetRegionId', 'attrib', $sanitizedParams->getInt('targetRegionId'));

        // Save
        $widget->save([
            'saveWidgetOptions' => true,
            'saveWidgetAudio' => false,
            'saveWidgetMedia' => false,
            'notify' => true,
            'notifyPlaylists' => true,
            'notifyDisplays' => false,
            'audit' => true
        ]);

        // Successful
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Target region set'),
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/elements",
     *  operationId="widgetSaveElements",
     *  tags={"widget"},
     *  summary="Save elements to a widget",
     *  description="Update a widget with elements associated with it",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of the Widget to set region on",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\RequestBody(
     *      description="JSON representing the elements assigned to this widget"
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
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
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function saveElements(Request $request, Response $response, $id)
    {
        $widget = $this->widgetFactory->getById($id);
        if (!$this->getUser()->checkEditable($widget)) {
            throw new AccessDeniedException(__('This Widget is not shared with you with edit permission'));
        }

        // Test to see if we are on a Region Specific Playlist or a standalone
        $playlist = $this->playlistFactory->getById($widget->playlistId);

        // If we are a region Playlist, we need to check whether the owning Layout is a draft or editable
        if (!$playlist->isRegionPlaylist() || !$playlist->isEditable()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        // Store the target regionId
        $widget->load();

        // Get a list of elements that already exist and their mediaId's
        $newMediaIds = [];
        $existingMediaIds = [];
        foreach (json_decode($widget->getOptionValue('elements', '[]'), true) as $widgetElement) {
            foreach ($widgetElement['elements'] ?? [] as $element) {
                if (!empty($element['mediaId'])) {
                    $existingMediaIds[] = intval($element['mediaId']);
                }
            }
        }

        $this->getLog()->debug('saveElements: there are ' . count($existingMediaIds) . ' existing mediaIds');

        // Pull out elements directly from the request body
        $elements = $request->getBody()->getContents();
        $elementJson = json_decode($elements, true);
        if ($elementJson === null) {
            throw new InvalidArgumentException(__('Invalid element JSON'), 'body');
        }

        // Validate that we have elements remaining.
        if (count($elementJson) <= 0) {
            throw new InvalidArgumentException(
                __('At least one element is required for this Widget. Please delete it if you no longer need it.'),
                'body',
            );
        }

        // Parse the element JSON to see if we need to set `itemsPerPage`
        $slots = [];
        $uniqueSlots = 0;
        foreach ($elementJson as $widgetElement) {
            foreach ($widgetElement['elements'] ?? [] as $element) {
                $slotNo = 'slot_' . ($element['slot'] ?? 0);
                if (!in_array($slotNo, $slots)) {
                    $slots[] = $slotNo;
                    $uniqueSlots++;
                }

                // Handle elements with the mediaId property so that media is linked and unlinked correctly.
                if (!empty($element['mediaId'])) {
                    $mediaId = intval($element['mediaId']);

                    if (!in_array($mediaId, $existingMediaIds)) {
                        // Make sure it exists, and we have permission to use it.
                        $this->mediaFactory->getById($mediaId, false);
                    }
                    $widget->assignMedia($mediaId);
                    $newMediaIds[] = $mediaId;
                }
            }
        }

        if ($uniqueSlots > 0) {
            $currentItemsPerPage = $widget->getOptionValue('itemsPerPage', null);

            $widget->setOptionValue('itemsPerPage', 'attrib', $uniqueSlots);

            // We should calculate the widget duration as it might have changed
            if ($currentItemsPerPage != $uniqueSlots) {
                $this->getLog()->debug('saveElements: updating unique slots to ' . $uniqueSlots
                    . ', currentItemsPerPage: ' . $currentItemsPerPage);

                $module = $this->moduleFactory->getByType($widget->type);
                $widget->calculateDuration($module);
            }
        }

        // Save elements
        $widget->setOptionValue('elements', 'raw', $elements);

        // Unassign any mediaIds from elements which are no longer used.
        foreach ($existingMediaIds as $existingMediaId) {
            if (!in_array($existingMediaId, $newMediaIds)) {
                $widget->unassignMedia($existingMediaId);
            }
        }

        // Save, without auditing widget options.
        $widget->save([
            'saveWidgetOptions' => true,
            'saveWidgetAudio' => false,
            'saveWidgetMedia' => true,
            'notifyDisplays' => false,
            'audit' => true,
            'auditWidgetOptions' => false,
            'auditMessage' => 'Elements Updated',
        ]);

        // Successful
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Saved elements')
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function additionalWidgetEditOptions(Request $request, Response $response, $id)
    {
        $params = $this->getSanitizer($request->getParams());

        // Load the widget
        $widget = $this->widgetFactory->loadByWidgetId($id);

        // Sanitizer options
        $sanitizerOptions = [
            'throw' => function () {
                throw new InvalidArgumentException(__('Please supply a propertyId'), 'propertyId');
            },
            'rules' => ['notEmpty' => []],
        ];

        // Which property is this for?
        $propertyId = $params->getString('propertyId', $sanitizerOptions);
        $propertyValue = $params->getString($propertyId);

        // Dispatch an event to service this widget.
        $event = new WidgetEditOptionRequestEvent($widget, $propertyId, $propertyValue);
        $this->getDispatcher()->dispatch($event, $event::$NAME);

        // Return the options.
        $options = $event->getOptions();
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = count($options);
        $this->getState()->setData($options);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}/dataType",
     *  operationId="widgetGetDataType",
     *  tags={"widget"},
     *  summary="Widget DataType",
     *  description="Get DataType for a Widget according to the widgets module definition",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="Id of the Widget",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\RequestBody(
     *      description="A datatype"
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @param int $id the widgetId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getDataType(Request $request, Response $response, int $id): Response
    {
        if (empty($id)) {
            throw new InvalidArgumentException(__('Please provide a widgetId'), 'id');
        }

        // Load the widget
        $widget = $this->widgetFactory->loadByWidgetId($id);

        // Does this widget have a data type?
        $module = $this->moduleFactory->getByType($widget->type);

        // Does this module have a data type?
        // We have special handling for dataset because the data type returned is variable.
        if ($module->dataType === 'dataset') {
            // Raise an event to get the modifiedDt of this dataSet
            $event = new DataSetDataTypeRequestEvent($widget->getOptionValue('dataSetId', 0));
            $this->getDispatcher()->dispatch($event, DataSetDataTypeRequestEvent::$NAME);
            return $response->withJson($event->getDataType());
        } else if ($module->isDataProviderExpected()) {
            return $response->withJson($this->moduleFactory->getDataTypeById($module->dataType));
        } else {
            throw new NotFoundException(__('Widget does not have a data type'));
        }
    }
}
