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

use Xibo\Helper\ApplicationState;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


class Template extends Base
{
    /**
     * Display page logic
     */
    function displayPage()
    {
        // Default options
        if (\Kit::IsFilterPinned('template', 'Filter')) {
            $pinned = 1;
            $name = Session::Get('template', 'filter_name');
            $tags = Session::Get('template', 'filter_tags');
            $showThumbnail = Session::Get('template', 'showThumbnail');
        } else {
            $pinned = 0;
            $name = '';
            $tags = '';
            $showThumbnail = 1;
        }

        $data = [
            'defaults' => [
                'name' => $name,
                'tags' => $tags,
                'showThumbnail' => $showThumbnail,
                'filterPinned' => $pinned
            ]
        ];

        // Call to render the template
        $this->getState()->template = 'template-page';
        $this->getState()->setData($data);
    }

    /**
     * Data grid
     */
    function grid()
    {
        $response = $this->getState();

        $filter_name = Sanitize::getString('filter_name');
        $filter_tags = Sanitize::getString('filter_tags');

        Session::Set('template', 'filter_name', $filter_name);
        Session::Set('template', 'filter_tags', $filter_tags);
        Session::Set('template', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));

        // Show filter_showThumbnail
        $showThumbnail = Sanitize::getCheckbox('showThumbnail');
        Session::Set('layout', 'showThumbnail', $showThumbnail);

        $templates = $this->getUser()->TemplateList($filter_name, $filter_tags);

