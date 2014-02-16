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
<h1>Dashboard <small>CMS at a glance</small></h1>

<p>The Dashboard is a personalised area which allows quick access to the main functions enabled on your user account. There are two types of dashboard which can be enabled for a user.</p>

<h2>Icon Dashboard <small>The default dashboard</small></h2>

<p>When a user first logs in they are presented with the <?php echo PRODUCT_NAME; ?> Dashboard. This
page presents all the options available to maintain your <?php echo PRODUCT_NAME; ?> Network. </p>

<p><img class="img-thumbnail" alt="Default Dashboard" src="content/coreconcepts/dashboard_admin.png"></p>

<p>The dashboard icons allow the user to navigate to specific sections of the
<?php echo PRODUCT_NAME; ?> Interface, below is a discription of each icon.</p>
<ul>
  <li>Schedule - Access the schedule for each display, and schedule new or existing layouts to displays.</li>
  <li>Layouts - Links to manage all the user's defined layouts i.e. create, edit or delete layouts.</li>
  <li>Library - Links to server library to manage its media contentes i.e. create, replace or delete media items.</li>
  <li>Templates - View the templates that are offered to users when they create new layouts.</li>
  <li>Users - Allows administration of users on the system (Admins Only).</li>
  <li>Settings - <?php echo PRODUCT_NAME; ?> server configuration settings (Admins Only)</li>
  <li>License - Information about the licenses <?php echo PRODUCT_NAME; ?> is released under.</li>
  <li>Manual - Access <?php echo PRODUCT_NAME; ?> online manual</li>
</ul>


<h2 id="Media_Dashboard">Media Manager <small>Ideal for users that only edit specific items</small></h2>

<p>The media manager dashboard is used to delegate specific tasks to users. When a user has had their homepage configured they will no longer be able to access admin parts of <?php echo PRODUCT_NAME; ?>. The media manager will replace the default dashboard with a simple dashboard, pictured below.</p>

<p><img class="img-thumbnail" alt="Media Manager Dashboard" src="content/coreconcepts/dashboard_user.png"></p>

<p>This icon provides the user with a direct link to the region of the template that they have been delegated.</p>

<h2 id="Status_Dashboard">Status Dashboard <small>Ideal for system administrators</small></h2>
<p>The status dashboard provides an overview of system health, including a Bandwidth usage chart, a library usage chart and a display activity grid.</p>
<p><img class="img-thumbnail" alt="Media Manager Dashboard" src="content/coreconcepts/dashboard_status.png"></p>
