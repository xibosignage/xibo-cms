<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

use Parsedown;
use Xibo\Entity\Permission;
use Xibo\Entity\Playlist;
use Xibo\Entity\Region;
use Xibo\Entity\Session;
use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\GeneralException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayGroupFactory;
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
use Xibo\Widget\ModuleWidget;

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

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

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
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session, $userFactory, $resolutionFactory, $layoutFactory, $moduleFactory, $permissionFactory, $userGroupFactory, $tagFactory, $mediaFactory, $dataSetFactory, $campaignFactory, $displayGroupFactory)
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
        $this->displayGroupFactory = $displayGroupFactory;
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
            'groups' => $this->userGroupFactory->query(),
            'displayGroups' => $this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1])
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

        // Get the parent layout if it's editable
        if ($layout->isEditable()) {
            // Get the Layout using the Draft ID
            $layout = $this->layoutFactory->getByParentId($layoutId);
        }

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
            'users' => $this->userFactory->query(),
            'modules' => array_map(function($element) use ($moduleFactory) { 
                    $module = $moduleFactory->createForInstall($element->class);
                    $module->setModule($element);
                    return $module;
                }, $moduleFactory->getAssignableModules())
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
     *  @SWG\Parameter(
     *      name="returnDraft",
     *      in="formData",
     *      description="Should we return the Draft Layout or the Published Layout on Success?",
     *      type="boolean",
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
     *
     * @throws XiboException
     */
    function add()
    {
        $name = $this->getSanitizer()->getString('name');
        $description = $this->getSanitizer()->getString('description');
        $templateId = $this->getSanitizer()->getInt('layoutId');
        $resolutionId = $this->getSanitizer()->getInt('resolutionId');
        $enableStat = $this->getSanitizer()->getCheckbox('enableStat');
        $autoApplyTransitions = $this->getSanitizer()->getCheckbox('autoApplyTransitions');

        $template = null;

        if ($templateId != 0) {
            // Load the template
            $template = $this->layoutFactory->loadById($templateId);
            $template->load();

            // Empty all of the ID's
            $layout = clone $template;

            // Overwrite our new properties
            $layout->layout = $name;
            $layout->description = $description;

            // Create some tags (overwriting the old ones)
            $layout->tags = $this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags'));

            // Set the owner
            $layout->setOwner($this->getUser()->userId);

            // Ensure we have Playlists for each region
            foreach ($layout->regions as $region) {
                // Set the ownership of this region to the user creating from template
                $region->setOwner($this->getUser()->userId, true);
            }
        }
        else {
            $layout = $this->layoutFactory->createFromResolution($resolutionId, $this->getUser()->userId, $name, $description, $this->getSanitizer()->getString('tags'));
        }

        // Set layout enableStat flag
        $layout->enableStat = $enableStat;

        // Set auto apply transitions flag
        $layout->autoApplyTransitions = $autoApplyTransitions;

        // Save
        $layout->save();

        // Permissions
        foreach ($this->permissionFactory->createForNewEntity($this->getUser(), 'Xibo\\Entity\\Campaign', $layout->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
            /* @var Permission $permission */
            $permission->save();
        }

        foreach ($layout->regions as $region) {
            /* @var Region $region */

            if ($templateId != null && $template !== null) {
                // Match our original region id to the id in the parent layout
                $original = $template->getRegion($region->getOriginalValue('regionId'));

                // Make sure Playlist closure table from the published one are copied over
                $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);
            }

            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($region), $region->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            $playlist = $region->getPlaylist();

            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            foreach ($playlist->widgets as $widget) {
                /* @var Widget $widget */
                foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($widget), $widget->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
                }
            }
        }

        $this->getLog()->debug('Layout Added');

        // automatically checkout the new layout for edit
        $this->checkout($layout->layoutId);

        if ($this->getSanitizer()->getCheckbox('returnDraft')) {
            // This is a workaround really - the call to checkout above ought to be separated into a public/private
            // method, with the private method returning the draft layout
            // is it stands the checkout method will have already set the draft layout id to the state data property
            // we just need to set the message.
            $this->getState()->hydrate([
                'httpStatus' => 201,
                'message' => sprintf(__('Added %s'), $layout->layout),
            ]);
        } else {
            // Make sure we adjust the published status
            // again, this is a workaround because checkout doesn't return a Layout object
            $layout->publishedStatus = __('Draft');
            $layout->publishedStatusId = 2;

            // Return
            $this->getState()->hydrate([
                'httpStatus' => 201,
                'message' => sprintf(__('Added %s'), $layout->layout),
                'id' => $layout->layoutId,
                'data' => $layout
            ]);
        }
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
     *      name="enableStat",
     *      in="formData",
     *      description="Flag indicating whether the Layout stat is enabled",
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
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws XiboException
     */
    function edit($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);
        $isTemplate = false;

        // check if we're dealing with the template
        $currentTags = explode(',', $layout->tags);
        foreach ($currentTags as $tag) {
            if ($tag === 'template') {
                $isTemplate = true;
            }
        }

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot edit Layout properties on a Draft'), 'layoutId');
        }

        $layout->layout = $this->getSanitizer()->getString('name');
        $layout->description = $this->getSanitizer()->getString('description');
        $layout->replaceTags($this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags')));
        $layout->retired = $this->getSanitizer()->getCheckbox('retired');
        $layout->enableStat = $this->getSanitizer()->getCheckbox('enableStat');

        $tags = $this->getSanitizer()->getString('tags');
        $tagsArray = explode(',', $tags);

        if (!$isTemplate) {
            foreach ($tagsArray as $tag) {
                if ($tag === 'template') {
                    throw new InvalidArgumentException('Cannot assign a Template tag to a Layout, to create a template use the Save Template button instead.', 'tags');
                }
            }
        }

        // Save
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => true,
            'setBuildRequired' => false,
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
     * Edit Layout Background
     * @param int $layoutId
     *
     * @SWG\Put(
     *  path="/layout/background/{layoutId}",
     *  operationId="layoutEditBackground",
     *  summary="Edit Layout Background",
     *  description="Edit a Layout Background",
     *  tags={"layout"},
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="integer",
     *      in="path",
     *      required=true
     *  ),
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
    function editBackground($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Check that this Layout is a Draft
        if (!$layout->isChild())
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');

        $layout->backgroundColor = $this->getSanitizer()->getString('backgroundColor');
        $layout->backgroundImageId = $this->getSanitizer()->getInt('backgroundImageId');
        $layout->backgroundzIndex = $this->getSanitizer()->getInt('backgroundzIndex');
        $layout->autoApplyTransitions = $this->getSanitizer()->getCheckbox('autoApplyTransitions');

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

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException(__('Cannot delete Layout from its Draft, delete the parent'), 'layoutId');

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

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException(__('Cannot modify Layout from its Draft'), 'layoutId');

        // Make sure we aren't the global default
        if ($layout->layoutId == $this->getConfig()->getSetting('DEFAULT_LAYOUT')) {
            throw new InvalidArgumentException(__('This Layout is used as the global default and cannot be retired'),
                'layoutId');
        }

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
     * Unretire Layout Form
     * @param int $layoutId
     * @throws XiboException
     */
    public function unretireForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $data = [
            'layout' => $layout,
            'help' => $this->getHelp()->link('Layout', 'Retire')
        ];

        $this->getState()->template = 'layout-form-unretire';
        $this->getState()->setData($data);
    }

    /**
     * Unretires a layout
     * @param int $layoutId
     *
     * @SWG\Put(
     *  path="/layout/unretire/{layoutId}",
     *  operationId="layoutUnretire",
     *  tags={"layout"},
     *  summary="Unretire Layout",
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
    function unretire($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException(__('Cannot modify Layout from its Draft'), 'layoutId');

        $layout->retired = 0;
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false,
            'notify' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Unretired %s'), $layout->layout)
        ]);
    }

    /**
     * Set Enable Stats Collection of a layout
     * @param int $layoutId
     *
     * @SWG\Put(
     *  path="/layout/setenablestat/{layoutId}",
     *  operationId="layoutSetEnableStat",
     *  tags={"layout"},
     *  summary="Enable Stats Collection",
     *  description="Set Enable Stats Collection? to use for the collection of Proof of Play statistics for a Layout.",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="Flag indicating whether the Layout stat is enabled",
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
    function setEnableStat($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException(__('Cannot modify Layout from its Draft'), 'layoutId');

        $enableStat = $this->getSanitizer()->getCheckbox('enableStat');

        $layout->enableStat = $enableStat;
        $layout->save(['saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('For Layout %s Enable Stats Collection is set to %s'), $layout->layout, ($layout->enableStat == 1) ? __('On') : __('Off'))
        ]);
    }

    /**
     * Set Enable Stat Form
     * @param int $layoutId
     * @throws XiboException
     */
    public function setEnableStatForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $data = [
            'layout' => $layout,
            'help' => $this->getHelp()->link('Layout', 'EnableStat')
        ];

        $this->getState()->template = 'layout-form-setenablestat';
        $this->getState()->setData($data);
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
     *      in="query",
     *      description="Filter by Layout Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="parentId",
     *      in="query",
     *      description="Filter by parent Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showDrafts",
     *      in="query",
     *      description="Flag indicating whether to show drafts",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="layout",
     *      in="query",
     *      description="Filter by partial Layout name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userId",
     *      in="query",
     *      description="Filter by user Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="query",
     *      description="Filter by retired flag",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="tags",
     *      in="query",
     *      description="Filter by Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="query",
     *      description="A flag indicating whether to treat the tags filter as an exact match",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerUserGroupId",
     *      in="query",
     *      description="Filter by users in this UserGroupId",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="publishedStatusId",
     *      in="query",
     *      description="Filter by published status id, 1 - Published, 2 - Draft",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data such as regions, playlists, widgets, tags, campaigns, permissions",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="query",
     *      description="Get all Layouts for a given campaignId",
     *      type="integer",
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
     *
     * @throws NotFoundException
     * @throws XiboException
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
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'retired' => $this->getSanitizer()->getInt('retired'),
            'tags' => $this->getSanitizer()->getString('tags'),
            'exactTags' => $this->getSanitizer()->getCheckbox('exactTags'),
            'filterLayoutStatusId' => $this->getSanitizer()->getInt('layoutStatusId'),
            'layoutId' => $this->getSanitizer()->getInt('layoutId'),
            'parentId' => $this->getSanitizer()->getInt('parentId'),
            'showDrafts' => $this->getSanitizer()->getInt('showDrafts'),
            'ownerUserGroupId' => $this->getSanitizer()->getInt('ownerUserGroupId'),
            'mediaLike' => $this->getSanitizer()->getString('mediaLike'),
            'publishedStatusId' => $this->getSanitizer()->getInt('publishedStatusId'),
            'activeDisplayGroupId' => $this->getSanitizer()->getInt('activeDisplayGroupId'),
            'campaignId' => $this->getSanitizer()->getInt('campaignId'),
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

            // Annotate each Widget with its validity, tags and permissions
            if (in_array('widget_validity', $embed) || in_array('tags', $embed) || in_array('permissions', $embed)) { 
                foreach ($layout->getWidgets() as $widget) {
                    /* @var Widget $widget */
                    $module = $this->moduleFactory->createWithWidget($widget);

                    $widget->name = $module->getName();

                    // Augment with tags
                    $widget->tags = $module->getMediaTags();

                    // Add widget module type name
                    $widget->moduleName = $module->getModuleName();

                    // apply default transitions to a dynamic parameters on widget object.
                    if ($layout->autoApplyTransitions == 1) {
                        $widgetTransIn = $widget->getOptionValue('transIn', $this->getConfig()->getSetting('DEFAULT_TRANSITION_IN'));
                        $widgetTransOut = $widget->getOptionValue('transOut', $this->getConfig()->getSetting('DEFAULT_TRANSITION_OUT'));
                        $widgetTransInDuration = $widget->getOptionValue('transInDuration', $this->getConfig()->getSetting('DEFAULT_TRANSITION_DURATION'));
                        $widgetTransOutDuration = $widget->getOptionValue('transOutDuration', $this->getConfig()->getSetting('DEFAULT_TRANSITION_DURATION'));
                    } else {
                        $widgetTransIn = $widget->getOptionValue('transIn', null);
                        $widgetTransOut = $widget->getOptionValue('transOut', null);
                        $widgetTransInDuration = $widget->getOptionValue('transInDuration', null);
                        $widgetTransOutDuration = $widget->getOptionValue('transOutDuration', null);
                    }

                    $widget->transitionIn = $widgetTransIn;
                    $widget->transitionOut = $widgetTransOut;
                    $widget->transitionDurationIn = $widgetTransInDuration;
                    $widget->transitionDurationOut = $widgetTransOutDuration;

                    if (in_array('permissions', $embed)) {
                        // Augment with editable flag
                        $widget->isEditable = $this->getUser()->checkEditable($widget);

                        // Augment with deletable flag
                        $widget->isDeletable = $this->getUser()->checkDeleteable($widget);

                        // Augment with permissions flag
                        $widget->isPermissionsModifiable = $this->getUser()->checkPermissionsModifyable($widget);
                    }

                    if (in_array('widget_validity', $embed)) {
                        try {
                            $widget->isValid = (int)$module->isValid();
                        } catch (XiboException $xiboException) {
                            $widget->isValid = 0;
                        }
                    }
                }

                // Augment regions with permissions
                foreach ($layout->regions as $region) {
                    if (in_array('permissions', $embed)) {
                        // Augment with editable flag
                        $region->isEditable = $this->getUser()->checkEditable($region);

                         // Augment with deletable flag
                        $region->isDeletable = $this->getUser()->checkDeleteable($region);

                        // Augment with permissions flag
                        $region->isPermissionsModifiable = $this->getUser()->checkPermissionsModifyable($region);
                    }
                }

            }

            if ($this->isApi())
                continue;

            $layout->includeProperty('buttons');
            //$layout->excludeProperty('regions');

            $layout->thumbnail = '';

            if ($layout->backgroundImageId != 0) {
                $download = $this->urlFor('layout.download.background', ['id' => $layout->layoutId]) . '?preview=1' . '&backgroundImageId=' . $layout->backgroundImageId;
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
                    foreach ($region->getPlaylist()->widgets as $widget) {
                        /* @var Widget $widget */
                        $widget->module = $this->moduleFactory->createWithWidget($widget, $region);
                    }
                }

                // provide our layout object to a template to render immediately
                $layout->descriptionFormatted = $this->renderTemplateToString('layout-page-grid-widgetlist', $layout);
            }

            switch ($layout->status) {

                case ModuleWidget::$STATUS_VALID:
                    $layout->statusDescription = __('This Layout is ready to play');
                    break;

                case ModuleWidget::$STATUS_PLAYER:
                    $layout->statusDescription = __('There are items on this Layout that can only be assessed by the Display');
                    break;

                case 3:
                    $layout->statusDescription = __('This Layout has not been built yet');
                    break;

                default:
                    $layout->statusDescription = __('This Layout is invalid and should not be scheduled');
            }

            switch ($layout->enableStat) {

                case 1:
                    $layout->enableStatDescription = __('This Layout has enable stat collection set to ON');
                    break;

                default:
                    $layout->enableStatDescription = __('This Layout has enable stat collection set to OFF');
            }

            // Published status, draft with set publishedDate
            $layout->publishedStatusFuture = __('Publishing %s');
            $layout->publishedStatusFailed = __('Publish failed ');

            // Check if user has view permissions to the schedule now page - for layout designer to show/hide Schedule Now button
            $layout->scheduleNowPermission = $this->getUser()->routeViewable('/schedulenow/form/now/:from/:id');

            // Add some buttons for this row
            if ($this->getUser()->checkEditable($layout)) {
                // Design Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_design',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('layout.designer', array('id' => $layout->layoutId)),
                    'text' => __('Design')
                );

                // Should we show a publish/discard button?
                if ($layout->isEditable()) {

                    $layout->buttons[] = ['divider' => true];

                    $layout->buttons[] = array(
                        'id' => 'layout_button_publish',
                        'url' => $this->urlFor('layout.publish.form', ['id' => $layout->layoutId]),
                        'text' => __('Publish')
                    );

                    $layout->buttons[] = array(
                        'id' => 'layout_button_discard',
                        'url' => $this->urlFor('layout.discard.form', ['id' => $layout->layoutId]),
                        'text' => __('Discard')
                    );

                    $layout->buttons[] = ['divider' => true];
                } else {
                    $layout->buttons[] = ['divider' => true];

                    // Checkout Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_checkout',
                        'url' => $this->urlFor('layout.checkout.form', ['id' => $layout->layoutId]),
                        'text' => __('Checkout')
                    );

                    $layout->buttons[] = ['divider' => true];
                }
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
            if ($this->getUser()->routeViewable('/schedulenow/form/now/:from/:id') === true) {
                $layout->buttons[] = array(
                    'id' => 'layout_button_schedulenow',
                    'url' => $this->urlFor('schedulenow.now.form', ['id' => $layout->campaignId, 'from' => 'Layout']),
                    'text' => __('Schedule Now')
                );
            }
            // Assign to Campaign
            if ($this->getUser()->routeViewable('/campaign')) {
                $layout->buttons[] = array(
                    'id' => 'layout_button_assignTo_campaign',
                    'url' => $this->urlFor('layout.assignTo.campaign.form', ['id' => $layout->layoutId]),
                    'text' => __('Assign to Campaign')
                );
            }

            $layout->buttons[] = ['divider' => true];

            if ($this->getUser()->routeViewable('/playlist/view')) {
                $layout->buttons[] = [
                    'id' => 'layout_button_playlist_jump',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('playlist.view') .'?layoutId=' . $layout->layoutId,
                    'text' => __('Jump to Playlists included on this Layout')
                ];
            }

            if ($this->getUser()->routeViewable('/campaign/view')) {
                $layout->buttons[] = [
                    'id' => 'layout_button_campaign_jump',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('campaign.view') .'?layoutId=' . $layout->layoutId,
                    'text' => __('Jump to Campaigns containing this Layout')
                ];
            }

            if ($this->getUser()->routeViewable('/library/view')) {
                $layout->buttons[] = [
                    'id' => 'layout_button_media_jump',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('library.view') .'?layoutId=' . $layout->layoutId,
                    'text' => __('Jump to Media included on this Layout')
                ];
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
                if ($layout->retired == 0) {
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
                } else {
                    $layout->buttons[] = array(
                        'id' => 'layout_button_unretire',
                        'url' => $this->urlFor('layout.unretire.form', ['id' => $layout->layoutId]),
                        'text' => __('Unretire'),
                    );
                }

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

                // Set Enable Stat
                $layout->buttons[] = array(
                    'id' => 'layout_button_setenablestat',
                    'url' => $this->urlFor('layout.setenablestat.form', ['id' => $layout->layoutId]),
                    'text' => __('Enable stats collection?'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('layout.setenablestat', ['id' => $layout->layoutId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'layout_button_setenablestat'),
                        array('name' => 'text', 'value' => __('Enable stats collection?')),
                        array('name' => 'rowtitle', 'value' => $layout->layout),
                        ['name' => 'form-callback', 'value' => 'setEnableStatMultiSelectFormOpen']
                    )
                );

                $layout->buttons[] = ['divider' => true];

                if ($this->getUser()->routeViewable('template') && !$layout->isEditable()) {
                    // Save template button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_save_template',
                        'url' => $this->urlFor('template.from.layout.form', ['id' => $layout->layoutId]),
                        'text' => __('Save Template')
                    );
                }

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
     * @throws XiboException
     */
    function editForm($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        $tags = '';

        $arrayOfTags = array_filter(explode(',', $layout->tags));
        $arrayOfTagValues = array_filter(explode(',', $layout->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL')) {
                $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                $tags .= ',';
            } else {
                $tags .= $arrayOfTags[$i] . ',';
            }
        }

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        $this->getState()->template = 'layout-form-edit';
        $this->getState()->setData([
            'layout' => $layout,
            'tags' => $tags,
            'help' => $this->getHelp()->link('Layout', 'Edit')
        ]);
    }

    /**
     * Edit form
     * @param int $layoutId
     * @throws XiboException
     */
    function editBackgroundForm($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();
            
        // Edits always happen on Drafts, get the draft Layout using the Parent Layout ID
        if ($layout->schemaVersion < 2) {
            $resolution = $this->resolutionFactory->getByDesignerDimensions($layout->width, $layout->height);
        } else {
            $resolution = $this->resolutionFactory->getByDimensions($layout->width, $layout->height);
        }

        // If we have a background image, output it
        $backgroundId = $this->getSanitizer()->getInt('backgroundOverride', $layout->backgroundImageId);
        $backgrounds = ($backgroundId != null) ? [$this->mediaFactory->getById($backgroundId)] : [];

        $this->getState()->template = 'layout-form-background';
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
     *
     * @throws XiboException
     */
    public function copy($layoutId)
    {
        // Get the layout
        $originalLayout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($originalLayout)) {
            throw new AccessDeniedException();
        }
        // Make sure we're not a draft
        if ($originalLayout->isChild()) {
            throw new InvalidArgumentException(__('Cannot copy a Draft Layout'), 'layoutId');
        }

        // Load the layout for Copy
        $originalLayout->load(['loadTags' => false]);

        // Clone
        $layout = clone $originalLayout;
        $tags = $this->tagFactory->getTagsWithValues($layout);

        $this->getLog()->debug('Tag values from original layout: ' . $tags);

        $layout->layout = $this->getSanitizer()->getString('name');
        $layout->description = $this->getSanitizer()->getString('description');
        $layout->replaceTags($this->tagFactory->tagsFromString($tags));
        $layout->setOwner($this->getUser()->userId, true);

        // Copy the media on the layout and change the assignments.
        // https://github.com/xibosignage/xibo/issues/1283
        if ($this->getSanitizer()->getCheckbox('copyMediaFiles') == 1) {
            // track which Media Id we already copied
            $copiedMediaIds = [];
            foreach ($layout->getWidgets() as $widget) {
                // Copy the media
                    if ( $widget->type === 'image' || $widget->type === 'video' || $widget->type === 'pdf' || $widget->type === 'powerpoint' || $widget->type === 'audio' ) {
                        $oldMedia = $this->mediaFactory->getById($widget->getPrimaryMediaId());

                        // check if we already cloned this media, if not, do it and add it the array
                        if (!array_key_exists($oldMedia->mediaId, $copiedMediaIds)) {
                            $media = clone $oldMedia;
                            $media->setOwner($this->getUser()->userId);
                            $media->save();
                            $copiedMediaIds[$oldMedia->mediaId] = $media->mediaId;
                        } else {
                            // if we already cloned that media, look it up and assign to Widget.
                            $mediaId = $copiedMediaIds[$oldMedia->mediaId];
                            $media = $this->mediaFactory->getById($mediaId);
                        }

                        $widget->unassignMedia($oldMedia->mediaId);
                        $widget->assignMedia($media->mediaId);

                        // Update the widget option with the new ID
                        $widget->setOptionValue('uri', 'attrib', $media->storedAs);
                    }
            }

            // Also handle the background image, if there is one
            if ($layout->backgroundImageId != 0) {
                $oldMedia = $this->mediaFactory->getById($layout->backgroundImageId);
                // check if we already cloned this media, if not, do it and add it the array
                if (!array_key_exists($oldMedia->mediaId, $copiedMediaIds)) {
                    $media = clone $oldMedia;
                    $media->setOwner($this->getUser()->userId);
                    $media->save();
                    $copiedMediaIds[$oldMedia->mediaId] = $media->mediaId;
                } else {
                    // if we already cloned that media, look it up and assign to Layout backgroundImage.
                    $mediaId = $copiedMediaIds[$oldMedia->mediaId];
                    $media = $this->mediaFactory->getById($mediaId);
                }

                $layout->backgroundImageId = $media->mediaId;
            }
        }

        // Save the new layout
        $layout->save();

        // Sub-Playlist
        foreach ($layout->regions as $region) {
            // Match our original region id to the id in the parent layout
            $original = $originalLayout->getRegion($region->getOriginalValue('regionId'));

            // Make sure Playlist closure table from the published one are copied over
            $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);
        }

        // Permissions
        foreach ($this->permissionFactory->createForNewEntity($this->getUser(), 'Xibo\\Entity\\Campaign', $layout->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
            /* @var Permission $permission */
            $permission->save();
        }

        foreach ($layout->regions as $region) {
            /* @var Region $region */
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($region), $region->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            $playlist = $region->getPlaylist();
            /* @var Playlist $playlist */
            foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($playlist), $playlist->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                /* @var Permission $permission */
                $permission->save();
            }

            foreach ($playlist->widgets as $widget) {
                /* @var Widget $widget */
                foreach ($this->permissionFactory->createForNewEntity($this->getUser(), get_class($widget), $widget->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
                    /* @var Permission $permission */
                    $permission->save();
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
     * @throws XiboException
     */
    public function tag($layoutId)
    {
        // Edit permission
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException('Cannot manage tags on a Draft Layout', 'layoutId');

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
     * @throws XiboException
     */
    public function untag($layoutId)
    {
        // Edit permission
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException('Cannot manage tags on a Draft Layout', 'layoutId');

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
     * @throws \Xibo\Exception\InvalidArgumentException
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\XiboException
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
        $layout = $this->layoutFactory->getById($layoutId);

        $layout->xlfToDisk();

        switch ($layout->status) {

            case ModuleWidget::$STATUS_VALID:
                $status = __('This Layout is ready to play');
                break;

            case ModuleWidget::$STATUS_PLAYER:
                $status = __('There are items on this Layout that can only be assessed by the Display');
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

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException('Cannot manage tags on a Draft Layout', 'layoutId');

        // Render the form
        $this->getState()->template = 'layout-form-export';
        $this->getState()->setData([
            'layout' => $layout,
            'saveAs' => 'export_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($layout->layout))
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

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException('Cannot export a Draft Layout', 'layoutId');

        // Save As?
        $saveAs = $this->getSanitizer()->getString('saveAs');

        // Make sure our file name is reasonable
        if (empty($saveAs)) {
            $saveAs = 'export_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($layout->layout));
        } else {
            $saveAs = preg_replace('/[^a-z0-9]+/', '-', strtolower($saveAs));
        }

        $fileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $saveAs . '.zip';
        $layout->toZip($this->dataSetFactory, $fileName, ['includeData' => ($this->getSanitizer()->getCheckbox('includeData') == 1), 'user' => $this->getUser()]);

        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($fileName) . "\"");
        header('Content-Length: ' . filesize($fileName));

        // Send via Apache X-Sendfile header?
        if ($this->getConfig()->getSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $fileName");
            $this->getApp()->halt(200);
        }
        // Send via Nginx X-Accel-Redirect?
        if ($this->getConfig()->getSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/temp/" . basename($fileName));
            $this->getApp()->halt(200);
        }

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($fileName);
        exit;
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
     *
     * @throws XiboException
     * @throws \Exception
     */
    public function import()
    {
        $this->getLog()->debug('Import Layout');

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        // Make sure there is room in the library
        /** @var Library $libraryController */
        $libraryController = $this->getApp()->container->get('\Xibo\Controller\Library')->setApp($this->getApp());
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

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

    /**
     * Checkout Layout Form
     * @param int $layoutId
     * @throws XiboException
     */
    public function checkoutForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $data = ['layout' => $layout];

        $this->getState()->template = 'layout-form-checkout';
        $this->getState()->setData($data);
    }

    /**
     * Checkout Layout
     *
     * @SWG\Put(
     *  path="/layout/checkout/{layoutId}",
     *  operationId="layoutCheckout",
     *  tags={"layout"},
     *  summary="Checkout Layout",
     *  description="Checkout a Layout so that it can be edited. The original Layout will still be played",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param int $layoutId
     * @throws XiboException
     */
    public function checkout($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        // Can't checkout a Layout which can already be edited
        if ($layout->isEditable())
            throw new InvalidArgumentException(__('Layout is already checked out'), 'statusId');

        // Load the Layout
        $layout->load();

        // Make a skeleton copy of the Layout
        $draft = clone $layout;
        $draft->parentId = $layout->layoutId;
        $draft->campaignId = $layout->campaignId;
        $draft->publishedStatusId = 2; // Draft
        $draft->publishedStatus = __('Draft');
        $draft->autoApplyTransitions = $layout->autoApplyTransitions;

        // Do not copy any of the tags, these will belong on the parent and are not editable from the draft.
        $draft->tags = [];

        // Save without validation or notification.
        $draft->save([
            'validate' => false,
            'notify' => false
        ]);

        // Update the original
        $layout->publishedStatusId = 2; // Draft
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false,
            'validate' => false,
            'notify' => false
        ]);

        // Permissions && Sub-Playlists
        // Layout level permissions are managed on the Campaign entity, so we do not need to worry about that
        // Regions/Widgets need to copy down our layout permissions
        foreach ($draft->regions as $region) {
            // Match our original region id to the id in the parent layout
            $original = $layout->getRegion($region->getOriginalValue('regionId'));

            // Make sure Playlist closure table from the published one are copied over
            $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);

            // Copy over original permissions
            foreach ($original->permissions as $permission) {
                $new = clone $permission;
                $new->objectId = $region->regionId;
                $new->save();
            }

            // Playlist
            foreach ($original->getPlaylist()->permissions as $permission) {
                $new = clone $permission;
                $new->objectId = $region->getPlaylist()->playlistId;
                $new->save();
            }

            // Widgets
            foreach ($region->getPlaylist()->widgets as $widget) {
                $originalWidget = $original->getPlaylist()->getWidget($widget->getOriginalValue('widgetId'));

                // Copy over original permissions
                foreach ($originalWidget->permissions as $permission) {
                    $new = clone $permission;
                    $new->objectId = $widget->widgetId;
                    $new->save();
                }
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Checked out %s'), $layout->layout),
            'id' => $draft->layoutId,
            'data' => $draft
        ]);
    }

    /**
     * Publish Layout Form
     * @param int $layoutId
     * @throws XiboException
     */
    public function publishForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $data = ['layout' => $layout];

        $this->getState()->template = 'layout-form-publish';
        $this->getState()->setData($data);
    }

    /**
     * Publish Layout
     *
     * @SWG\Put(
     *  path="/layout/publish/{layoutId}",
     *  operationId="layoutPublish",
     *  tags={"layout"},
     *  summary="Publish Layout",
     *  description="Publish a Layout, discarding the original",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="publishNow",
     *      in="formData",
     *      description="Flag, indicating whether to publish layout now",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="publishDate",
     *      in="formData",
     *      description="The date/time at which layout should be published",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param $layoutId
     * @throws XiboException
     */
    public function publish($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);
        $publishDate = $this->getSanitizer()->getDate('publishDate');
        $publishNow = $this->getSanitizer()->getCheckbox('publishNow');

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        // if we have publish date update it in database
        if (isset($publishDate) && !$publishNow) {
            $layout->setPublishedDate($publishDate);
        }

        // We want to take the draft layout, and update the campaign links to point to the draft, then remove the
        // parent.
        if ($publishNow || (isset($publishDate) && $publishDate->format('U') <  $this->getDate()->getLocalDate(null, 'U')) ) {
            $draft = $this->layoutFactory->getByParentId($layoutId);
            $draft->publishDraft();
            $draft->load();

            // We also build the XLF at this point, and if we have a problem we prevent publishing and raise as an
            // error message
            $draft->xlfToDisk(['notify' => true, 'exceptionOnError' => true, 'exceptionOnEmptyRegion' => false, 'publishing' => true]);

            // Return
            $this->getState()->hydrate([
                'httpStatus' => 200,
                'message' => sprintf(__('Published %s'), $draft->layout),
                'data' => $draft
            ]);
        } else {
            // Return
            $this->getState()->hydrate([
                'httpStatus' => 200,
                'message' => sprintf(__('Layout will be published on %s'), $publishDate),
                'data' => $layout
            ]);
        }
    }

    /**
     * Discard Layout Form
     * @param int $layoutId
     * @throws XiboException
     */
    public function discardForm($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        $data = ['layout' => $layout];

        $this->getState()->template = 'layout-form-discard';
        $this->getState()->setData($data);
    }

    /**
     * Discard Layout
     *
     * @SWG\Put(
     *  path="/layout/discard/{layoutId}",
     *  operationId="layoutDiscard",
     *  tags={"layout"},
     *  summary="Discard Layout",
     *  description="Discard a Layout restoring the original",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param $layoutId
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws XiboException
     */
    public function discard($layoutId)
    {
        $layout = $this->layoutFactory->getById($layoutId);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable())
            throw new InvalidArgumentException(__('Layout is not checked out'), 'statusId');

        $draft = $this->layoutFactory->getByParentId($layoutId);
        $draft->discardDraft();

        // The parent is no longer a draft
        $layout->publishedStatusId = 1;

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Discarded %s'), $draft->layout),
            'data' => $layout
        ]);
    }
}
