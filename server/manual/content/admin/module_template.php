<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Daniel Garner
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
?>
<p class="alert alert-info">This documentation applies to <?php echo PRODUCT_NAME; ?> 1.7 and greater.</p>
<h1>Module Template</h1>
<p>The Module Template can be used as a guide to creating a simple module. The anatomy of the template is examined below and the module_template file shipped with the product is commented thoroughly.</p>

<h2>Licence Notice</h2>
<p>All <?php echo PRODUCT_NAME; ?> modules must be released under a licence that is compatible with the AGPLv3. This Licence notice, as well as any Copyright information for the author should be placed at the top of the file and any companion files.</p>
<pre>
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Daniel Garner
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
 * along with Xibo.  If not, see http://www.gnu.org/licenses/.
 */ 
</pre>

<h2>The Class Declaration and Constructor</h2>
<p>Modules are loaded by the framework dynamically and should therefore adhere to the naming convention. The file name should be <code>moduletemplate.module.php</code> where <code>moduletemplate</code> is the class name. The Class must extend the Module class.</p>

<p>The constructor of the class must conform to the below specification and must set the module type to be the same as the class name.</p>

<pre>
class moduletemplate extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '') {
        // The Module Type must be set - this should be a unique text string of no more than 50 characters.
        // It is used to uniquely identify the module globally.
        $this->type = 'moduletemplate';

        // This is the code schema version, it should be 1 for a new module and should be incremented each time the 
        // module data structure changes.
        // It is used to install / update your module and to put updated modules down to the display clients.
        $this->codeSchemaVersion = 1;
        
        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }
</pre>
<p>The codeSchemaVersion is used for upgrading the module in place, should the author release a new version the number should be incremented.</p>

<h2>Installing and Updating</h2>
<p>Each Module must have a record in the <code>module</code> table. This can be automatically added and changed using the InstallOrUpdate method in the class.</p>
<pre>
/**
 * Install or Update this module
 */
public function InstallOrUpdate() {
    // This function should update the `module` table with information about your module.
    // The current version of the module in the database can be obtained in $this->schemaVersion
    // The current version of this code can be obtained in $this->codeSchemaVersion
    
    // $settings will be made available to all instances of your module in $this->settings.
    // These are global settings to your module, 
    // not instance specific (i.e. not settings specific to the layout you are adding the module to).
    // $settings will be collected from the Administration -> Modules CMS page.
    // 
    // Layout specific settings should be managed with $this->SetOption in your add / edit forms.
    
    if ($this->schemaVersion <= 1) {
        // Install
        // Call "$this->InstallModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)"
    }
    else {
        // Update
        // Call "$this->UpdateModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings)"
        // with the updated items
    }

    // After calling either Install or Update your code schema version will match the database schema version
    // and this method will not be called again. 
    // This means that if you want to change those fields in an update to your module, 
    // you will need to increment your codeSchemaVersion.
}
</pre>
<p>The <code>$this->schemaVersion</code> variable can be compared to the <code>$this->codeSchemaVersion</code> to determine the install or update actions required.</p>

<h2>Adding and Editing on a Layout</h2>
<p>Each module should provide a method to display add / edit forms and to save the module to the Layout. There are 4 methods that should be implemented to provide this functionality:
<ul>
    <li>AddForm</li>
    <li>AddMedia</li>
    <li>EditForm</li>
    <li>EditMedia</li>
</ul>
</p>

<h3>The response object</h3>
<p>All module forms in <?php echo PRODUCT_NAME; ?> are requested and rendered via AJAX and a helper object called Response Manager is included to facilitate the AJAX communication. All content that is returned to the browser is done through the <code>$this-&gt;response</code> object.</p>

<p>The response object is the last item to be returned from any of the above 4 calls.</p>
<pre>
// The response must be returned.
return $this->response;
</pre>

<p>The response object can take a number of actions once the request has completed, for example loading another form.</p>

<h3>Permissions</h3>
<p>When editing a module the permissions of the logged in user should be validated.</p>
<pre>
// Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
if (!$this->auth->edit) {
    $this->response->SetError('You do not have permission to edit this assignment.');
    $this->response->keepOpen = false;
    return $this->response;
}
</pre>

<h3>Using the Theme Class</h3>
<p>The Theme class should always be used to provide the end user with the option of Theming your form. Module Theme files are stored in <code>modules/theme</code> rather than the <code>theme/html/</code> folder.</p>

<p>The application will always try to load a template specific theme file before any module specific file provided with the module.</p>

