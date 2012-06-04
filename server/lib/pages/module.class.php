<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class moduleDAO 
{
	private $db;
	private $user;
	private $module;

    /**
     * Module constructor.
     * @return
     * @param $db Object
     */
    function __construct(database $db, user $user)
    {
        $this->db 	=& $db;
        $this->user =& $user;

        $mod = Kit::GetParam('mod', _REQUEST, _WORD);

        // If we have the module - create an instance of the module class
        // This will only be true when we are displaying the Forms
        if ($mod != '')
        {
            require_once("modules/$mod.module.php");

            // Try to get the layout, region and media id's
            $layoutid   = Kit::GetParam('layoutid', _REQUEST, _INT);
            $regionid   = Kit::GetParam('regionid', _REQUEST, _STRING);
            $mediaid    = Kit::GetParam('mediaid', _REQUEST, _STRING);
            $lkid       = Kit::GetParam('lkid', _REQUEST, _INT);

            Debug::LogEntry($db, 'audit', 'Creating new module with MediaID: ' . $mediaid . ' LayoutID: ' . $layoutid . ' and RegionID: ' . $regionid);

            $this->module = new $mod($db, $user, $mediaid, $layoutid, $regionid, $lkid);
        }

        return true;
    }
	
    /**
     * No display page functionaility
     * @return
     */
    function displayPage()
    {
        include('template/pages/module_view.php');
        return false;
    }

    /**
     * No onload
     * @return
     */
    function on_page_load()
    {
            return '';
    }

    /**
     * No page heading
     * @return
     */
    function echo_page_heading()
    {
            return true;
    }

    public function Filter()
    {
        $filterForm = <<<END
        <div id="GroupFilter" class="FilterDiv">
                <form>
                        <input type="hidden" name="p" value="module">
                        <input type="hidden" name="q" value="Grid">
                </form>
        </div>
END;
        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        $filterForm
                </div>
                <div class="XiboData">

                </div>
        </div>
HTML;
        echo $xiboGrid;
    }

    public function Grid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $SQL = '';
        $SQL .= 'SELECT ModuleID, ';
        $SQL .= '   Name, ';
        $SQL .= '   Enabled, ';
        $SQL .= '   Description, ';
        $SQL .= '   RegionSpecific, ';
        $SQL .= '   ValidExtensions, ';
        $SQL .= '   ImageUri ';
        $SQL .= '  FROM `module` ';
        $SQL .= ' ORDER BY Name ';

        if (!$rows = $db->GetArray($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the list of modules'), E_USER_ERROR);
        }

        $output  = '<div class="info_table"><table style="width:100%">';
        $output .= '    <thead>';
        $output .= '    <tr>';
        $output .= '    <th>' . __('Name') .'</th>';
        $output .= '    <th>' . __('Description') .'</th>';
        $output .= '    <th>' . __('Library Media') .'</th>';
        $output .= '    <th>' . __('Valid Extensions') .'</th>';
        $output .= '    <th>' . __('Image Uri') .'</th>';
        $output .= '    <th>' . __('Enabled') .'</th>';
        $output .= '    <th>' . __('Actions') .'</th>';
        $output .= '    </tr>';
        $output .= '    </thead>';
        $output .= '    <tbody>';

        foreach($rows as $module)
        {
            $moduleId = Kit::ValidateParam($module['ModuleID'], _INT);
            $name = Kit::ValidateParam($module['Name'], _STRING);
            $description = Kit::ValidateParam($module['Description'], _STRING);
            $isRegionSpecific = Kit::ValidateParam($module['RegionSpecific'], _INT);
            $validExtensions = Kit::ValidateParam($module['ValidExtensions'], _STRING);
            $imageUri = Kit::ValidateParam($module['ImageUri'], _STRING);
            $enabled = Kit::ValidateParam($module['Enabled'], _INT);

            $output .= '<tr>';
            $output .= '<td>' . $name . '</td>';
            $output .= '<td>' . $description . '</td>';
            $output .= '<td>' . (($isRegionSpecific == 0) ? '<img src="img/act.gif" />' : '') . '</td>';
            $output .= '<td>' . $validExtensions . '</td>';
            $output .= '<td>' . $imageUri . '</td>';
            $output .= '<td>' . (($enabled == 1) ? '<img src="img/act.gif" />' : '<img src="img/disact.gif" />') . '</td>';
            $output .= '<td>' . ((Config::GetSetting($db, 'MODULE_CONFIG_LOCKED_CHECKB') == 'Checked') ? __('Modufle Config Locked') : '<button class="XiboFormButton" href="index.php?p=module&q=EditForm&ModuleID=' . $moduleId . '"><span>' . __('Edit') . '</span></button>') . '</td>';
            $output .= '</tr>';
        }

        $output .= "</tbody></table></div>";

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        // Can we edit?
        if (Config::GetSetting($db, 'MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Module Config Locked'), E_USER_ERROR);

        $moduleId = Kit::GetParam('ModuleID', _GET, _INT);

        // Pull the currently known info from the DB
        $SQL = '';
        $SQL .= 'SELECT ModuleID, ';
        $SQL .= '   Name, ';
        $SQL .= '   Enabled, ';
        $SQL .= '   Description, ';
        $SQL .= '   RegionSpecific, ';
        $SQL .= '   ValidExtensions, ';
        $SQL .= '   ImageUri ';
        $SQL .= '  FROM `module` ';
        $SQL .= ' WHERE ModuleID = %d ';

        $SQL = sprintf($SQL, $moduleId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Module'));
        }

        $validExtensions = Kit::ValidateParam($row['ValidExtensions'], _STRING);
        $imageUri = Kit::ValidateParam($row['ImageUri'], _STRING);
        $isRegionSpecific = Kit::ValidateParam($row['RegionSpecific'], _INT);
        $enabled = Kit::ValidateParam($row['Enabled'], _INT);
        $enabledChecked = ($enabled) ? 'checked' : '';

        // Help UI
        $iconModuleExtensions = $helpManager->HelpIcon(__('The Extensions allowed on files uploaded using this module. Comma Seperated.'), true);
        $iconModuleImage = $helpManager->HelpIcon(__('The Image to display for this module'), true);
        $iconModuleEnabled = $helpManager->HelpIcon(__('When Enabled users will be able to add media using this module'), true);

        $msgSave = __('Save');
        $msgCancel = __('Cancel');
        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $msgModuleExtensions = __('Valid Extensions');
        $msgModuleImage = __('Image');
        $msgModuleEnabled = __('Enabled');

        // The valid extensions field is only for library media
        $validExtensionsField = <<<END
                <tr>
                    <td>$msgModuleExtensions</td>
                    <td>$iconModuleExtensions <input class="required" type="text" name="ValidExtensions" value="$validExtensions" maxlength="254"></td>
                </tr>
END;

        $validExtensionsField = ($isRegionSpecific == 0) ? $validExtensionsField : '';

        $form = <<<END
        <form id="ModuleEditForm" class="XiboForm" action="index.php?p=module&q=Edit" method="post">
            <input type="hidden" name="ModuleID" value="$moduleId" />
            <table>
                $validExtensionsField
                <tr>
                    <td>$msgModuleImage</span></td>
                    <td>$iconModuleImage <input class="required" type="text" name="ImageUri" value="$imageUri" maxlength="254"></td>
                </tr>
                <tr>
                    <td>$msgModuleEnabled</span></td>
                    <td>$iconModuleEnabled <input type="checkbox" name="Enabled" $enabledChecked></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Edit Module'), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Module', 'Edit') . '")');
        $response->AddButton($msgCancel, 'XiboDialogClose()');
        $response->AddButton($msgSave, '$("#ModuleEditForm").submit()');
        $response->Respond();
    }

    public function Edit()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        // Can we edit?
        if (Config::GetSetting($db, 'MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Module Config Locked'), E_USER_ERROR);

        $moduleId = Kit::GetParam('ModuleID', _POST, _INT);
        $validExtensions = Kit::GetParam('ValidExtensions', _POST, _STRING, '');
        $imageUri = Kit::GetParam('ImageUri', _POST, _STRING);
        $enabled = Kit::GetParam('Enabled', _POST, _CHECKBOX);

        // Validation
        if ($moduleId == 0 || $moduleId == '')
            trigger_error(__('Module ID is missing'), E_USER_ERROR);

        if ($imageUri == '')
            trigger_error(__('Image Uri is a required field.'), E_USER_ERROR);

        // Deal with the Edit
        $SQL = "UPDATE `module` SET ImageUri = '%s', ValidExtensions = '%s', Enabled = %d WHERE ModuleID = %d";
        $SQL = sprintf($SQL, $db->escape_string($imageUri), $db->escape_string($validExtensions), $enabled, $moduleId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to update module'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Module Edited'), false);
        $response->Respond();
    }
	
    /**
     * What action to perform?
     * @return
     */
    public function Exec()
    {
        // What module has been requested?
        $method	= Kit::GetParam('method', _REQUEST, _WORD);
        $raw = Kit::GetParam('raw', _REQUEST, _WORD);

        if (method_exists($this->module,$method))
        {
            $response = $this->module->$method();
        }
        else
        {
            // Set the error to display
            trigger_error(__('This Module does not exist'), E_USER_ERROR);
        }

        if ($raw == 'true')
        {
            echo $response;
            exit();
        }
        else
        {
            $response->Respond();
        }
    }

	/**
	 * Returns an image stream to the browser - for the mediafile specified.
	 * @return 
	 */
	function GetImage()
	{
            $db         =& $this->db;

            $mediaID 	= Kit::GetParam('id', _GET, _INT, 0);
            $proportional = Kit::GetParam('proportional', _GET, _BOOL, true);
            $thumb = Kit::GetParam('thumb', _GET, _BOOL, false);
            $dynamic	= isset($_REQUEST['dynamic']);

            if ($mediaID == 0)
                die ('No media ID provided');

            // Get the file URI
            $SQL = sprintf("SELECT StoredAs FROM media WHERE MediaID = %d", $mediaID);

            if (!$file = $db->GetSingleValue($SQL, 'StoredAs', _STRING))
                die ('No media found for that media ID');

            //File upload directory.. get this from the settings object
            $library 	= Config::GetSetting($db, "LIBRARY_LOCATION");
            $fileName 	= $library . $file;

            // If we are a thumb request then output the cached thumbnail
            if ($thumb)
                $fileName = $library . 'tn_' . $file;

            // If the thumbnail doesnt exist then create one
            if (!file_exists($fileName))
            {
                Debug::LogEntry($db, 'audit', 'File doesnt exist, creating a thumbnail for ' . $fileName);

                if (!$info = getimagesize($library . $file))
                    die($library . $file . ' is not an image');

                ResizeImage($library . $file, $fileName, 80, 80, $proportional, 'file');
            }
            
            // Get the info for this new temporary file
            if (!$info = getimagesize($fileName))
            {
                echo $fileName . ' is not an image';
                exit;
            }

            if ($dynamic && $info[2])
            {
                $width  = Kit::GetParam('width', _GET, _INT);
                $height = Kit::GetParam('height', _GET, _INT);

                // dynamically create an image of the correct size - used for previews
                ResizeImage($fileName, '', $width, $height, $proportional, 'browser');

                exit;
            }

            if (!$image = file_get_contents($fileName))
            {
                //not sure
                Debug::LogEntry($db, 'audit', "Cant find: $uid", 'module', 'GetImage');

                $fileName = 'img/forms/filenotfound.png';
                $image 	= file_get_contents($fileName);
            }

            $size = getimagesize($fileName);

            //Output the image header
            header("Content-type: {$size['mime']}");

            echo $image;
            exit;
	}
}
?>