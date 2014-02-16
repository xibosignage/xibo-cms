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
<li class="toclevel-1 tocsection-1"><a href="#Introduction"><span class="tocnumber">1</span> <span class="toctext">Introduction</span></a>
<ul>
<li class="toclevel-2 tocsection-2"><a href="#Who_should_test_this_software"><span class="tocnumber">1.1</span> <span class="toctext">Who should test this software</span></a></li>
<li class="toclevel-2 tocsection-3"><a href="#What_you_should_expect"><span class="tocnumber">1.2</span> <span class="toctext">What you should expect</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-4"><a href="#Hardware_Requirements"><span class="tocnumber">2</span> <span class="toctext">Hardware Requirements</span></a>
<ul>
<li class="toclevel-2 tocsection-5"><a href="#Suggested_Hardware"><span class="tocnumber">2.1</span> <span class="toctext">Suggested Hardware</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-6"><a href="#Software_Requirements"><span class="tocnumber">3</span> <span class="toctext">Software Requirements</span></a></li>
<li class="toclevel-1 tocsection-7"><a href="#Automated_Installation"><span class="tocnumber">4</span> <span class="toctext">Automated Installation</span></a></li>
<li class="toclevel-1 tocsection-8"><a href="#Manual_Install_Guide"><span class="tocnumber">5</span> <span class="toctext">Manual Install Guide</span></a>
<ul>
<li class="toclevel-2 tocsection-11"><a href="#Ubuntu_12.04_and_derivatives"><span class="tocnumber">5.1</span> <span class="toctext">Ubuntu 12.04 and derivatives</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-13"><a href="#Configuration"><span class="tocnumber">6</span> <span class="toctext">Configuration</span></a>
<ul>
<li class="toclevel-2 tocsection-14"><a href="#Online_Mode"><span class="tocnumber">6.1</span> <span class="toctext">Online Mode</span></a></li>
<li class="toclevel-2 tocsection-15"><a href="#Offline_Mode"><span class="tocnumber">6.2</span> <span class="toctext">Offline Mode</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-16"><a href="#Running_the_client"><span class="tocnumber">7</span> <span class="toctext">Running the client</span></a></li>
<li class="toclevel-1 tocsection-17"><a href="#Reporting_Problems"><span class="tocnumber">8</span> <span class="toctext">Reporting Problems</span></a>
<ul>
<li class="toclevel-2 tocsection-18"><a href="#Full_Debug_Output"><span class="tocnumber">8.1</span> <span class="toctext">Full Debug Output</span></a></li>
<li class="toclevel-2 tocsection-19"><a href="#Known_Issues"><span class="tocnumber">8.2</span> <span class="toctext">Known Issues</span></a></li>
</ul>
</li>
</ul>
</td></tr></table>
<h2> <span class="mw-headline" id="Introduction"> Introduction </span></h2>
<p>There follows some basic instructions for installing the Xibo Python client on Linux.
</p><p><i><b>This guide is for the 1.6.0 Development Preview release.</b></i>
</p><p><i><b>Note that this is beta software. It should not be used in production.</b></i>
</p>
<h3> <span class="mw-headline" id="Who_should_test_this_software"> Who should test this software </span></h3>
<p>Lots of people have asked about testing the Python codebase early. While it would be great to have lots of feedback early on, we're a very very small development team and can't support even tens of people though the installation and configuration. I respectfully request therefore that you have a few months of experience with the Xibo system and enough Linux knowhow that you'd be comfortable compiling some software from source yourself if you want to be involved at this early stage.
</p>
<h3> <span class="mw-headline" id="What_you_should_expect"> What you should expect </span></h3>
<p>The goal initially is to replicate the functionality of the Windows .net client, with the exception of PowerPoint support. It should give you broadly the same output for any given layout, except that the Python client is multi-threaded so there are some low level differences between the two. The Python client will not play any layout that contains PowerPoint items. The Python client supports overlapping regions. The Python client does not currently return layout statistics, but can return media statistics.
</p>
<h2> <span class="mw-headline" id="Hardware_Requirements"> Hardware Requirements </span></h2>
<p>Most reasonably modern PCs should be fine. If you need HD video playback then the faster the CPU the better, otherwise the most basic nettops are normally OK providing they have nVidia graphics. You <i>must</i> have a supported graphics card - most nVidia cards work fine using the binary nVidia drivers.
</p><p>There is a list of hardware and it's compatibility here: <a href="index.php?toc=getting_started&p=install/python_client_hardware" title="Python Client Hardware Compatibility List">Python Client Hardware Compatibility List</a>
</p>
<h2> <span class="mw-headline" id="Software_Requirements"> Software Requirements </span></h2>
<p>Most modern linux distros should work. I'm developing on Ubuntu so <b>Ubuntu 12.04</b> - <b>32 bit Desktop edition</b>. I've not built, tested or provided binaries for 64 bit versions.
</p><p>Xibo server 1.6.0 or later.
</p>
<h2> <span class="mw-headline" id="Automated_Installation"> Automated Installation </span></h2>
<p>The easiest way to install the client is by using the official installer.
</p><p>It can be downloaded from the release page - currently <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.6/1.6.0-rc1">https://launchpad.net/xibo/1.6/1.6.0-rc1</a>.
</p><p>Once downloaded on to your Ubuntu system, open a Terminal and type
</p>
<pre> sudo bash /path/to/the/xibo-1.6.0-ubuntu.all-pyclient.sh 
</pre>
<p>The installer will then take you through the steps required to install.
</p><p>Skip straight to the configuration section below.
</p>
<h2> <span class="mw-headline" id="Manual_Install_Guide"> Manual Install Guide </span></h2>
<p>The official manual install guide is below. You're recommended to use the official installer where possible as it's a great deal simpler!
</p>
<h3> <span class="mw-headline" id="Ubuntu_12.04_and_derivatives"> Ubuntu 12.04 and derivatives </span></h3>
<p>This guide is aimed at people who know what they're doing with Linux, so I'm not going to hold your hand through the install. Here's the basic information you need based on an Ubuntu 12.04 Precise Pangolin installation.
</p>
<ul><li> Download <a rel="nofollow" class="external text" href="http://www.ubuntu.com/getubuntu/download">Ubuntu 12.04</a> 32 bit Desktop Edition and install on the target machine. I suggest a 20GB / partition, some swap and the remainder of the disk formatted and mounted as /opt/xibo. This ensures that the client writing logs etc won't lock you out of the box if the drive fills up.
</li><li> Once Ubuntu is installed, update the machine to get any patches etc:
</li></ul>
<pre>  sudo apt-get update
  sudo apt-get dist-upgrade
