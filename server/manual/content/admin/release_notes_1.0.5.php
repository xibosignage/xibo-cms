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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.0.5_-_Codename_.22Halley.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.0.5 - Codename "Halley"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a></li>
<li class="toclevel-1 tocsection-4"><a href="#Bug_Fixes"><span class="tocnumber">4</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-5"><a href="#Changes_in_Functionality"><span class="tocnumber">5</span> <span class="toctext">Changes in Functionality</span></a>
<ul>
<li class="toclevel-2 tocsection-6"><a href="#Support_PPTX"><span class="tocnumber">5.1</span> <span class="toctext">Support PPTX</span></a></li>
<li class="toclevel-2 tocsection-7"><a href="#Client_Loading_Time"><span class="tocnumber">5.2</span> <span class="toctext">Client Loading Time</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-8"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">6</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.0.5_-_Codename_.22Halley.22">Xibo 1.0.5 - Codename "Halley"</span></h3>
<p>This is a stability release of Xibo. We recommend all Xibo installations be upgraded to 1.0.5
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.0/1.0.5">https://launchpad.net/xibo/1.0/1.0.5</a>
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
<ul><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/373626">373626</a> - xmds.php has stray Unix CR characters
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/421297">421297</a> - Regions will not resize back to full screen - always 2px missing on each edge.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/451545">451545</a> - Client does not redownload corrupt media
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/492086">492086</a> - Client Stats setting ignored
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/392846">392846</a> - Library filter form defaults "all" in the Name field
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/423724">423724</a> - Windows Client hardware key should be user configurable
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/431044">431044</a> - Cannot use an IFRAME as embedded HTML
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/451695">451695</a> - Client background image has lower quality than original
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/459671">459671</a> - The server doesnt resize GIF images.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/490049">490049</a> - WMP object shown before video is started resulting in a white flicker
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/419361">419361</a> - Multiple RSS Tickers run slowly or stop
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/422753">422753</a> - Cannot upload files that have special characters in them
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/435056">435056</a> - Should be able to remove "Manual" button from the "Dashboard" menu.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/461002">461002</a> - Upgrade backup switch is incorrectly labelled
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/488191">488191</a> - Client and Server should make version number visble
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/490856">490856</a> - Something trying to access non-existent javascript
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/455630">455630</a> - Uploading more than one file revision without changing the name causes a "duplicate name" error
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/471636">471636</a> - Source link wrong on login
</li></ul>
<h3> <span class="mw-headline" id="Changes_in_Functionality">Changes in Functionality</span></h3>
<h4> <span class="mw-headline" id="Support_PPTX">Support PPTX</span></h4>
<p><a rel="nofollow" class="external text" href="https://blueprints.launchpad.net/xibo/+spec/support-pptx">Support PPTX Extension</a>
The PPTX extension is now an allowed file type.
</p>
<h4> <span class="mw-headline" id="Client_Loading_Time">Client Loading Time</span></h4>
<p>The first time a 1.0.5 series client runs it will recalculate checksums for all currently active media items. If you have large numbers or large media items this can take some time to complete. Once this process has completed the checksums will be cached and subsequent startups will be uneffected.
</p>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.0 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.1 series - which will be unstable.
</p><p>Once we are happy with 1.1, we will release Xibo 1.2 which will be the next stable release series and the direct upgrade path for 1.0. Any future releases of Xibo 1.0 will be bug fix releases only.
</p><p>Therefore there are the following known issues and limitations with Xibo 1.0.5:
</p>
<ul><li> Internationalisation: Xibo currently deals with non-english characters badly. You are strongly recommended to avoid using non-english characters wherever possible. <a rel="nofollow" class="external autonumber" href="https://blueprints.launchpad.net/xibo/translate-xibo">[1]</a>
</li><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF or PNG image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[2]</a>
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[3]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[4]</a>
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[5]</a>
</li></ul>