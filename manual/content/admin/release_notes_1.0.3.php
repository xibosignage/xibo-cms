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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.0.3_-_Codename_.22Halley.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.0.3 - Codename "Halley"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a></li>
<li class="toclevel-1 tocsection-4"><a href="#Bug_Fixes"><span class="tocnumber">4</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-5"><a href="#Changes_in_Functionality"><span class="tocnumber">5</span> <span class="toctext">Changes in Functionality</span></a>
<ul>
<li class="toclevel-2 tocsection-6"><a href="#Configure_RSS_Update_Interval"><span class="tocnumber">5.1</span> <span class="toctext">Configure RSS Update Interval</span></a></li>
<li class="toclevel-2 tocsection-7"><a href="#Embed_RAW_HTML_-_Calling_Javascript"><span class="tocnumber">5.2</span> <span class="toctext">Embed RAW HTML - Calling Javascript</span></a></li>
<li class="toclevel-2 tocsection-8"><a href="#Text_Scrolling_Speed"><span class="tocnumber">5.3</span> <span class="toctext">Text Scrolling Speed</span></a></li>
<li class="toclevel-2 tocsection-9"><a href="#Display_Statistics"><span class="tocnumber">5.4</span> <span class="toctext">Display Statistics</span></a></li>
<li class="toclevel-2 tocsection-10"><a href="#.net_Client_Display_Improvements"><span class="tocnumber">5.5</span> <span class="toctext">.net Client Display Improvements</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-11"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">6</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.0.3_-_Codename_.22Halley.22">Xibo 1.0.3 - Codename "Halley"</span></h3>
<p>This is a bug fix release of Xibo. We recommend all Xibo installations be upgraded to 1.0.3
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.0/1.0.3">https://launchpad.net/xibo/1.0/1.0.3</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You must use the 1.0.3 client with this version of the Xibo server. Previous clients will try to use a deprecated webservice call which may cause them to break.
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
<p>Fixes for the following Bugs
</p>
<ul><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/395881">395881</a> - Encoding of RSS incorrectly set when downloading a RSS feed
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/396735">396735</a> - Restricting sessions to 1 IP is not appropriate for people behind Load Balancers or ISP's that have dynamic addresses
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/393062">393062</a> - When editing an image file saving the name will fail it it had an apostrophe in it.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/396391">396391</a> - Malformed Log files should be ignored
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/396430">396391</a> - Session logout buttons don't work.
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/398952">398952</a> - Client ignores the 6kb file limit on the log when adding stat records
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/401192">401192</a> - Text and RSS scrolling strip chars off right hand side
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/398932">398932</a> - Embedded HTML has no way of calling JavaScript
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/398903">398903</a> - Webservice inconsistently validates client license strings
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/402885">402885</a> - Retiring a layout does not remove it from any displays that are showing it
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/392847">392847</a> - After sorting a table the action buttons don't work
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/395880">395880</a> - Cannot set 0 as an update interval for RSS
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/397692">397692</a> - webpages &amp; embedded html (show incorrect media name)
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/408401">408401</a> - Client memory leak when showing Pictures
</li><li> <a rel="nofollow" class="external text" href="https://bugs.launchpad.net/bugs/341116">341116</a> - Current image "flashes" on content refresh
</li></ul>
<h3> <span class="mw-headline" id="Changes_in_Functionality">Changes in Functionality</span></h3>
<h4> <span class="mw-headline" id="Configure_RSS_Update_Interval">Configure RSS Update Interval</span></h4>
<p><a rel="nofollow" class="external text" href="https://blueprints.launchpad.net/xibo/+spec/rss-update-interval">Blueprint</a>
It is now possible to set this to 0 as documented.
</p>
<h4> <span class="mw-headline" id="Embed_RAW_HTML_-_Calling_Javascript">Embed RAW HTML - Calling Javascript</span></h4>
<p><a rel="nofollow" class="external text" href="https://blueprints.launchpad.net/xibo/+spec/embed-html">Blueprint</a>
It is now possible to embed Javascript along with your HTML and have the client load that. The EmbedInit function is called when the embed media is show in the client, and can be used to simulate the browser onLoad() call.
</p>
<h4> <span class="mw-headline" id="Text_Scrolling_Speed">Text Scrolling Speed</span></h4>
<p><a rel="nofollow" class="external text" href="https://blueprints.launchpad.net/xibo/+spec/adjust-text-scroll-speed">Blueprint</a>
It is now possible to change the scrolling speed for text items.
</p>
<h4> <span class="mw-headline" id="Display_Statistics">Display Statistics</span></h4>
<p><a rel="nofollow" class="external text" href="https://blueprints.launchpad.net/xibo/+spec/display-statistics">Blueprint</a>
Xibo's logging and statistics gathering has undergone a massive overhaul after we had reports of very high CPU loadings caused by clients returning stats to the server. The changes are documented in the blueprint above.
</p><p>We have also exposed the display statistics for the first time in the server interface. From the Management menu, you will have a new "Statistics" menu that will allow you to generate a CSV export of the collected statistics over a date range specified.
</p>
<h4> <span class="mw-headline" id=".net_Client_Display_Improvements">.net Client Display Improvements</span></h4>
<p>The .net client has had a bit of an overhaul to clean up the way it renders media on the screen. This should give faster media/layout changes and reduce the "flickering" when adding media to a layout with a background image.
</p><p>Also scrolling text / RSS ticker items are no longer cropped on their right hand edge.
</p>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.0 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.1 series - which will be unstable.
</p><p>Once we are happy with 1.1, we will release Xibo 1.2 which will be the next stable release series and the direct upgrade path for 1.0. Any future releases of Xibo 1.0 will be bug fix releases only.
</p><p>Therefore there are the following known issues and limitations with Xibo 1.0.3:
</p>
<ul><li> Internationalisation: Xibo currently deals with non-english characters badly. You are strongly recommended to avoid using non-english characters wherever possible. <a rel="nofollow" class="external autonumber" href="https://blueprints.launchpad.net/xibo/translate-xibo">[1]</a>
</li><li> Tickers slow when only one layout scheduled. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/336589">[2]</a>
</li><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[3]</a>
</li><li> Videos will not loop if they are the only media in a region. Add a short empty text field as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/346260">[4]</a>
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[5]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[6]</a>
</li><li> Client will not run under VirtualBox. We think this is a VirtualBox bug as it's a low-level Windows API call that is failing. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/338021">[7]</a>
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[8]</a>
</li></ul>