        foreach ($templates as $template) {
            /* @var \Xibo\Entity\Layout $template */

            $template->thumbnail = '';

            if ($template->backgroundImageId != 0)
                $template->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" data-img-src="index.php?p=content&q=getFile&mediaid=' . $template->backgroundImageId . '&width=100&height=100&dynamic=true&thumb=true" href="index.php?p=content&q=getFile&mediaid=' . $template->backgroundImageId . '"><i class="fa fa-file-image-o"></i></a>';

            $template->buttons = array();

            // Parse down for description
            $template->descriptionWithMarkup = \Parsedown::instance()->text($template->description);

            if ($this->getUser()->checkEditable($template)) {
                // Edit Button
                $template->buttons[] = array(
                    'id' => 'template_button_edit',
                    'url' => 'index.php?p=template&q=EditForm&modify=true&layoutid=' . $template->layoutId,
                    'text' => __('Edit')
                );
            }

            if ($this->getUser()->checkDeleteable($template)) {
                // Delete Button
                $template->buttons[] = array(
                    'id' => 'layout_button_delete',
                    'url' => 'index.php?p=template&q=DeleteTemplateForm&layoutid=' . $template->layoutId,
                    'text' => __('Delete')
                );
            }

            if ($this->getUser()->checkPermissionsModifyable($template)) {
                // Permissions Button
                $template->buttons[] = array(
                    'id' => 'layout_button_delete',
                    'url' => 'index.php?p=user&q=permissionsForm&entity=Campaign&objectId=' . $template->campaignId,
                    'text' => __('Permissions')
                );
            }

            $template->buttons[] = ['divider' => true];

            // Export Button
            $template->buttons[] = array(
                'id' => 'layout_button_export',
                'linkType' => '_self', 'external' => true,
                'url' => 'index.php?p=layout&q=Export&layoutid=' . $template->layoutId,
                'text' => __('Export')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($templates);
    }

    /**
     * Displays the TemplateForm (for adding)
     */
    function TemplateForm()
    {
        $response = $this->getState();

        // Get the layout
        $layout = \Xibo\Factory\LayoutFactory::getById(Kit::GetParam('layoutid', _GET, _INT));

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            trigger_error(__('You do not have permissions to view this layout'), E_USER_ERROR);

        Theme::Set('form_id', 'TemplateAddForm');
        Theme::Set('form_action', 'index.php?p=template&q=AddTemplate');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layout->layoutId . '">');

        $formFields = array();
        $formFields[] = Form::AddText('template', __('Name'), NULL,
            __('The Name of the Template - (1 - 50 characters)'), 'n', 'maxlength="50" required');

        $formFields[] = Form::AddText('tags', __('Tags'), NULL,
            __('Tags for this Template - used when searching for it. Comma delimited. (1 - 250 characters)'), 't', 'maxlength="250"');

        $formFields[] = Form::AddMultiText('description', __('Description'), NULL,
            __('An optional description of the Template. (1 - 250 characters)'), 'd', 5, 'maxlength="250"');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Save this Layout as a Template?'), '550px', '230px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Template', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TemplateAddForm").submit()');

    }

    function EditForm()
    {
        $response = $this->getState();

        // Get the layout
        $layout = \Xibo\Factory\LayoutFactory::getById(Kit::GetParam('layoutid', _GET, _INT));

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            trigger_error(__('You do not have permissions to view this layout'), E_USER_ERROR);

        Theme::Set('form_id', 'TemplateEditForm');

        // Two tabs
        $tabs = array();
        $tabs[] = Form::AddTab('general', __('General'));
        $tabs[] = Form::AddTab('description', __('Description'));

        Theme::Set('form_tabs', $tabs);

        $formFields = array();
        $formFields['general'][] = Form::AddText('layout', __('Name'), $layout->layout, __('The Name of the Layout - (1 - 50 characters)'), 'n', 'required');

        $formFields['description'][] = Form::AddMultiText('description', __('Description'), $layout->description,
            __('An optional description of the Layout. (1 - 250 characters)'), 'd', 5, 'maxlength="250"');

        // We are editing
        Theme::Set('form_action', 'index.php?p=template&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layout->layoutId . '">');

        $formFields['general'][] = Form::AddCombo(
            'retired',
            __('Retired'),
            $layout->retired,
            array(array('retiredid' => '1', 'retired' => 'Yes'), array('retiredid' => '0', 'retired' => 'No')),
            'retiredid',
            'retired',
            __('Retire this template or not? It will no longer be visible in lists'),
            'r');

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_description', $formFields['description']);

        // Initialise the template and capture the output
        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Edit Template'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Template', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#TemplateEditForm").submit()');

    }

    /**
     * Adds a template
     */
    function AddTemplate()
    {


        $response = $this->getState();

        // Get the layout
        $layout = clone \Xibo\Factory\LayoutFactory::getById(Kit::GetParam('layoutid', _POST, _INT));

        $layout->layout = \Xibo\Helper\Sanitize::getString('template');
        $layout->tags = \Xibo\Factory\TagFactory::tagsFromString(Kit::GetParam('tags', _POST, _STRING));
        $layout->tags[] = \Xibo\Factory\TagFactory::getByTag('template');
        $layout->description = \Xibo\Helper\Sanitize::getString('description');

        $layout->validate();
        $layout->save();

        $response->SetFormSubmitResponse('Template Added.');

    }

    function Edit()
    {


        $response = $this->getState();

        // Get the layout
        $layout = \Xibo\Factory\LayoutFactory::getById(Kit::GetParam('layoutid', _POST, _INT));

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            trigger_error(__('You do not have permissions to view this layout'), E_USER_ERROR);

        $layout->layout = \Xibo\Helper\Sanitize::getString('layout');
        $layout->tags = \Xibo\Factory\TagFactory::tagsFromString(Kit::GetParam('tags', _POST, _STRING));
        $layout->tags[] = \Xibo\Factory\TagFactory::getByTag('template');
        $layout->description = \Xibo\Helper\Sanitize::getString('description');
        $layout->retired = \Kit::GetParam('retired', _POST, _INT, 0);

        $layout->validate();
        $layout->save();

        $response->SetFormSubmitResponse(__('Template Details Changed.'));

    }

    /**
     * Deletes a template
     */
    function DeleteTemplate()
    {


        $response = $this->getState();

        // Get the layout
        $layout = \Xibo\Factory\LayoutFactory::getById(Kit::GetParam('layoutid', _POST, _INT));

        // Check Permissions
        if (!$this->getUser()->checkDeleteable($layout))
            trigger_error(__('You do not have permissions to view this layout'), E_USER_ERROR);

        $layout->delete();

        $response->SetFormSubmitResponse(__('The Template has been Deleted'));

    }

    /**
     * Shows the form to delete a template
     */
    public function DeleteTemplateForm()
    {
        $response = $this->getState();

        // Get the layout
        $layout = \Xibo\Factory\LayoutFactory::getById(Kit::GetParam('layoutid', _GET, _INT));

        // Check Permissions
        if (!$this->getUser()->checkDeleteable($layout))
            trigger_error(__('You do not have permissions to view this layout'), E_USER_ERROR);

        Theme::Set('form_id', 'DeleteForm');
        Theme::Set('form_action', 'index.php?p=template&q=DeleteTemplate');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layout->layoutId . '">');
        Theme::Set('form_fields', array(
            Form::AddMessage(__('Are you sure you want to delete this template?'))
        ));

        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Delete Template'), '300px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Template', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteForm").submit()');

    }
}
