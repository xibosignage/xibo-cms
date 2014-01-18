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

    <p>Once a DataSet has been defined, anyone with &#8220;View&#8221; permissions can use the DataSet on layouts. They are added 
    by selecting the &#8220;DataSet&#8221; button from a region Timeline, which presents a drop down list of all DataSets available 
    to the user as well as the usual duration field for entering the duration in seconds that the DataSet should be shown.</p>

	<p><img alt="Dataset Add View" src="content/layout/ss_layout_dataset_addview.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="358" height="178"></p>

    <p>Once added, the edit dialog will automatically appear allowing the user to pick their options for the DataSet View.</p>

	<p><img alt="Dataset View Edit" src="content/layout/ss_layout_dataset_view_edit.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="658" height="583"></p>

    <p>On this form the user can specify:</p>
    <ul>
      <li><strong>Duration</strong>: The display duration in seconds</li>
      <li><strong>Update Interval</strong>: The duration in minutes the data should be cached on the client</li>
      <li><strong>Lower Row Limit</strong>: The row number to start displaying the data</li>
      <li><strong>Upper Row Limit</strong>: The row number to end displaying the data</li>
      <li><strong>Order</strong>: The Ordering of the data (column name ASC|DESC)</li>
      <li><strong>Filter</strong>: A filter option to filter the data with (Column Name=Value, Column Name=Value)</li>
      <li><strong>Show Table Headings</strong>: Whether to show the column headings in the table</li>
      <li><strong>Columns Selected</strong>: The columns to display (drag or double click to move between lists)</li>
      <li><strong>Columns Available</strong>: The columns available to select (drag or double click to move between lists)</li>
      <li><strong>Stylesheet</strong>: A CSS Stylesheet to render with the table</li>
    </ul>

    <p>Following is an example of the "Styleshee for the Table" which will produce the table on the Clent Display as shown below:</p>
    
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
    <p>The resulting view will be showing the region preview window and displayed on the client.</p>

	<p><img alt="Dataset Table" src="content/layout/ss_layout_dataset_table.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="474" height="182"></p>

    <p>Note: Once the DataSet view is configured it will automatically respond to edits made on the data &#8211; and it
    multiple views on the same DataSet can be created.</p>
	
