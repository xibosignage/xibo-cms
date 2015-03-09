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
    
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'embedded';
    
        // Must call the parent class   
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    public function InstallFiles() {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
    }
    
    /**
     * Return the Add Form as HTML
     * @return 
     */
    public function AddForm()
    {
        $this->response = new ResponseManager();
        $db         =& $this->db;
        $user       =& $this->user;
                
        // Would like to get the regions width / height 
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $rWidth     = Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight    = Kit::GetParam('rHeight', _REQUEST, _STRING);
    
        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');

        $formFields = array();
        
        $formFields[] = FormManager::AddText('name', __('Name'), NULL, 
            __('An optional name for this media'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
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

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->html           = Theme::RenderReturn('form_render');
        $this->response->dialogTitle    = 'Add Embedded HTML';
        $this->response->dialogSize     = true;
        $this->response->dialogWidth    = '650px';
        $this->response->dialogHeight   = '450px';
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }
    
    /**
     * Return the Edit Form as HTML
     * @return ResponseManager
     */
    public function EditForm()
    {
        $this->response = new ResponseManager();

        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        // Can this user edit?
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this media.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
        
        // Get the embedded HTML out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());
        
        //Debug::LogEntry('audit', 'Raw XML returned: ' . $this->GetRaw());
        
        $formFields = array();

        $formFields[] = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');
        
        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddCheckbox('transparency', __('Background transparent?'), 
            $this->GetOption('transparency'), __('Should the HTML be shown with a transparent background. Not current available on the Windows Display Client.'), 
            't');

        $formFields[] = FormManager::AddCheckbox('scaleContent', __('Scale Content?'), 
            $this->GetOption('scaleContent'), __('Should the embedded content be scaled along with the layout?'), 
            's');

        $formFields[] = FormManager::AddMultiText('embedHtml', NULL, $this->GetRawNode('embedHtml'),
            __('HTML to Embed'), 'h', 10);

        $formFields[] = FormManager::AddMultiText('embedStyle', NULL, $this->GetRawNode('embedStyle'),
            __('Custom Style Sheets'), 'h', 10);

        $formFields[] = FormManager::AddMultiText('embedScript', NULL, $this->GetRawNode('embedScript'),
            __('HEAD content to Embed (including script tags)'), 'h', 10);

        Theme::Set('form_fields', $formFields);

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->html           = Theme::RenderReturn('form_render');;
        $this->response->dialogTitle    = __('Edit Embedded HTML');
        $this->response->dialogSize     = true;
        $this->response->dialogWidth    = '650px';
        $this->response->dialogHeight   = '450px';
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }
    
    /**
     * Add Media to the Database
     * @return 
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;
        
        //Other properties
        $embedHtml    = Kit::GetParam('embedHtml', _POST, _HTMLSTRING);
        $embedScript  = Kit::GetParam('embedScript', _POST, _HTMLSTRING);
        $embedStyle   = Kit::GetParam('embedStyle', _POST, _HTMLSTRING);
        $duration     = Kit::GetParam('duration', _POST, _INT, 0, false);
        $transparency = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
        $name = Kit::GetParam('name', _POST, _STRING);
        
        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
                        
        //Validate the URL?
        if ($embedHtml == "")
        {
            $this->response->SetError('Please enter some HTML to embed.');
            $this->response->keepOpen = true;
            return $this->response;
        }
        
        if ($duration == 0)
        {
            $this->response->SetError('You must enter a duration.');
            $this->response->keepOpen = true;
            return $this->response;
        }
        
        // Required Attributes
        $this->mediaid = md5(uniqid());
        $this->duration = $duration;
        $this->SetOption('transparency', $transparency);
        $this->SetOption('name', $name);
        $this->SetOption('scaleContent', Kit::GetParam('scaleContent', _POST, _CHECKBOX, 'off'));
        
        // Any Options
        $this->SetRaw('<embedHtml><![CDATA[' . $embedHtml . ']]></embedHtml><embedScript><![CDATA[' . $embedScript . ']]></embedScript><embedStyle><![CDATA[' . $embedStyle . ']]></embedStyle>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();
        
        //Set this as the session information
        setSession('content', 'type', $this->type);
        
    if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }
        
        return $this->response;
    }
    
    /**
     * Edit Media in the Database
     * @return 
     */
    public function EditMedia()
    {
        $this->response = new ResponseManager();
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }
        
        //Other properties
        $embedHtml    = Kit::GetParam('embedHtml', _POST, _HTMLSTRING);
        $embedScript  = Kit::GetParam('embedScript', _POST, _HTMLSTRING);
        $embedStyle   = Kit::GetParam('embedStyle', _POST, _HTMLSTRING);
        $transparency = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
        $name = Kit::GetParam('name', _POST, _STRING);

        $this->SetOption('transparency', $transparency);
        $this->SetOption('name', $name);
        $this->SetOption('scaleContent', Kit::GetParam('scaleContent', _POST, _CHECKBOX, 'off'));

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);
        
        $url          = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
                        
        // Validate the URL?
        if ($embedHtml == "")
        {
            $this->response->SetError('Please enter some HTML to embed.');
            $this->response->keepOpen = true;
            return $this->response;
        }
        
        if ($this->duration == 0)
        {
            $this->response->SetError('You must enter a duration.');
            $this->response->keepOpen = true;
            return $this->response;
        }
        
        // Any Options
        $this->SetRaw('<embedHtml><![CDATA[' . $embedHtml . ']]></embedHtml><embedScript><![CDATA[' . $embedScript . ']]></embedScript><embedStyle><![CDATA[' . $embedStyle . ']]></embedStyle>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();
        
        //Set this as the session information
        setSession('content', 'type', $this->type);
        
        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }
        
        return $this->response; 
    }
    
    public function GetName() {
        return $this->GetOption('name');
    }
    
    public function IsValid() {
        // Can't be sure because the client does the rendering
        return 2;
    }
   
    public function GetResource($display = 0) {
        // Behave exactly like the client.
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');

        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // Get the text out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        // Get the Text Node
        $html = $this->parseLibraryReferences($isPreview, $this->GetRawNode('embedHtml', ''));

        // Include some vendor items
        $javaScriptContent = '<script src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
        $javaScriptContent .= '<script src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';

        // Get the Script
        $javaScriptContent .= $this->parseLibraryReferences($isPreview, $this->GetRawNode('embedScript', ''));

        // Get the Style Sheet
        $styleSheetContent = $this->parseLibraryReferences($isPreview, $this->GetRawNode('embedStyle', ''));

        // Set some options
        $options = array(
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
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
            $entry = Media::Entries(null, array('mediaId' => $mediaId));

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

?>
