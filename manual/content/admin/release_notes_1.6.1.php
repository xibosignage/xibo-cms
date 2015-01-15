<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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

$LP_RELEASE_PAGE = 'https://launchpad.net/xibo/1.6/1.6.1';
?>
<h1 id="Notes_Title">Xibo 1.6.1 - Codename "Pons-Winnecke"</span></h1>
<p><strong>This is a bug fixing release of the 1.6 Series of Xibo</strong>.</p>
<p>You can download this release from <a rel="nofollow" class="external free" href="<?php echo $LP_RELEASE_PAGE; ?>"><?php echo $LP_RELEASE_PAGE; ?></a></p>

<div id="toctitle"><h2>Contents</h2></div>

<ol>
	<li class="toclevel-1 tocsection-1"><a href="#Notes_Title">Introduction</a></li>
	<li class="toclevel-1 tocsection-2"><a href="#Requirements">Requirements</a></li>
	<li class="toclevel-1 tocsection-3"><a href="#Upgrading">Upgrading</a></li>
	<li class="toclevel-1 tocsection-4"><a href="#Help">Help</a></li>
	<li class="toclevel-1 tocsection-7"><a href="#Bug_Fixes">Bug Fixes</a></li>
	<li class="toclevel-1 tocsection-8"><a href="#Known_Issues_and_Limitations">Known Issues and Limitations</a></li>
</ol>


<h2 id="Requirements">Requirements</h2>
<p>You must use the 1.6.1 version of the Windows and Ubuntu Display Clients with this version of the Xibo CMS.</p>

<p>Xibo requires PHP 5.3 or higher. A full list of module requirements is presented at the point of installation - we'll even tell you which modules you're missing!</p>

<p class="alert alert-warning">The PHP-PDO module is required from this release onwards.</p>

<h2 id="Upgrading">Upgrading</h2>
<p>There are significant database schema changes between the 1.4 series of Xibo and prior released. The upgrade wizard will take a prior database and convert it to a schema suitable for the 1.4 series to date. Note that this is a one-way conversion. Please do not upgrade your production database to test Xibo 1.6 functionality, and then expect to run a prior series code base against that database.</p>

<p>Instructions for cloning a Xibo database are available here <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Clone Database">Clone Database</a>.</p>

<ul>
	<li>Clone your existing Xibo database and grant permissions (see <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Clone Database">Release Notes:Clone Database</a> for details)</li>
	<li>Backup settings.php from your installation</li>
	<li>Manually take a backup of your database</li>
	<li>Replace your existing installation with the new version from the tar.gz or zip file</li>
	<li>Replace your backup settings.php file in your Xibo installation directory</li>
	<li>Browse to <a rel="nofollow" class="external free" href="http://your.server/path">http://your.server/path</a>as normal</li>
	<li>You will be prompted that an upgrade is required.</li>
	<li>Enter your xibo_admin password, and follow the upgrade wizard.</li>
	<li>The upgrade should run, and finally ask you to log in as you would normally.</li>
</ul>

<h2 id="Help">Help</h2>
<p>Please ask for help / advice in the Answers section of Launchpad: <a rel="nofollow" class="external free" href="https://answers.launchpad.net/xibo">https://answers.launchpad.net/xibo</a></p>

<p>Please report any bugs in the Bugs section of Launchpad: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a> (if you're not sure that what you have found is a bug, please ask in the Answers section first!)</p>

<p>Please report any enhancement requests in the Blueprints section of Launchpad: <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a></p>

<p>When asking for assistance with this release, please make it clear that you're using the release candidate and not a stable release of Xibo.</p>

<h2 id="Bug_Fixes">Bug Fixes</h2>
<p>For a full list of bug fixes please refer to the Release Project Page: <a rel="nofollow" class="external free" href="<?php echo $LP_RELEASE_PAGE; ?>"><?php echo $LP_RELEASE_PAGE; ?></a></p>

<h2 id="Known_Issues_and_Limitations">Known Issues and Limitations</h2>
<p>There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.6 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.7 series - which will be made available as a BETA.</p>
