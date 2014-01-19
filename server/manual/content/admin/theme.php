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
<h1>Theme <small>CMS themes</small></h1>

<p>The CMS has a theme engine which currently covers 90% of the application. The theme engine has been designed with the principle of inheritance in mind, meaning that any resource requested by the CMS is passed through the currently active theme and if that theme does not contain the resource, the default theme resource is served.</p>

<p>This allows for a few minor modifications to have a majour impact on the User Interface.</p>

<p>Themes are modelled as sub-folders under the "theme" folder. The theme folder is contained in the root CMS installation folder. The default theme exists in a folder called "default".</p>

<p><img class="img-thumbnail" alt="Theme folder structure" src="content/admin/theme_folder_structure.png"></p>

<p>Each theme has a simple config file called <code>config.php</code> which sets the title for the theme and some other meta data, for example:</p>
<pre>
	$config = array(
		'theme_name' => 'Xibo Default Theme',
		'theme_title' => 'Xibo Digital Signage',
		'app_name' => 'Xibo',
		'theme_url' => 'http://www.xibo.org.uk'
	);
</pre>

<p>The current CMS theme is activated in the General CMS Settings and is called GLOBAL_THEME_NAME.</p>

<p class="alert alert-info">A typical use case for theming is to change the logo and application name. This can be done with a new sub-folder, a <code>config.php</code> file and a new logo image file in the img sub folder.</p>