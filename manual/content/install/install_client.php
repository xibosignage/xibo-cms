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
<h1><?php echo PRODUCT_NAME; ?> Windows Client Installation</h1>
<p>Installing a Windows client is easy:
	<ol>
		<li>Ensure your PC meets the minimum requirements</li>
		<li>Ensure you have the Windows Client MSI installer file</li>
		<li>Double click the <?php echo PRODUCT_NAME; ?>Client.msi installation file to start the install process</li>
	</ol>
</p>

<h2>Minimum Requirements</h2>
<p><?php echo PRODUCT_NAME; ?> is a low resource signage solution, however with the advance of technology it has been necessary to set out some minumum requirements. The installation process will check these for you, but they are listed below for conveniance:
	<ul>
		<li>A network connection to the Xibo Server (possibly over the Internet)</li>
		<li>Microsoft Windows 2000/XP/Vista/7</li>
		<li>.NET Framework v3.5 SP1</li>
		<li>Internet Explorer 7 or 8</li>
		<li>Flash Player Version 9 or later</li>
		<li>Windows Media Player 11 or later</li>
		<li>For Powerpoint support, Microsoft Powerpoint 2003 or later. Powerpoint viewer is not suitable.</li>
	</ul>
</p>

<h2>Installation <small>Step by Step Installer</small></h2>
<p>To start the installation double click on the xibo-client-<?php echo PRODUCT_VERSION; ?>-x86.msi file you downloaded along with the package. The installer will take you through a number of screens asking for your confirmation at each stage. The steps are outlined below.</p>

<h3>Step 1</h3>
<p>You may see the following security warning when installing <?php echo PRODUCT_NAME; ?>. Please click on "Run" to begin the installation.</p>
<img class="img-thumbnail" alt="Security Warning" src="content/install/securitywarning.png" />

<h3>Step 2</h3>
<p>The installer presents a welcome screen. Please press "Next".</p>
<p><img class="img-thumbnail" alt="Welcome Screen" src="content/install/setup1.png" /></p>

<h3>Step 3</h3>
<p>Choose the location where <?php echo PRODUCT_NAME; ?> should be installed. The default location
should normally be sufficient, however to change the location click
browse. After making the selection (or if leaving to the default) click "Next" to continue.</p>
<p><img class="img-thumbnail" alt="Install Location" src="content/install/setup2.png" /></p>

<h3>Step 4</h3>
<p>A confirmation message is then shown. When happy with the selections made click "Install" to begin. Otherwise click "Back" to correct any errors.</p>

<p><img class="img-thumbnail" alt="Begin Installation Confirmation" src="content/install/setup3.png" /></p>

<h3>Step 5</h3>
<p>The Installation is complete. Click finish to exit.</p>

<p><img class="img-thumbnail" alt="Install Complete" src="content/install/setup4.png" /></p>


<h2>Configuring your Client <small>The Options Screen</small></h2>
<p>All newly installed client must be configured and registered with the <?php echo PRODUCT_NAME; ?> Server before it can be used. The <?php echo PRODUCT_NAME; ?> Configuration Options are accessible on each client installation from the Start Menu -&gt; All programs</p>

<p><img class="img-thumbnail" alt="Program Menu" src="content/install/pm.png"></p>

<p>Select "<?php echo PRODUCT_NAME; ?> Client Options" to register this display on the <?php echo PRODUCT_NAME; ?> Network; or to
make changes to this display configuration.</p>

<h3>Options <small>General</small></h3>

<p><img class="img-thumbnail" alt="<?php echo PRODUCT_NAME; ?> General Settings" src="content/install/settings_gen.png" /></p>

