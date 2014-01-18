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
<li class="toclevel-1 tocsection-1"><a href="#Introduction"><span class="tocnumber">1</span> <span class="toctext">Introduction</span></a></li>
<li class="toclevel-1 tocsection-2"><a href="#Installation"><span class="tocnumber">2</span> <span class="toctext">Installation</span></a>
<ul>
<li class="toclevel-2 tocsection-3"><a href="#Linux_Debian_Derivatives_.28eg_Ubuntu.29"><span class="tocnumber">2.1</span> <span class="toctext">Linux Debian Derivatives (eg Ubuntu)</span></a></li>
<li class="toclevel-2 tocsection-4"><a href="#Windows_XP_or_later"><span class="tocnumber">2.2</span> <span class="toctext">Windows XP or later</span></a></li>
</ul>
</li>
<li class="toclevel-1 tocsection-5"><a href="#Usage"><span class="tocnumber">3</span> <span class="toctext">Usage</span></a></li>
<li class="toclevel-1 tocsection-6"><a href="#Transferring_settings"><span class="tocnumber">4</span> <span class="toctext">Transferring settings</span></a></li>
</ul>
</td></tr></table>
<h2> <span class="mw-headline" id="Introduction"> Introduction </span></h2>
<p>The Offline Download Client is a cross platform python application which downloads media and schedule information from a Xibo server and saves it to a USB memory stick for transfer to a Xibo client running in <a href="/wiki/Install_Guide_Python_Client#Offline_Mode" title="Install Guide Python Client">Offline Mode</a>.
</p>
<h2> <span class="mw-headline" id="Installation"> Installation </span></h2>
<h3> <span class="mw-headline" id="Linux_Debian_Derivatives_.28eg_Ubuntu.29"> Linux Debian Derivatives (eg Ubuntu) </span></h3>
<ul><li> Install the following packages:
</li></ul>
<pre> sudo apt-get install python-wxgtk2.8 python-soappy
</pre>
<ul><li> Download the <a rel="nofollow" class="external text" href="http://dl.dropbox.com/u/58386/downloadClient/Xibo-Offline-Download-v1.2.1a2.1.tar.gz">Offline Download Client</a> files and extract to a directory of your choice.
</li><li> Run the XiboOfflineDownload.py application
</li></ul>
<h3> <span class="mw-headline" id="Windows_XP_or_later"> Windows XP or later </span></h3>
<ul><li> Download the Offline Download Client from the Xibo Release page - currently <a rel="nofollow" class="external free" href="https://launchpad.net/xibo/1.2/1.2.1">https://launchpad.net/xibo/1.2/1.2.1</a>
</li><li> Double click the MSI file to start the installation.
</li><li> You may need to install the Microsoft Visual C++ Runtime files if you don't have them installed already. Download them here: <a rel="nofollow" class="external autonumber" href="http://www.microsoft.com/downloads/en/details.aspx?FamilyID=9b2da534-3e03-4391-8a4d-074b9f2bc1bf&amp;displaylang=en">[1]</a>
</li></ul>
<h2> <span class="mw-headline" id="Usage"> Usage </span></h2>
<p>When you first open the client, you first need to configure your Xibo Server's (v1.2.0 or later) settings. Go to the settings tab:
<img class="img-thumbnail" alt="Offline Download Client Settings" src="content/install/offline_download_client_settings.png" />
</p><p>Enter the URL of your Xibo server, your Xibo server key and click "Save".
</p><p>Back on the Download tab, you can now create your first offline display.
</p>
<ul><li> Click "Displays: Add"
</li><li> Give your new display a name and license key.
</li><li> Click "Generate Key" to generate a new license key. You could optionally copy the license key from an existing Xibo display to connect to an existing account on the server.
</li><li> Click "Add Display".
</li></ul>
<p>The client will now create a new display on the Xibo server. You should go to Display Management on the Xibo server and license the display as you would for a normal client.
</p><p>Next, on the Xibo server schedule content on your new display as you desire. By default, the Offline Download Client will be given two days worth of content to transfer. If you want a longer timespan, adjust "REQUIRED_FILES_LOOKAHEAD" in the general section of the server settings.
</p><p>Once you've setup your displays and scheduled content on them:
</p>
<ul><li> Connect a USB memory stick to the PC running the Offline Download Client. Make a note of the drive letter or location where the stick is mounted.
</li><li> Select the display(s) (one or more) that you want to download content for from the left hand list. Hold down shift or control to select more than one item.
</li><li> Click "Download".
</li><li> The application will ask you to select a folder to save the content to. You should select the root directory of your USB memory stick. On Windows, that would be for example E:\. On Linux, that would be something like /media/THUMBDRIVE.
</li></ul>
<p><img class="img-thumbnail" alt="Offline Download Client Operation" src="content/install/offline_download_client_operation.png" /></a>
</p><p>The content will now download on to the memory stick. For large numbers of clients or if there are large files, the process can take some time. The progress bar will show the progress of each client as it is processed.
</p><p>Once complete, safely remove the USB stick and connect it to the client running in offline mode.
</p>
<h2> <span class="mw-headline" id="Transferring_settings"> Transferring settings </span></h2>
<p>You can backup/restore or transfer the Offline Download Client application settings on the Configuration tab using the Import/Export buttons. The configuration can be transferred across Windows/Linux machines.
</p>