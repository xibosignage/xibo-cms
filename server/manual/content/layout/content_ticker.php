<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and / or modify
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
<h1 id="Ticker">Ticker</h1>
	
<p>The Ticker module allows dynamic Feed and DataSet content to be added to a Layout. The Ticker module primarily consists of a data source location and a template to apply to each data item retrieved from that data source.</p>

<p>For example, if the data source is a RSS Feed, the feed will have multiple items and the template will be applied to each of these items.</p>

<p>Tickers are specific to a layout rather than saved in the library - this means that text items are not reusable and will need to be copied / pasted between Layouts if the same text is required on more than one.</p>

<h2>Adding a Ticker</h2>
<p>When adding a new Ticker the CMS provides a simple form asking for the Data Source and duration of the Ticker. These items cannot be changed without re-creating the Ticker.</p>

<p><img class="img-thumbnail" alt="Add Ticker Form" src="content/layout/Ss_layout_designer_add_ticker.png"></p>

<dl class="dl-horizontal">
	<dt>Source Type</dt>
	<dd>Whether the Ticker uses a Feed (RSS / Atom / XML) or a CMS DataSet as its Data Source.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Feed URL</dt>
	<dd>If using a Feed, the URL of the Feed.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>DataSet</dt>
	<dd>If using a DataSet, the DataSet to use.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Duration</dt>
	<dd>The Duration this media item should be shown in the Timeline. In seconds.</dd>
</dl>

<p>After choosing the Source of the Ticker the Edit form will automatically open, providing access to the other options.</p>

<h2>Editing</h2>
<p>All Tickers have some common settings in the CMS, regardless of the Data Source.</p>

<dl class="dl-horizontal">
	<dt>Direction</dt>
	<dd>Tickers can be scrolling left / right / top and bottom. Tickers can also be static using the "None" direction and can be split into pages that are automatically cycled using the "Single" mode.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Duration</dt>
	<dd>The duration in seconds that this text item should remain in the Region.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Duration is per Item</dt>
	<dd>If the Duration is per Item the configured Duration will be extended by the Number of Items that is returned by the Data Source. This should be used carefully as it can create long running media items. Typically it is preferable to use this setting in conjunction with a setting to limit the number of items shown.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Scroll Speed</dt>
	<dd>Speed up or slow down the scroll rate - assuming you have a Direction selected.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Fix text to region?</dt>
	<dd>Should the text resize to fill the entire available space in the Region? This option should only be used for a single line text item. The default behaviour is to scale the text to fill the Display Client resolution.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Update Interval</dt>
	<dd><?php echo PRODUCT_NAME; ?> Display Clients can cache the content of this media type to prevent repeated download of identical resources. They are also cached for off-line playback. </dd>
</dl>

<dl class="dl-horizontal">
	<dt>Items per Page</dt>
	<dd>When using "Single" mode, how many items should appear on each page.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Show side by Side</dt>
	<dd>When there is more than 1 item per page, should the items be shown side-by-side.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Style Sheet</dt>
	<dd>An optional style sheet to apply to the entire Ticker.</dd>
</dl>

<p class="alert alert-info">The Ticker Edit form has minor differences depending on the Data Source of the Ticker that is being edited.</p>

<h3>Feed</h3>
<p><img class="img-thumbnail" alt="Edit Feed Ticker Form" src="content/layout/timeline_ticker_feed_edit.png"></p>

<dl class="dl-horizontal">
	<dt>from the</dt>
	<dd>Where should items be taken from? Used in conjunction with the "Number of Items" setting.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Copyright</dt>
	<dd>Should the Display Client insert a Copyright notice at the end of the feed.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Substitutions</dt>
	<dd>The available keywords to use in the template that will be substituted with content from the feed. The CMS supports a set of default keywords that will be present of the majority of feeds. A special notation is also available where the user can specify the Tag|Namespace within the feed for <?php echo PRODUCT_NAME; ?> to extract content.</dd>
</dl>

<p class="alert alert-info">The Available Substitutions can be double clicked to automatically insert them into the Template editing area.</p>

<h3>Data Set</h3>
<p><img class="img-thumbnail" alt="Edit Feed Ticker Form" src="content/layout/timeline_ticker_dataset_edit.png"></p>

<dl class="dl-horizontal">
	<dt>Order</dt>
	<dd>An Order by clause using SQL syntax that should be applied to the Data Source. e.g. Name DESC</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Filter</dt>
	<dd>A filtering clause using SQL syntax that should be applied to the Data Source. e.g. Region = 'Europe'</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Upper Row Limit</dt>
	<dd>The upper row count (0 = unlimited)</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Lower Row Limit</dt>
	<dd>The lower row count (0 = unlimited)</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Substitutions</dt>
	<dd>The available columns to use in the template that will be substituted with content from the DataSet Columns. The CMS will look up the columns for the DataSet and present a set to substitutions. These should be double clicked to add into the template.</dd>
</dl>

<h2>Optional Style sheet</h2>
<p>The Optional Style sheet is applied to the entire Ticker media item when shown on the Display Clients. This is intended for advanced use to "tweak" the CMS generated output.</p>
