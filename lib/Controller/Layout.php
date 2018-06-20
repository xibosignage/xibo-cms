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

use Parsedown;
use Xibo\Entity\Permission;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Session;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ResolutionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\LayoutUploadHandler;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Layout
 * @package Xibo\Controller
 *
 */
class Layout extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var ResolutionFactory
     */
    private $resolutionFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  CampaignFactory */
    private $campaignFactory;

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
     * @param UserFactory $userFactory
     * @param ResolutionFactory $resolutionFactory
     * @param LayoutFactory $layoutFactory
     * @param ModuleFactory $moduleFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param TagFactory $tagFactory
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param CampaignFactory $campaignFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session, $userFactory, $resolutionFactory, $layoutFactory, $moduleFactory, $permissionFactory, $userGroupFactory, $tagFactory, $mediaFactory, $dataSetFactory, $campaignFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->session = $session;
        $this->userFactory = $userFactory;
        $this->resolutionFactory = $resolutionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->moduleFactory = $moduleFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->tagFactory = $tagFactory;
        $this->mediaFactory = $mediaFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->campaignFactory = $campaignFactory;
    }

    /**
     * @return LayoutFactory
     */
    public function getLayoutFactory()
    {
        return $this->layoutFactory;
    }

    /**
     * @return DataSetFactory
     */
    public function getDataSetFactory()
    {
        return $this->dataSetFactory;
    }

    /**
     * Displays the Layout Page
     */
    function displayPage()
    {
        // Call to render the template
        $this->getState()->template = 'layout-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
            'groups' => $this->userGroupFactory->query()
        ]);
    }

    /**
     * Display the Layout Designer
     * @param int $layoutId
     */
    public function displayDesigner($layoutId)
    {
        $layout = $this->layoutFactory->loadById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Work out our resolution
        if ($layout->schemaVersion < 2)
            $resolution = $this->resolutionFactory->getByDesignerDimensions($layout->width, $layout->height);
        else
            $resolution = $this->resolutionFactory->getByDimensions($layout->width, $layout->height);

        $moduleFactory = $this->moduleFactory;
        $isTemplate = $layout->hasTag('template');

        // Set up any JavaScript translations
        $data = [
            'layout' => $layout,
            'resolution' => $resolution,
            'isTemplate' => $isTemplate,
            'zoom' => $this->getSanitizer()->getDouble('zoom', $this->getUser()->getOptionValue('defaultDesignerZoom', 1)),
            'modules' => array_map(function($element) use ($moduleFactory) { return $moduleFactory->createForInstall($element->class); }, $moduleFactory->getAssignableModules())
        ];

        // Call the render the template
        $this->getState()->template = 'layout-designer-page';
        $this->getState()->setData($data);
    }

    /**
     * Add a Layout
     * @SWG\Post(
     *  path="/layout",
     *  operationId="layoutAdd",
     *  tags={"layout"},
     *  summary="Add a Layout",
     *  description="Add a new Layout to the CMS",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The layout name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The layout description",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="If the Layout should be created with a Template, provide the ID, otherwise don't provide",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="resolutionId",
     *      in="formData",
     *      description="If a Template is not provided, provide the resolutionId for this Layout.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    function add()
    {
        $name = $this->getSanitizer()->getString('name');
        $description = $this->getSanitizer()->getString('description');
        $templateId = $this->getSanitizer()->getInt('layoutId');
        $resolutionId = $this->getSanitizer()->getInt('resolutionId');

        if ($templateId != 0)
            $layout = $this->layoutFactory->createFromTemplate($templateId, $this->getUser()->userId, $name, $description, $this->getSanitizer()->getString('tags'));
        else
            $layout = $this->layoutFactory->createFromResolution($resolutionId, $this->getUser()->userId, $name, $description, $this->getSanitizer()->getString('tags'));

        // Save
        $layout->save();

        // Permissions
        foreach ($this->permissionFactory->createForNewEntity($this->getUser(), 'Xibo\\Entity\\Campaign', $layout->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
            /* @var Permission $permission */
            $permission->save();
        }

        foreach ($layout->regions as $region) {
            /* @var Region $region */
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($region), $region->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            foreach ($region->playlists as $playlist) {
                /* @var Playlist $playlist */
                foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
                }

                foreach ($playlist->widgets as $widget) {
                    /* @var Widget $widget */
                    foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($widget), $widget->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                        /* @var Permission $permission */
                        $permission->save();
                    }
                }
            }
        }

        $this->getLog()->debug('Layout Added');

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);
    }

    /**
     * Edit Layout
     * @param int $layoutId
     *
     * @SWG\Put(
     *  path="/layout/{layoutId}",
     *  operationId="layoutEdit",
     *  summary="Edit Layout",
     *  description="Edit a Layout",
     *  tags={"layout"},
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="integer",
     *      in="path",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Layout Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Layout Description",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="A comma separated list of Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="A flag indicating whether this Layout is retired.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundColor",
     *      in="formData",
     *      description="A HEX color to use as the background color of this Layout.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundImageId",
     *      in="formData",
     *      description="A media ID to use as the background image for this Layout.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundzIndex",
     *      in="formData",
     *      description="The Layer Number to use for the background.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="resolutionId",
     *      in="formData",
     *      description="The Resolution ID to use on this Layout.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @throws XiboException
     */
    function edit($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $layout->layout = $this->getSanitizer()->getString('name');
        $layout->description = $this->getSanitizer()->getString('description');
        $layout->replaceTags($this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags')));
        $layout->retired = $this->getSanitizer()->getCheckbox('retired');
        $layout->backgroundColor = $this->getSanitizer()->getString('backgroundColor');
        $layout->backgroundImageId = $this->getSanitizer()->getInt('backgroundImageId');
        $layout->backgroundzIndex = $this->getSanitizer()->getInt('backgroundzIndex');

        // Resolution
        $saveRegions = false;
        $resolution = $this->resolutionFactory->getById($this->getSanitizer()->getInt('resolutionId'));

        if ($layout->width != $resolution->width || $layout->height != $resolution->height) {
            $saveRegions = true;
            $layout->width = $resolution->width;
            $layout->height = $resolution->height;
        }

        // Save
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => $saveRegions,
            'saveTags' => true,
            'setBuildRequired' => true,
            'notify' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);
    }

    /**
     * Delete Layout Form
     * @param int $layoutId
     */
    function deleteForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkDeleteable($layout))
            throw new AccessDeniedException(__('You do not have permissions to delete this layout'));

        $data = [
            'layout' => $layout,
            'help' => [
                'delete' => $this->getHelp()->link('Layout', 'Delete')
            ]
        ];

        $this->getState()->template = 'layout-form-delete';
        $this->getState()->setData($data);
    }

    /**
     * Retire Layout Form
     * @param int $layoutId
     */
    public function retireForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $data = [
            'layout' => $layout,
            'help' => [
                'delete' => $this->getHelp()->link('Layout', 'Retire')
            ]
        ];

        $this->getState()->template = 'layout-form-retire';
        $this->getState()->setData($data);
    }

    /**
     * Deletes a layout
     * @param int $layoutId
     *
     * @SWG\Delete(
     *  path="/layout/{layoutId}",
     *  operationId="layoutDelete",
     *  tags={"layout"},
     *  summary="Delete Layout",
     *  description="Delete a Layout",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    function delete($layoutId)
    {
        $layout = $this->layoutFactory->loadById($layoutId);

        if (!$this->getUser()->checkDeleteable($layout))
            throw new AccessDeniedException(__('You do not have permissions to delete this layout'));

        $layout->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $layout->layout)
        ]);
    }

    /**
     * Retires a layout
     * @param int $layoutId
     *
     * @SWG\Put(
     *  path="/layout/retire/{layoutId}",
     *  operationId="layoutRetire",
     *  tags={"layout"},
     *  summary="Retire Layout",
     *  description="Retire a Layout so that it isn't available to Schedule. Existing Layouts will still be played",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    function retire($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $layout->retired = 1;
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Retired %s'), $layout->layout)
        ]);
    }

    /**
     * Shows the Layout Grid
     *
     * @SWG\Get(
     *  path="/layout",
     *  operationId="layoutSearch",
     *  tags={"layout"},
     *  summary="Search Layouts",
     *  description="Search for Layouts viewable by this user",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="Filter by Layout Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="layout",
     *      in="formData",
     *      description="Filter by partial Layout name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userId",
     *      in="formData",
     *      description="Filter by user Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Filter by retired flag",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Filter by Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="formData",
     *      description="A flag indicating whether to treat the tags filter as an exact match",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerUserGroupId",
     *      in="formData",
     *      description="Filter by users in this UserGroupId",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="formData",
     *      description="Embed related data such as regions, playlists, tags, etc",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Layout")
     *      )
     *  )
     * )
     */
    function grid()
    {
        $this->getState()->template = 'grid';

        // Should we parse the description into markdown
        $showDescriptionId = $this->getSanitizer()->getInt('showDescriptionId');

        // We might need to embed some extra content into the response if the "Show Description"
        // is set to media listing
        if ($showDescriptionId === 3) {
            $embed = ['regions', 'playlists', 'widgets'];
        } else {
            // Embed?
            $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];
        }

        // Get all layouts
        $layouts = $this->layoutFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'layout' => $this->getSanitizer()->getString('layout'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'retired' => $this->getSanitizer()->getInt('retired'),
            'tags' => $this->getSanitizer()->getString('tags'),
            'exactTags' => $this->getSanitizer()->getCheckbox('exactTags'),
            'filterLayoutStatusId' => $this->getSanitizer()->getInt('layoutStatusId'),
            'layoutId' => $this->getSanitizer()->getInt('layoutId'),
            'ownerUserGroupId' => $this->getSanitizer()->getInt('ownerUserGroupId'),
            'mediaLike' => $this->getSanitizer()->getString('mediaLike')
        ]));

        foreach ($layouts as $layout) {
            /* @var \Xibo\Entity\Layout $layout */

            if (in_array('regions', $embed)) {
                $layout->load([
                    'loadPlaylists' => in_array('playlists', $embed),
                    'loadCampaigns' => in_array('campaigns', $embed),
                    'loadPermissions' => in_array('permissions', $embed),
                    'loadTags' => in_array('tags', $embed),
                    'loadWidgets' => in_array('widgets', $embed)
                ]);
            }

            // Populate the status message
            $layout->getStatusMessage();

            if ($this->isApi())
                continue;

            $layout->includeProperty('buttons');
            $layout->excludeProperty('regions');

            $layout->thumbnail = '';

            if ($layout->backgroundImageId != 0) {
                $download = $this->urlFor('layout.download.background', ['id' => $layout->layoutId]) . '?preview=1';
                $layout->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56" /></i></a>';
            }

            // Fix up the description
            $layout->descriptionFormatted = $layout->description;

            if ($layout->description != '') {
                if ($showDescriptionId == 1) {
                    // Parse down for description
                    $layout->descriptionFormatted = Parsedown::instance()->text($layout->description);
                } else if ($showDescriptionId == 2) {
                    $layout->descriptionFormatted = strtok($layout->description, "\n");
                }
            }

            if ($showDescriptionId === 3) {
                // Load in the entire object model - creating module objects so that we can get the name of each
                // widget and its items.
                foreach ($layout->regions as $region) {
                    foreach ($region->playlists as $playlist) {
                        /* @var Playlist $playlist */

                        foreach ($playlist->widgets as $widget) {
                            /* @var Widget $widget */
                            $widget->module = $this->moduleFactory->createWithWidget($widget, $region);
                        }
                    }
                }

                // provide our layout object to a template to render immediately
                $layout->descriptionFormatted = $this->renderTemplateToString('layout-page-grid-widgetlist', $layout);
            }

            switch ($layout->status) {

                case 1:
                    $layout->statusDescription = __('This Layout is ready to play');
                    break;

                case 2:
                    $layout->statusDescription = __('There are items on this Layout that can only be assessed by the Display');
                    break;

                case 3:
                    $layout->statusDescription = __('This Layout has not been built yet');
                    break;

                default:
                    $layout->statusDescription = __('This Layout is invalid and should not be scheduled');
            }

            // Add some buttons for this row
            if ($this->getUser()->checkEditable($layout)) {
                // Design Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_design',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('layout.designer', array('id' => $layout->layoutId)),
                    'text' => __('Design')
                );
            }

            // Preview
            $layout->buttons[] = array(
                'id' => 'layout_button_preview',
                'linkType' => '_blank',
                'external' => true,
                'url' => $this->urlFor('layout.preview', ['id' => $layout->layoutId]),
                'text' => __('Preview Layout')
            );

            $layout->buttons[] = ['divider' => true];

            // Schedule Now
            $layout->buttons[] = array(
                'id' => 'layout_button_schedulenow',
                'url' => $this->urlFor('schedule.now.form', ['id' => $layout->campaignId, 'from' => 'Campaign']),
                'text' => __('Schedule Now')
            );

            // Assign to Campaign
            if ($this->getUser()->routeViewable('/campaign')) {
                $layout->buttons[] = array(
                    'id' => 'layout_button_assignTo_campaign',
                    'url' => $this->urlFor('layout.assignTo.campaign.form', ['id' => $layout->layoutId]),
                    'text' => __('Assign to Campaign')
                );
            }

            $layout->buttons[] = ['divider' => true];

            // Only proceed if we have edit permissions
            if ($this->getUser()->checkEditable($layout)) {

                // Edit Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_edit',
                    'url' => $this->urlFor('layout.edit.form', ['id' => $layout->layoutId]),
                    'text' => __('Edit')
                );

                // Copy Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_copy',
                    'url' => $this->urlFor('layout.copy.form', ['id' => $layout->layoutId]),
                    'text' => __('Copy')
                );

                // Retire Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_retire',
                    'url' => $this->urlFor('layout.retire.form', ['id' => $layout->layoutId]),
                    'text' => __('Retire'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('layout.retire', ['id' => $layout->layoutId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'layout_button_retire'),
                        array('name' => 'text', 'value' => __('Retire')),
                        array('name' => 'rowtitle', 'value' => $layout->layout)
                    )
                );

                // Extra buttons if have delete permissions
                if ($this->getUser()->checkDeleteable($layout)) {
                    // Delete Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_delete',
                        'url' => $this->urlFor('layout.delete.form', ['id' => $layout->layoutId]),
                        'text' => __('Delete'),
                        'multi-select' => true,
                        'dataAttributes' => array(
                            array('name' => 'commit-url', 'value' => $this->urlFor('layout.delete', ['id' => $layout->layoutId])),
                            array('name' => 'commit-method', 'value' => 'delete'),
                            array('name' => 'id', 'value' => 'layout_button_delete'),
                            array('name' => 'text', 'value' => __('Delete')),
                            array('name' => 'rowtitle', 'value' => $layout->layout)
                        )
                    );
                }

                $layout->buttons[] = ['divider' => true];

                // Export Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_export',
                    'url' => $this->urlFor('layout.export.form', ['id' => $layout->layoutId]),
                    'text' => __('Export')
                );

                // Extra buttons if we have modify permissions
                if ($this->getUser()->checkPermissionsModifyable($layout)) {
                    // Permissions button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_permissions',
                        'url' => $this->urlFor('user.permissions.form', ['entity' => 'Campaign', 'id' => $layout->campaignId]),
                        'text' => __('Permissions')
                    );
                }
            }
        }

        // Store the table rows
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($layouts);
    }

    /**
     * Displays an Add/Edit form
     */
    function addForm()
    {
        $this->getState()->template = 'layout-form-add';
        $this->getState()->setData([
            'layouts' => $this->layoutFactory->query(['layout'], ['excludeTemplates' => 0, 'tags' => 'template']),
            'resolutions' => $this->resolutionFactory->query(['resolution']),
            'help' => $this->getHelp()->link('Layout', 'Add')
        ]);
    }

    /**
     * Edit form
     * @param int $layoutId
     */
    function editForm($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $resolution = $this->resolutionFactory->getByDimensions($layout->width, $layout->height);

        // If we have a background image, output it
        $backgroundId = $this->getSanitizer()->getInt('backgroundOverride', $layout->backgroundImageId);
        $backgrounds = ($backgroundId != null) ? [$this->mediaFactory->getById($backgroundId)] : [];

        $this->getState()->template = 'layout-form-edit';
        $this->getState()->setData([
            'layout' => $layout,
            'resolution' => $resolution,
            'resolutions' => $this->resolutionFactory->query(['resolution'], ['withCurrent' => $resolution->resolutionId]),
            'backgroundId' => $backgroundId,
            'backgrounds' => $backgrounds,
            'help' => $this->getHelp()->link('Layout', 'Edit')
        ]);
    }

    /**
     * Copy layout form
     * @param int $layoutId
     */
    public function copyForm($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        $this->getState()->template = 'layout-form-copy';
        $this->getState()->setData([
            'layout' => $layout,
            'help' => $this->getHelp()->link('Layout', 'Copy')
        ]);
    }

    /**
     * Copies a layout
     * @param int $layoutId
     *
     * @SWG\Post(
     *  path="/layout/copy/{layoutId}",
     *  operationId="layoutCopy",
     *  tags={"layout"},
     *  summary="Copy Layout",
     *  description="Copy a Layout, providing a new name if applicable",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID to Copy",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The name for the new Layout",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Description for the new Layout",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyMediaFiles",
     *      in="formData",
     *      description="Flag indicating whether to make new Copies of all Media Files assigned to the Layout being Copied",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function copy($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        // Load the layout for Copy
        $layout->load();
        $layout = clone $layout;

        $layout->layout = $this->getSanitizer()->getString('name');
        $layout->description = $this->getSanitizer()->getString('description');
        $layout->setOwner($this->getUser()->userId, true);

        // Copy the media on the layout and change the assignments.
        // https://github.com/xibosignage/xibo/issues/1283
        if ($this->getSanitizer()->getCheckbox('copyMediaFiles') == 1) {
            foreach ($layout->getWidgets() as $widget) {
                // Copy the media
                    if ( $widget->type === 'image' || $widget->type === 'video' || $widget->type === 'pdf' || $widget->type === 'powerpoint' || $widget->type === 'audio' ) {
                        $oldMedia = $this->mediaFactory->getById($widget->getPrimaryMediaId());
                        $media = clone $oldMedia;
                        $media->setOwner($this->getUser()->userId);
                        $media->save();

                        $widget->unassignMedia($oldMedia->mediaId);
                        $widget->assignMedia($media->mediaId);

                        // Update the widget option with the new ID
                        $widget->setOptionValue('uri', 'attrib', $media->storedAs);
                    }
            }

            // Also handle the background image, if there is one
            if ($layout->backgroundImageId != 0) {
                $oldMedia = $this->mediaFactory->getById($layout->backgroundImageId);
                $media = clone $oldMedia;
                $media->setOwner($this->getUser()->userId);
                $media->save();

                $layout->backgroundImageId = $media->mediaId;
            }
        }

        // Save the new layout
        $layout->save();

        // Permissions
        foreach ($this->permissionFactory->createForNewEntity($this->getUser(), 'Xibo\\Entity\\Campaign', $layout->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
            /* @var Permission $permission */
            $permission->save();
        }

        foreach ($layout->regions as $region) {
            /* @var Region $region */
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($region), $region->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            foreach ($region->playlists as $playlist) {
                /* @var Playlist $playlist */
                foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
                }

                foreach ($playlist->widgets as $widget) {
                    /* @var Widget $widget */
                    foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($widget), $widget->getId(), $this->getConfig()->GetSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                        /* @var Permission $permission */
                        $permission->save();
                    }
                }
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied as %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);
    }

    /**
     * @SWG\Post(
     *  path="/layout/{layoutId}/tag",
     *  operationId="layoutTag",
     *  tags={"layout"},
     *  summary="Tag Layout",
     *  description="Tag a Layout with one or more tags",
     * @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout Id to Tag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param $layoutId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function tag($layoutId)
    {
        // Edit permission
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $tags = $this->getSanitizer()->getStringArray('tag');

        if (count($tags) <= 0)
            throw new \InvalidArgumentException(__('No tags to assign'));

        foreach ($tags as $tag) {
            $layout->assignTag($this->tagFactory->tagFromString($tag));
        }

        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Tagged %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);
    }

    /**
     * @SWG\Post(
     *  path="/layout/{layoutId}/untag",
     *  operationId="layoutUntag",
     *  tags={"layout"},
     *  summary="Untag Layout",
     *  description="Untag a Layout with one or more tags",
     * @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout Id to Untag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param $layoutId
     * @throws \Xibo\Exception\NotFoundException
     * @throws InvalidArgumentException
     */
    public function untag($layoutId)
    {
        // Edit permission
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $tags = $this->getSanitizer()->getStringArray('tag');

        if (count($tags) <= 0)
            throw new InvalidArgumentException(__('No tags to unassign'), 'tag');

        foreach ($tags as $tag) {
            $layout->unassignTag($this->tagFactory->tagFromString($tag));
        }

        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Untagged %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);
    }

    /**
     * Layout Status
     * @param int $layoutId
     *
     * @SWG\Get(
     *  path="/layout/status/{layoutId}",
     *  operationId="layoutStatus",
     *  tags={"layout"},
     *  summary="Layout Status",
     *  description="Calculate the Layout status and return a Layout",
     * @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout Id to get the status",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     */
    public function status($layoutId)
    {
        // Get the layout
        /* @var \Xibo\Entity\Layout $layout */
        $layout = $this->layoutFactory->getById($layoutId);
        $layout->xlfToDisk();

        switch ($layout->status) {

            case 1:
                $status = __('This Layout is ready to play');
                break;

            case 2:
                $status = __('There are items on this Layout that can only be assessed by the client');
                break;

            case 3:
                $status = __('This Layout has not been built yet');
                break;

            default:
                $status = __('This Layout is invalid and should not be scheduled');
        }

        // We want a different return depending on whether we are arriving through the API or WEB routes
        if ($this->isApi()) {

            $this->getState()->hydrate([
                'httpStatus' => 200,
                'message' => $status,
                'id' => $layout->status,
                'data' => $layout
            ]);

        } else {

            $this->getState()->html = $status;
            $this->getState()->extra = [
                'status' => $layout->status,
                'duration' => $layout->duration,
                'statusMessage' => $layout->getStatusMessage()
            ];

            $this->getState()->success = true;
            $this->session->refreshExpiry = false;
        }
    }

    /**
     * Export Form
     * @param $layoutId
     */
    public function exportForm($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        // Render the form
        $this->getState()->template = 'layout-form-export';
        $this->getState()->setData([
            'layout' => $layout
        ]);
    }

    /**
     * @param int $layoutId
     * @throws XiboException
     */
    public function export($layoutId)
    {
        $this->setNoOutput(true);

        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        // Make sure our file name is reasonable
        $layoutName = preg_replace('/[^a-z0-9]+/', '-', strtolower($layout->layout));

        $fileName = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp/export_' . $layoutName . '.zip';
        $layout->toZip($this->dataSetFactory, $fileName, ['includeData' => ($this->getSanitizer()->getCheckbox('includeData')== 1)]);

        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
        header('Content-Length: ' . filesize($fileName));

        // Send via Apache X-Sendfile header?
        if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $fileName");
            $this->getApp()->halt(200);
        }
        // Send via Nginx X-Accel-Redirect?
        if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/temp/" . basename($fileName));
            $this->getApp()->halt(200);
        }

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        readfile($fileName);
    }

    /**
     * TODO: Not sure how to document this.
     * SWG\Post(
     *  path="/layout/import",
     *  operationId="layoutImport",
     *  tags={"layout"},
     *  summary="Import Layout",
     *  description="Upload and Import a Layout",
     *  consumes="multipart/form-data",
     *  SWG\Parameter(
     *      name="file",
     *      in="formData",
     *      description="The file",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function import()
    {
        $this->getLog()->debug('Import Layout');

        $libraryFolder = $this->getConfig()->GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists($this->getConfig()->GetSetting('LIBRARY_LOCATION'));

        // Make sure there is room in the library
        /** @var Library $libraryController */
        $libraryController = $this->getApp()->container->get('\Xibo\Controller\Library')->setApp($this->getApp());
        $libraryLimit = $this->getConfig()->GetSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'libraryController' => $libraryController,
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('layout.import'),
            'upload_url' => $this->urlFor('layout.import'),
            'image_versions' => array(),
            'accept_file_types' => '/\.zip$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $libraryController->libraryUsage() > $libraryLimit)
        );

        $this->setNoOutput(true);

        // Hand off to the Upload Handler provided by jquery-file-upload
        new LayoutUploadHandler($options);
    }

    /**
     * Upgrade Form
     * @param int $layoutId
     */
    public function upgradeForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $this->getState()->template = 'layout-form-upgrade';
        $this->getState()->setData([
            'layout' => $layout,
            'resolutions' => $this->resolutionFactory->query(null, ['enabled' => 1])
        ]);
    }

    /**
     * Upgrade Layout
     * @param int $layoutId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function upgrade($layoutId)
    {
        $layout = $this->layoutFactory->loadById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Resolution
        $resolution = $this->resolutionFactory->getById($this->getSanitizer()->getInt('resolutionId'));
        $scaleContent = ($this->getSanitizer()->getCheckbox('scaleContent') == 1);

        // Upgrade the Layout
        $ratio = min($resolution->width / $layout->width, $resolution->height / $layout->height);

        // Set the widget and height on layouts/regions
        $layout->width = $layout->width * $ratio;
        $layout->height = $layout->height * $ratio;

        foreach ($layout->regions as $region) {
            /* @var \Xibo\Entity\Region $region */
            $region->width = $region->width * $ratio;
            $region->height = $region->height * $ratio;
            $region->top = $region->top * $ratio;
            $region->left = $region->left * $ratio;

            if ($scaleContent) {
                // We need to get every widget that might have some date/time related stuff on it
                // pull out the widget content
                // run a regex over it to try and adjust its size
                foreach ($region->playlists as $playlist) {
                    $saveRequired = false;
                    foreach ($playlist->widgets as $widget) {
                        foreach ($widget->widgetOptions as $widgetOption) {

                            if ($widgetOption->option == 'text' ||
                                $widgetOption->option == 'styleSheet' ||
                                $widgetOption->option == 'css' ||
                                $widgetOption->option == 'embedHtml' ||
                                $widgetOption->option == 'embedScript' ||
                                $widgetOption->option == 'embedStyle'
                            ) {

                                // Replace widths
                                $widgetOption->value = preg_replace_callback(
                                    '/width:(.*?)/',
                                    function ($matches) use ($ratio) {
                                        return "width:" . $matches[1] * $ratio;
                                    }, $widgetOption->value);

                                // Replace heights
                                $widgetOption->value = preg_replace_callback(
                                    '/height:(.*?)/',
                                    function ($matches) use ($ratio) {
                                        return "height:" . $matches[1] * $ratio;
                                    }, $widgetOption->value);

                                // Replace fonts
                                $widgetOption->value = preg_replace_callback(
                                    '/font-size:(.*?)px;/',
                                    function ($matches) use ($ratio) {
                                        return "font-size:" . $matches[1] * $ratio . "px;";
                                    }, $widgetOption->value);

                                $saveRequired = true;
                            }
                        }
                    }

                    if ($saveRequired)
                        $playlist->save();
                }
            }
        }

        $layout->schemaVersion = $this->getConfig()->Version('XlfVersion');
        $layout->save(['validate' => false, 'notify' => $scaleContent]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Upgraded %s'), $layout->layout)
        ]);
    }



    /**
     * Gets a file from the library
     * @param int $layoutId
     * @throws NotFoundException
     * @throws AccessDeniedException
     */
    public function downloadBackground($layoutId)
    {
        $this->getLog()->debug('Layout Download background request for layoutId ' . $layoutId);

        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        if ($layout->backgroundImageId == null)
            throw new NotFoundException();

        // This media may not be viewable, but we won't check it because the user has permission to view the
        // layout that it is assigned to.
        $media = $this->mediaFactory->getById($layout->backgroundImageId);

        // Make a media module
        $widget = $this->moduleFactory->createWithMedia($media);

        if ($widget->getModule()->regionSpecific == 1)
            throw new NotFoundException('Cannot download non-region specific module');

        $widget->getResource(0);

        $this->setNoOutput(true);
    }

    /**
     * Assign to Campaign Form
     * @param $layoutId
     */
    public function assignToCampaignForm($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        // Render the form
        $this->getState()->template = 'layout-form-assign-to-campaign';
        $this->getState()->setData([
            'layout' => $layout,
            'campaigns' => $this->campaignFactory->query()
        ]);
    }
}