<p>Forms will usually pass the same set of core data to the theme file for rendering.</p>
<pre>
Theme::Set('form_id', 'ModuleForm');
Theme::Set('form_action', 'index.php?p=module&mod=' . $this-&gt;type . '&q=Exec&method=AddMedia');
Theme::Set('form_meta', '
    &lt;input type="hidden" name="layoutid" value="' . $this-&gt;layoutid . '"&gt;
    &lt;input type="hidden" id="iRegionId" name="regionid" value="' . $this-&gt;regionid . '"&gt;
    &lt;input type="hidden" name="showRegionOptions" value="' . $this-&gt;showRegionOptions . '" />
    ');
</pre>
<p>The template file is then rendered with a call to <code>$this-&gt;response->html = Theme::RenderReturn('media_form_text_add');</code>

<h3>Validating Input</h3>
<p>Input should be validated using the Kit class GetParam method.</p>
<pre>
$variable = Kit::GetParam('duration', _POST, _INT, 0);
</pre>

<h3>Getting and Setting options on the XLF</h3>
<p>Data associated with the configuration of the module can either be set as "options" or "raw" content in the XLF. In most cases an option is sufficient.</p>
<pre>
$this->SetOption('name', $value);
</pre>
<p>Options can be retrieved by name.</p>
<pre>
$value = $this->GetOption('name');
</pre>

<h2>Preview</h2>
<p>In most cases the preview should be handled exactly as the client would handle the preview. There is a helper method to make the necessary framework calls.</p>
<pre>
return $this->PreviewAsClient($width, $height);
</pre>

<h2>GetResource</h2>
<p>When it comes time to render the content on the Display client it is the CMS responsibility to download a fully rendered HTML page containing all of the information required to display the module.</p>

<h3>HTML Templates</h3>
<p>A HTML template should be used as the starting point for all rendered module output. The template is run in isolate on the client and shouldn't rely on any resources that might not be available. For example on-line resources that would not be present if the client is running off-line.</p>

<p>A standard template is provided for convenience.</p>

<pre>
&lt;!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"&gt;
&lt;html lang="en"&gt;
    &lt;head&gt;
        &lt;title&gt;Xibo Open Source Digital Signage&lt;/title&gt;
        &lt;meta http-equiv="X-UA-Compatible" content="IE=edge" /&gt;
        &lt;meta name="viewport" content="width=[[ViewPortWidth]], user-scalable=no, initial-scale=1.0, target-densitydpi=device-dpi" /&gt;
        &lt;meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /&gt;
        &lt;!-- Copyright 2006-2014 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. --&gt;
        &lt;!--[[[HEADCONTENT]]]--&gt;
    &lt;/head&gt;
    &lt;!--[if lt IE 7 ]&gt;&lt;body class="ie6"&gt;&lt;![endif]--&gt;
    &lt;!--[if IE 7 ]&gt;&lt;body class="ie7"&gt;&lt;![endif]--&gt;
    &lt;!--[if IE 8 ]&gt;&lt;body class="ie8"&gt;&lt;![endif]--&gt;
    &lt;!--[if IE 9 ]&gt;&lt;body class="ie9"&gt;&lt;![endif]--&gt;
    &lt;!--[if (gt IE 9)|!(IE)]&gt;&lt;!--&gt;&lt;body&gt;&lt;!--&lt;![endif]--&gt;
        &lt;!--[[[BODYCONTENT]]]--&gt;
    &lt;/body&gt;
    &lt;!--[[[JAVASCRIPTCONTENT]]]--&gt;
&lt;/html&gt;
&lt;!--[[[CONTROLMETA]]]--&gt;
</pre>

<p>The template contains place holders that should be substituted by the GetResource method.</p>

<p>The <code>[[ViewPortWidth]]</code> place holder is used by the client at run time.</p>

<p>Vendor libraries have been provided and can be loaded into the JAVASCRIPTCONTENT place holder.</p>
<pre>
$javaScriptContent  = '&lt;script&gt;' . file_get_contents('modules/preview/vendor/jquery-1.11.1.min.js') . '&lt;/script&gt;';

// Replace the After body Content
$template = str_replace('&lt;!--[[[JAVASCRIPTCONTENT]]]--&gt;', $javaScriptContent, $template);
</pre>

<h3>Altering the Duration at Run Time</h3>
<p>The duration of the running media can be altered at run time on the client by providing an override to the <code>CONTROLMETA</code> place holder.</p>
<pre>
$template = str_replace('&lt;!--[[[CONTROLMETA]]]--&gt;', '&lt;!-- NUMITEMS=' . $pages . ' --&gt;' . PHP_EOL . '&lt;!-- DURATION=' . $totalDuration . ' --&gt;', $template);
</pre>