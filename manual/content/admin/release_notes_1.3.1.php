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
<table id="toc" class="toc"><tr><td><div id="toctitle"><h2>Contents</h2></div>
<ul>
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.3.1_-_Codename_.22Faye.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.3.1 - Codename "Faye"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a>
<ul>
<li class="toclevel-2 tocsection-4"><a href="#Upgrading_from_1.2_.28Biela.29"><span class="tocnumber">3.1</span> <span class="toctext">Upgrading from 1.2 (Biela)</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-5"><a href="#Help"><span class="tocnumber">4</span> <span class="toctext">Help</span></a></li>
<li class="toclevel-1 tocsection-6"><a href="#New_Features"><span class="tocnumber">5</span> <span class="toctext">New Features</span></a>
<ul>
<li class="toclevel-2 tocsection-7"><a href="#Data_Sets"><span class="tocnumber">5.1</span> <span class="toctext">Data Sets</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-8"><a href="#Bug_Fixes"><span class="tocnumber">6</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-9"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">7</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.3.1_-_Codename_.22Faye.22">Xibo 1.3.1 - Codename "Faye"</span></h3>
<p><b>This is a development preview release of Xibo. This release is the first of the development preview releases of Xibo working towards the release of Xibo 1.4.0, the next stable line of Xibo released. This should NOT BE USED IN PRODUCTION.</b>
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.3/1.3.0">https://launchpad.net/xibo/1.3/1.3.0</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You must use the 1.3.1 .NET Client or 1.3.1 Python Client or later with this version of the Xibo server, other clients will not get new content from the Server.
</p><p>Xibo requires PHP 5.2.9 or higher. A full list of module requirements is presented at the point of installation - we'll even tell you which modules you're missing!
</p>
<h3> <span class="mw-headline" id="Upgrading"> Upgrading </span></h3>
<h4> <span class="mw-headline" id="Upgrading_from_1.2_.28Biela.29"> Upgrading from 1.2 (Biela) </span></h4>
<p>There are significant database schema changes between the 1.2 series of Xibo and the 1.3 series. The upgrader will take a 1.2 series database and convert it to a schema suitable for the 1.3 series to date. Note that this is a one-way conversion. Please do not upgrade your production database to test Xibo 1.3 functionality, and then expect to run a 1.2 series codebase against that database. Instructions for cloning a Xibo database are available here <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a>.
</p>
<ul><li> Clone your existing Xibo database and grant permissions (see <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a> for details)
</li><li> Backup settings.php from your installation by copying this file somewhere outside your Xibo installation folder.
</li><li> The upgrader will offer to take a backup your database, but manually taking a backup IS ESSENTIAL.
</li><li> Replace your existing installation with the new version from the tar.gz or zip file. For example, if your installation is in a folder called xibo, rename or delete your xibo folder, and then extract a fresh copy of the server in to a folder with the same name - ie xibo.
</li><li> Replace your settings.php file in to the server folder. There won't be a settings.php there already so you won't be prompted to overwrite the existing file.
</li><li> Browse to <a rel="nofollow" class="external free" href="http://your.server/path">http://your.server/path</a> as normal
</li><li> You will be prompted that an upgrade is required.
</li><li> Enter your xibo_admin password, and follow the upgrade wizard.
</li><li> The upgrade should run, and finally ask you to log in as you would normally.
</li></ul>
<h3> <span class="mw-headline" id="Help"> Help </span></h3>
<p>Please ask for help/advice in the Answers section of Launchpad: <a rel="nofollow" class="external free" href="https://answers.launchpad.net/xibo">https://answers.launchpad.net/xibo</a>
</p><p>Please report any bugs in the Bugs section of Launchpad: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a> (if you're not sure that what you have found is a bug, please ask in the Answers section first!)
</p><p>Please report any enhancement requests in the Blueprints section of Launchpad: <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a>
</p><p>When asking for assistance with Xibo 1.3 series releases, please make it clear that you're using a development preview and not a stable release of Xibo.
</p>
<h3> <span class="mw-headline" id="New_Features">New Features</span></h3>
<p>For a complete list of new features please refer to the Release Project Page Blueprint section: <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.3/1.3.1">https://launchpad.net/xibo/1.3/1.3.1</a>
</p>
<h4> <span class="mw-headline" id="Data_Sets">Data Sets</span></h4>
<p>The most significant change in 1.3.0 is the "Data Sets" functionality which allows basic "spreadsheet" style definition and data entry for displaying tabular style data (such as a restaurant menu, sports day results) on the client using the "Data Set View" module.
</p>
<h3> <span class="mw-headline" id="Bug_Fixes">Bug Fixes</span></h3>
<p>For a full list of bug fixes please refer to the Release Project Page: <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.3/1.3.1">https://launchpad.net/xibo/1.3/1.3.1</a>
</p>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.2 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.3 series - which will be unstable.
</p><p>Once we are happy with 1.3, we will release Xibo 1.4 which will be the next stable release series and the direct upgrade path for 1.2. Any future releases of Xibo 1.4 will be bug fix releases only.
</p>