<p>
	<ul>
		<li>Server Address: Fill in the address of your <?php echo PRODUCT_NAME; ?> server address. If your <?php echo PRODUCT_NAME; ?> server is on the same machine as the client, enter "http://localhost/<?php echo PRODUCT_NAME; ?>". If <?php echo PRODUCT_NAME; ?> server is installed on a different computer, enter the IP address or hostname of the machine the <?php echo PRODUCT_NAME; ?> server is installed on - for example "http://192.168.0.4/<?php echo PRODUCT_NAME; ?>" or "http://www.my-server.com/<?php echo PRODUCT_NAME; ?>" or similar.</li>

		<li>Server Key: Enter your server key in the "Server Key" box. If you cannot remember the key you can find it in the Settings dialogue on the <a href="../admin/settings.php#server_key">Administration->Settings</a> menu in the web interface on the server.</li>

		<li><p>Local Library Location: defaults to a folder called "<?php echo PRODUCT_NAME; ?> Library" It is used to cache content from the <?php echo PRODUCT_NAME; ?> server so that the client can continue to play if the connection to the <?php echo PRODUCT_NAME; ?> server is lost. If you want to change to a different folder, use the "Browse" button to choose an alternative folder. The library folder must be given write access right to the <?php echo PRODUCT_NAME; ?> client.</p>
		
		<div class="alert alert-warning">You must NOT use the server's library location (if it is on the same PC as the client or via a file share)</div>
		</li>

		<li>The collection interval for content: is the interval in seconds that the client will poll the server for new content. The more
		frequent the collections, the quicker the client will update when changes are made on the server - but at the expense of bandwidth 
		and possibly minor freezes in things like scrolling text when the collection happens. We don't recommend values lower than 60 seconds.</li>

		<li>The unique key for this client: is a unique identifier for this client machine. It is generated from a mix of Windows system identifiers and
		your hardware. If you are installing for the first time, there is no need to amend this value. If you are changing hardware or want to have
		two clients using the same server account then you can edit the key as required.</li>

		<li>Scroll Step Amount (px): is the number of pixels scrolling text will advance each time scrolling text items are told to move. You should
		leave this set to 1px for smooth viewing.</li>

		<li><p>Enable Powerpoint: Tick the box if you have the full version of PowerPoint 2003 or later installed and want to use PowerPoint media items.</p>
		<div class="alert alert-info">Be sure to read the notes on Powerpoint setup here: <a href="#windows_modifications"> Windows Modifications.</a></div></li>

		<li>Enable Statistics: Tick the box if you want the client to send statistics back to the <?php echo PRODUCT_NAME; ?> server. This will generate alot of data that will be 
		stored in the <?php echo PRODUCT_NAME; ?> database. If you don't have any specific use for statistics, we recommend you turn this option off.

		<p>Click the "Save" button.</p></li>	
	</ul>
</p>

<h3>Options <small>Proxy</small></h3>

<p>If you use a proxy server to access your <?php echo PRODUCT_NAME; ?> server, go to the "Proxy" server tab and fill in the details for your network, 
then click "Save". Make sure to set your proxy information in Internet Explorer too.</p>

<p><img class="img-thumbnail" alt="<?php echo PRODUCT_NAME; ?> General Settings" src="content/install/settings_proxy.png" /></p>	

<h3>Options <small>Apperance</small></h3>

<p>Click "Apperance" tab to set the client display window size, and the offsets from the screen origin if so required.</p>
<p>When the specified client display window size i.e. width &amp; height is different from the original layout intended size, some 
of the content will be cropped. e.g. embbedded html, linked html, dataset table etc.</p>
<p>You may use the "Offset X' i.e. set equal to the primary display width, to place the client diplay window on an extended display monitor.</p>

<p><img class="img-thumbnail" alt="<?php echo PRODUCT_NAME; ?> General Settings" src="content/install/settings_app.png" /></p>

<p>The default <?php echo PRODUCT_NAME; ?> Splash screen is display when the client is first launch. You may specify your own Splash Image by entering
the image filename in the "Override Splash Screen" box.</p>

<h3>Options <small>Advanced</small></h3>
<p>The advanced options control some of the fine tuning options as well as settings to aid in troubleshooting. These options are discussed in greater detail in the Troubleshooting section of this manual.</p>
<p><img class="img-thumbnail" alt="<?php echo PRODUCT_NAME; ?> General Settings" src="content/install/settings_adv.png" /></p>

<h3>Options <small>Register</small></h3>
<p>Finally go to the "Register" display tab.</p>

<p><img class="img-thumbnail" alt="<?php echo PRODUCT_NAME; ?> General Settings" src="content/install/settings_reg.png" /></p>

<br />
<p>Optionally rename the client by entering a name in the "Display Name" box. It defaults to the hostname of the PC.
Click the "Register" button.</p>

<p>You should see a message "Display registered and awaiting licensing". If you don't get that message, ensure you entered
the correct URL for the <?php echo PRODUCT_NAME; ?> server, and that your server key is entered correctly.</p>
</blockquote>

<h2>CMS Display Management <small>Enabling your new display client</small></h2>

<p>Now log in to the server web interface, go to the <a href="../admin/displays.php">&quot;Display-&gt;Displays&quot;</a> page. 
You should see your newly registered client in the list. Click the "Edit" button next to the display. The "License Display" 
option will automatically change to "Yes". Optionally select a different default layout (the layout the client will play 
if nothing is scheduled).</p>

<p class="alert alert-warning">Make sure you click Click "Save" before exiting the Client Options.</p>

<h2>Start Client Player</h2>

<p>You can now start the <?php echo PRODUCT_NAME; ?> Client Player. It should show you the <?php echo PRODUCT_NAME; ?> splash screen while the default layout and its media contents
(and anything else you have scheduled) are downloaded and then begin playing layouts'contents.


