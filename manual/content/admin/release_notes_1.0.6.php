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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.0.6_-_Codename_.22Halley.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.0.6 - Codename "Halley"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a></li>
<li class="toclevel-1 tocsection-4"><a href="#Bug_Fixes"><span class="tocnumber">4</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-5"><a href="#Changes_in_Functionality"><span class="tocnumber">5</span> <span class="toctext">Changes in Functionality</span></a>
<ul>
<li class="toclevel-2 tocsection-6"><a href="#Layout_Designer"><span class="tocnumber">5.1</span> <span class="toctext">Layout Designer</span></a></li>
<li class="toclevel-2 tocsection-7"><a href="#Custom_Resolutions"><span class="tocnumber">5.2</span> <span class="toctext">Custom Resolutions</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-8"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">6</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.0.6_-_Codename_.22Halley.22">Xibo 1.0.6 - Codename "Halley"</span></h3>
<p>This is a stability release of Xibo. It also includes a security fix. We recommend all Xibo installations be upgraded to 1.0.6
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.0/1.0.6">https://launchpad.net/xibo/1.0/1.0.6</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You must use the 1.0.5 client with this version of the Xibo server. Previous clients will incorrectly checksum the layout files which may cause them to break.
</p><p>Xibo requires PHP 5.2.0 or higher. Xibo will function in most cases with 5.1.0 but you may experience some problems with experimental PHP functions. In particular Xibo may present a restricted list of timezones in the Admin Interface.
</p>
<h3> <span class="mw-headline" id="Upgrading"> Upgrading </span></h3>
<ul><li> Backup settings.php from your installation
</li><li> The upgrader will attempt to backup your database, but manually taking a backup would be a good idea.
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
</p><p><br />
</p>
<h3> <span class="mw-headline" id="Bug_Fixes">Bug Fixes</span></h3>
<p>Fixes for the following Bugs:
</p>
<ul><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/509638">509638</a> - Layout XML cannot be longer than 64k
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/376585">376585</a> - Can not change order of display of content when same media is used more than once
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/497166">497166</a> - Cannot add new resolutions
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/497167">497167</a> - Region preview differs from what is actually shown on the client
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/497170">497170</a> - Adding a background image from the layout designer
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/497177">497177</a> - Not clear what units are expected for duration
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/497178">497178</a> - When adding something to a region the preview does not refresh
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/502118">502118</a> - Impossible to access items on the Management Menu in IE8
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/502749">502749</a> - Error installing 1.1.0 when importing SQL-queries
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/501789">501789</a> - Not able to logout when "Homepage and Login" security not assigned
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/502456">502456</a> - Spelling of PowerPoint
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/511924">511924</a> - Incorrect foreign key reference created in 1.sql
</li></ul>
<h3> <span class="mw-headline" id="Changes_in_Functionality">Changes in Functionality</span></h3>
<h4> <span class="mw-headline" id="Layout_Designer">Layout Designer</span></h4>
<p><a rel="nofollow" class="external text" href="https://blueprints.launchpad.net/xibo/+spec/manual-region-positioning">Manual Region Positioning</a>
It's now possible to size a region by entering its position and dimensions within the layout. A regions size is now shown and updated as it is resized or moved inside the designer. Region previews now auto-refresh when changes have been made to their timelines.
</p><p><a rel="nofollow" class="external text" href="https://blueprints.launchpad.net/xibo/+spec/server-region-preview">Region Preview Improvements</a>
Media modules now generate their own preview in the designer. This will allow for greater flexibility in the future. There are also improvements to try and ensure text is rendered on the display in a similar scale to that shown in the designer preview.
</p><p><a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/497170">Upload Background image from Layout Designer</a>
It's now possible to upload a new image to use as a background image from the background section of the layout designer.
</p>
<h4> <span class="mw-headline" id="Custom_Resolutions">Custom Resolutions</span></h4>
<p>It's now possible to add custom resolutions (eg dual monitor configurations) directly from the Management menu. This feature is explained in greater detail <a href="/wiki/Manual:Administration:Resolutions" title="Manual:Administration:Resolutions">here</a>.
</p>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.0 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.1 series - which will be unstable.
</p><p>Once we are happy with 1.1, we will release Xibo 1.2 which will be the next stable release series and the direct upgrade path for 1.0. Any future releases of Xibo 1.0 will be bug fix releases only.
</p><p>Therefore there are the following known issues and limitations with Xibo 1.0.6:
</p>
<ul><li> Internationalisation: Xibo currently deals with non-english characters badly. You are strongly recommended to avoid using non-english characters wherever possible. <a rel="nofollow" class="external autonumber" href="https://blueprints.launchpad.net/xibo/translate-xibo">[1]</a>
</li><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF or PNG image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[2]</a>
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[3]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[4]</a>
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[5]</a>
</li></ul>