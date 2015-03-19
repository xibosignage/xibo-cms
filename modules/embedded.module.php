<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2015 Daniel Garner
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
class embedded extends Module
{
    /**
     * Install Files
     */
    public function InstallFiles()
    {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
    }
    
    /**
     * Return the Add Form as HTML
     */
    public function AddForm()
    {
        $response = new ResponseManager();
        // Configure form
        $this->configureForm('AddMedia');

        $formFields = array();
        
        $formFields[] = FormManager::AddText('name', __('Name'), NULL, 
            __('An optional name for this media'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this item should be displayed'), 'd', 'required');

        $formFields[] = FormManager::AddCheckbox('transparency', __('Background transparent?'), 
            NULL, __('Should the HTML be shown with a transparent background. Not current available on the Windows Display Client.'), 
            't');

        $formFields[] = FormManager::AddCheckbox('scaleContent', __('Scale Content?'), 
            $this->GetOption('scaleContent'), __('Should the embedded content be scaled along with the layout?'), 
            's');

        $formFields[] = FormManager::AddMultiText('embedHtml', NULL, NULL, 
            __('HTML to Embed'), 'h', 10);

        $formFields[] = FormManager::AddMultiText('embedStyle', NULL, '
<style type="text/css">

</style>',
            __('Custom Style Sheets'), 'h', 10);


        $formFields[] = FormManager::AddMultiText('embedScript', NULL, '
<script type="text/javascript">
function EmbedInit()
{
    // Init will be called when this page is loaded in the client.

    return;
}
</script>',
            __('HEAD content to Embed (including script tags)'), 'h', 10);

        Theme::Set('form_fields', $formFields);

        $response->html = Theme::RenderReturn('form_render');
        $this->configureFormButtons($response);

        return $response;
    }
    
    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        $response = new ResponseManager();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');
        
        $formFields = array();
        $formFields[] = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');
        
        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddCheckbox('transparency', __('Background transparent?'), 
            $this->GetOption('transparency'), __('Should the HTML be shown with a transparent background. Not current available on the Windows Display Client.'), 
            't');

        $formFields[] = FormManager::AddCheckbox('scaleContent', __('Scale Content?'), 
            $this->GetOption('scaleContent'), __('Should the embedded content be scaled along with the layout?'), 
            's');

        $formFields[] = FormManager::AddMultiText('embedHtml', NULL, $this->getRawNode('embedHtml', null),
            __('HTML to Embed'), 'h', 10);

        $formFields[] = FormManager::AddMultiText('embedStyle', NULL, $this->getRawNode('embedStyle', null),
            __('Custom Style Sheets'), 'h', 10);

        $formFields[] = FormManager::AddMultiText('embedScript', NULL, $this->getRawNode('embedScript', null),
            __('HEAD content to Embed (including script tags)'), 'h', 10);

        Theme::Set('form_fields', $formFields);

        $response->html= Theme::RenderReturn('form_render');;
        $this->configureFormButtons($response);
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');

        return $response;
    }
    
    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = new ResponseManager();

        // Required Attributes
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('transparency', Kit::GetParam('transparency', _POST, _CHECKBOX));
        $this->SetOption('name', Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('scaleContent', Kit::GetParam('scaleContent', _POST, _CHECKBOX, 'off'));
        $this->setRawNode('embedHtml', Kit::GetParam('embedHtml', _POST, _HTMLSTRING));
        $this->setRawNode('embedScript', Kit::GetParam('embedScript', _POST, _HTMLSTRING));
        $this->setRawNode('embedStyle', Kit::GetParam('embedStyle', _POST, _HTMLSTRING));

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }
    
    /**
     * Edit Media in the Database
     */
    public function EditMedia()
    {
        $response = new ResponseManager();
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('transparency', Kit::GetParam('transparency', _POST, _CHECKBOX));
        $this->SetOption('name', Kit::GetParam('name', _POST, _STRING));
        $this->SetOption('scaleContent', Kit::GetParam('scaleContent', _POST, _CHECKBOX, 'off'));
        $this->setRawNode('embedHtml', Kit::GetParam('embedHtml', _POST, _HTMLSTRING));
        $this->setRawNode('embedScript', Kit::GetParam('embedScript', _POST, _HTMLSTRING));
        $this->setRawNode('embedStyle', Kit::GetParam('embedStyle', _POST, _HTMLSTRING));

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }
    
    public function IsValid()
    {
            $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';
        // Can't be sure because the client does the rendering
        return 2;
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        // Behave exactly like the client.
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->region->width, $template);

        // Embedded Html
        $html = $this->parseLibraryReferences($isPreview, $this->getRawNode('embedHtml', null));

        // Include some vendor items
        $javaScriptContent = '<script src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
        $javaScriptContent .= '<script src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';

        // Get the Script
        $javaScriptContent .= $this->parseLibraryReferences($isPreview, $this->getRawNode('embedScript', null));

        // Get the Style Sheet
        $styleSheetContent = $this->parseLibraryReferences($isPreview, $this->getRawNode('embedStyle', null));

        // Set some options
        $options = array(
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );


        // Add an options variable with some useful information for scaling
        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   $(document).ready(function() { EmbedInit(); });';
        $javaScriptContent .= '</script>';

        // Do we want to scale?
        if ($this->GetOption('scaleContent') == 1) {
            $javaScriptContent .= '<script>
                $(document).ready(function() {
                    $("body").xiboLayoutScaler(options);
                });
            </script>';
        }

        // Add our fonts.css file
        $headContent = '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';


        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Replace the Style Sheet Content with our generated Style Sheet
        $template = str_replace('<!--[[[STYLESHEETCONTENT]]]-->', $styleSheetContent, $template);

        // Replace the Head Content with our generated java script
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $html, $template);

        return $template;
    }

    /**
     * Parse for any library references
     * @param $isPreview bool
     * @param $content string
     * @return mixed The Parsed Content
     */
    private function parseLibraryReferences($isPreview, $content)
    {
        $parsedContent = $content;
        $matches = '';
        preg_match_all('/\[.*?\]/', $content, $matches);

        foreach ($matches[0] as $sub) {
            // Parse out the mediaId
            $mediaId = str_replace(']', '', str_replace('[', '', $sub));

            // Only proceed if the content is actually an ID
            if (!is_numeric($mediaId))
                continue;

            // Check that this mediaId exists and get some information about it
            $entry = \Xibo\Factory\MediaFactory::query(null,array('mediaId' => $mediaId));

            if (count($entry) <= 0)
                continue;

            // We have a valid mediaId to substitute
            $replace = ($isPreview) ? 'index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $entry[0]->mediaId : $entry[0]->storedAs;

            // Substitute the replacement we have found (it might be '')
            $parsedContent = str_replace($sub, $replace, $parsedContent);
        }

        return $parsedContent;
    }
}
