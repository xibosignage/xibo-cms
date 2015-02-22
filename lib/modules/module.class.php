<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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

abstract class Module implements ModuleInterface
{
    // Module Information
    /**
     * @var \Xibo\Entity\Module $module
     */
    protected $module;

    /**
     * @var \Xibo\Entity\Widget $widget Widget
     */
    private $widget;

    /**
     * @var PermissionManager $auth Widget Permissions
     */
    protected $auth;

    // The Schema Version of this code
    protected $codeSchemaVersion = -1;

    /**
     * Set the Widget
     * @param \Xibo\Entity\Widget $widget
     */
    final public function setWidget($widget)
    {
        $this->widget = $widget;
    }

    /**
     * Set Widget Permissions
     * @param PermissionManager $auth
     */
    final public function setPermissions($auth)
    {
        $this->auth = $auth;
    }

    /**
     * Set the Module
     * @param \Xibo\Entity\Module $module
     */
    final public function setModule($module)
    {
        $this->module = $module;
    }

    /**
     * Save the Module
     */
    protected final function saveSettings()
    {
        // Save
        try {
            $this->module->save();
        }
        catch (Exception $e) {
            trigger_error(__('Cannot Save Settings'), E_USER_ERROR);
        }
    }

    /**
     * Set Option
     * @param string $name
     * @param string $value
     */
    final protected function SetOption($name, $value)
    {
        $this->widget->setOptionValue($name, 'attrib', $value);
    }

    /**
     * Get Option or Default
     * @param string $name
     * @param mixed[Optional] $default
     * @return mixed
     */
    final protected function GetOption($name, $default = null)
    {
        return $this->widget->getOptionValue($name, $default);
    }

    /**
     * Get Raw Node Value
     * @param $name
     * @param $default
     * @return mixed
     */
    final protected function getRawNode($name, $default)
    {
        return $this->widget->getOptionValue($name, $default);
    }

    /**
     * Set Raw Node Value
     * @param $name
     * @param $value
     */
    final protected function setRawNode($name, $value)
    {
        $this->widget->setOptionValue($name, 'cdata', $value);
    }

    /**
     * Get WidgetId
     * @return int
     */
    final protected function getWidgetId()
    {
        return $this->widget->widgetId;
    }

    /**
     * Save the Widget
     */
    final protected function saveWidget()
    {
        $this->widget->save();
    }

    /**
     * Default Edit Form
     */
    public function EditForm()
    {
        $this->baseEditForm();
    }

