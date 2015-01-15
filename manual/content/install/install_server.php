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
<h1>CMS Installation <small>Content Management System</small></h1>
<p>The <?php echo PRODUCT_NAME; ?> CMS is a PHP web application backed by a MySQL database. The PHP/MySQL combination is a very popular web platform and can be run on Linux or Windows servers.</p>

<p class="alert alert-success">We have provided some basic instructions for installing your own web server <a href="index.php?toc=getting_started&p=install/install_environment" title="Installing a web server">here</a>.</p>

<p>If configuring and maintaining your own web server is not something you care to take on, then you may consider SAAS (software as a service) - more commonly known as <?php echo PRODUCT_NAME; ?> Hosting.</p>

<h3>Install <?php echo PRODUCT_NAME; ?></h3>
<p>Beyond this point we assume you have a web server running with PHP and MySQL and that you have the compressed archive (ZIP or Tarball) of the CMS installation package.</p>

<p>The screenshots and instructions below assume a windows installation - however the same is true for Linux.</p>

<ol>
	<li>
		<h4>Download and extract the archive</h4>
		<p>The CMS archive contains a sub folder called <?php echo PRODUCT_NAME; ?>-server-<?php echo PRODUCT_VERSION; ?>, copy this folder into your web root and rename as necessary. In the simpliest of web server configurations the name of this folder will be the name of the URL you use to access the CMS. For exammple: "http://localhost/<code>your target folder name</code>"</p>

		<p>At this point we should also make a folder for the CMS library. This will be used to store images, videos and other file based media that is uploaded to the CMS. Once created make a note of the path as the CMS installation will require this information.</p>

		<p class="alert alert-danger">The library folder should not be in a web-servable location.</p>

		<p>Completing this step should give you a folder containing the following:</p>
		<div><a href="content/install/win32_install_extracted.png"><img class="img-thumbnail" src="content/install/win32_install_extracted_thumb.png" /></a></div>
	</li>

	<li>
		<h4>Starting the install</h4>
		<p>Now open your web browser (Mozilla Firefox, Internet Explorer etc). In the address bar, enter "http://localhost/<code>your target folder name</code>". You should see the <?php echo PRODUCT_NAME; ?> Installer start screen. Click "Next".</p>
		<div><a href="content/install/win32_install_start.png"><img class="img-thumbnail" src="content/install/win32_install_start_thumb.png" /></a></div>
	</li>
	
	<li>
		<h4>Prerequisites</h4>
		<p>The installer contains a detailed check list of all the items required for a successful installation. Each item will have either:
			<ul>
				<li>A tick - the item is present and correct</li>
				<li>An exclaimation mark - the item is present but may not be configured correctly.</li>
				<li>A cross - the item is missing.</li>
			</ul>
		</p>
		<div><a href="content/install/win32_install_prereq.png"><img class="img-thumbnail" src="content/install/win32_install_prereq_thumb.png" /></a></div>

		<p>Any items with an exclaimation mark or a cross should be addressed and the retest button used to run this step again.</p>

		<p>The most common problems here are missing PHP modules, configuration of PHP settings and file permissions issues to the library.</p>

		<p>Once all the items are ticked press next to advance.</p>
	</li>
	<li>
		<h4>Creating the database</h4>
		<p>The CMS can install into a new database, or an exsiting one. We recommend a new database.</p>
		<p class="alert alert-danger"><?php echo PRODUCT_NAME; ?> does not prefix its table names and may conflict with content in an existing database.</p>

		<div><a href="content/install/win32_install_database_step1.png"><img class="img-thumbnail" src="content/install/win32_install_database_step1_thumb.png" /></a></div>
	</li>

	<li>
		<h4>Database Details</h4>
		<p>Whether you chose an existing database or a new one, the installer will need to collect some information about that database to allow the CMS to connect, read and write.</p>		
		<p>The installer will need the following information:
			<dl class="dl-horizontal">
				<dt>Host</dt>
				<dd>The hostname for your MySQL installation - in the majority of cases this will be "localhost".</dd>
			</dl>
			<dl class="dl-horizontal">
				<dt>Admin Username</dt>
				<dd>The "root" username for your MySQL installation. This is only used for the installation and is only required if you have asked the installer to create a new database.</dd>
			</dl>
			<dl class="dl-horizontal">
				<dt>Admin Password</dt>
				<dd>The "root" password. This is only used for the installation and is only required if you have asked the installer to create a new database.</dd>
			</dl>
			<dl class="dl-horizontal">
				<dt>Database Name</dt>
				<dd>The name for the CMS database.</dd>
			</dl>
			<dl class="dl-horizontal">
				<dt>Database Username</dt>
				<dd>The username for the CMS to use to connect to the database - usually this can be the same.</dd>
			</dl>
			<dl class="dl-horizontal">
				<dt>Database Password</dt>
				<dd>The password to use to connect to the database.</dd>
			</dl>
		</p>

		<div>
			<a href="content/install/win32_install_database_new.png"><img class="img-thumbnail" src="content/install/win32_install_database_new_thumb.png" /></a>
			<a href="content/install/win32_install_database_existing.png"><img class="img-thumbnail" src="content/install/win32_install_database_existing_thumb.png" /></a>
		</div>
	</li>

	<li>
		<h4>Start the Installation</h4>
		<p>The installer will now create/populate database for <?php echo PRODUCT_NAME; ?>. You should see a series of dots appear on the screen as this happens. It can take a few moments to complete. Assuming everything went well, click "Next".</p>

		<div><a href="content/install/win32_install_database_progress.png"><img class="img-thumbnail" src="content/install/win32_install_database_progress_thumb.png" /></a></div>

		<p class="alert alert-info">If there are errors at this point, please see the <a href="index.php?toc=toc_getting_started&p=install/troubleshooting">troubleshooting</a> section of this manual.</p>
	</li>

	<li>
		<h4>Admin Password</h4>
		<p>Now we need to choose a password for the xibo_admin user. This is the first user in the <?php echo PRODUCT_NAME; ?> system, and is the person who typically administers <?php echo PRODUCT_NAME; ?> (does updgrades etc). Choose a password for the user and enter it in both boxes.</p>

		<p class="alert alert-info">Make a note "Xibo Admin: Username: xibo_admin Password:" and then the password that you chose. You'll need this to log on, and to perform upgrades at a later date.</p>
		
		<div><a href="content/install/win32_install_admin_user.png"><img class="img-thumbnail" src="content/install/win32_install_admin_user_thumb.png" /></a></div>

		<p>Assuming the password you chose was acceptable, you'll be told the password change succeeded. Click Next.</p>
	</li>

	<li>
		<h4>Settings</h4>
		<p>The next screen deals with configuring Xibo. The first box asks for the location that Xibo should store the media you upload. We created a folder for this earlier and wrote it down. Enter the directory you wrote down next to "Library Directory", eg "c:\XiboLibrary".</p>
		
		<p>The next box asks for a server key. You can think of this as a password for adding client PCs to the system. Choose something obscure. You won't have to enter this very often. Make a note on your piece of paper. You'll need to refer to this when installing the client.</p>

		<p>The final tick box asks if it's OK to send anonymous statistics back to the Xibo project. There's information on exactly what we collect is available <a rel="nofollow" class="external text" href="http://xibo.org.uk/2009/03/05/xibo-statistics/">here</a>. If you're happy for that to happen, tick the box. Otherwise, untick it. Click "Next".</p>
		
		<div><a href="content/install/win32_install_settings.png"><img class="img-thumbnail" src="content/install/win32_install_settings_thumb.png" /></a></div>
	</li>

	<li>
		<h4>Complete</h4>
		<p>And we're done. You should be presented with a link to log in to your new Xibo system. Click the link and log in with your xibo_admin username and password.</p>

		<div>
			<a href="content/install/win32_install_success.png"><img class="img-thumbnail" src="content/install/win32_install_success_thumb.png" /></a>
			<a href="content/install/win32_install_success_login.png"><img class="img-thumbnail" src="content/install/win32_install_success_login_thumb.png" /></a>
		</div>
	</li>
</ol>