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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<h1>CMS Settings</h1>
<p>Like any complex application the <?php echo PRODUCT_NAME; ?> CMS comes with a number of options. These are configured in the Administration menu, Settings sub menu.</p>

<p>The settings are organised into relevant categories which are accessible using the category selector on the left hand side.</p>

<p><img class="img-thumbnail" alt="Setting_Content" src="content/admin/settings_overview.png"></p>

<p>Changes to settings can be saved using the Save button in the category selector or at the top of the page.</p>

<h2>Categories</h2>
<p>The settings are split into related Categories: </p>
<dl>
    <dt>Configuration</dt>
    <dd>Essential configuration options that must be set.</dd>
</dl>
<dl>
    <dt>Content</dt>
    <dd>Settings for defaulting the durations of certain media items.</dd>
</dl>
<dl>
    <dt>Defaults</dt>
    <dd>Settings for defaulting certain form fields to particular selections.</dd>
</dl>
<dl>
    <dt>Displays</dt>
    <dd>Settings related to Displays and display management pages.</dd>
</dl>
<dl>
    <dt>General</dt>
    <dd>General settings for the behaviour of items in the CMS.</dd>
</dl>
<dl>
    <dt>Maintenance</dt>
    <dd>Settings related to the Maintenance Module.</dd>
</dl>
<dl>
    <dt>Network</dt>
    <dd>Network settings such as Proxy Server information (if your CMS is behind a proxy)</dd>
</dl>
<dl>
    <dt>Permissions</dt>
    <dd>Settings for controlling how permissions effect certain things in the CMS.</dd>
</dl>
<dl>
    <dt>Regional</dt>
    <dd>Timezone and Language regional settings.</dd>
</dl>
<dl>
    <dt>Troubleshooting</dt>
    <dd>Tab allows you to alter how <?php echo PRODUCT_NAME; ?> handles errors. You can turn the error and audit logs on and off. As logs records 
size growth rapidly, you should take care to enable it only when necessary e.g. during system debug.</dd>
</dl>
<dl>
    <dt>Users</dt>
    <dd>Authentication and Password policy settings.</dd>
</dl>

<h2>Notable Settings</h2>
<h3>User Password Management</h3>
<p>This section will take a look at the User Password Management with three features / improvements:</p>
<ul>
<li>Users can change their passwords without having access to the user administration page</li>
<li>Administrators can set a regular expression to test password complexity</li>
<li>Administrators can override users passwords in a more intuitive way</li>
</ul>

<h3>User Password Policy</h3>
<p>This feature has the utmost flexibility given to the administrator to test passwords in which ever way they chose. 
This is done with a setting in the &#8220;Permissions&#8221; tab called USER_PASSWORD_POLICY.</p>

<p>An administrator can put any valid regular expression in this box, which will cause all users password change requests (and new users)
to be tested against this expression.</p>
<p>It is also important for the user to know what the policy actually is; the setting USER_PASSWORD_ERROR which will be presented 
to the user when they enter a password that does not validate against the regular expression.</p>

<h3>Library Size &amp; Bandwidth</h3> 
<p>To manage limits on the library file size and monthly bandwidth usage. This is done by virtue of two settings in the database
table (LIBRARY_SIZE_LIMIT_KB &amp; MONTHLY_XMDS_TRANSFER_LIMIT_KB).</p> 

<p>If no limits are entered in database, everything continues to work without restriction; however once a limit is entered <?php echo PRODUCT_NAME; ?> will start 
validating against these limits, and when they are exceeded will prevent the upload of new media / updates to clients.</p>

<p>However these two settings are currently not available through the user interface; Only the statistical data is shown in Content page</p>

<h3>Maintenance</h3>
<p>Maintenance is a very import part of the system and therefore is covered in its <a href="index.php?toc=getting_started&p=admin/settings_maintenance">own section</a>.</p>

<h2>Other functions</h2>
<p>The settings page has a set of other functions available in the top right corner of the sub-menu bar.</p>

<h3>Import</h3>
<p>Import the entire CMS database.</p>

<h3>Export</h3>
<p>Export the entire CMS database.</p>

<h3>Tidy Library</h3>
<p>The library tidy function clears up orphaned media items and temporary files.</p>