    /**
     * Basic Edit Form
     * @param array $extraFormFields
     */
    public function baseEditForm($extraFormFields = null)
    {
        $response = new ResponseManager();

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->module->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="regionId" value="' . Kit::GetParam('regionId', _POST, _INT) . '"><input type="hidden" id="widgetId" name="widgetId" value="' . $this->getWidgetId() . '">');

        $formFields = array();

        $formFields[] = FormManager::AddText('name', __('Name'), $this->GetOption('name'),
            __('The Name of this item - Leave blank to use the file name'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->widget->duration,
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        // Add in any extra form fields we might have provided
        if ($extraFormFields != NULL && is_array($extraFormFields)) {
            foreach($extraFormFields as $field) {
                $formFields[] = $field;
            }
        }

        Theme::Set('form_fields', $formFields);

        // Generate the Response
        $response->html = Theme::RenderReturn('form_render');
        $response->dialogTitle = sprintf(__('Edit %s'), $this->module->name);

        if (Kit::GetParam('designer', _REQUEST, _INT) == 1)
            $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&q=RegionOptions&regionid=' . Kit::GetParam('regionId', _POST, _INT) . '")');
        else
            $response->AddButton(__('Cancel'), 'XiboDialogClose()');

        $response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        $response->Respond();
    }

    /**
     * Default Edit Form
     * @throws Exception
     */
    public function EditMedia()
    {
        $response = new ResponseManager();

        // Can this user delete?
        if (!$this->auth->edit)
            throw new Exception('You do not have permission to delete this media.');

        $this->widget->duration = Kit::GetParam('duration', _POST, _INT, $this->widget->duration);
        $this->SetOption('name', Kit::GetParam('name', _POST, _STRING, $this->GetOption('name')));

        // Save the widget
        $this->widget->save();

        // Return
        $response->SetFormSubmitResponse(__('The Widget has been Edited'));
        $response->loadForm = true;
        $response->loadFormUri= 'index.php?p=timeline&q=Timeline&regionid=' . Kit::GetParam('regionId', _POST, _INT);
        $response->Respond();
    }

    /**
     * Delete Form
     * All widgets are deleted in a generic way
     */
    public function DeleteForm()
    {
        $response = new ResponseManager();
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Media', 'Delete') . '")');

        // Can this user delete?
        if (!$this->auth->del)
            throw new Exception('You do not have permission to delete this media.');

        Theme::Set('form_id', 'MediaDeleteForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->module->type . '&q=Exec&method=DeleteMedia');
        Theme::Set('form_meta', '<input type="hidden" name="widgetId" value="' . $this->getWidgetId() . '"><input type="hidden" name"regionId" value="' . Kit::GetParam('regionId', _POST, _INT) . '">');
        $formFields = array(
            FormManager::AddMessage(__('Are you sure you want to remove this widget?')),
            FormManager::AddMessage(__('This action cannot be undone.')),
        );

        // If we have linked media items, should we also delete them?
        if (count($this->widget->mediaIds) > 0) {
            $formFields[] = FormManager::AddCheckbox('deleteMedia', __('Also delete from the Library?'), 0, __('This widget is linked to Media in the Library. Check this option to also delete that Media.'), 'd');
        }

        Theme::Set('form_fields', $formFields);
        $form = Theme::RenderReturn('form_render');

        $response->SetFormRequestResponse($form, __('Delete Widget'), '300px', '200px');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#LayoutDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Delete Widget
     */
    public function DeleteMedia()
    {
        $response = new ResponseManager();

        // Can this user delete?
        if (!$this->auth->del)
            throw new Exception('You do not have permission to delete this media.');

        // Delete associated media?
        if (Kit::GetParam('deleteMedia', _POST, _CHECKBOX) == 1) {
            $media = new Media();
            foreach ($this->widget->mediaIds as $mediaId) {
                $media->Delete($mediaId);
            }
        }

        // Delete the widget
        $this->widget->delete();

        // Return
        $response->SetFormSubmitResponse(__('The Widget has been Deleted'));
        $response->loadForm = true;
        $response->loadFormUri= 'index.php?p=timeline&q=Timeline&regionid=' . Kit::GetParam('regionId', _POST, _INT);
        $response->Respond();
    }

    /**
     * Get Name
     * @return string
     */
    public function GetName()
    {
        $name = '';
        if (count($this->widget->mediaIds) > 0) {
            $media = new Media();
            $name = $media->getName($this->widget->mediaIds[0]);
        }

        return $this->GetOption('name', $name);
    }

    /**
     * Preview code for a module
     * @param double $width
     * @param double $height
     * @param int[Optional] $scaleOverride
     * @return string
     */
    public function Preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return '<div style="text-align:center;"><img alt="' . $this->module->type . ' thumbnail" src="theme/default/img/forms/' . $this->module->type . '.gif" /></div>';
            
        return $this->PreviewAsClient($width, $height, $scaleOverride);
    }

    /**
     * Preview as the Client
     * @param double $width
     * @param double $height
     * @param int[Optional] $scaleOverride
     * @return string
     */
    public function PreviewAsClient($width, $height, $scaleOverride = 0)
    {
        $widthPx = $width .'px';
        $heightPx = $height .'px';

        return '<iframe scrolling="no" src="index.php?p=module&mod=' . $this->module->type . '&q=Exec&method=GetResource&raw=true&preview=true&scale_override=' . $scaleOverride . '&widgetId=' . $this->getWidgetId() . '&width=' . $width . '&height=' . $height . '" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
    }

    /**
     * Default code for the hover preview
     * @return string
     */
    public function HoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="well">';
        $output .= '<div class="preview-module-image"><img alt="' . __($this->module->name) . ' thumbnail" src="theme/default/img/forms/' . $this->module->type . '.gif" /></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . __('Type') . ': ' . $this->displayType . '</li>';
        $output .= '    <li>' . __('Name') . ': ' . $this->GetName() . '</li>';
        $output .= '    <li>' . __('Duration') . ': ' . $this->widget->duration . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Default Get Resource
     * @param int $displayId
     * @return bool
     */
    public function GetResource($displayId = 0)
    {
        return false;
    }
    
    /**
     * Form to Edit a transition
     */
    public function TransitionEditForm()
    {
        $response = new ResponseManager();

        if (!$this->auth->edit)
        {
            $response->SetError('You do not have permission to edit this media.');
            $response->keepOpen = false;
            return $response;
        }
        
        // Are we dealing with an IN or an OUT
        $type = Kit::GetParam('type', _REQUEST, _WORD);
        $transition = '';
        $duration = '';
        $direction = '';

        switch ($type)
        {
            case 'in':
                $transition = $this->GetOption('transIn');
                $duration = $this->GetOption('transInDuration', 0);
                $direction = $this->GetOption('transInDirection');
                
                break;
            
            case 'out':
                $transition = $this->GetOption('transOut');
                $duration = $this->GetOption('transOutDuration', 0);
                $direction = $this->GetOption('transOutDirection');
                
                break;
            
            default:
                trigger_error(_('Unknown transition type'), E_USER_ERROR);
        }
        
        // Add none to the list
        $transitions = $this->user->TransitionAuth($type);
        $transitions[] = array('code' => '', 'transition' => 'None', 'class' => '');
        
        // Compass points for direction
        $compassPoints = array(
            array('id' => 'N', 'name' => __('North')), 
            array('id' => 'NE', 'name' => __('North East')), 
            array('id' => 'E', 'name' => __('East')), 
            array('id' => 'SE', 'name' => __('South East')), 
            array('id' => 'S', 'name' => __('South')), 
            array('id' => 'SW', 'name' => __('South West')), 
            array('id' => 'W', 'name' => __('West')),
            array('id' => 'NW', 'name' => __('North West'))
        );
        
        Theme::Set('form_id', 'TransitionForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->module->type . '&q=Exec&method=TransitionEdit');
        Theme::Set('form_meta', '
            <input type="hidden" name="type" value="' . $type . '">
            <input type="hidden" name="widgetId" value="' . $this->getWidgetId() . '">
            ');

        $formFields[] = FormManager::AddCombo(
                    'transitionType', 
                    __('Transition'), 
                    $transition,
                    $transitions,
                    'code',
                    'transition',
                    __('What transition should be applied when this region is finished?'), 
                    't');

        $formFields[] = FormManager::AddNumber('transitionDuration', __('Duration'), $duration,
            __('The duration for this transition, in milliseconds.'), 'l', '', 'transition-group');
        
        $formFields[] = FormManager::AddCombo(
                    'transitionDirection', 
                    __('Direction'), 
                    $direction,
                    $compassPoints,
                    'id',
                    'name',
                    __('The direction for this transition. Only appropriate for transitions that move, such as Fly.'),
                    'd',
                    'transition-group transition-direction');

        // Add some dependencies
        $response->AddFieldAction('transitionType', 'init', '', array('.transition-group' => array('display' => 'none')));
        $response->AddFieldAction('transitionType', 'init', '', array('.transition-group' => array('display' => 'block')), 'not');
        $response->AddFieldAction('transitionType', 'change', '', array('.transition-group' => array('display' => 'none')));
        $response->AddFieldAction('transitionType', 'change', '', array('.transition-group' => array('display' => 'block')), 'not');

        // Decide where the cancel button will take us
        if (Kit::GetParam('designer', _REQUEST, _INT))
            $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&regionid=' . Kit::GetParam('regionId', _REQUEST, _INT) . '&q=RegionOptions")');
        else
            $response->AddButton(__('Cancel'), 'XiboDialogClose()');

        // Always include the save button
        $response->AddButton(__('Save'), '$("#TransitionForm").submit()');
        
        // Output the form and dialog
        Theme::Set('form_fields', $formFields);
        $response->html = Theme::RenderReturn('form_render');
        $response->dialogTitle = sprintf(__('Edit %s Transition for %s'), $type, $this->module->name);
        $response->dialogSize = true;
        $response->dialogWidth = '450px';
        $response->dialogHeight = '280px';
        
        return $response;
    }
    
    /**
     * Edit a transition
     */
    public function TransitionEdit()
    {
        $response = new ResponseManager();

        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this media.'));
        
        // Get the transition type
        $transitionType = Kit::GetParam('transitionType', _POST, _WORD);
        $duration = Kit::GetParam('transitionDuration', _POST, _INT, 0);
        $direction = Kit::GetParam('transitionDirection', _POST, _WORD, '');
        $type = Kit::GetParam('type', _REQUEST, _WORD);
        
        switch ($type)
        {
            case 'in':
                $this->SetOption('transIn', $transitionType);
                $this->SetOption('transInDuration', $duration);
                $this->SetOption('transInDirection', $direction);
                
                break;
            
            case 'out':
                $this->SetOption('transOut', $transitionType);
                $this->SetOption('transOutDuration', $duration);
                $this->SetOption('transOutDirection', $direction);
                
                break;
            
            default:
                trigger_error(_('Unknown transition type'), E_USER_ERROR);
        }
        
        // This saves the Media Object to the Region
        $this->saveWidget();
        
        if (Kit::GetParam('designer', _REQUEST, _INT))
        {
            // We want to load a new form
            $response->loadForm = true;
            $response->loadFormUri = 'index.php?p=timeline&regionid=' . Kit::GetParam('regionId', _REQUEST, _INT) . '&q=RegionOptions';
        }

        return $response;
    }
    
    /**
     * Get the the Transition for this media
     * @param string $type Either "in" or "out"
     * @return string
     */
    public function GetTransition($type)
    {

        switch ($type)
        {
            case 'in':
                $code = $this->GetOption('transIn');
                break;
            
            case 'out':
                $code = $this->GetOption('transOut');
                break;
            
            default:
                $code = '';
                trigger_error(_('Unknown transition type'), E_USER_ERROR);
        }
        
        if ($code == '')
            return __('None');
        
        // Look up the real transition name
        $transition = $this->user->TransitionAuth('', $code);
        
        return __($transition[0]['transition']);
    }

    /**
     * Default behaviour for install / upgrade
     * this should be overridden for new modules
     */
    public function InstallOrUpdate() {

        if ($this->module->renderAs != 'native')
            throw new Exception(__('Module must implement InstallOrUpgrade'));

        return true;
    }

    /**
     * Installs any files specific to this module
     */
    public function InstallFiles()
    {

    }

    public function InstallModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings) {
        
        Debug::LogEntry('audit', 'Request to install module with name: ' . $name, 'module', 'InstallModule');

        try {
            // Validate some things.
            if ($this->type == '')
                throw new Exception(__('Module has not set the module type'));

            if ($name == '')
                throw new Exception(__('Module has not set the module name'));

            if ($description == '')
                throw new Exception(__('Module has not set the description'));

            if (!is_numeric($previewEnabled))
                throw new Exception(__('Preview Enabled variable must be a number'));

            if (!is_numeric($assignable))
                throw new Exception(__('Assignable variable must be a number'));

            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                    INSERT INTO `module` (`Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, 
                        `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`) 
                    VALUES (:module, :name, :enabled, :region_specific, :description, 
                        :image_uri, :schema_version, :valid_extensions, :preview_enabled, :assignable, :render_as, :settings);
                ');

            Debug::LogEntry('audit', 'Executing SQL', 'module', 'InstallModule');

            $sth->execute(array(
                    'module' =>  $this->module->type,
                    'name' =>  $name,
                    'enabled' =>  1,
                    'region_specific' =>  1,
                    'description' =>  $description, 
                    'image_uri' =>  $imageUri,
                    'schema_version' =>  $this->codeSchemaVersion,
                    'valid_extensions' =>  '',
                    'preview_enabled' =>  $previewEnabled,
                    'assignable' =>  $assignable,
                    'render_as' =>  'html',
                    'settings' => json_encode($settings)
                ));
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage());
        
            throw new Exception(__('Unable to install module. Please check the Error Log'));
        }
    }

    public function UpgradeModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings) {
        
        try {
            // Validate some things.
            if ($this->module->moduleId == '')
                throw new Exception(__('This module does not exist - should you have called Install?'));

            if ($name == '')
                throw new Exception(__('Module has not set the module name'));

            if ($description == '')
                throw new Exception(__('Module has not set the description'));

            if (!is_numeric($previewEnabled))
                throw new Exception(__('Preview Enabled variable must be a number'));

            if (!is_numeric($assignable))
                throw new Exception(__('Assignable variable must be a number'));

            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                    UPDATE `module` SET `Name` = :name, `Description` = :description, 
                        `ImageUri` = :image_uri, `SchemaVersion` = :schema_version, `PreviewEnabled` = :preview_enabled, 
                        `assignable` = :assignable, `settings` = :settings
                     WHERE ModuleID = :module_id
                ');

            $sth->execute(array(
                    'name' =>  $name,
                    'description' =>  $description, 
                    'image_uri' =>  $imageUri,
                    'schema_version' =>  $this->codeSchemaVersion,
                    'preview_enabled' =>  $previewEnabled,
                    'assignable' =>  $assignable,
                    'settings' => $settings,
                    'module_id' => $this->module->moduleId
                ));
        }
        catch (Exception $e) {
            
            Debug::Error($e->getMessage());

            throw $e;
        }
    }

    /**
     * Form for updating the module settings
     */
    public function ModuleSettingsForm() {
        return array();
    }

    /**
     * Process any module settings
     */
    public function ModuleSettings() {
        return array();
    }

    /**
     * Updates the settings on the module
     * @param array $settings The Settings
     * @throws InvalidArgumentException
     */
    public function UpdateModuleSettings($settings) {
        if (!is_array($settings))
            throw new InvalidArgumentException(__('Module settings must be an array'));

        // Update the settings on the module record.
        $dbh = PDOConnect::init();

        $sth = $dbh->prepare('UPDATE `module` SET settings = :settings WHERE ModuleID = :module_id');
        $sth->execute(array(
            'settings' => json_encode($settings),
            'module_id' => $this->module_id
        ));
    }

    /**
     * Get Module Setting
     * @param string $setting
     * @param mixed $default
     * @return mixed
     */
    public function GetSetting($setting, $default = NULL) {
        if (isset($this->module->settings[$setting]))
            return $this->module->settings[$setting];
        else
            return $default;
    }
}