<h2 id="windows_modifications">Windows Modifications</h1>
<p>Here are some suggested settings for Windows / PowerPoint for a Display Client:</p>
<ul>
	<li>Turn off all <a href="http://www.microsoft.com/windowsxp/using/setup/personalize/screensaver.mspx" 
	title="Microsoft Knowledgebase Article" rel="nofollow">screensavers</a></li>

	<li>Turn off screen <a href="http://www.microsoft.com/windowsxp/using/setup/tips/sleep.mspx" 
	title="Microsoft Knowledgebase Article" rel="nofollow">power saving</a></li>

	<li>Load the "No Sounds" <a href="http://www.microsoft.com/windowsxp/using/accessibility/soundscheme.mspx"
	title="Microsoft Knowledgebase Article" rel="nofollow">Sound Scheme</a>
	(Control Panel -&gt; Sounds and Audio Devices Properties)</li>

	<li>Set a plain wallpaper (Hopefully nobody will see it, but you might need to reboot the client, or restart <?php echo PRODUCT_NAME; ?> and a sane wallpaper is a help)</li>

	<li>If the client is accessible from where you manage your displays from, you might want to install <a href="http://www.uvnc.com/" 
	title="UltraVNC Homepage" rel="nofollow">UltraVNC</a> server so you can connect in and check on the client from time to time. 
	Use the "View only" option in the VNC client to avoid disturbing the display.</li>

	<li>Set Windows to <a href="http://www.mvps.org/marksxp/WindowsXP/welskip.php" title="MVPS Atricle" 
	rel="nofollow">log on as your display client user automatically</a></li>

	<li>Disable <a href="http://support.microsoft.com/kb/307729" title="Microsoft Knowledgebase Article" rel="nofollow">
	balloon tips in the notification area</a></li>

	<li>Disable Windows Error Reporting. Occasionally PowerPoint seems to "crash" when <?php echo PRODUCT_NAME; ?> closes it. Unfortunately this leaves an unsightly
	"PowerPoint has encountered a problem and needs to close" message on the display. Follow the steps 
	<a href="http://www.windowsnetworking.com/articles_tutorials/Disable-Error-Reporting-Windows-XP-Server-2003.html"
	title="Windows Networking .com Article" rel="nofollow">here</a>
	to disable Windows Error Reporting completely - including notifications.</li>

	<li>Also disable Office Application Error reporting. Follow instructions at <a href="http://support.microsoft.com/kb/325075"
	title="Microsoft Knowledgebase Article" rel="nofollow">KB325075</a> or merge <a href="DWNeverUpload.reg"
	title="Registry Patch"> this registry patch</a>.
	<p class="alert alert-warning">Please take a backup before modifying the registry</p>
	</li>
</ul>

<p>If you're using PowerPoint, then there are a couple of extra steps:</p>
<p>First consider if you would be better converting your PowerPoint content to video files. PowerPoint 2010 and later can "Save As" a WMV file
which can be loaded straight in to Xibo and is far more reliable. If however you still need to play PowerPoint files, please ensure you action
the following:</p>

<ul>
<li>The first time you run <?php echo PRODUCT_NAME; ?> with a PowerPoint, you might get a popup appear that asks what <?php echo PRODUCT_NAME; ?> should do with the PowerPoint file.
The popup actually originates from Internet Explorer. Choose to "Open" the file, and untick the box so you won't be prompted again.</li>

<li>In some circumstances, you may find that PowerPoint, the application, loads instead of the file opening within <?php echo PRODUCT_NAME; ?> itself. If that happens,
try merging <a href="Powerpoint-fix.reg" title="Powerpoint-fix.reg"> this registry patch</a>. (Taken from 
<a href="http://www.pptfaq.com/FAQ00189.htm" title="http://www.pptfaq.com/FAQ00189.htm" rel="nofollow">pptfaq.com</a>).
Users of Powerpoint 2007 should go to Microsoft <a href="http://support.microsoft.com/kb/927009" 
title="http://support.microsoft.com/kb/927009" rel="nofollow">KB927009</a> and run the FixIT application instead. Users of PowerPoint 2010 
should go here instead <a href="http://support.microsoft.com/kb/982995/en-us" title="http://support.microsoft.com/kb/982995/en-us" 
rel="nofollow">KB982995</a></li>

<li>Note also that PowerPoint will put scroll bars up the side of your presentation, unless you do the following for each PowerPoint file BEFORE you upload it:</li>
</ul>

<ul>
	<li>Open your PowerPoint Document</li>
	<li>Slide Show -&gt; Setup Show</li>
	<li>Under "Show Type", choose "Browsed by an individual (window)" and then untick "Show scrollbar"</li>
</ul>
