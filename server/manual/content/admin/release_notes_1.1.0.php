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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.1.0_-_Codename_.22Encke.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.1.0 - Codename "Encke"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a></li>
<li class="toclevel-1 tocsection-4"><a href="#New_Features"><span class="tocnumber">4</span> <span class="toctext">New Features</span></a>
<ul>
<li class="toclevel-2 tocsection-5"><a href="#Permissions_for_Users.2C_Groups_and_Displays"><span class="tocnumber">4.1</span> <span class="toctext">Permissions for Users, Groups and Displays</span></a></li>
<li class="toclevel-2 tocsection-6"><a href="#Display_Groups"><span class="tocnumber">4.2</span> <span class="toctext">Display Groups</span></a></li>
<li class="toclevel-2 tocsection-7"><a href="#Translations"><span class="tocnumber">4.3</span> <span class="toctext">Translations</span></a></li>
<li class="toclevel-2 tocsection-8"><a href="#Wiki_Help"><span class="tocnumber">4.4</span> <span class="toctext">Wiki Help</span></a></li>
<li class="toclevel-2 tocsection-9"><a href="#Scheduler"><span class="tocnumber">4.5</span> <span class="toctext">Scheduler</span></a></li>
<li class="toclevel-2 tocsection-10"><a href="#Linux_Client"><span class="tocnumber">4.6</span> <span class="toctext">Linux Client</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-11"><a href="#Bug_Fixes"><span class="tocnumber">5</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-12"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">6</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.1.0_-_Codename_.22Encke.22">Xibo 1.1.0 - Codename "Encke"</span></h3>
<p><b>This is a development preview release of Xibo. This release is the first of the development preview releases of Xibo working towards the release of Xibo 1.2.0, the next stable line of Xibo released. This should NOT BE USED IN PRODUCTION.</b>
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.1/1.1.0">https://launchpad.net/xibo/1.1/1.1.0</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You must use the 1.0.5 .net client or 1.1.0a19 or later client with this version of the Xibo server. Previous clients will try to use a deprecated webservice call which may cause them to break or will fail to download files correctly.
</p><p>Xibo requires PHP 5.2.0 or higher. A full list of module requirements is presented at the point of installation - we'll even tell you which modules you're missing!
</p>
<h3> <span class="mw-headline" id="Upgrading"> Upgrading </span></h3>
<p>There are significant database schema changes between the 1.0 series of Xibo and the 1.1 series. The upgrader will take a 1.0 series database and convert it to a schema suitable for the 1.1 series to date. Note that this is a one-way conversion. Please do not upgrade your production database to test Xibo 1.1 functionality, and then expect to run a 1.0 series codebase against that database. Instructions for cloning a Xibo database are available here <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a>.
</p>
<ul><li> Clone your existing Xibo database and grant permissions (see <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a> for details)
</li><li> Backup settings.php from your installation
</li><li> The upgrader will offer to take a backup your database, but manually taking a backup would be a good idea.
</li><li> Replace your existing installation with the new version from the tar.gz or zip file
</li><li> Replace your settings.php file
</li><li> Browse to <a rel="nofollow" class="external free" href="http://your.server/path">http://your.server/path</a> as normal
</li><li> You will be prompted that an upgrade is required.
</li><li> Enter your xibo_admin password, and follow the upgrade wizard.
</li><li> The upgrade should run, and finally ask you to log in as you would normally.
</li></ul>
<p>Please ask for help/advice in the Answers section of Launchpad: <a rel="nofollow" class="external free" href="https://answers.launchpad.net/xibo">https://answers.launchpad.net/xibo</a>
</p><p>Please report any bugs in the Bugs section of Launchpad: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a> (if you're not sure that what you have found is a bug, please ask in the Answers section first!)
</p><p>Please report any enhancement requests in the Blueprints section of Launchpad: <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a>
</p><p>When asking for assistance with Xibo 1.1 series releases, please make it clear that you're using a development preview and not a stable release of Xibo.
</p>
<h3> <span class="mw-headline" id="New_Features">New Features</span></h3>
<h4> <span class="mw-headline" id="Permissions_for_Users.2C_Groups_and_Displays">Permissions for Users, Groups and Displays</span></h4>
<p>The permissions system has been overhauled. Displays are now grouped for scheduling purposes and it's now possible to assign groups of users permission to schedule content on any display or group of displays. <a rel="nofollow" class="external autonumber" href="https://blueprints.launchpad.net/xibo/+spec/xibo-server-permissions">[1]</a> Users can now be members of multiple groups.
</p>
<h4> <span class="mw-headline" id="Display_Groups">Display Groups</span></h4>
<p>Displays can now be assigned to groups to ease organisation with large numbers of clients.
</p>
<h4> <span class="mw-headline" id="Translations">Translations</span></h4>
<p>There has been a first pass at setting up Xibo for translations. This is still a work in progress, but the majority of Xibo's web interface can now be translated using Launchpad Translations <a rel="nofollow" class="external autonumber" href="https://translations.launchpad.net/xibo">[2]</a> and GNU gettext. We've included all the translations we have to date with the distribution files. Many of these are partial translations. 1.1.1 has targeted a second pass at translations to tidy up duplicate or very similar strings, plurals. Feedback is welcome on translations! Thanks to Damien Laureaux for his help in this area.
</p>
<h4> <span class="mw-headline" id="Wiki_Help">Wiki Help</span></h4>
<p>Xibo used to include context help dialogues, however these were out of date and in bad need of an overhaul. We've moved the context help out of the application itself and in to the wiki. You'll find the pages in the Manual namespace. <a href="/wiki/Manual:TOC" title="Manual:TOC">Manual:TOC</a>
</p>
<h4> <span class="mw-headline" id="Scheduler">Scheduler</span></h4>
<p>The introduction of display groups meant we needed to overhaul the scheduler. The scheduler screen month view has been re-written and you can now see the schedule for multiple displays, or groups of displays all in one place. You can also schedule on to a display group or a single display. The actual dialogues for scheduling have also been overhauled to use AJAX calendars.
</p>
<h4> <span class="mw-headline" id="Linux_Client">Linux Client</span></h4>
<p>An early preview (1.1.0a19) of the Python client is available to run on Linux. Instructions are here: <a href="/wiki/Install_Guide_Python_Client" title="Install Guide Python Client">Install_Guide_Python_Client</a>
</p>
<h3> <span class="mw-headline" id="Bug_Fixes">Bug Fixes</span></h3>
<p>Xibo 1.1.0 has been developed alongside Xibo 1.0.x since just before we released 1.0.0. We've ported all the fixes from 1.0.x in to 1.1.0 - so hopefully there aren't any regressions. If you have previously reported a bug with Xibo 1.0 that we've fixed, we'd greatly appreciate you checking that that bug has actually been fixed in the 1.1 series too!
</p>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.0 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.1 series - which will be unstable.
</p><p>Once we are happy with 1.1, we will release Xibo 1.2 which will be the next stable release series and the direct upgrade path for 1.0. Any future releases of Xibo 1.0 will be bug fix releases only.
</p><p>Therefore there are the following known issues and limitations with Xibo 1.1.0:
</p>
<ul><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[3]</a> (applies only to .net client)
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[4]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[5]</a> (applies only to .net client)
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[6]</a> (applies only to .net client)
</li><li> RSS Tickers using "single" mode render incorrectly. (applies only to Python client)
</li></ul>