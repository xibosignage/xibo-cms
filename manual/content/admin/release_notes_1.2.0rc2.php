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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.2.0-rc2_-_Codename_.22Biela.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.2.0-rc2 - Codename "Biela"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a>
<ul>
<li class="toclevel-2 tocsection-4"><a href="#Upgrading_from_1.0_.28Halley.29"><span class="tocnumber">3.1</span> <span class="toctext">Upgrading from 1.0 (Halley)</span></a></li>
<li class="toclevel-2 tocsection-5"><a href="#Upgrading_from_1.1.x_.28Encke.29"><span class="tocnumber">3.2</span> <span class="toctext">Upgrading from 1.1.x (Encke)</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-6"><a href="#Help"><span class="tocnumber">4</span> <span class="toctext">Help</span></a></li>
<li class="toclevel-1 tocsection-7"><a href="#New_Features"><span class="tocnumber">5</span> <span class="toctext">New Features</span></a>
<ul>
<li class="toclevel-2 tocsection-8"><a href="#Schedule_Lookahead"><span class="tocnumber">5.1</span> <span class="toctext">Schedule Lookahead</span></a></li>
<li class="toclevel-2 tocsection-9"><a href="#Maintenance_and_Alerts"><span class="tocnumber">5.2</span> <span class="toctext">Maintenance and Alerts</span></a></li>
<li class="toclevel-2 tocsection-10"><a href="#64_bit_Flash_Support"><span class="tocnumber">5.3</span> <span class="toctext">64 bit Flash Support</span></a></li>
<li class="toclevel-2 tocsection-11"><a href="#Display_name_link_targets"><span class="tocnumber">5.4</span> <span class="toctext">Display name link targets</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-12"><a href="#Bug_Fixes"><span class="tocnumber">6</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-13"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">7</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.2.0-rc2_-_Codename_.22Biela.22">Xibo 1.2.0-rc2 - Codename "Biela"</span></h3>
<p><b>This is the second release candidate for Xibo Server 1.2.0. We need as many users as possible to test this release on their systems so that we can iron out any issues before 1.2.0 is released. Please work on a copy of your production Xibo database. This should NOT BE USED IN PRODUCTION.</b>
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.2/1.2.0-rc2">https://launchpad.net/xibo/1.2/1.2.0-rc2</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You must use the 1.2.0-rc2 .net client or pyclient-1.2.0a2 client with this version of the Xibo server. Older client versions will not even connect to this server version.
</p><p>Xibo requires PHP 5.3.9 or higher. A full list of module requirements is presented at the point of installation - we'll even tell you which modules you're missing!
</p>
<h3> <span class="mw-headline" id="Upgrading"> Upgrading </span></h3>
<h4> <span class="mw-headline" id="Upgrading_from_1.0_.28Halley.29"> Upgrading from 1.0 (Halley) </span></h4>
<p>There are significant database schema changes between the 1.0 series of Xibo and the 1.2 series. The upgrader will take a 1.0 series database and convert it to a schema suitable for the 1.2 series to date. Note that this is a one-way conversion. Please do not upgrade your production database to test Xibo 1.2 functionality, and then expect to run a 1.0 series codebase against that database. Instructions for cloning a Xibo database are available here <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a>.
</p>
<ul><li> Clone your existing Xibo database and grant permissions (see <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a> for details)
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
<h3> <span class="mw-headline" id="Help"> Help </span></h3>
<p>Please ask for help/advice in the Answers section of Launchpad: <a rel="nofollow" class="external free" href="https://answers.launchpad.net/xibo">https://answers.launchpad.net/xibo</a>
</p><p>Please report any bugs in the Bugs section of Launchpad: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a> (if you're not sure that what you have found is a bug, please ask in the Answers section first!)
</p><p>Please report any enhancement requests in the Blueprints section of Launchpad: <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a>
</p>
<h3> <span class="mw-headline" id="New_Features">New Features</span></h3>
<h4> <span class="mw-headline" id="Schedule_Lookahead">Schedule Lookahead</span></h4>
<p>Both the Windows .net and python clients now download schedule information for a configurable period of time (see the option REQUIRED_FILES_LOOKAHEAD). The default is 48 hours. This will allow clients to change schedules without a connection to the server. In addition the .net client will now change schedules within 10 seconds of the start of the new schedule.
</p>
<h4> <span class="mw-headline" id="Maintenance_and_Alerts">Maintenance and Alerts</span></h4>
<p>A new alerts option ALWAYS_ALERT can be configured so that you receive only one email when a client goes offline, and one when it comes back online - rather than an email each time the maintenance script runs. Information on setting up maintenance is available here: <a href="/wiki/Manual:Admin:Settings_Help#Maintenance" title="Manual:Admin:Settings Help" class="mw-redirect">Manual:Admin:Settings_Help#Maintenance</a>
</p>
<h4> <span class="mw-headline" id="64_bit_Flash_Support">64 bit Flash Support</span></h4>
<p>The windows .net client should now play flash content correctly. Thanks to Dustin Drewery for researching this and providing the fix.
</p>
<h4> <span class="mw-headline" id="Display_name_link_targets">Display name link targets</span></h4>
<p>It's now possible to configure the target of the link added to the display name in Management-&gt;Display. Typical usage would be setting the target to _blank when using the link to link to a VNC server running on the client.
</p>
<h3> <span class="mw-headline" id="Bug_Fixes">Bug Fixes</span></h3>
<p>A complete list of bugs fixed can be found in Launchpad. <a rel="nofollow" class="external text" href="https://launchpad.net/xibo/+milestone/1.2.0-rc2">Bugs fixed in 1.2.0-rc2 Milestone</a>
</p>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.0 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.1 series - which will be unstable.
</p><p>Once we are happy with 1.1, we will release Xibo 1.2 which will be the next stable release series and the direct upgrade path for 1.0. Any future releases of Xibo 1.0 will be bug fix releases only.
</p><p>Therefore there are the following known issues and limitations with Xibo 1.2.0-rc2:
</p>
<ul><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[1]</a> (applies only to .net client)
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[2]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[3]</a> (applies only to .net client)
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[4]</a> (applies only to .net client)
</li><li> RSS Tickers using "single" mode render incorrectly. (applies only to Python client)
</li></ul>
<p>Please also check the bug tracker for new issues: <a rel="nofollow" class="external autonumber" href="https://launchpad.net/xibo/+milestone/1.2.0-rc3">[5]</a>
</p>