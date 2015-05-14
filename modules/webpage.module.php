<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner and James Packer
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
class webpage extends Module
{
    
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'webpage';
    
        // Must call the parent class   
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    public function InstallFiles() {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');;
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');;
        $media->addModuleFile('modules/preview/xibo-webpage-render.js');;
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
         
        $formFields[] = FormManager::AddText('uri', __('Link'), NULL, 
            __('The Location (URL) of the webpage'), 'l', 'required');

        $formFields[] = FormManager::AddText('name', __('Name'), NULL, 
            __('An optional name for this media'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this item should be displayed'), 'd', 'required');

        $formFields[] = FormManager::AddCombo(
            'modeid', 
            __('Options'), 
            NULL,
            array(
                    array('modeid' => '1', 'mode' => __('Open Natively')), 
                    array('modeid' => '2', 'mode' => __('Manual Position')),
                    array('modeid' => '3', 'mode' => __('Best Fit'))
                ),
            'modeid',
            'mode',
            __('How should this web page be embedded?'), 
            'm');

        $formFields[] = FormManager::AddNumber('pageWidth', __('Page Width'), NULL, 
            __('The width of the page. Leave empty to use the region width.'), 'w', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('pageHeight', __('Page Height'), NULL, 
            __('The height of the page. Leave empty to use the region height'), 'h', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('offsetTop', __('Offset Top'), NULL, 
            __('The starting point from the top in pixels'), 't', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('offsetLeft', __('Offset Left'), NULL, 
            __('The starting point from the left in pixels'), 'l', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('scaling', __('Scale Percentage'), NULL, 
            __('The Percentage to Scale this Webpage (0 - 100)'), 's', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddCheckbox('transparency', __('Background transparent?'), 
            NULL, __('Should the HTML be shown with a transparent background. Not currently available on the Windows Display Client.'), 
            't');

        // Field dependencies
        $modeFieldDepencies_1 = array(
                '.webpage-widths' => array('display' => 'none'),
                '.webpage-offsets' => array('display' => 'none'),
            );
        $modeFieldDepencies_2 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'block'),
            );
        $modeFieldDepencies_3 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'none'),
            );

        $this->response->AddFieldAction('modeid', 'init', 1, $modeFieldDepencies_1);
        $this->response->AddFieldAction('modeid', 'change', 1, $modeFieldDepencies_1);
        $this->response->AddFieldAction('modeid', 'init', 2, $modeFieldDepencies_2);
        $this->response->AddFieldAction('modeid', 'change', 2, $modeFieldDepencies_2);
        $this->response->AddFieldAction('modeid', 'init', 3, $modeFieldDepencies_3);
        $this->response->AddFieldAction('modeid', 'change', 3, $modeFieldDepencies_3);

        Theme::Set('form_fields', $formFields);

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        $this->response->dialogTitle = __('Add Webpage');
        $this->response->dialogSize     = true;
        $this->response->dialogWidth    = '450px';
        $this->response->dialogHeight   = '250px';

        return $this->response;
    }
    
    /**
     * Return the Edit Form as HTML
     * @return 
     */
    public function EditForm()
    {
        $this->response = new ResponseManager();
        $db         =& $this->db;
        
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;

        // Permissions
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');
            
        $formFields = array();
        
        $formFields[] = FormManager::AddText('uri', __('Link'), urldecode($this->GetOption('uri')), 
            __('The Location (URL) of the webpage'), 'l', 'required');

        $formFields[] = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddCombo(
            'modeid', 
            __('Options'), 
            $this->GetOption('modeid'),
            array(
                    array('modeid' => '1', 'mode' => __('Open Natively')), 
                    array('modeid' => '2', 'mode' => __('Manual Position')),
                    array('modeid' => '3', 'mode' => __('Best Fit'))
                ),
            'modeid',
            'mode',
            __('How should this web page be embedded?'), 
            'm');

        $formFields[] = FormManager::AddNumber('pageWidth', __('Page Width'), $this->GetOption('pageWidth'), 
            __('The width of the page. Leave empty to use the region width.'), 'w', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('pageHeight', __('Page Height'), $this->GetOption('pageHeight'), 
            __('The height of the page. Leave empty to use the region height'), 'h', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('offsetTop', __('Offset Top'), $this->GetOption('offsetTop'), 
            __('The starting point from the top in pixels'), 't', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('offsetLeft', __('Offset Left'), $this->GetOption('offsetLeft'), 
            __('The starting point from the left in pixels'), 'l', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('scaling', __('Scale Percentage'), $this->GetOption('scaling'), 
            __('The Percentage to Scale this Webpage (0 - 100)'), 's', NULL, 'webpage-offsets');
           
        $formFields[] = FormManager::AddCheckbox('transparency', __('Background transparent?'), 
            $this->GetOption('transparency'), __('Should the HTML be shown with a transparent background. Not currently available on the Windows Display Client.'), 
            't');

        // Field dependencies
        $modeFieldDepencies_1 = array(
                '.webpage-widths' => array('display' => 'none'),
                '.webpage-offsets' => array('display' => 'none'),
            );
        $modeFieldDepencies_2 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'block'),
            );
        $modeFieldDepencies_3 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'none'),
            );

        $this->response->AddFieldAction('modeid', 'init', 1, $modeFieldDepencies_1);
        $this->response->AddFieldAction('modeid', 'change', 1, $modeFieldDepencies_1);
        $this->response->AddFieldAction('modeid', 'init', 2, $modeFieldDepencies_2);
        $this->response->AddFieldAction('modeid', 'change', 2, $modeFieldDepencies_2);
        $this->response->AddFieldAction('modeid', 'init', 3, $modeFieldDepencies_3);
        $this->response->AddFieldAction('modeid', 'change', 3, $modeFieldDepencies_3);

        Theme::Set('form_fields', $formFields);

        
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->html = Theme::RenderReturn('form_render');
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        $this->response->dialogTitle = __('Edit Webpage');
        $this->response->dialogSize     = true;
        $this->response->dialogWidth    = '450px';
        $this->response->dialogHeight   = '250px';

        return $this->response;
    }
    
    /**
     * Add Media to the Database
     * @return 
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();
        $db         =& $this->db;
        
        $layoutid   = $this->layoutid;
        $regionid   = $this->regionid;
        $mediaid    = $this->mediaid;
        
        //Other properties
        $uri          = Kit::GetParam('uri', _POST, _URI);
        $duration     = Kit::GetParam('duration', _POST, _INT, 0, false);
        $scaling      = Kit::GetParam('scaling', _POST, _INT, 100);
        $transparency     = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
        $offsetLeft = Kit::GetParam('offsetLeft', _POST, _INT);
        $offsetTop = Kit::GetParam('offsetTop', _POST, _INT);
	$name = Kit::GetParam('name', _POST, _STRING);
        
        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
                        
        //Validate the URL?
        if ($uri == "" || $uri == "http://")
        {
            $this->response->SetError('Please enter a Link');
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
        $this->mediaid  = md5(uniqid());
        $this->duration = $duration;
        
        // Any Options
        $this->SetOption('xmds', true);
        $this->SetOption('uri', $uri);
        $this->SetOption('scaling', $scaling);
        $this->SetOption('transparency', $transparency);
        $this->SetOption('offsetLeft', $offsetLeft);
        $this->SetOption('offsetTop', $offsetTop);
        $this->SetOption('pageWidth', Kit::GetParam('pageWidth', _POST, _INT));
        $this->SetOption('pageHeight', Kit::GetParam('pageHeight', _POST, _INT));
        $this->SetOption('modeid', Kit::GetParam('modeid', _POST, _INT));
	$this->SetOption('name', $name);

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();
        
        //Set this as the session information
        setSession('content', 'type', 'webpage');
        
        if ($this->showRegionOptions) {
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
        $db         =& $this->db;
        
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
        $uri          = Kit::GetParam('uri', _POST, _URI);
        $scaling      = Kit::GetParam('scaling', _POST, _INT, 100);
        $transparency     = Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
        $offsetLeft = Kit::GetParam('offsetLeft', _POST, _INT);
        $offsetTop = Kit::GetParam('offsetTop', _POST, _INT);
	$name = Kit::GetParam('name', _POST, _STRING);
        
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        $url = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
                        
        //Validate the URL?
        if ($uri == "" || $uri == "http://")
        {
            $this->response->SetError('Please enter a Link');
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
        $this->SetOption('xmds', true);
        $this->SetOption('uri', $uri);
        $this->SetOption('scaling', $scaling);
        $this->SetOption('transparency', $transparency);
        $this->SetOption('offsetLeft', $offsetLeft);
        $this->SetOption('offsetTop', $offsetTop);
        $this->SetOption('pageWidth', Kit::GetParam('pageWidth', _POST, _INT));
        $this->SetOption('pageHeight', Kit::GetParam('pageHeight', _POST, _INT));
        $this->SetOption('modeid', Kit::GetParam('modeid', _POST, _INT));
	$this->SetOption('name', $name);

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();
        
        //Set this as the session information
        setSession('content', 'type', 'webpage');
        
    if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }
        
        return $this->response; 
    }

    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @return string The Rendered Content
     */
    public function Preview($width, $height, $scaleOverride = 0)
    {
        // If we are opening the web page natively on the device, then we cannot offer a preview
        if ($this->GetOption('modeid') == 1)
            return '<div style="text-align:center;"><img alt="' . $this->type . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif" /></div>';

        return $this->PreviewAsClient($width, $height, $scaleOverride);
    }

    /**
     * GetResource for Web page Media
     * @param int $displayId
     * @return mixed|string
     */
    public function GetResource($displayId = 0)
    {
        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');
        
        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // Get some parameters
        $width = Kit::GetParam('width', _REQUEST, _DOUBLE);
        $height = Kit::GetParam('height', _REQUEST, _DOUBLE);

        // Work out the url
        $url = urldecode($this->GetOption('uri'));
        $url = (preg_match('/^' . preg_quote('http') . "/", $url)) ? $url : 'http://' . $url;

        // Set the iFrame dimensions
        $iframeWidth = $this->GetOption('pageWidth');
        $iframeHeight = $this->GetOption('pageHeight');

        $options = array(
                'modeId' => $this->GetOption('modeid'),
                'originalWidth' => intval($this->width),
                'originalHeight' => intval($this->height),
                'iframeWidth' => intval(($iframeWidth == '' || $iframeWidth == 0) ? $this->width : $iframeWidth),
                'iframeHeight' => intval(($iframeHeight == '' || $iframeHeight == 0) ? $this->height : $iframeHeight),
                'previewWidth' => intval($width),
                'previewHeight' => intval($height),
                'offsetTop' => intval($this->GetOption('offsetTop', 0)),
                'offsetLeft' => intval($this->GetOption('offsetLeft', 0)),
                'scale' => ($this->GetOption('scaling', 100) / 100),
                'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
            );

        // Head Content
        $headContent = '<style>#iframe { border:0; }</style>';
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Body content
        $output = '<iframe id="iframe" scrolling="no" frameborder="0" src="' . $url . '"></iframe>';
        
        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $output, $template);

        // After body content
        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
        $after_body  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
        $after_body .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $after_body .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-webpage-render.js"></script>';
        $after_body .= '<script>
            var options = ' . json_encode($options) . '
            $(document).ready(function() {
                $("#content").xiboLayoutScaler(options);
                $("#iframe").xiboIframeScaler(options);
            });
            </script>';

        // Replace the After body Content
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $after_body, $template);

        return $template;
    }

    public function GetName() {
        return $this->GetOption('name');
    }

    public function IsValid() {
        // Can't be sure because the client does the rendering
        return 2;
    }
}
?>
