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
<h1>Library <small>Media Administration</small></h1>
<p>The CMS Library contains all file based media, such as Images and Video, and all table based data (Data Sets).</p>

<p>In <?php echo PRODUCT_NAME; ?>, library content is placed on layouts for display. The content library is a store of all the content that has been used on layouts in the past, and content to be used on new layouts.</p>

<p>Each piece of content is assigned a duration which is used at display time to determine when the item is finished.</p>

<h2>Navigation <small>Media / DataSets</small></h2>
<p><img class="img-thumbnail" alt="Library Sub Menu" src="content/content/library_submenu.png"></p>
<p>There are two types of content in the library, file based media and data set based. The secondary menu pictured above allows you to switch between the media view and the data set administration.</p>

<p class="alert alert-danger">Please be aware that <?php echo PRODUCT_NAME; ?> takes no measures to control what is put on your displays. It is your responsibility to ensure that any content is appropriate material for the audience and is either appropriately attributed or you own the rights to it.</p>

<h2>Media</h2>
<p>Selecting the media sub menu item will take you to the media library page. The library table comprises of all the content items that you have uploaded or created. You can also see any other content items that a user has shared within this table.</p>

<h3>Types <small>What is supported?</small></h3>
<p><?php echo PRODUCT_NAME; ?> supports a wide range of different file types - each is supported by one of 4 modules.</p>
<p><img class="img-thumbnail" alt="Library Modules" src="content/content/library_media_add.png"></p>
<dl class="dl-horizontal">
    <dt>Flash</dt>
    <dd>Flash content in a SWF file.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Image</dt>
    <dd>Image content in JPG/PNG/GIF/BMP files.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>PowerPoint</dt>
    <dd>PowerPoint presentations in PPT/PPTX files.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Video</dt>
    <dd>Video files in WMV/AVI/MP4.</dd>
</dl>

<p class="alert alert-info">Tip! From PowerPoint 2010 onwards it is possible to export PowerPoint to a WMV which provides better playback in <?php echo PRODUCT_NAME; ?></p>

<p class="alert alert-danger">Tip! Supported video formats may vary depending on the display client you are running and the hardware the client is installed on.</p>

<h3>Non-Library Content</h3>
<p>Not all types of content are saved in the <?php echo PRODUCT_NAME; ?> media library. Instead they are embbedded within the Layouts in which they are
assigned. These types of content are covered in the <a href="index.php?toc=layouts&p=layout/overview">Layouts</a> section.</p>

<p>Other types of content include:
	<ul>
		<li>Text</li>
		<li>Ticker (RSS)</li>
		<li>Embedded HTML and WebPages</li>
		<li>Counters</li>
		<li>Shell Commands</li>
	</ul>
</p>

<h3>Filtering the Results <small>Filter Form</small></h3>
<p>You can use the filter to search for the content item that you would like to view/modify. To open the filter form press the "filter" button on the top right of the library table.</p>

<p><img class="img-thumbnail" alt="Library Filter" src="content/content/library_media_filter.png"></p>

<dl class="dl-horizontal">
    <dt>Name</dt>
    <dd>Partial Name matching with the media items for display. Tip: Use + or - infront of words to include/exclude them from the results.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Owner</dt>
    <dd>List media items with the specified owner.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Type</dt>
    <dd>List specific media type i.e. all, image or video</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Retired</dt>
    <dd>List inactive media items in the library.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Duration</dt>
    <dd>Display the duration in seconds rather than text.</dd>
</dl>

<dl class="dl-horizontal">
    <dt>Keep Open</dt>
    <dd>Checkbox to indicate whether to keep this filter open with the selections currently made. Next time you visit this page the library will automatically be filtered.</dd>
</dl>

<h3>Sorting</h3>
<p>The library table can be sorted by clicking on any column heading.</p>

<h3>Action Menu</h3>
<p>Each row in the table has an action menu that provides various transactions that can occur on the media item represented by that row.</p>

<p><img class="img-thumbnail" alt="Action Menu" src="content/content/library_media_actionmenu.png"></p>

<p class="alert alert-info">The available actions are shown depending on the permissions your login account has against that item. If you would like extra permissions you should ask the Owner.</p>

<h2>DataSets</h2>
<p>Selecting the DataSets sub menu item will take you to the DataSets Library page. The library table comprises of all the DataSets you have permission to view/edit.<p>