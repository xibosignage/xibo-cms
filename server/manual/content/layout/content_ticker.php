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

<p>After choosing the type of Ticker the Edit form will automatically open, providing access to the other options.</p>

<h3>

	<p>The add ticker form is similar to the text form. It is used to add an RSS feed into your layout. An RSS feed can 
	be used to get up-to-date information from a variety of sources on the internet 
	e.g. http://newsrss.bbc.co.uk/rss/newsonline_world_edition/asia-pacific/rss.xml. There are a couple of additional
	options which are required.</P>

	

	<p> An RSS feed has a couple of default tags. Each section takes on the properties that you set for each keyword. 
	So if you make [Date] red, then your RSS feeds date will appear red.</p>

	<ul>
		<li>[Date]<br />
		This item is used to style the time and date of the story in a RSS feed.</li>

  		<li>[Title]<br />
		This item is used to extract the title from an RSS story.</li>

		<li>[Description]<br />
		This item can be used to style the description of the RSS story. This text provides a more detailed overview of an RSS title.</li>
	</ul>

	<p>Any of these options can be removed and the contents will not be shown. Therefore if you just want the titles of the RSS feed, 
	you just need to include the [Title] tag in the text window.</p>
