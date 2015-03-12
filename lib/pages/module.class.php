<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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

class moduleDAO extends baseDAO 
{
    private $module;

    /**
     * Module constructor.
     * @param $db database
     * @param $user user
     */
    function __construct(database $db, user $user)
    {
        $this->db   =& $db;
        $this->user =& $user;

        $mod = Kit::GetParam('mod', _REQUEST, _WORD);

        // If we have the module - create an instance of the module class
        // This will only be true when we are displaying the Forms
        if ($mod != '')
        {
            // Try to get the layout, region and media id's
            $layoutId   = Kit::GetParam('layoutid', _REQUEST, _INT);
            $regionId   = Kit::GetParam('regionid', _REQUEST, _STRING);
            $mediaId    = Kit::GetParam('mediaid', _REQUEST, _STRING);
            $lkId       = Kit::GetParam('lkid', _REQUEST, _INT);

            Debug::LogEntry('audit', 'Creating new module with MediaID: ' . $mediaId . ' LayoutID: ' . $layoutId . ' and RegionID: ' . $regionId);

            try {
                $this->module = ModuleFactory::load($mod, $layoutId, $regionId, $mediaId, $lkId, $this->db, $this->user);
            }
            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return true;
    }

    function actionMenu()
    {
        return array(
                array('title' => __('Verify All'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=module&q=VerifyForm',
                    'help' => __('Verify all modules have been installed correctly.'),
                    'onclick' => ''
                    )
            );
    }

    /**
     * Display the module page
     * @return
     */
    function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="module"><input type="hidden" name="q" value="Grid">');
        Theme::Set('pager', ResponseManager::Pager($id));

        // Do we have any modules to install?!
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {
            // Get a list of matching files in the modules folder
            $files = glob('modules/*.module.php');

            // Get a list of all currently installed modules
            try {
                $dbh = PDOConnect::init();

                $sth = $dbh->prepare("SELECT CONCAT('modules/', LOWER(Module), '.module.php') AS Module FROM `module`");
                $sth->execute();

                $rows = $sth->fetchAll();
                $installed = array();

                foreach($rows as $row)
                    $installed[] = $row['Module'];
            }
            catch (Exception $e) {
                trigger_error(__('Cannot get installed modules'), E_USER_ERROR);
            }

            // Compare the two
            $to_install = array_diff($files, $installed);

            if (count($to_install) > 0) {
                Theme::Set('module_install_url', 'index.php?p=module&q=Install&module=');
                Theme::Set('to_install', $to_install);
                Theme::Set('prepend', Theme::RenderReturn('module_page_install_modules'));
            }
        }

        // Call to render the template
        Theme::Set('header_text', __('Modules'));
        Theme::Set('form_fields', array());
        Theme::Render('grid_render');
    }

    /**
     * A grid of modules
     */
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
        $SQL .= '   ImageUri, ';
        $SQL .= '   PreviewEnabled, ';
        $SQL .= '   assignable, ';
        $SQL .= '   settings ';
        $SQL .= '  FROM `module` ';
        $SQL .= ' ORDER BY Name ';

        if (!$modules = $db->GetArray($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the list of modules'), E_USER_ERROR);
        }

        $cols = array(
                array('name' => 'name', 'title' => __('Name')),
                array('name' => 'description', 'title' => __('Description')),
                array('name' => 'isregionspecific', 'title' => __('Library Media'), 'icons' => true),
                array('name' => 'validextensions', 'title' => __('Valid Extensions')),
                array('name' => 'imageuri', 'title' => __('Image Uri')),
                array('name' => 'preview_enabled', 'title' => __('Preview Enabled'), 'icons' => true),
                array('name' => 'assignable', 'title' => __('Assignable'), 'icons' => true, 'helpText' => __('Can this module be assigned to a Layout?')),
                array('name' => 'enabled', 'title' => __('Enabled'), 'icons' => true)
            );
        Theme::Set('table_cols', $cols);

        $rows = array();

        foreach($modules as $module)
        {
            $row = array();
            $row['moduleid'] = Kit::ValidateParam($module['ModuleID'], _INT);
            $row['name'] = Kit::ValidateParam($module['Name'], _STRING);
            $row['description'] = Kit::ValidateParam($module['Description'], _STRING);
            $row['isregionspecific'] = (Kit::ValidateParam($module['RegionSpecific'], _INT) == 0) ? 1 : 0;
            $row['validextensions'] = Kit::ValidateParam($module['ValidExtensions'], _STRING);
            $row['imageuri'] = Kit::ValidateParam($module['ImageUri'], _STRING);
            $row['enabled'] = Kit::ValidateParam($module['Enabled'], _INT);
            $row['preview_enabled'] = Kit::ValidateParam($module['PreviewEnabled'], _INT);
            $row['assignable'] = Kit::ValidateParam($module['assignable'], _INT);
            $row['settings'] = json_decode(Kit::ValidateParam($module['settings'], _HTMLSTRING), true);
            
            // Initialise array of buttons, because we might not have any
            $row['buttons'] = array();

            // If the module config is not locked, present some buttons
            if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') != 'Checked') {
                
                // Edit button
                $row['buttons'][] = array(
                        'id' => 'module_button_edit',
                        'url' => 'index.php?p=module&q=EditForm&ModuleID=' . $row['moduleid'],
                        'text' => __('Edit')
                    );
            }

            // Are there any buttons we need to provide as part of the module?
            if (isset($row['settings']['buttons'])) {
                foreach($row['settings']['buttons'] as $button) {
                    $button['text'] = __($button['text']);
                    $row['buttons'][] = $button;
                }
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * Edit Form
     */
    public function EditForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        // Can we edit?
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Module Config Locked'), E_USER_ERROR);

        $moduleId = Kit::GetParam('ModuleID', _GET, _INT);

        // Pull the currently known info from the DB
        $SQL = '';
        $SQL .= 'SELECT ModuleID, ';
        $SQL .= '   Module, ';
        $SQL .= '   Name, ';
        $SQL .= '   Enabled, ';
        $SQL .= '   Description, ';
        $SQL .= '   RegionSpecific, ';
        $SQL .= '   ValidExtensions, ';
        $SQL .= '   ImageUri, ';
        $SQL .= '   PreviewEnabled ';
        $SQL .= '  FROM `module` ';
        $SQL .= ' WHERE ModuleID = %d ';

        $SQL = sprintf($SQL, $moduleId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Module'));
        }

        $type = Kit::ValidateParam($row['Module'], _WORD);

        // Set some information about the form
        Theme::Set('form_id', 'ModuleEditForm');
        Theme::Set('form_action', 'index.php?p=module&q=Edit');
        Theme::Set('form_meta', '<input type="hidden" name="ModuleID" value="'. $moduleId . '" /><input type="hidden" name="type" value="' . $type . '" />');

        $formFields = array();
        $formFields[] = FormManager::AddText('ValidExtensions', __('Valid Extensions'), Kit::ValidateParam($row['ValidExtensions'], _STRING), 
            __('The Extensions allowed on files uploaded using this module. Comma Separated.'), 'e', '');

        $formFields[] = FormManager::AddText('ImageUri', __('Image Uri'), Kit::ValidateParam($row['ImageUri'], _STRING), 
            __('The Image to display for this module. This should be a path relative to the root of the installation.'), 'i', '');

        $formFields[] = FormManager::AddCheckbox('PreviewEnabled', __('Preview Enabled?'), 
            Kit::ValidateParam($row['PreviewEnabled'], _INT), __('When PreviewEnabled users will be able to see a preview in the layout designer'), 
            'p');

        $formFields[] = FormManager::AddCheckbox('Enabled', __('Enabled?'), 
            Kit::ValidateParam($row['Enabled'], _INT), __('When Enabled users will be able to add media using this module'), 
            'b');

        // Set any module specific form fields
        $module = ModuleFactory::create($type, $this->db, $this->user);

        // Merge in the fields from the settings
        foreach($module->ModuleSettingsForm() as $field)
            $formFields[] = $field;
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Module'), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Module', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ModuleEditForm").submit()');
        $response->Respond();
    }

    public function Edit()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();

        // Can we edit?
        if (Config::GetSetting('MODULE_CONFIG_LOCKED_CHECKB') == 'Checked')
            trigger_error(__('Module Config Locked'), E_USER_ERROR);

        $moduleId = Kit::GetParam('ModuleID', _POST, _INT);
        $type = Kit::GetParam('type', _POST, _WORD);
        $validExtensions = Kit::GetParam('ValidExtensions', _POST, _STRING, '');
        $imageUri = Kit::GetParam('ImageUri', _POST, _STRING);
        $enabled = Kit::GetParam('Enabled', _POST, _CHECKBOX);
        $previewEnabled = Kit::GetParam('PreviewEnabled', _POST, _CHECKBOX);

        // Validation
        if ($moduleId == 0 || $moduleId == '')
            trigger_error(__('Module ID is missing'), E_USER_ERROR);

        if ($type == '')
            trigger_error(__('Type is missing'), E_USER_ERROR);

        if ($imageUri == '')
            trigger_error(__('Image Uri is a required field.'), E_USER_ERROR);

        // Process any module specific form fields
        $module = ModuleFactory::create($type, $this->db, $this->user);

        // Install Files for this module
        $module->InstallFiles();

        try {
            // Get the settings (may throw an exception)
            $settings = json_encode($module->ModuleSettings());
            
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                UPDATE `module` SET ImageUri = :image_url, ValidExtensions = :valid_extensions, 
                    Enabled = :enabled, PreviewEnabled = :preview_enabled, settings = :settings 
                 WHERE ModuleID = :module_id');

            $sth->execute(array(
                    'image_url' => $imageUri,
                    'valid_extensions' => $validExtensions,
                    'enabled' => $enabled,
                    'preview_enabled' => $previewEnabled,
                    'settings' => $settings,
                    'module_id' => $moduleId
                ));
          
            $response->SetFormSubmitResponse(__('Module Edited'), false);
            $response->Respond();
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            trigger_error(__('Unable to update module'), E_USER_ERROR);
        }
    }

    /**
     * Edit Form
     */
    public function VerifyForm()
    {
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager(NULL, $user);

        // Set some information about the form
        Theme::Set('form_id', 'VerifyForm');
        Theme::Set('form_action', 'index.php?p=module&q=Verify');

        $formFields = array();
        $formFields[] = FormManager::AddMessage(__('Verify all modules have been installed correctly by reinstalling any module related files'));
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Verify'), '350px', '325px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Module', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Verify'), '$("#VerifyForm").submit()');
        $response->Respond();
    }

    public function Verify()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);

        $response = new ResponseManager();

        try {
            $dbh = PDOConnect::init();
        
            $dbh->exec('UPDATE `media` SET valid = 0 WHERE moduleSystemFile = 1');
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }

        Media::installAllModuleFiles();

        $response->SetFormSubmitResponse(__('Verified'), false);
        $response->Respond();
    }

    public function Install()
    {
        // Module file name
        $file = Kit::GetParam('module', _GET, _STRING);

        if ($file == '')
            trigger_error(__('Unable to install module'), E_USER_ERROR);

        Debug::LogEntry('audit', 'Request to install Module: ' . $file, 'module', 'Install');

        // Check that the file exists
        if (!file_exists($file))
            trigger_error(__('File does not exist'), E_USER_ERROR);

        // Make sure the file is in our list of expected module files
        $files = glob('modules/*.module.php');
        
        if (!in_array($file, $files))
            trigger_error(__('Not a module file'), E_USER_ERROR);

        // Load the file
        include_once($file);

        $type = str_replace('modules/', '', $file);
        $type = str_replace('.module.php', '', $type);

        // Load the module object inside the file
        if (!class_exists($type))
            trigger_error(__('Module file does not contain a class of the correct name'), E_USER_ERROR);

        try {
            Debug::LogEntry('audit', 'Validation passed, installing module.', 'module', 'Install');
            $moduleObject = ModuleFactory::create($type, $this->db, $this->user);
            $moduleObject->InstallOrUpdate();
        }
        catch (Exception $e) {
            trigger_error(__('Unable to install module'), E_USER_ERROR);
        }

        Debug::LogEntry('audit', 'Module Installed: ' . $file, 'module', 'Install');

        // Excellent... capital... success
        $response = new ResponseManager();
        $response->refresh = true;
        $response->refreshLocation = 'index.php?p=module';
        $response->Respond();
    }
    
    /**
     * What action to perform?
     * @return
     */
    public function Exec()
    {
        // What module has been requested?
        $method = Kit::GetParam('method', _REQUEST, _WORD);
        $raw = Kit::GetParam('raw', _REQUEST, _WORD);

        if (method_exists($this->module, $method))
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
}
?>
