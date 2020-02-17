<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2019 Xibo Signage Ltd
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

use Xibo\Entity\Display;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\XMR\ChangeLayoutAction;
use Xibo\XMR\CollectNowAction;
use Xibo\XMR\CommandAction;
use Xibo\XMR\OverlayLayoutAction;
use Xibo\XMR\RevertToSchedule;

/**
 * Class DisplayGroup
 * @package Xibo\Controller
 */
class DisplayGroup extends Base
{
    /**
     * @var PlayerActionServiceInterface
     */
    private $playerAction;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var CampaignFactory
     */
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
     * @param PlayerActionServiceInterface $playerAction
     * @param DisplayFactory $displayFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param ModuleFactory $moduleFactory
     * @param MediaFactory $mediaFactory
     * @param CommandFactory $commandFactory
     * @param ScheduleFactory $scheduleFactory
     * @param TagFactory $tagFactory
     * @param CampaignFactory $campaignFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $playerAction, $displayFactory, $displayGroupFactory, $layoutFactory, $moduleFactory, $mediaFactory, $commandFactory, $scheduleFactory, $tagFactory, $campaignFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->playerAction = $playerAction;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->layoutFactory = $layoutFactory;
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
        $this->commandFactory = $commandFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->tagFactory = $tagFactory;
        $this->campaignFactory = $campaignFactory;
    }

    /**
     * Display Group Page Render
     */
    public function displayPage()
    {
        $this->getState()->template = 'displaygroup-page';
    }

    /**
     * @SWG\Get(
     *  path="/displaygroup",
     *  summary="Get Display Groups",
     *  tags={"displayGroup"},
     *  operationId="displayGroupSearch",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="query",
     *      description="Filter by DisplayGroup Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="query",
     *      description="Filter by DisplayGroup Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="query",
     *      description="Filter by DisplayGroups containing a specific display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="nestedDisplayId",
     *      in="query",
     *      description="Filter by DisplayGroups containing a specific display in there nesting",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteria",
     *      in="query",
     *      description="Filter by DisplayGroups containing a specific dynamic criteria",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDisplaySpecific",
     *      in="query",
     *      description="Filter by whether the Display Group belongs to a Display or is user created",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="forSchedule",
     *      in="query",
     *      description="Should the list be refined for only those groups the User can Schedule against?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="a successful response",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/DisplayGroup")
     *      ),
     *      @SWG\Header(
     *          header="X-Total-Count",
     *          description="The total number of records",
     *          type="integer"
     *      )
     *  )
     * )
     */
    public function grid()
    {
        $filter = [
            'displayGroupId' => $this->getSanitizer()->getInt('displayGroupId'),
            'displayGroup' => $this->getSanitizer()->getString('displayGroup'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'displayId' => $this->getSanitizer()->getInt('displayId'),
            'nestedDisplayId' => $this->getSanitizer()->getInt('nestedDisplayId'),
            'dynamicCriteria' => $this->getSanitizer()->getString('dynamicCriteria'),
            'tags' => $this->getSanitizer()->getString('tags'),
            'exactTags' => $this->getSanitizer()->getCheckbox('exactTags'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'isDisplaySpecific' => $this->getSanitizer()->getInt('isDisplaySpecific'),
            'displayGroupIdMembers' => $this->getSanitizer()->getInt('displayGroupIdMembers'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'isDynamic' => $this->getSanitizer()->getInt('isDynamic')
        ];

        $scheduleWithView = ($this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 1);

        $displayGroups = $this->displayGroupFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($displayGroups as $group) {
            /* @var \Xibo\Entity\DisplayGroup $group */

            // Check to see if we're getting this data for a Schedule attempt, or for a general list
            if ($this->getSanitizer()->getCheckbox('forSchedule') == 1) {
                // Can't schedule with view, but no edit permissions
                if (!$scheduleWithView && !$this->getUser()->checkEditable($group))
                    continue;
            }

            if ($this->isApi())
                continue;

            $group->includeProperty('buttons');

            if ($this->getUser()->checkEditable($group)) {
                // Show the edit button, members button

                if ($group->isDynamic == 0) {
                    // Group Members
                    $group->buttons[] = array(
                        'id' => 'displaygroup_button_group_members',
                        'url' => $this->urlFor('displayGroup.members.form', ['id' => $group->displayGroupId]),
                        'text' => __('Members')
                    );

                    $group->buttons[] = ['divider' => true];
                }

                // Edit
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_edit',
                    'url' => $this->urlFor('displayGroup.edit.form', ['id' => $group->displayGroupId]),
                    'text' => __('Edit')
                );

                $group->buttons[] = array(
                    'id' => 'displaygroup_button_copy',
                    'url' => $this->urlFor('displayGroup.copy.form', ['id' => $group->displayGroupId]),
                    'text' => __('Copy')
                );
            }

            if ($this->getUser()->checkDeleteable($group)) {
                // Show the delete button
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_delete',
                    'url' => $this->urlFor('displayGroup.delete.form', ['id' => $group->displayGroupId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('displayGroup.delete', ['id' => $group->displayGroupId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'displaygroup_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $group->displayGroup),
                        ['name' => 'form-callback', 'value' => 'setDeleteMultiSelectFormOpen'],
                        ['name' => 'form-confirm', 'value' => true]
                    )
                );
            }

            $group->buttons[] = ['divider' => true];

            if ($this->getUser()->checkEditable($group)) {
                // File Associations
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => $this->urlFor('displayGroup.media.form', ['id' => $group->displayGroupId]),
                    'text' => __('Assign Files')
                );

                // Layout Assignments
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_layout_associations',
                    'url' => $this->urlFor('displayGroup.layout.form', ['id' => $group->displayGroupId]),
                    'text' => __('Assign Layouts')
                );
            }

            if ($this->getUser()->checkPermissionsModifyable($group)) {
                // Show the modify permissions button
                $group->buttons[] = array(
                    'id' => 'displaygroup_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'DisplayGroup', 'id' => $group->displayGroupId]),
                    'text' => __('Permissions')
                );
            }

            if ($this->getUser()->checkEditable($group)) {
                $group->buttons[] = ['divider' => true];

                $group->buttons[] = array(
                    'id' => 'displaygroup_button_command',
                    'url' => $this->urlFor('displayGroup.command.form', ['id' => $group->displayGroupId]),
                    'text' => __('Send Command')
                );

                $group->buttons[] = array(
                    'id' => 'displaygroup_button_collectNow',
                    'url' => $this->urlFor('displayGroup.collectNow.form', ['id' => $group->displayGroupId]),
                    'text' => __('Collect Now')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayGroupFactory->countLast();
        $this->getState()->setData($displayGroups);
    }

    /**
     * Shows an add form for a display group
     */
    public function addForm()
    {
        $this->getState()->template = 'displaygroup-form-add';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('DisplayGroup', 'Add')
        ]);
    }

    /**
     * Shows an edit form for a display group
     * @param int $displayGroupId
     */
    public function editForm($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $tags = '';

        $arrayOfTags = array_filter(explode(',', $displayGroup->tags));
        $arrayOfTagValues = array_filter(explode(',', $displayGroup->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                $tags .= ',';
            } else {
                $tags .= $arrayOfTags[$i] . ',';
            }
        }

        $this->getState()->template = 'displaygroup-form-edit';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'help' => $this->getHelp()->link('DisplayGroup', 'Edit'),
            'tags' => $tags
        ]);
    }

    /**
     * Shows the Delete Group Form
     * @param int $displayGroupId
     */
    function deleteForm($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkDeleteable($displayGroup))
            throw new AccessDeniedException();

        $this->getState()->template = 'displaygroup-form-delete';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'help' => $this->getHelp()->link('DisplayGroup', 'Delete')
        ]);
    }

    /**
     * Display Group Members form
     * @param int $displayGroupId
     */
    public function membersForm($displayGroupId)
    {
        
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Displays in Group
        $displaysAssigned = $this->displayFactory->getByDisplayGroupId($displayGroup->displayGroupId);
        // Get all the DisplayGroups assigned to this Group directly
        $groupsAssigned = $this->displayGroupFactory->getByParentId($displayGroup->displayGroupId);

        $this->getState()->template = 'displaygroup-form-members';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'extra' => [
                'displaysAssigned' => $displaysAssigned,
                'displayGroupsAssigned' => $groupsAssigned
            ],
            'tree' => $this->displayGroupFactory->getRelationShipTree($displayGroupId),
            'help' => $this->getHelp()->link('DisplayGroup', 'Members')
        ]);
    }

    /**
     * Adds a Display Group
     * @SWG\Post(
     *  path="/displaygroup",
     *  operationId="displayGroupAdd",
     *  tags={"displayGroup"},
     *  summary="Add a Display Group",
     *  description="Add a new Display Group to the CMS",
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="formData",
     *      description="The Display Group Name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Display Group Description",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="A comma separated list of tags for this item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDynamic",
     *      in="formData",
     *      description="Flag indicating whether this DisplayGroup is Dynamic",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteria",
     *      in="formData",
     *      description="The filter criteria for this dynamic group. A comma separated set of regular expressions to apply",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayGroup"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new DisplayGroup",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add()
    {
        $displayGroup = $this->displayGroupFactory->createEmpty();
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);

        $displayGroup->displayGroup = $this->getSanitizer()->getString('displayGroup');
        $displayGroup->description = $this->getSanitizer()->getString('description');
        $displayGroup->tags = $this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags'));
        $displayGroup->isDynamic = $this->getSanitizer()->getCheckbox('isDynamic');
        $displayGroup->dynamicCriteria = $this->getSanitizer()->getString('dynamicCriteria');
        $displayGroup->dynamicCriteriaTags = $this->getSanitizer()->getString('dynamicCriteriaTags');

        $displayGroup->userId = $this->getUser()->userId;
        $displayGroup->save();

        // Return
        $this->getState()->hydrate([
            'httpState' => 201,
            'message' => sprintf(__('Added %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId,
            'data' => $displayGroup
        ]);
    }

    /**
     * Edits a Display Group
     * @param int $displayGroupId
     *
     * @throws XiboException
     * @throws \Xibo\Exception\NotFoundException
     *
     * @SWG\Put(
     *  path="/displaygroup/{displayGroupId}",
     *  operationId="displayGroupEdit",
     *  tags={"displayGroup"},
     *  summary="Edit a Display Group",
     *  description="Edit an existing Display Group identified by its Id",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The displayGroupId to edit.",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="formData",
     *      description="The Display Group Name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Display Group Description",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="A comma separated list of tags for this item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDynamic",
     *      in="formData",
     *      description="Flag indicating whether this DisplayGroup is Dynamic",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteria",
     *      in="formData",
     *      description="The filter criteria for this dynamic group. A command separated set of regular expressions to apply",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayGroup")
     *  )
     * )
     */
    public function edit($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
        $preEditIsDynamic = $displayGroup->getOriginalValue('isDynamic');

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->displayGroup = $this->getSanitizer()->getString('displayGroup');
        $displayGroup->description = $this->getSanitizer()->getString('description');
        $displayGroup->replaceTags($this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags')));
        $displayGroup->isDynamic = $this->getSanitizer()->getCheckbox('isDynamic');
        $displayGroup->dynamicCriteria = ($displayGroup->isDynamic == 1) ? $this->getSanitizer()->getString('dynamicCriteria') : null;
        $displayGroup->dynamicCriteriaTags = ($displayGroup->isDynamic == 1) ? $this->getSanitizer()->getString('dynamicCriteriaTags') : null;

        // if we have changed the type from dynamic to non-dynamic or other way around, clear display/dg members
        if ($preEditIsDynamic != $displayGroup->isDynamic) {
            $this->getLog()->debug('Display Group Id ' . $displayGroup->displayGroupId . ' switched is dynamic from ' . $preEditIsDynamic . ' To ' . $displayGroup->isDynamic . ' Clearing members for this Display Group.');
            // get an array of assigned displays
            $membersDisplays = $this->displayFactory->getByDisplayGroupId($displayGroupId);

            // get an array of assigned display groups
            $membersDisplayGroups = $this->displayGroupFactory->getByParentId($displayGroupId);

            // unassign Displays
            foreach ($membersDisplays as $display) {
                $displayGroup->unassignDisplay($display);
            }

            // unassign Display Groups
            foreach ($membersDisplayGroups as $dg) {
                $displayGroup->unassignDisplayGroup($dg);
            }
        }

        $displayGroup->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId,
            'data' => $displayGroup
        ]);
    }

    /**
     * Deletes a Group
     * @param int $displayGroupId
     *
     * @throws \Xibo\Exception\NotFoundException
     *
     * @SWG\Delete(
     *  path="/displaygroup/{displayGroupId}",
     *  operationId="displayGroupDelete",
     *  tags={"displayGroup"},
     *  summary="Delete a Display Group",
     *  description="Delete an existing Display Group identified by its Id",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The displayGroupId to delete",
     *      required=true
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function delete($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);

        if (!$this->getUser()->checkDeleteable($displayGroup))
            throw new AccessDeniedException();

        $displayGroup->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $displayGroup->displayGroup)
        ]);
    }

    /**
     * Sets the Members of a group
     * @param int $displayGroupId
     * @throws InvalidArgumentException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/display/assign",
     *  operationId="displayGroupDisplayAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more Displays to a Display Group",
     *  description="Adds the provided Displays to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      type="array",
     *      in="formData",
     *      description="The Display Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignDisplayId",
     *      in="formData",
     *      description="An optional array of Display IDs to unassign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function assignDisplay($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if ($displayGroup->isDisplaySpecific == 1)
            throw new InvalidArgumentException(__('This is a Display specific Display Group and its assignments cannot be modified.'), 'displayGroupId');

        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        if ($displayGroup->isDynamic == 1)
            throw new \InvalidArgumentException(__('Displays cannot be manually assigned to a Dynamic Group'));

        $modifiedDisplays = [];

        $displays = $this->getSanitizer()->getIntArray('displayId');

        foreach ($displays as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if (!$this->getUser()->checkViewable($this->displayGroupFactory->getById($display->displayGroupId)))
                throw new AccessDeniedException(__('Access Denied to Display'));

            $displayGroup->assignDisplay($display);

            // Store so that we can flag as incomplete
            if (!in_array($display, $modifiedDisplays))
                $modifiedDisplays[] = $display;
        }

        // Have we been provided with unassign id's as well?
        $displays = $this->getSanitizer()->getIntArray('unassignDisplayId');

        foreach ($displays as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if (!$this->getUser()->checkViewable($this->displayGroupFactory->getById($display->displayGroupId)))
                throw new AccessDeniedException(__('Access Denied to Display'));

            $displayGroup->unassignDisplay($display);

            // Store so that we can flag as incomplete
            if (!in_array($display, $modifiedDisplays))
                $modifiedDisplays[] = $display;
        }

        // Save the result
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Save the displays themselves
        foreach ($modifiedDisplays as $display) {
            /** @var Display $display */
            $display->notify();
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Displays assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Unassign displays from a Display Group
     * @param int $displayGroupId
     * @throws InvalidArgumentException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/display/unassign",
     *  operationId="displayGroupDisplayUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassigns one or more Displays to a Display Group",
     *  description="Removes the provided Displays from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      type="array",
     *      in="formData",
     *      description="The Display Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function unassignDisplay($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if ($displayGroup->isDisplaySpecific == 1)
            throw new InvalidArgumentException(__('This is a Display specific Display Group and its assignments cannot be modified.'), 'displayGroupId');

        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        if ($displayGroup->isDynamic == 1)
            throw new \InvalidArgumentException(__('Displays cannot be manually unassigned to a Dynamic Group'));

        $displays = $this->getSanitizer()->getIntArray('displayId');

        foreach ($displays as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if (!$this->getUser()->checkViewable($this->displayGroupFactory->getById($display->displayGroupId)))
                throw new AccessDeniedException(__('Access Denied to Display'));

            $this->getLog()->debug('Unassigning ' . $display->display);

            $displayGroup->unassignDisplay($display);
        }

        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Displays unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Sets the Members of a group
     * @param int $displayGroupId
     * @throws InvalidArgumentException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/displayGroup/assign",
     *  operationId="displayGroupDisplayGroupAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more DisplayGroups to a Display Group",
     *  description="Adds the provided DisplayGroups to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="array",
     *      in="formData",
     *      description="The displayGroup Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignDisplayGroupId",
     *      in="formData",
     *      description="An optional array of displayGroup IDs to unassign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function assignDisplayGroup($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if ($displayGroup->isDisplaySpecific == 1)
            throw new InvalidArgumentException(__('This is a Display specific Display Group and its assignments cannot be modified.'), 'displayGroupId');

        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        if ($displayGroup->isDynamic == 1)
            throw new \InvalidArgumentException(__('DisplayGroups cannot be manually assigned to a Dynamic Group'));

        $displayGroups = $this->getSanitizer()->getIntArray('displayGroupId');

        foreach ($displayGroups as $assignDisplayGroupId) {
            $displayGroupAssign = $this->displayGroupFactory->getById($assignDisplayGroupId);

            if (!$this->getUser()->checkViewable($displayGroupAssign))
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));

            $displayGroup->assignDisplayGroup($displayGroupAssign);
        }

        // Have we been provided with unassign id's as well?
        $displayGroups = $this->getSanitizer()->getIntArray('unassignDisplayGroupId');

        foreach ($displayGroups as $assignDisplayGroupId) {
            $displayGroupUnassign = $this->displayGroupFactory->getById($assignDisplayGroupId);

            if (!$this->getUser()->checkViewable($displayGroupUnassign))
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));

            $displayGroup->unassignDisplayGroup($displayGroupUnassign);
        }

        // Save the result
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('DisplayGroups assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Unassign DisplayGroups from a Display Group
     * @param int $displayGroupId
     * @throws InvalidArgumentException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/displayGroup/unassign",
     *  operationId="displayGroupDisplayGroupUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassigns one or more DisplayGroups to a Display Group",
     *  description="Removes the provided DisplayGroups from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="array",
     *      in="formData",
     *      description="The DisplayGroup Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function unassignDisplayGroup($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if ($displayGroup->isDisplaySpecific == 1)
            throw new InvalidArgumentException(__('This is a Display specific Display Group and its assignments cannot be modified.'), 'displayGroupId');

        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        if ($displayGroup->isDynamic == 1)
            throw new \InvalidArgumentException(__('DisplayGroups cannot be manually unassigned to a Dynamic Group'));

        $displayGroups = $this->getSanitizer()->getIntArray('displayGroupId');

        foreach ($displayGroups as $assignDisplayGroupId) {
            $displayGroup->unassignDisplayGroup($this->displayGroupFactory->getById($assignDisplayGroupId));
        }

        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('DisplayGroups unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Media Form (media linked to displays)
     * @param int $displayGroupId
     * @throws XiboException
     */
    public function mediaForm($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->load();

        $this->getState()->template = 'displaygroup-form-media';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'modules' => $this->moduleFactory->query(null, ['regionSpecific' => 0]),
            'media' => $this->mediaFactory->getByDisplayGroupId($displayGroup->displayGroupId),
            'help' => $this->getHelp()->link('DisplayGroup', 'FileAssociations')
        ]);
    }

    /**
     * Assign Media
     * @param int $displayGroupId
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/media/assign",
     *  operationId="displayGroupMediaAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more Media items to a Display Group",
     *  description="Adds the provided Media to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      type="array",
     *      in="formData",
     *      description="The Media Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignMediaId",
     *      type="array",
     *      in="formData",
     *      description="Optional array of Media Id to unassign",
     *      required=false,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function assignMedia($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->load();

        $mediaIds = $this->getSanitizer()->getIntArray('mediaId');

        // Loop through all the media
        foreach ($mediaIds as $mediaId) {

            $media = $this->mediaFactory->getById($mediaId);

            if (!$this->getUser()->checkViewable($media))
                throw new AccessDeniedException(__('You have selected media that you no longer have permission to use. Please reload the form.'));

            $displayGroup->assignMedia($media);
        }

        // Check for unassign
        foreach ($this->getSanitizer()->getIntArray('unassignMediaId') as $mediaId) {
            // Get the media record
            $media = $this->mediaFactory->getById($mediaId);

            if (!$this->getUser()->checkViewable($media))
                throw new AccessDeniedException(__('You have selected media that you no longer have permission to use. Please reload the form.'));

            $displayGroup->unassignMedia($media);
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Files assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Unassign Media
     * @param int $displayGroupId
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/media/unassign",
     *  operationId="displayGroupMediaUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassign one or more Media items from a Display Group",
     *  description="Removes the provided from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      type="array",
     *      in="formData",
     *      description="The Media Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function unassignMedia($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->load();

        $mediaIds = $this->getSanitizer()->getIntArray('mediaId');

        // Loop through all the media
        foreach ($mediaIds as $mediaId) {

            $displayGroup->unassignMedia($this->mediaFactory->getById($mediaId));
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Files unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Layouts Form (layouts linked to displays)
     * @param int $displayGroupId
     *
     * @throws XiboException
     */
    public function LayoutsForm($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->load();

        $this->getState()->template = 'displaygroup-form-layouts';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'modules' => $this->moduleFactory->query(null, ['regionSpecific' => 0]),
            'layouts' => $this->layoutFactory->getByDisplayGroupId($displayGroup->displayGroupId),
            'help' => $this->getHelp()->link('DisplayGroup', 'FileAssociations')
        ]);
    }

    /**
     * Assign Layouts
     * @param int $displayGroupId
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/layout/assign",
     *  operationId="displayGroupLayoutsAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more Layouts items to a Display Group",
     *  description="Adds the provided Layouts to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="array",
     *      in="formData",
     *      description="The Layouts Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignLayoutId",
     *      type="array",
     *      in="formData",
     *      description="Optional array of Layouts Id to unassign",
     *      required=false,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function assignLayouts($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->load();

        $layoutIds = $this->getSanitizer()->getIntArray('layoutId');

        // Loop through all the media
        foreach ($layoutIds as $layoutId) {

            $layout = $this->layoutFactory->getById($layoutId);

            if (!$this->getUser()->checkViewable($layout))
                throw new AccessDeniedException(__('You have selected a layout that you no longer have permission to use. Please reload the form.'));

            $displayGroup->assignLayout($layout);
        }

        // Check for unassign
        foreach ($this->getSanitizer()->getIntArray('unassignLayoutId') as $layoutId) {
            // Get the layout record
            $layout = $this->layoutFactory->getById($layoutId);

            if (!$this->getUser()->checkViewable($layout))
                throw new AccessDeniedException(__('You have selected a layout that you no longer have permission to use. Please reload the form.'));

            $displayGroup->unassignLayout($layout);
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Layouts assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Unassign Layout
     * @param int $displayGroupId
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/layout/unassign",
     *  operationId="displayGroupLayoutUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassign one or more Layout items from a Display Group",
     *  description="Removes the provided from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="array",
     *      in="formData",
     *      description="The Layout Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function unassignLayouts($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        // Load the groups details
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->load();

        $layoutIds = $this->getSanitizer()->getIntArray('layoutId');

        // Loop through all the media
        foreach ($layoutIds as $layoutId) {
            $this->getLog()->debug('Unassign layoutId ' . $layoutId . ' from ' . $displayGroupId);
            $displayGroup->unassignLayout($this->layoutFactory->getById($layoutId));
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Layouts unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * @param int $displayGroupId
     */
    public function collectNowForm($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $this->getState()->template = 'displaygroup-form-collect-now';
        $this->getState()->setData([
            'displayGroup' => $displayGroup
        ]);
    }

    /**
     * Cause the player to collect now
     * @param int $displayGroupId
     * @throws ConfigurationException when the message cannot be sent
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/collectNow",
     *  operationId="displayGroupActionCollectNow",
     *  tags={"displayGroup"},
     *  summary="Action: Collect Now",
     *  description="Send the collect now action to this DisplayGroup",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function collectNow($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($displayGroupId), new CollectNowAction());

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Cause the player to collect now
     * @param int $displayGroupId
     * @throws ConfigurationException when the message cannot be sent
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/clearStatsAndLogs",
     *  operationId="displayGroupActionClearStatsAndLogs",
     *  tags={"displayGroup"},
     *  summary="Action: Clear Stats and Logs",
     *  description="Clear all stats and logs on this Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function clearStatsAndLogs($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($displayGroupId), new CollectNowAction());

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Change to a new Layout
     * @param $displayGroupId
     * @throws ConfigurationException
     * @throws \Xibo\Exception\NotFoundException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/changeLayout",
     *  operationId="displayGroupActionChangeLayout",
     *  tags={"displayGroup"},
     *  summary="Action: Change Layout",
     *  description="Send the change layout action to this DisplayGroup, you can pass layoutId or layout specific campaignId",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The Display Group Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="The Layout Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The layout specific Campaign Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The duration in seconds for this Layout change to remain in effect",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="downloadRequired",
     *      in="formData",
     *      description="Flag indicating whether the player should perform a collect before playing the Layout",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="changeMode",
     *      in="formData",
     *      description="Whether to queue or replace with this action",
     *      type="string",
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
    public function changeLayout($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Get the layoutId or campaignId
        $layoutId = $this->getSanitizer()->getInt('layoutId');
        $campaignId = $this->getSanitizer()->getInt('campaignId');
        $downloadRequired = ($this->getSanitizer()->getCheckbox('downloadRequired') == 1);

        if ($layoutId == 0 && $campaignId == 0) {
            throw new InvalidArgumentException(__('Please provide a Layout ID or Campaign ID'), 'layoutId');
        }

        // Check that this user has permissions to see this layout
        if ($layoutId != 0 && $campaignId == 0) {
            $layout = $this->layoutFactory->getById($layoutId);
        } elseif ($layoutId == 0 && $campaignId != 0) {
            $campaign = $this->campaignFactory->getById($campaignId);

            if ($campaign->isLayoutSpecific == 0) {
                throw new NotFoundException(__('Please provide Layout specific campaign ID'));
            }

            $layouts = $this->layoutFactory->getByCampaignId($campaignId);

            if (count($layouts) <= 0) {
                throw new NotFoundException(__('Cannot find layout by campaignId'));
            }

            $layout = $layouts[0];
        } else {
            throw new InvalidArgumentException(__('Please provide Layout id or Campaign id'), 'layoutId');
        }

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        // Check to see if this layout is assigned to this display group.
        if (count($this->layoutFactory->query(null, ['disableUserCheck' => 1, 'layoutId' => $layout->layoutId, 'displayGroupId' => $displayGroupId])) <= 0) {
            // Assign
            $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
            $displayGroup->load();
            $displayGroup->assignLayout($layout);

            // Don't collect now, this player action will cause a download.
            // notify will still occur if the layout isn't already assigned (which is shouldn't be)
            $displayGroup->setCollectRequired(false);

            $displayGroup->save(['validate' => false, 'saveTags' => false]);

            // Convert into a download required
            $downloadRequired = true;
        } else {
            // The layout may not be built at this point
            if ($downloadRequired) {
                // in this case we should build it and notify before we send the action
                // notify should NOT collect now, as we will do that during our own action.
                $layout->xlfToDisk(['notify' => true, 'collectNow' => false]);
            }
        }

        // Create and send the player action
        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($displayGroupId), (new ChangeLayoutAction())->setLayoutDetails(
            $layout->layoutId,
            $this->getSanitizer()->getInt('duration'),
            $downloadRequired,
            $this->getSanitizer()->getString('changeMode', 'queue')
        ));

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Cause the player to revert to its scheduled content
     * @param int $displayGroupId
     * @throws ConfigurationException when the message cannot be sent
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/revertToSchedule",
     *  operationId="displayGroupActionRevertToSchedule",
     *  tags={"displayGroup"},
     *  summary="Action: Revert to Schedule",
     *  description="Send the revert to schedule action to this DisplayGroup",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function revertToSchedule($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($displayGroupId), new RevertToSchedule());

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Add an Overlay Layout
     * @param $displayGroupId
     * @throws ConfigurationException
     * @throws \Xibo\Exception\NotFoundException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/overlayLayout",
     *  operationId="displayGroupActionOverlayLayout",
     *  tags={"displayGroup"},
     *  summary="Action: Overlay Layout",
     *  description="Send the overlay layout action to this DisplayGroup, you can pass layoutId or layout specific campaignId",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The Display Group Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="The Layout Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The layout specific Campaign Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The duration in seconds for this Overlay to remain in effect",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="downloadRequired",
     *      in="formData",
     *      description="Flag indicating whether the player should perform a collect before adding the Overlay",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function overlayLayout($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Get the layoutId
        $layoutId = $this->getSanitizer()->getInt('layoutId');
        $campaignId = $this->getSanitizer()->getInt('campaignId');
        $downloadRequired = ($this->getSanitizer()->getCheckbox('downloadRequired') == 1);

        if ($layoutId == 0 && $campaignId == 0) {
            throw new \InvalidArgumentException(__('Please provide a Layout ID or Campaign ID'));
        }

        // Check that this user has permissions to see this layout
        if ($layoutId != 0 && $campaignId == 0) {
            $layout = $this->layoutFactory->getById($layoutId);
        } elseif ($layoutId == 0 && $campaignId != 0) {
            $campaign = $this->campaignFactory->getById($campaignId);

            if ($campaign->isLayoutSpecific == 0) {
                throw new NotFoundException(__('Please provide Layout specific campaign ID'));
            }

            $layouts = $this->layoutFactory->getByCampaignId($campaignId);

            if (count($layouts) <= 0) {
                throw new NotFoundException(__('Cannot find layout by campaignId'));
            }

            $layout = $layouts[0];
        } else {
            throw new InvalidArgumentException(__('Please provide Layout id or Campaign id'), 'layoutId');
        }

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        // Check to see if this layout is assigned to this display group.
        if (count($this->layoutFactory->query(null, ['disableUserCheck' => 1, 'layoutId' => $layout->layoutId, 'displayGroupId' => $displayGroupId])) <= 0) {
            // Assign
            $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
            $displayGroup->load();
            $displayGroup->assignLayout($layout);
            // Don't notify, this player action will cause a download.
            $displayGroup->setCollectRequired(false);
            $displayGroup->save(['validate' => false, 'saveTags' => false]);

            // Convert into a download required
            $downloadRequired = true;
        } else {
            // The layout may not be built at this point
            if ($downloadRequired) {
                // in this case we should build it and notify before we send the action
                // notify should NOT collect now, as we will do that during our own action.
                $layout->xlfToDisk(['notify' => true, 'collectNow' => false]);
            }
        }

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($displayGroupId), (new OverlayLayoutAction())->setLayoutDetails(
            $layout->layoutId,
            $this->getSanitizer()->getInt('duration'),
            $downloadRequired
        ));

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * Command Form
     * @param int $displayGroupId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function commandForm($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $this->getState()->template = 'displaygroup-form-command';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'commands' => $this->commandFactory->query()
        ]);
    }

    /**
     * @param $displayGroupId
     * @throws ConfigurationException
     * @throws XiboException
     * @throws \Xibo\Exception\NotFoundException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/command",
     *  operationId="displayGroupActionCommand",
     *  tags={"displayGroup"},
     *  summary="Send Command",
     *  description="Send a predefined command to this Group of Displays",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="formData",
     *      description="The Command Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function command($displayGroupId)
    {
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup))
            throw new AccessDeniedException();

        $command = $this->commandFactory->getById($this->getSanitizer()->getInt('commandId'));
        $displays = $this->displayFactory->getByDisplayGroupId($displayGroupId);

        $this->playerAction->sendAction($displays, (new CommandAction())->setCommandCode($command->code));

        // Update the flag
        foreach ($displays as $display) {
            /* @var \Xibo\Entity\Display $display */
            $display->lastCommandSuccess = 0;
            $display->save(['validate' => false, 'audit' => false]);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);
    }

    /**
     * @param $displayGroupId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function copyForm($displayGroupId)
    {
        // Create a form out of the config object.
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayGroup->userId)
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));

        $this->getState()->template = 'displaygroup-form-copy';
        $this->getState()->setData([
            'displayGroup' => $displayGroup
        ]);
    }

    /**
     * Copy Display Group
     * @param int $displayGroupId
     * @throws \Xibo\Exception\XiboException
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/copy",
     *  operationId="displayGroupCopy",
     *  tags={"displayGroup"},
     *  summary="Copy Display Group",
     *  description="Copy an existing Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The Display Group ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="formData",
     *      description="The name for the copy",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The description for the copy",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyMembers",
     *      in="formData",
     *      description="Flag indicating whether to copy all display and display group members",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyAssignments",
     *      in="formData",
     *      description="Flag indicating whether to copy all layout and media assignments",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyTags",
     *      in="formData",
     *      description="Flag indicating whether to copy all tags",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayGroup"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function copy($displayGroupId)
    {
        // get display group object
        $displayGroup = $this->displayGroupFactory->getById($displayGroupId);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // get an array of assigned displays
        $membersDisplays = $this->displayFactory->getByDisplayGroupId($displayGroupId);

        // get an array of assigned display groups
        $membersDisplayGroups = $this->displayGroupFactory->getByParentId($displayGroupId);

        // get an array of assigned layouts
        $assignedLayouts = $this->layoutFactory->getByDisplayGroupId($displayGroupId);

        // get an array of assigned media files
        $assignedFiles = $this->mediaFactory->getByDisplayGroupId($displayGroupId);

        $copyMembers = $this->getSanitizer()->getCheckbox('copyMembers', 0);
        $copyTags = $this->getSanitizer()->getCheckbox('copyTags', 0);
        $copyAssignments = $this->getSanitizer()->getCheckbox('copyAssignments', 0);



        $new = clone $displayGroup;

        // handle display group members
        if ($copyMembers && !$displayGroup->isDynamic) {

            //copy display members
            foreach ($membersDisplays as $display) {
                $new->assignDisplay($display);
            }

            // copy display group members
            foreach ($membersDisplayGroups as $dg) {
                $new->assignDisplayGroup($dg);
            }

        }

        // handle layout and file assignment
        if ($copyAssignments) {

            // copy layout assignments
            foreach ($assignedLayouts as $layout) {
                $new->assignLayout($layout);
            }

            // copy media assignments
            foreach ($assignedFiles as $media) {
                $new->assignMedia($media);
            }
        }

        // Dynamic display group needs to have at least one criteria specified to be added, we always want to copy criteria when we copy dynamic display group
        if ($displayGroup->isDynamic) {
            $new->dynamicCriteria = $displayGroup->dynamicCriteria;
            $new->dynamicCriteriaTags = $displayGroup->dynamicCriteriaTags;
        }

        // handle tags
        if ($copyTags) {
            $tags = '';

            $arrayOfTags = array_filter(explode(',', $displayGroup->tags));
            $arrayOfTagValues = array_filter(explode(',', $displayGroup->tagValues));

            for ($i=0; $i<count($arrayOfTags); $i++) {
                if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                    $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                    $tags .= ',';
                } else {
                    $tags .= $arrayOfTags[$i] . ',';
                }
            }
            $new->replaceTags($this->tagFactory->tagsFromString($tags));
        }

        $new->displayGroup = $this->getSanitizer()->getString('displayGroup');
        $new->description = $this->getSanitizer()->getString('description');
        $new->setOwner($this->getUser()->userId);

        // save without managing links, we need to save for new display group to get an ID, which is then used in next save to manage links - for dynamic groups.
        // we also don't want to call notify at this point (for file/layout assignment)
        $new->save(['manageDisplayLinks' => false, 'allowNotify' => false]);

        // load the created display group and save along with display links and notify
        $new->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $new->load();
        $new->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $new->displayGroup),
            'id' => $new->displayGroupId,
            'data' => $new
        ]);
    }
}
