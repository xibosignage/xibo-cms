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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.2.3_-_Codename_.22Biela.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.2.3 - Codename "Biela"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a>
<ul>
<li class="toclevel-2 tocsection-4"><a href="#Notes_on_the_Automated_Backups"><span class="tocnumber">3.1</span> <span class="toctext">Notes on the Automated Backups</span></a></li>
<li class="toclevel-2 tocsection-5"><a href="#Upgrading_from_1.0_.28Halley.29"><span class="tocnumber">3.2</span> <span class="toctext">Upgrading from 1.0 (Halley)</span></a></li>
<li class="toclevel-2 tocsection-6"><a href="#Upgrading_from_1.1.x_.28Encke.29"><span class="tocnumber">3.3</span> <span class="toctext">Upgrading from 1.1.x (Encke)</span></a></li>
<li class="toclevel-2 tocsection-7"><a href="#Upgrading_from_1.2.x_.28Biela.29"><span class="tocnumber">3.4</span> <span class="toctext">Upgrading from 1.2.x (Biela)</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-8"><a href="#Help"><span class="tocnumber">4</span> <span class="toctext">Help</span></a></li>
<li class="toclevel-1 tocsection-9"><a href="#New_Features"><span class="tocnumber">5</span> <span class="toctext">New Features</span></a></li>
<li class="toclevel-1 tocsection-10"><a href="#Bugs_Fixed"><span class="tocnumber">6</span> <span class="toctext">Bugs Fixed</span></a></li>
<li class="toclevel-1 tocsection-11"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">7</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.2.3_-_Codename_.22Biela.22">Xibo 1.2.3 - Codename "Biela"</span></h3>
<p><b>This is the third bug fix release in the 1.2 series of Xibo server and clients. It contains a single security fix.</b>
</p><p>Our thanks go to everyone who has helped out with testing and developing some of the new features in this release. Thanks also to everyone who sent bug reports, patches, translations or ideas. Special thanks to everyone who has supported us financially too by making a donation to the project.
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.2/1.2.3">https://launchpad.net/xibo/1.2/1.2.3</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You are strongly advised to use 1.2.2 .net client, or 1.2.2a2 Python client with 1.2.2 server.
</p><p>You may however optionally use 1.2.0 client with 1.2.2 server, however media inventory will not work in that configuration. We recommend not using 1.2.1 client as there is a bug with video end detection.
</p><p>Xibo requires PHP 5.2.9 or higher. A full list of module requirements is presented at the point of installation - we'll even tell you which modules you're missing!
</p><p>Xibo now requires the PHP SOAP module to be installed and enabled.
</p><p>If your installation of PHP has SOAP WSDL caching turned on, you may need to delete the WSDL cache before the media inventory feature will function. On Linux servers, you need to delete files called wsdl* from /tmp. On Windows servers, delete files called wsdl* from your PHP installations temporary files directory.
</p>
<h3> <span class="mw-headline" id="Upgrading"> Upgrading </span></h3>
<h4> <span class="mw-headline" id="Notes_on_the_Automated_Backups"> Notes on the Automated Backups </span></h4>
<p>We currently provide an automated backup as part of the upgrade routine. It's there as an absolute last resort fall back and restoring data from it is not trivial. Please ensure you take a full database backup using mysqldump or another appropriate tool before you undertake any upgrade. We may not be able to support you if an upgrade fails and you do not have a backup of the database before you started.
</p>
<h4> <span class="mw-headline" id="Upgrading_from_1.0_.28Halley.29"> Upgrading from 1.0 (Halley) </span></h4>
<p>There are significant database schema changes between the 1.0 series of Xibo and the 1.2 series. The upgrader will take a 1.0 series database and convert it to a schema suitable for the 1.2 series to date. Note that this is a one-way conversion. Please ensure you have a full backup of your 1.0 series database before upgrading. DO NOT RELY ON THE AUTOMATED BACKUP ROUTINE! Instructions for cloning a Xibo database are available here <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a> if you would prefer to copy your database and upgrade that instead.
</p>
<ul><li> Backup your database
</li><li> Optionally clone your existing Xibo database and grant permissions (see <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a> for details)
</li><li> Continue with instructions from 1.1.x below.
</li></ul>
<h4> <span class="mw-headline" id="Upgrading_from_1.1.x_.28Encke.29"> Upgrading from 1.1.x (Encke) </span></h4>
<ul><li> Backup settings.php from your installation
</li><li> The upgrader will offer to take a backup your database, but manually taking a backup would be a good idea.
</li><li> Replace your existing installation with the new version from the tar.gz or zip file
</li><li> Replace your settings.php file
</li><li> Browse to <a rel="nofollow" class="external free" href="http://your.server/path">http://your.server/path</a> as normal
</li><li> You will be prompted that an upgrade is required.
</li><li> Enter your xibo_admin password, and follow the upgrade wizard.
</li><li> The upgrade should run, and finally ask you to log in as you would normally.
</li></ul>
<h4> <span class="mw-headline" id="Upgrading_from_1.2.x_.28Biela.29"> Upgrading from 1.2.x (Biela) </span></h4>
<ul><li> Backup settings.php from your installation
</li><li> The upgrader will offer to take a backup your database, but manually taking a backup would be a good idea.
</li><li> Replace your existing installation with the new version from the tar.gz or zip file
</li><li> Replace your settings.php file
</li><li> Browse to <a rel="nofollow" class="external free" href="http://your.server/path">http://your.server/path</a> as normal
</li><li> You will be prompted that an upgrade is required.
</li><li> Enter your xibo_admin password, and follow the upgrade wizard.
</li><li> The upgrade should run, and finally ask you to log in as you would normally.
</li></ul>
<h3> <span class="mw-headline" id="Help"> Help </span></h3>
<p>Please ask for help/advice in the Answers section of Launchpad: <a rel="nofollow" class="external free" href="https://answers.launchpad.net/xibo">https://answers.launchpad.net/xibo</a>
</p><p>Please report any bugs in the Bugs section of Launchpad: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a> (if you're not sure that what you have found is a bug, please ask in the Answers section first!)
</p><p>Please report any enhancement requests in the Blueprints section of Launchpad: <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a>
</p>
<h3> <span class="mw-headline" id="New_Features">New Features</span></h3>
<p>No new features
</p>
<h3> <span class="mw-headline" id="Bugs_Fixed"> Bugs Fixed </span></h3>
<p>A full list of issues resolved in this release can be found here <a rel="nofollow" class="external autonumber" href="https://launchpad.net/xibo/+milestone/1.2.3">[1]</a>.
</p>
<ul><li> Contains a security fix for a directory traversal bug. Identified by Mahendra, BAE Systems Detica.
</li></ul>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.2 series of releases are now feature-frozen (no new features will be implemented).
</p><p>Therefore there are the following known issues and limitations with Xibo 1.2.0:
</p>
<ul><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[2]</a> (applies only to .net client)
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[3]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[4]</a> (applies only to .net client)
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[5]</a> (applies only to .net client)
</li><li> RSS Tickers using "single" mode render incorrectly. (applies only to Python client)
</li></ul>
<p>Please also check the bug tracker for new issues: <a rel="nofollow" class="external autonumber" href="https://launchpad.net/xibo/+milestone/1.2.4">[6]</a>
</p>