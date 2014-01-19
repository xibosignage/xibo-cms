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
		
<h1>Modules</h1>
<p>All content displayed in <?php echo PRODUCT_NAME; ?> is added/edited and served by a media module. There are 13 media modules to choose from and more are being added as new releases are made.</p>

<p>Modules can be enabled and disabled using the CMS administrator menu, Modules sub menu. Each module also has a range of settings available.</p>

<p><img  class="img-thumbnail" alt="Module List" src="content/admin/sa_modules.png"></p>

<p>Each module has the following properties.</p>

<dl class="dl-horizontal">
    <dt>Name</dt>
    <dd>System given Name for the specific module.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Description</dt>
    <dd>A breif description of the module.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Library Media</dt>
    <dd>Indicated if the content is saved in the <?php echo PRODUCT_NAME; ?> Library. Unchecked indicate the specific content is only saved with the Layout-Regions.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Valid Extensions</dt>
    <dd>File extensions that are supported by the specific module.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Image Uri</dt>
    <dd>Link to the module icon for UI display - this can also be provided by your Theme.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Preview Enabled</dt>
    <dd>Should this module output a preview in the Layout Designer.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Enabled</dt>
    <dd>Can this module be used?</dd>
</dl>


<h2>Modules Settings</h2>
<p>A Module can have a selection of its settings adjusted, exactly which ones depends on the module.</p>

<h3>File based Modules</h3>
<p>At times it may be necessary to add or removed the allowed extensions on a particular file based module. A typical use case would be if a client is being used which does not support that particular type of file.</p>

<p><img class="img-thumbnail" alt="Modules" src="content/admin/sa_modules_video.png"></p>
