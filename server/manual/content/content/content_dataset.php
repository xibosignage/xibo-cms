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
<h1>DataSets <small>Tabular data</small></h1>

<p>DataSets are a feature to design and display tabular data, formatted nicely, in a region on a layout. This data can be imported from a CSV file and provides a conveniant way to display data from other systems in <?php echo PRODUCT_NAME; ?>.</p>

<p>Examples of where this could be used are:
	<ul>
		<li>A drinks menu at a bar</li>
		<li>Tee times at a golf club</li>
		<li>Meeting room bookings</li>
	</ul>
</p>

<p>DataSets have been designed to be versatile and reusable and therefore come in two parts:
	<ul>
		<li>The DataSet (Data Structure and Content)</li>
		<li>The Display (DataSet View / Ticker)</li>
	</ul>
</p>

<p class="alert alert-info">The DataSet is defined in the Library and can be reused across multiple DataSet views and Layouts.</p>

<p>DataSets are accessed using the Library menu, DataSet sub menu.</p>

<p><img class="img-thumbnail" alt="Add Dataset" src="content/content/library_dataset_table.png"></p>

<p>Each data set can have a number of actions performed against it, using the Action Menu.</p>

<p><img class="img-thumbnail" alt="Add Dataset" src="content/content/dataset_action_menu.png"></p>


<h2 id="Create_Dataset">Adding DataSets <small>create a new empty DataSet</small></h2>

<p>Use the Add DataSet button to show the Add DataSet form.</p>

<p><img class="img-thumbnail" alt="Add Dataset" src="content/content/ss_layout_add_dataset.png"></p>

<dl class="dl-horizontal">
	<dt>Name</dt>
	<dd>A name for the DataSet - used to idenfity it later in the CMS.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Description</dt>
	<dd>A description of this DataSet - what is it and what is it for.</dd>
</dl>

<p>The creator of a dataset (or an admin) is able to set the permissions for the DataSet on a user group, or on a user by 
user basis. Only users with Edit permissions will be able to add/edit data and reorganise the data structure, only users with 
a view permission will be able to use the DataSet in their layouts.</p>

<h2 id="Edit_Dataset">Editing DataSets <small>changing the name</small></h2>
<p>DataSets can be Edited using the action menu. Select the Edit menu item to show the DataSet Edit form.</p>

<p><img class="img-thumbnail" alt="Add Dataset" src="content/content/dataset_edit_form.png"></p>

<h2 id="Delete_Dataset">Deleting DataSets</h2>
<p>DataSets can be deleted using the action menu. Select the Delete menu item to show the DataSet Delete form.</p>
<p><img class="img-thumbnail" alt="Add Dataset" src="content/content/dataset_delete_form.png"></p>
<p class="alert alert-warning">DataSets can only be deleted if they are not currently being used.</p>

<h2 id="Dataset_Column">DataSet Columns <small>Defining Dataset Structure</small></h2>

<p>Data Columns are used to define the structure of the data, each column can have a number of settings to achieve this, these are:</p>

<p><img class="img-thumbnail" alt="Dataset Column" src="content/content/ss_layout_dataset_column.png"></p>

<dl class="dl-horizontal">
	<dt>Heading</dt>
	<dd>The Heading for this Column</dd>
</dl>
<dl class="dl-horizontal">
	<dt>List Content</dt>
	<dd>A comma separated list of values that can be selected for this field.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Column Order</dt>
	<dd>The position this column should appear when viewing / entering data.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Data Type</dt>
	<dd>What is the format for this data? String, Number or Date.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Column Type</dt>
	<dd>Value or Formula. Is this column a user entered value (Value) or a calculated column (Formula).</dd>
</dl>

<p class="alert alert-info">There is no theoretical limit to the number of columns <?php echo PRODUCT_NAME; ?> can support; although a smaller DataSet is often easier to enter and display.</p>

<p class="alert alert-warning">Columns can be added and removed after data has been entered. The ordering and list content of columns can also be changed after data has been collected.</p>

<h3 id="Dataset_Row">Adding Data</h3>

<p>Once all the required columns have been added, the DataSet is ready to have data recorded against it. This is done using 
the "View Data" task on the DataSet table view. This view will contain all of the columns that were added in the 
previous step and allow you to go through each one and enter data.</p>

<p><img class="img-thumbnail" alt="Dataset Row" src="content/content/ss_layout_dataset_row.png"></p>

<p>There is no "save" button on this interface, <?php echo PRODUCT_NAME; ?> will automatically save your changes after each data entry.</p>

<p class="alert alert-info">Note: If all the rows are taken, more rows can be added to the data set by clicking the "More Rows" button.</p>

<h2 id="Import_CSV">Importing from CSV file</h2>
<p>The CMS has a DataSet CSV importer that can be used to extract data from a CSV file and put it into a DataSet.</p>
<p><img class="img-thumbnail" alt="Dataset Import CSV" src="content/content/dataset_importcsv_form.png"></p>