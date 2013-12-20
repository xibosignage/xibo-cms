<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
		<li>The Data (DataSet)</li>
		<li>The Display (DataSet View / Ticker)</li>
	</ul>
</p>

<p>This means that you can define a data set as a number of columns, add rows and then create "views" of this data on your layouts.</p>

<h2>Creating a DataSet</h2>

<p>DataSets are accessed using the "DataSets" link in the "Library" menu, navigating here will bring you
to a very familiar <?php echo PRODUCT_NAME; ?> "table" view of all the data sets you have permission to access. You can add a new dataset 
by giving it a name and an optional description, you can also edit existing ones and add data.</p>

<p><img class="img-thumbnail" alt="Add Dataset" src="content/content/ss_layout_add_dataset.png"></p>

<p>The creator of a dataset (or an admin) is able to set the permissions for the DataSet on a user group, or on a user by 
user basis. Only users with Edit permissions will be able to add/edit data and reorganise the data structure, only users with 
a view permission will be able to use the DataSet in their layouts.</p>

<p>The first thing to do is Add a new DataSet using the "Add Dataset" button, after doing so the columns 
of the DataSet can be defined to describe the structure of the data.</p>

<h3>Defining Dataset Structure</h3>
<p>Data Columns are used to define the structure of the data, each column can have a number of settings to achieve this, these are:</p>
<ul>
<li><strong>Heading</strong>: the column heading to appear when you enter data</li>
<li><strong>List Content</strong>: enter a comma separated list of values in here. The list is displayed in the drop down 
list during row data entry</li>
<li><strong>Column Order</strong>: the order to place the column</li>
</ul>

<p><img class="img-thumbnail" alt="Dataset Column" src="content/content/ss_layout_dataset_column.png"></p>

<p>There is not a theoretical limit to the number of columns <?php echo PRODUCT_NAME; ?> can support; although a smaller DataSet is often easier to enter 
and display than an overly large one. Columns may be extended in the future to have support for different data types. Currently only 
strings are supported.</p>

<p>Note: Columns can be added and removed after data has been entered. The ordering and list content of columns can also be changed 
after data has been collected.</p>

<h3>Adding Data</h3>
<p>Once all the required columns have been added, the DataSet is ready to have data recorded against it. This is done using 
the "View Data" task on the DataSet table view. This view will contain all of the columns that were added in the 
previous step and allow you to go through each one and enter data.</p>

<p><img class="img-thumbnail" alt="Dataset Row" src="content/content/ss_layout_dataset_row.png"></p>

<p>There is no "save" button on this interface, <?php echo PRODUCT_NAME; ?> will automatically save your changes after each data entry.</p>

<p class="alert alert-info">Note: If all the rows are taken, more rows can be added to the data set by clicking the "Add Rows" button.</p>
