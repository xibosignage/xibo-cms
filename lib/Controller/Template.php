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

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\XiboException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\TagFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Template
 * @package Xibo\Controller
 */
class Template extends Base
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param LayoutFactory $layoutFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $layoutFactory, $tagFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->layoutFactory = $layoutFactory;
        $this->tagFactory = $tagFactory;
    }

    /**
     * Display page logic
     */
    function displayPage()
    {
        // Call to render the template
        $this->getState()->template = 'template-page';
    }

    /**
     * Data grid
     *
     * @SWG\Get(
     *  path="/template",
     *  operationId="templateSearch",
     *  tags={"template"},
     *  summary="Template Search",
     *  description="Search templates this user has access to",
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
        // Embed?
        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];

        $templates = $this->layoutFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'excludeTemplates' => 0,
            'tags' => $this->getSanitizer()->getString('tags'),
            'layoutId' => $this->getSanitizer()->getInt('templateId'),
            'layout' => $this->getSanitizer()->getString('template')
        ]));

        foreach ($templates as $template) {
            /* @var \Xibo\Entity\Layout $template */

            if (in_array('regions', $embed)) {
                $template->load([
                    'loadPlaylists' => in_array('playlists', $embed),
                    'loadCampaigns' => in_array('campaigns', $embed),
                    'loadPermissions' => in_array('permissions', $embed),
                    'loadTags' => in_array('tags', $embed),
                    'loadWidgets' => in_array('widgets', $embed)
                ]);
            }

            if ($this->isApi())
                break;

            $template->includeProperty('buttons');

            $template->thumbnail = '';

            if ($template->backgroundImageId != 0) {
                $download = $this->urlFor('layout.download.background', ['id' => $template->layoutId]) . '?preview=1';
                $template->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56" /></i></a>';
            }

            // Parse down for description
            $template->descriptionWithMarkup = \Parsedown::instance()->text($template->description);

            if ($this->getUser()->checkEditable($template)) {

                // Design Button
                $template->buttons[] = array(
                    'id' => 'layout_button_design',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor('layout.designer', array('id' => $template->layoutId)),
                    'text' => __('Alter Template')
                );

                // Edit Button
                $template->buttons[] = array(
                    'id' => 'layout_button_edit',
                    'url' => $this->urlFor('layout.edit.form', ['id' => $template->layoutId]),
                    'text' => __('Edit')
                );

                // Copy Button
                $template->buttons[] = array(
                    'id' => 'layout_button_copy',
                    'url' => $this->urlFor('layout.copy.form', ['id' => $template->layoutId]),
                    'text' => __('Copy')
                );
            }

            // Extra buttons if have delete permissions
            if ($this->getUser()->checkDeleteable($template)) {
                // Delete Button
                $template->buttons[] = array(
                    'id' => 'layout_button_delete',
                    'url' => $this->urlFor('layout.delete.form', ['id' => $template->layoutId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('layout.delete', ['id' => $template->layoutId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'layout_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $template->layout)
                    )
                );
            }

            $template->buttons[] = ['divider' => true];

            // Extra buttons if we have modify permissions
            if ($this->getUser()->checkPermissionsModifyable($template)) {
                // Permissions button
                $template->buttons[] = array(
                    'id' => 'layout_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Campaign', 'id' => $template->campaignId]),
                    'text' => __('Permissions')
                );
            }

            $template->buttons[] = ['divider' => true];

            // Export Button
            $template->buttons[] = array(
                'id' => 'layout_button_export',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor('layout.export', ['id' => $template->layoutId]),
                'text' => __('Export')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($templates);
    }

    /**
     * Template Form
     * @param int $layoutId
     */
    function addTemplateForm($layoutId)
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
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException(__('You do not have permissions to view this layout'));

        $this->getState()->template = 'template-form-add-from-layout';
        $this->getState()->setData([
            'layout' => $layout,
            'tags' => $tags,
            'help' => $this->getHelp()->link('Template', 'Add')
        ]);
    }

    /**
     * Add template
     * @param int $layoutId
     *
     * @SWG\Post(
     *  path="/template/{layoutId}",
     *  operationId="template.add.from.layout",
     *  tags={"template"},
     *  summary="Add a template from a Layout",
     *  description="Use the provided layout as a base for a new template",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="includeWidgets",
     *      in="formData",
     *      description="Flag indicating whether to include the widgets in the Template",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Template Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Comma separated list of Tags for the template",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of the Template",
     *      type="string",
     *      required=false
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
    function add($layoutId)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($layoutId);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException(__('You do not have permissions to view this layout'));

        // Should the copy include the widgets
        $includeWidgets = ($this->getSanitizer()->getCheckbox('includeWidgets') == 1);

        // Load without anything
        $layout->load([
            'loadPlaylists' => true,
            'loadWidgets' => $includeWidgets,
            'playlistIncludeRegionAssignments' => false,
            'loadTags' => false,
            'loadPermissions' => false,
            'loadCampaigns' => false
        ]);
        $originalLayout = $layout;

        $layout = clone $layout;

        $layout->layout = $this->getSanitizer()->getString('name');
        $layout->tags = $this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags'));
        $layout->tags[] = $this->tagFactory->getByTag('template');
        $layout->description = $this->getSanitizer()->getString('description');
        $layout->setOwner($this->getUser()->userId, true);
        $layout->save();

        if ($includeWidgets) {
            // Sub-Playlist
            foreach ($layout->regions as $region) {
                // Match our original region id to the id in the parent layout
                $original = $originalLayout->getRegion($region->getOriginalValue('regionId'));

                // Make sure Playlist closure table from the published one are copied over
                $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Saved %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);
    }
}
