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
<h1>DataSet Views</h1>
<p>The DataSet View module allows content from a DataSet to be added into a Region, organised and displayed in a tabular fashion.</p>

<p>When the underlying DataSet Data is changed, the view automatically updates with new Data.</p>

<p class="alert alert-warning">Once a DataSet has views care should be taken when editing the Column structure of the DataSet.</p>

<h2>Adding a DataSet View</h2>
<p>When adding a new DataSet View the CMS provides a simple form asking for the Data Source and duration. These items cannot be changed without re-creating the View.</p>

<p><img class="img-thumbnail" alt="Dataset Add View" src="content/layout/ss_layout_dataset_addview.png"></p>

<dl class="dl-horizontal">
	<dt>DataSet</dt>
	<dd>The DataSet to use as the Source of Data.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Duration</dt>
	<dd>The duration in seconds that this item should remain in the Region.</dd>
</dl>

<p>After choosing the Source of the View the Edit form will automatically open, providing access to the other options.</p>

<h2>Editing</h2>
	
<p><img class="img-thumbnail" alt="Dataset Add View" src="content/layout/ss_layout_dataset_view_edit.png"></p>

<dl class="dl-horizontal">
	<dt>Update Interval</dt>
	<dd><?php echo PRODUCT_NAME; ?> Display Clients can cache the content of this media type to prevent repeated download of identical resources. They are also cached for off-line playback. </dd>
</dl>

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
	<dt>Table Headings</dt>
	<dd>Show the column headings on the table, or have the table without headings.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Rows per Page</dt>
	<dd>Spilt the data into multiple pages that will be cycled.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Columns Selected</dt>
	<dd>An ordered list of Columns Selected for this View. Items can be dragged / dropped between lists and ordered within the same list.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Columns Available</dt>
	<dd>A list of Columns that are available for display.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Style Sheet</dt>
	<dd>A CSS Style Sheet to control the visual styling of the Table.</dd>
</dl>

<h2>Example Style Sheet</h2>    
<pre>
table.DataSetTable {
font-family:"Trebuchet MS", Arial, Helvetica, sans-serif;  
width:100%;
border-collapse:collapse;
}

tr.HeaderRow {
font-size:1.1em;
text-align:center;
padding-top:5px;
padding-bottom:4px;
background-color:#A7C942;
color:#ffffff;
}

tr#row_1 {
color:#000000;
background-color:#EAF2D3;
}

td#col_1 {
color:#000000;
background-color:#EAF2D3;
}

td.DataSetColumn {
color:#000000;
background-color:#EAF2D3;
border:1px solid #98bf21
}

tr.DataSetRow {
text-align:center;
color:#000000;
background-color:#EAF2D3;
border:1px solid #98bf21
padding-top:5px;
padding-bottom:4px;
}

th.DataSetColumnHeaderCell {
font-size:1em;
border:1px solid #98bf21;
padding:3px 7px 2px 7px;
}

span#1_1 {

}

span.DataSetColumnSpan {

}
</pre>    
    
<p>The resulting view will be showing the region preview window and displayed on the client. (example data)</p>

<p><img class="img-thumbnail" alt="Dataset Table" src="content/layout/ss_layout_dataset_table.png"></p>
