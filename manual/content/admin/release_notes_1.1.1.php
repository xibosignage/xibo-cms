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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.1.1_-_Codename_.22Encke.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.1.1 - Codename "Encke"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a>
<ul>
<li class="toclevel-2 tocsection-4"><a href="#Upgrading_from_1.0_.28Halley.29"><span class="tocnumber">3.1</span> <span class="toctext">Upgrading from 1.0 (Halley)</span></a></li>
<li class="toclevel-2 tocsection-5"><a href="#Upgrading_from_1.1.0_.28Encke.29"><span class="tocnumber">3.2</span> <span class="toctext">Upgrading from 1.1.0 (Encke)</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-6"><a href="#Help"><span class="tocnumber">4</span> <span class="toctext">Help</span></a></li>
<li class="toclevel-1 tocsection-7"><a href="#New_Features"><span class="tocnumber">5</span> <span class="toctext">New Features</span></a>
<ul>
<li class="toclevel-2 tocsection-8"><a href="#Microblog_Search"><span class="tocnumber">5.1</span> <span class="toctext">Microblog Search</span></a></li>
<li class="toclevel-2 tocsection-9"><a href="#Webservice_Changes"><span class="tocnumber">5.2</span> <span class="toctext">Webservice Changes</span></a></li>
<li class="toclevel-2 tocsection-10"><a href="#Linux_Client"><span class="tocnumber">5.3</span> <span class="toctext">Linux Client</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-11"><a href="#Bug_Fixes"><span class="tocnumber">6</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-12"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">7</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.1.1_-_Codename_.22Encke.22">Xibo 1.1.1 - Codename "Encke"</span></h3>
<p><b>This is a development preview release of Xibo. This release is the second of the development preview releases of Xibo working towards the release of Xibo 1.2.0, the next stable line of Xibo released. This should NOT BE USED IN PRODUCTION.</b>
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.1/1.1.1">https://launchpad.net/xibo/1.1/1.1.1</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You must use the 1.0.7 .net client or pyclient-1.1.0a22 or later with this version of the Xibo server.
</p><p>Xibo requires PHP 5.2.0 or higher. A full list of module requirements is presented at the point of installation - we'll even tell you which modules you're missing!
</p>
<h3> <span class="mw-headline" id="Upgrading"> Upgrading </span></h3>
<h4> <span class="mw-headline" id="Upgrading_from_1.0_.28Halley.29"> Upgrading from 1.0 (Halley) </span></h4>
<p>There are significant database schema changes between the 1.0 series of Xibo and the 1.1 series. The upgrader will take a 1.0 series database and convert it to a schema suitable for the 1.1 series to date. Note that this is a one-way conversion. Please do not upgrade your production database to test Xibo 1.1 functionality, and then expect to run a 1.0 series codebase against that database. Instructions for cloning a Xibo database are available here <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a>.
</p>
<ul><li> Clone your existing Xibo database and grant permissions (see <a href="index.php?toc=developer&p=admin/release_notes_clonedb" title="Release Notes:Clone Database">Release Notes:Clone Database</a> for details)
</li><li> Continue with instructions from 1.1.0 below.
</li></ul>
<h4> <span class="mw-headline" id="Upgrading_from_1.1.0_.28Encke.29"> Upgrading from 1.1.0 (Encke) </span></h4>
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
</p><p>When asking for assistance with Xibo 1.1 series releases, please make it clear that you're using a development preview and not a stable release of Xibo.
</p>
<h3> <span class="mw-headline" id="New_Features">New Features</span></h3>
<h4> <span class="mw-headline" id="Microblog_Search">Microblog Search</span></h4>
<p>This adds a new media type which displays the output of searching Identi.ca and/or Twitter in a region in a similar fashion to RSS Tickers. There is currently only client support in the Python client for this media type. See our <a rel="nofollow" class="external text" href="http://xibo.org.uk/2010/05/03/signing-oggcamp/">blogpost</a> for an overview of what this media type can be used to do. There is documentation for the new module in the manual <a href="/wiki/Manual:Layouts:Design#MicroBlog" title="Manual:Layouts:Design">Manual:Layouts:Design#MicroBlog</a>
</p>
<h4> <span class="mw-headline" id="Webservice_Changes">Webservice Changes</span></h4>
<p>nuSOAP has been removed and replaced with PHP's own SOAP extension. This removes the annoying error logging that 1.0 users are used to and also lays the ground work for an externally accessible API for Xibo servers.
</p>
<h4> <span class="mw-headline" id="Linux_Client">Linux Client</span></h4>
<p>An early preview (1.1.0a22) of the Python client is available to run on Linux. Instructions are here: <a href="/wiki/Install_Guide_Python_Client" title="Install Guide Python Client">Install_Guide_Python_Client</a>
</p>
<h3> <span class="mw-headline" id="Bug_Fixes">Bug Fixes</span></h3>
<p>Xibo 1.1.1 has been developed alongside Xibo 1.0.x since just before we released 1.0.5. We've ported all the fixes from 1.0.x in to 1.1.1 - so hopefully there aren't any regressions. If you have previously reported a bug with Xibo 1.0 that we've fixed, we'd greatly appreciate you checking that that bug has actually been fixed in the 1.1 series too!
</p><p>In addition these 1.1 specific bugs have been fixed.
</p>
<ul><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/502560">502560</a> - Layouts \ Media doesn't appear when permission set up for group.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/502124">502124</a> - Display group security duplicating.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/502126">502126</a> - Menu items duplicating.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/502455">502455</a> - Unable to add PowerPoint to library.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/503874">503874</a> - Responsemanager class not found on upgrade.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/516263">516263</a> - Background edit will not allow the none selection.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/519352">519352</a> - Some UserAgent strings are too long.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/552853">552853</a> - Default layout isn't always sent to client.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/502132">502132</a> - Get Text Error Notice.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/502979">502979</a> - Turn Off Debugging button fails.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/525666">525666</a> - A mistake made setting privileges for a user on the 1.1.0 server results in a permission denied error.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/525834">525834</a> - Unable to add a new user.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/560768">560768</a> - Spelling errors in Library editing dialog.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/570925">570925</a> - Recurrence "repeat every" should be an input box.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/xibo/+bug/576630">576630</a> - Unable to delete resolution.
</li></ul>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.0 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.1 series - which will be unstable.
</p><p>Once we are happy with 1.1, we will release Xibo 1.2 which will be the next stable release series and the direct upgrade path for 1.0. Any future releases of Xibo 1.0 will be bug fix releases only.
</p><p>Therefore there are the following known issues and limitations with Xibo 1.1.1:
</p>
<ul><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[1]</a> (applies only to .net client)
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[2]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[3]</a> (applies only to .net client)
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[4]</a> (applies only to .net client)
</li><li> RSS Tickers using "single" mode render incorrectly. (applies only to Python client)
</li></ul>