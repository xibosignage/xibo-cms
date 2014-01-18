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
<h1>Login <small>User name and password</small></h1>

<p>The CMS is protected by an authentication system with a user name and password. During installation a special "xibo_admin" account would have been created which allows super user access to the entire CMS. Additional user accounts can be created with various permissions (covered later in this manual).</p>

<p>Any access to the CMS before logging in will cause the login box to appear requesting your username and password. This will happen on first use, if you log out and after any periods of inactivity.</p>

<p><img class="img-thumbnail" alt="Login Form" src="content/coreconcepts/login_box.png"></p>

<p>As a user of the CMS you will have been given a username and password to enter on this form. Once logged in you will be directed to the <a href="index.php?toc=app_overview&p=coreconcepts/dashboard">Dashboard</a> or to your last known location in the CMS.</p>

<p class="alert alert-info">The CMS uses your browser cookies to check whether or not you are logged in. If you are having trouble logging in please try and clear you browser cache.</p>