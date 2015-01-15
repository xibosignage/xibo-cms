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
<li class="toclevel-1 tocsection-1"><a href="#Xibo_1.0.1_-_Codename_.22Halley.22"><span class="tocnumber">1</span> <span class="toctext">Xibo 1.0.1 - Codename "Halley"</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Requirements"><span class="tocnumber">2</span> <span class="toctext">Requirements</span></a></li>
<li class="toclevel-1 tocsection-3"><a href="#Upgrading"><span class="tocnumber">3</span> <span class="toctext">Upgrading</span></a></li>
<li class="toclevel-1 tocsection-4"><a href="#Bug_Fixes"><span class="tocnumber">4</span> <span class="toctext">Bug Fixes</span></a></li>
<li class="toclevel-1 tocsection-5"><a href="#Known_Issues_and_Limitations"><span class="tocnumber">5</span> <span class="toctext">Known Issues and Limitations</span></a></li>
</ul>
</td></tr></table>
<h3> <span class="mw-headline" id="Xibo_1.0.1_-_Codename_.22Halley.22">Xibo 1.0.1 - Codename "Halley"</span></h3>
<p>This is a bug fix release mainly aimed at people that are having trouble installing Xibo. If you already have a working version of Xibo 1.0.0 there is no need for you to upgrade to this latest release.
</p><p>You can download this release from <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.0/1.0.1">https://launchpad.net/xibo/1.0/1.0.1</a>
</p>
<h3> <span class="mw-headline" id="Requirements"> Requirements </span></h3>
<p>You should use the 1.0.0 client with this version of the Xibo server.
</p><p>Xibo now recommends PHP 5.2.0 or higher. Xibo will function in most cases with 5.1.0 but you may experience some problems with experimental PHP functions. In particular Xibo may present a restricted list of timezones in the Admin Interface.
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
<p>Please report any bugs in the Bugs section of Launchpad: <a rel="nofollow" class="external free" href="https://bugs.launchpad.net/xibo">https://bugs.launchpad.net/xibo</a>
</p><p>Please report any enhancement requests in the Blueprints section of Launchpad: <a rel="nofollow" class="external free" href="https://blueprints.launchpad.net/xibo">https://blueprints.launchpad.net/xibo</a>
</p><p>Please ask for help/advice in the Answers section of Launchpad: <a rel="nofollow" class="external free" href="https://answers.launchpad.net/xibo">https://answers.launchpad.net/xibo</a>
</p>
<h3> <span class="mw-headline" id="Bug_Fixes">Bug Fixes</span></h3>
<p>Fixes for the following Bugs
</p>
<ul><li> 358996 - [Linux Server] Install block on configuration of database
</li><li> 369674 - Xibo requires PHP 5.1.0 (with compile options) or 5.2.0 otherwise, but only checks for 5.0.2
</li><li> 362320 - Retired layouts shown in Default Layout select
</li><li> 361125 - The file name is not displayed in Layout / Region Options
</li></ul>
<h3> <span class="mw-headline" id="Known_Issues_and_Limitations"> Known Issues and Limitations </span></h3>
<p>Xibo is a young project. There are plenty of new features in the pipeline, but to provide a stable platform for users wanting Xibo now, the 1.0 series of releases are now feature-frozen (no new features will be implemented). All new development work will go in to the 1.1 series - which will be unstable.
</p><p>Once we are happy with 1.1, we will release Xibo 1.2 which will be the next stable release series and the direct upgrade path for 1.0. Any future releases of Xibo 1.0 will be bug fix releases only.
</p><p>Therefore there are the following known issues and limitations with Xibo 1.0.0-final:
</p>
<ul><li> Internationalisation: Xibo currently deals with non-english characters badly. You are strongly recommended to avoid using non-english characters wherever possible. <a rel="nofollow" class="external autonumber" href="https://blueprints.launchpad.net/xibo/translate-xibo">[1]</a>
</li><li> Tickers slow when only one layout scheduled. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/336589">[2]</a>
</li><li> The backgrounds of Text and RSS media items are not transparent if the background of a layout is a GIF image. Please use JPEG images as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/348506">[3]</a>
</li><li> Videos will not loop if they are the only media in a region. Add a short empty text field as a work around. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/346260">[4]</a>
</li><li> Overlapping regions are handled badly. The existing server/client were never developed to support overlapping regions, but they do work in some circumstances which is why we have decided to leave this functionality intact, but with the caveat that they may not work as you expect. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/321377">[5]</a> <a rel="nofollow" class="external autonumber" href="https://answers.launchpad.net/xibo/+question/64768">[6]</a>
</li><li> Client will not run under VirtualBox. We think this is a VirtualBox bug as it's a low-level Windows API call that is failing. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/338021">[7]</a>
</li><li> Backgrounds of FlashMedia items are not transparent. This is a limitation of the Adobe Flash C# control. <a rel="nofollow" class="external autonumber" href="https://bugs.launchpad.net/xibo/+bug/341634">[8]</a>
</li></ul>