</pre>
<ul><li> Reboot
</li><li> If you're using a nVidia card, ensure you're using the binary drivers by referring to the Restricted Driver Manager and enabling them if they're disabled. (Note: I've not tried the new OS nVidia drivers - they may work now).
</li><li> Install the following packages:
</li></ul>
<pre>sudo apt-get install bzr libvdpau1 libboost-python1.46.1 libboost-thread1.46.1 libdc1394-22 libswscale2 libavformat53 python-soapy libxss1 python-feedparser python-serial flashplugin-nonfree libavcodec53 libavformat53 libswscale0
</pre>
<ul><li> Unpack the binary distribution of libavg/Berkelium/libbrowsernode in to /. Binaries are available here: <a rel="nofollow" class="external autonumber" href="https://launchpad.net/xibo/1.6/1.6.0-rc1/+download/libavg-1.8.0-vdpau-berkelium11-12.04.tar.gz">[1]</a> If for some reason you need to compile all that yourself then full source and build instructions are available here: <a href="index.php?toc=developer&p=admin/pyclient_libbrowsernode_build" title="Libbrowsernode Build Instructions">libbrowsernode Build Instructions</a>. <i>(Trust me you don't want to. It takes hours and requires about 4GB of disc space)</i>
</li><li> Run the following:
</li></ul>
<pre>  sudo ldconfig
</pre>
<ul><li> If you want to use the Tahoma font, install the ttf-tahoma-replacement package.
</li><li> Download the python client:
</li></ul>
<pre>  cd /opt/xibo
  bzr branch lp:xibo/1.6 pyclient
</pre>
<h2> <span class="mw-headline" id="Configuration"> Configuration </span></h2>
<h3> <span class="mw-headline" id="Online_Mode"> Online Mode </span></h3>
<ul><li> Create / edit the configuration in /opt/xibo/pyclient/client/python/site.cfg. A site.cfg.default is provided for you to copy as a starting point. defaults.cfg contains all the possible configuration directives. You'll need to edit at least the following:
</li></ul>
<pre>  [Main]
  xmdsUrl=<a rel="nofollow" class="external free" href="http://127.0.0.1">http://127.0.0.1</a>
  xmdsClientID=test
  xmdsKey=test
  xmdsUpdateInterval=90
  requireXmds=false
  width=960
  height=540
  bpp=24
  fullscreen=false
</pre>
<p>xmdsUrl is the address of your Xibo server (eg <a rel="nofollow" class="external free" href="http://my.xibo.server.com/xibo">http://my.xibo.server.com/xibo</a>)
</p><p>xmdsClientID is a random string used to generate the client identifier. This will change in future but for now just set it to something random.
</p><p>xmdsKey is the server key for your Xibo instance
</p><p>xmdsUpdateInterval is the number of seconds between polls to the webservice for updated content
</p><p>requireXmds can be either "true" or "false" (ie not "True" or "False"). If true, the client must sucessfully connect to the webservice before playing cached content.
</p><p>width is the width of your screen in pixels (or the window you want the player to run in if not in fullscreen mode)
</p><p>height is the height of your screen in pixels (or the windows you want the player to run in if not in fulscreen mode)
</p><p>fullscreen can be either "true" or "false" (ie not "True" or "False"). If true, the client will runn fullscreen otherwise it'll run windowed.
</p>
<h3> <span class="mw-headline" id="Offline_Mode"> Offline Mode </span></h3>
<p>The client can be configured to run in an offline mode - where the client will have no direct communication with the Xibo server. <i><b>This feature is available in client versions 1.2.1a1 onwards.</b></i>
</p><p>In this mode, the client receives updates via a USB stick. Content is put on to the memory stick by the <a href="index.php?toc=getting_started&p=install/offline_download_client" title="Offline Download Client">Offline Download Client</a>.
</p>
<ul><li> Create / edit the configuration in /opt/xibo/pyclient/client/python/site.cfg. A site.cfg.default is provided for you to copy as a starting point. defaults.cfg contains all the possible configuration directives. You'll need to edit at least the following:
</li></ul>
<pre>  [Main]
  xmdsLicenseKey=key here
  manualUpdate=true
  width=960
  height=540
  bpp=24
  fullscreen=false
</pre>
<p>xmdsLicenseKey is the client's license key. You generate this with the offline download application.
</p><p>manualUpdate puts the client in offline update mode.
</p><p>width is the width of your screen in pixels (or the window you want the player to run in if not in fullscreen mode)
</p><p>height is the height of your screen in pixels (or the windows you want the player to run in if not in fullscreen mode)
</p><p>fullscreen can be either "true" or "false" (ie not "True" or "False"). If true, the client will run fullscreen otherwise it'll run windowed.
</p><p>The client expects the operating system to mount USB sticks inserted in to the client PC under the /media directory. The client will scan that location frequently looking for updated content for that display (a USB stick can hold updated content for one or more clients). If new content is found, an amber dot will be shown at the top left of the screen. Once the client has finished downloading content from the USB stick, the amber dot will be replaced with a green dot which will remain for a few seconds to indicate completion.
</p>
<h2> <span class="mw-headline" id="Running_the_client"> Running the client </span></h2>
<pre>  cd /opt/xibo/pyclient/client/python
  ./run.sh
</pre>
<p>Once the client is running, it will first attempt to register with the server and then proceed to attempt to pull content. Once the client is running, go to the server and give it a license.
</p><p>To see what the client is doing, press 'i' to bring up the hidden infoscreen. You'll see the client's IP address, remaining disk space, currently running layout ID, scheduled layout IDs and a list of media items for those layouts. Media items in italics failed checking and are therefore invalid. They will be downloaded again automatically.
</p><p>With the infoscreen up, you can force the client to collect from the server by pressing 'r' (refresh). You'll see the 'Schedule' and 'Required Files' lights blink amber as the client connects up and return to green. Red lights indicate either the client isn't licensed or a problem connecting to the server. Grey lights indicate no attempt to connect to the server yet.
</p><p>With the infoscreen up, you can skip to the next layout by pressing 'n'. You quit the client by pressing 'q'.
</p>
<h2> <span class="mw-headline" id="Reporting_Problems"> Reporting Problems </span></h2>
<p>As this isn't released code please do NOT report bugs in Launchpad bugs. Please ask a question in Launchpad questions making it very clear that you're using the python client and which bzr revision you've got. You can find out as follows:
</p>
<pre>  cd /opt/xibo/pyclient
  bzr log | head
  ------------------------------------------------------------
  revno: <b>182</b>
  committer: Alex Harrington &lt;alex@longhill.org.uk&gt;
  branch nick: xibo-python
  timestamp: Fri 2009-12-18 23:09:25 +0000
  message:
    [pyclient] Fixed a whole raft of issues with the previous two commits.
  ------------------------------------------------------------
</pre>
<p>If the client throws an exception then I'll need the full text of the exception along with the circumstances that caused it. If the client isn't doing what you expect, send a question. I may well ask you for full debug output as follows
</p>
<h3> <span class="mw-headline" id="Full_Debug_Output"> Full Debug Output </span></h3>
<ul><li> Edit site.cfg:
</li></ul>
<pre>  [Logging]
  logWriter=XiboLogFile
  logLevel=10
</pre>
<ul><li> Now run as normal (ie ./run.sh)
</li><li> Once the problem has occured, stop the client running and compress run.log (which will hopefully contain the information we need).
</li></ul>
<h3> <span class="mw-headline" id="Known_Issues"> Known Issues </span></h3>
