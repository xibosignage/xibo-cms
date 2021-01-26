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

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Tag
 * @package Xibo\Controller
 */
class Tag extends Base
{
    /** @var CampaignFactory */
    private $campaignFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var StorageServiceInterface */
    private $store;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param DisplayGroupFactory $displayGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param TagFactory $tagFactory
     * @param UserFactory $userFactory
     * @param DisplayFactory $displayFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @param CampaignFactory $campaignFactory
     * @param PlaylistFactory $playlistFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $displayGroupFactory, $layoutFactory, $tagFactory, $userFactory, $displayFactory, $mediaFactory, $scheduleFactory, $campaignFactory, $playlistFactory) {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->layoutFactory = $layoutFactory;
        $this->tagFactory = $tagFactory;
        $this->userFactory = $userFactory;
        $this->displayFactory = $displayFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->campaignFactory = $campaignFactory;
        $this->playlistFactory = $playlistFactory;
    }

    public function displayPage()
    {
        $this->getState()->template = 'tag-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query()
        ]);
    }

    /**
     * Tag Search
     *
     * @SWG\Get(
     *  path="/tag",
     *  operationId="tagSearch",
     *  tags={"tags"},
     *  summary="Search Tags",
     *  description="Search for Tags viewable by this user",
     *  @SWG\Parameter(
     *      name="tagId",
     *      in="query",
     *      description="Filter by Tag Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tag",
     *      in="query",
     *      description="Filter by partial Tag",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="exactTag",
     *      in="query",
     *      description="Filter by exact Tag",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="isSystem",
     *      in="query",
     *      description="Filter by isSystem flag",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="isRequired",
     *      in="query",
     *      description="Filter by isRequired flag",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="haveOptions",
     *      in="query",
     *      description="Set to 1 to show only results that have options set",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Tag")
     *      )
     *  )
     * )
     */
    function grid()
    {
        $filter = [
            'tagId' => $this->getSanitizer()->getInt('tagId'),
            'tag' => $this->getSanitizer()->getString('tag'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'isSystem' => $this->getSanitizer()->getCheckbox('isSystem'),
            'isRequired' => $this->getSanitizer()->getCheckbox('isRequired'),
            'haveOptions' => $this->getSanitizer()->getCheckbox('haveOptions')
        ];

        $tags = $this->tagFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($tags as $tag) {
            /* @var \Xibo\Entity\Tag $tag */

            if ($this->isApi()) {
                $tag->excludeProperty('layouts');
                $tag->excludeProperty('playlists');
                $tag->excludeProperty('campaigns');
                $tag->excludeProperty('medias');
                $tag->excludeProperty('displayGroups');
                continue;
            }

            $tag->includeProperty('buttons');
            $tag->buttons = [];


            //Show buttons for non system tags
            if ($tag->isSystem === 0) {
                // Edit the Tag
                $tag->buttons[] = [
                    'id' => 'tag_button_edit',
                    'url' => $this->urlFor('tag.edit.form', ['id' => $tag->tagId]),
                    'text' => __('Edit')
                ];

                // Delete Tag
                $tag->buttons[] = [
                    'id' => 'tag_button_delete',
                    'url' => $this->urlFor('tag.delete.form', ['id' => $tag->tagId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor('tag.delete', ['id' => $tag->tagId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'tag_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'rowtitle', 'value' => $tag->tag]
                    ]
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->tagFactory->countLast();
        $this->getState()->setData($tags);
    }

    /**
     * Tag Add Form
     */
    public function addForm()
    {
        $this->getState()->template = 'tag-form-add';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Tags', 'Add')
        ]);
    }

    /**
     * Add a Tag
     *
     * @SWG\Post(
     *  path="/tag",
     *  operationId="tagAdd",
     *  tags={"tags"},
     *  summary="Add a new Tag",
     *  description="Add a new Tag",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Tag name",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="isRequired",
     *      in="formData",
     *      description="A flag indicating whether value selection on assignment is required",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="options",
     *      in="formData",
     *      description="A comma separated string of Tag options",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Tag")
     *      )
     *  )
     * )
     */
    public function add()
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $values = [];
        $tag = $this->tagFactory->create($this->getSanitizer()->getString('name'));
        $tag->options = [];
        $tag->isRequired = $this->getSanitizer()->getCheckbox('isRequired');
        $optionValues = $this->getSanitizer()->getString('options');

        if ($optionValues != '') {
            $optionValuesArray = explode(',', $optionValues);
            foreach ($optionValuesArray as $options) {
                $values[] = $options;
            }
            $tag->options = json_encode($values);
        } else {
            $tag->options = null;
        }

        $tag->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $tag->tag),
            'id' => $tag->tagId,
            'data' => $tag
        ]);
    }

    /**
     * Edit a Tag
     *
     * @SWG\Put(
     *  path="/tag/{tagId}",
     *  operationId="tagEdit",
     *  tags={"tags"},
     *  summary="Edit existing Tag",
     *  description="Edit existing Tag",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Tag name",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="isRequired",
     *      in="formData",
     *      description="A flag indicating whether value selection on assignment is required",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="options",
     *      in="formData",
     *      description="A comma separated string of Tag options",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Tag")
     *      )
     *  )
     * )
     *
     *
     * @param $tagId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($tagId)
    {
        $tag = $this->tagFactory->getById($tagId);
        $tagOptions = '';

        if (isset($tag->options)) {
            $tagOptions = implode(',', json_decode($tag->options));
        }

        $this->getState()->template = 'tag-form-edit';
        $this->getState()->setData([
            'tag' => $tag,
            'options' => $tagOptions,
            'help' => $this->getHelp()->link('Tags', 'Add')
        ]);
    }

    /**
     * Edit a Tag
     *
     * @param $tagId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function edit($tagId)
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $tag = $this->tagFactory->getById($tagId);
        $tag->load();

        if ($tag->isSystem === 1) {
            throw new AccessDeniedException(__('Access denied System tags cannot be edited'));
        }

        if(isset($tag->options)) {
            $tagOptionsCurrent = implode(',', json_decode($tag->options));
            $tagOptionsArrayCurrent = explode(',', $tagOptionsCurrent);
        }

        $values = [];

        $tag->tag = $this->getSanitizer()->getString('name');
        $tag->isRequired = $this->getSanitizer()->getCheckbox('isRequired');
        $optionValues = $this->getSanitizer()->getString('options');

        if ($optionValues != '') {
            $optionValuesArray = explode(',', $optionValues);
            foreach ($optionValuesArray as $option) {
                $values[] = trim($option);
            }
            $tag->options = json_encode($values);
        } else {
            $tag->options = null;
        }

        // if option were changed, we need to compare the array of options before and after edit
        if($tag->hasPropertyChanged('options')) {

            if (isset($tagOptionsArrayCurrent)) {

                if(isset($tag->options)) {
                    $tagOptions = implode(',', json_decode($tag->options));
                    $tagOptionsArray = explode(',', $tagOptions);
                } else {
                    $tagOptionsArray = [];
                }

                // compare array of options before and after the Tag edit was made
                $tagValuesToRemove = array_diff($tagOptionsArrayCurrent, $tagOptionsArray);

                // go through every element of the new array and set the value to null if removed value was assigned to one of the lktag tables
                $tag->updateTagValues($tagValuesToRemove);
            }
        }

        $tag->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Edited %s'), $tag->tag),
            'id' => $tag->tagId,
            'data' => $tag
        ]);
    }

    /**
     * Shows the Delete Group Form
     * @param int $tagId
     * @throws \Xibo\Exception\NotFoundException
     */
    function deleteForm($tagId)
    {
        $tag = $this->tagFactory->getById($tagId);

        $this->getState()->template = 'tag-form-delete';
        $this->getState()->setData([
            'tag' => $tag,
            'help' => $this->getHelp()->link('Tag', 'Delete')
        ]);
    }

    /**
     * Delete Tag
     *
     * @SWG\Delete(
     *  path="/tag/{tagId}",
     *  operationId="tagDelete",
     *  tags={"tags"},
     *  summary="Delete Tag",
     *  description="Delete a Tag",
     *  @SWG\Parameter(
     *      name="tagId",
     *      in="path",
     *      description="The Tag ID to delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param int $tagId
     *
     * @throws \Xibo\Exception\InvalidArgumentException
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\XiboException
     */
    public function delete($tagId)
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $tag = $this->tagFactory->getById($tagId);
        $tag->load();

        if ($tag->isSystem === 1) {
            throw new AccessDeniedException(__('Access denied System tags cannot be deleted'));
        }

        // get all the linked items to the tag we want to delete
        $linkedLayoutsIds = $tag->layouts;
        $linkedDisplayGroupsIds = $tag->displayGroups;
        $linkedCampaignsIds = $tag->campaigns;
        $linkedPlaylistsIds = $tag->playlists;
        $linkedMediaIds = $tag->medias;

        // go through each linked layout and unassign the tag
        foreach($linkedLayoutsIds as $layoutId => $value) {
            $layout = $this->layoutFactory->getById($layoutId);
            $tag->unassignLayout($layoutId);
            $layout->save();
        }

        // go through each linked displayGroup and unassign the tag
        foreach ($linkedDisplayGroupsIds as $displayGroupId => $value) {
            $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
            $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
            $tag->unassignDisplayGroup($displayGroupId);
            $displayGroup->save();
        }

        // go through each linked campaign and unassign the tag
        foreach ($linkedCampaignsIds as $campaignId => $value) {
            $campaign = $this->campaignFactory->getById($campaignId);
            $campaign->setChildObjectDependencies($this->layoutFactory);
            $tag->unassignCampaign($campaignId);
            $campaign->save();
        }

        // go through each linked playlist and unassign the tag
        foreach ($linkedPlaylistsIds as $playlistId => $value) {
            $playlist = $this->playlistFactory->getById($playlistId);
            $tag->unassignPlaylist($playlistId);
            $playlist->save();
        }

        // go through each linked media and unassign the tag
        foreach($linkedMediaIds as $mediaId => $value) {
            $media = $this->mediaFactory->getById($mediaId);
            $tag->unassignMedia($mediaId);
            $media->save();
        }

        // finally call delete tag, which also removes the links from lktag tables
        $tag->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $tag->tag)
        ]);
    }

    /**
     * @throws NotFoundException
     */
    public function loadTagOptions()
    {
        $tagName = $this->getSanitizer()->getString('name');

        try {
            $tag = $this->tagFactory->getByTag($tagName);
        } catch (NotFoundException $e) {
            // User provided new tag, which is fine
            $tag = null;
        }

        $this->getState()->setData([
            'tag' => ($tag === null) ? null : $tag
        ]);
    }
}