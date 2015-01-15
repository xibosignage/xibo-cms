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
<h1>Choosing your Environment <small>SaaS or Self-Hosted?</small></h1>


<h2><span class="mw-headline" id="Install_on_Linux"> Install on Linux </span></h2>
<p>Linux makes a good home for a Xibo server. All of the software it needs to support it is provided by the major Linux distributions. We'll cover Ubuntu in detail here, as it's what I use. There are some notes further down for other distributions, however the whole procedure is very similar.
</p>
<h3> <span class="mw-headline" id="Setup_Apache_and_PHP"> Setup Apache and PHP </span></h3>
<h4> <span class="mw-headline" id="Ubuntu"> Ubuntu </span></h4>
<ul><li> Install Apache 2.x Webserver
</li></ul>
<pre> sudo apt-get install apache2
</pre>
<ul><li> Install MySQL Server
</li></ul>
<pre> sudo apt-get install mysql-server
</pre>
<ul><li> Install PHP5&#160;:
</li></ul>
<pre> sudo apt-get install php5 php5-mysql php5-gd
</pre>
<p><i>On Ubuntu, DOM, libXML, gettext and JSON extension is already compiled on PHP5</i>
</p>
<ul><li> Restart Apache 2 Webserver if necessary
</li></ul>
<pre> sudo /etc/init.d/apache2 restart
</pre>
<h4> <span class="mw-headline" id="CentOS_5.x_.2F_Redhat_RHEL_5.x"> CentOS 5.x / Redhat RHEL 5.x </span></h4>
<p>JSON extension is not include in CentOS 5.x or Redhat RHEL 5.x but you can install it with this steps&#160;:
</p>
<ul><li> Install JSON PHP Extension
</li></ul>
<pre> yum install php-devel
</pre>
<p><i>If you have more than 8MB of memory limit for PHP, install with PEAR&#160;:</i>
</p>
<pre> pear install pecl/json
</pre>
<p><i>If you have less than 8MB of memory limit for PHP, PEAR failed to install... Use PECL&#160;: </i>
</p>
<pre> pecl install json
</pre>
<ul><li> Active JSON extension&#160;:
</li></ul>
<pre> vim /etc/php.d/json.ini
</pre>
<pre> # Json Extension
 extension=json.so
</pre>
<ul><li> Save file and restart the Apache webserver&#160;:
</li></ul>
<pre> /etc/init.d/httpd restart
</pre>
<h4> <span class="mw-headline" id="SuSe"> SuSe </span></h4>
<ul><li> during web install library location says 'full path' but means relative to document root on suse linux 
</li></ul>
<pre>for me this is /srv/www/htdocs/xlib   in the box for full path, i put "xlib"
</pre>
<h3> <span class="mw-headline" id="Install_Xibo"> Install Xibo </span></h3>
<ul><li> Extract the tarball you downloaded inside your webserver's document root (eg /var/www/xibo) and ensure the webserver has permissions to read and write those files:
</li></ul>
<pre> cd /var/www
 sudo tar zxvf ~/xibo-1.0.5-server.tar.gz
 sudo mv xibo-1.0.5-server xibo
 sudo chown www-data.www-data -R xibo
</pre>
<ul><li> Make a directory for the server library. Make sure the webserver has permission to write to this location:
</li></ul>
<pre> sudo mkdir /xibo-library
 sudo chown www-data.www-data -R /xibo-library
</pre>
<ul><li> You should now use a webbrowser to visit your webserver - eg <a rel="nofollow" class="external free" href="http://myserver/xibo">http://myserver/xibo</a>
</li><li> The process is fairly self explainatory. Follow the final part of the <a href="/wiki/Install_Guide_Xibo_Server#start_install" title="Install Guide Xibo Server">Windows instructions</a> for greater detail.
</li></ul>
<h2> <span class="mw-headline" id="Install_on_Windows"> Install on Windows </span></h2>
<p>This Windows install guide is focused mainly on someone who wants to use a Windows PC to be the server as well as the client, or perhaps has a spare Windows XP machine to use as a server. The instructions should work equally well on Windows Server operating systems.
</p><p>Xibo should also run under IIS, however it's not a platform the Xibo development team currently tests with. If you know your way around IIS, then the latter half of this guide plus the "What you need to know!" section should get you going.
</p>
<h3> <span class="mw-headline" id="Install_the_Webserver_.28XAMPP.29"> Install the Webserver (XAMPP) </span></h3>
<p>We'll be using XAMPP to install Apache, MySQL, PHP (amongst other things) to support Xibo. This is convenient as it provides the whole system in a single installer, and can be uninstalled via Add/Remove Programs at a later date if required. If you already have XAMPP installed, you can skip to the next section.
</p><p>You can download XAMPP from <a rel="nofollow" class="external text" href="http://www.apachefriends.org/en/xampp.html">Apache Friends</a>. You need to download the full XAMPP Installer package for Windows systems. Save it to the Desktop.
</p><p>Once XAMPP Installer has downloaded, double click on it to run it. You may be prompted to allow the installer to run as the publisher could not be verified. Click "Run".
</p><p>You should get to the start of the install wizard.
</p>
<div class="img-thumbnail"><img src="content/install/Win32_xampp_install_start.jpg"></div>
<p>By default, XAMPP installs to "c:\xampp". Unless you need to move it somewhere else, then that's a good choice. If you do select a different directory, remember it for the next step when we install Xibo.  Click Install.
The installer will now run, and extract a number of files.  When it finishes, it will bring you to a command prompt.
</p><p>You'll now be asked a few questions about how XAMPP should install itself.  At the following prompts, you may select all default options.
</p>
<div class="img-thumbnail"><img src="content/install/Capture-2.jpg"></div>
<div class="img-thumbnail"><img src="content/install/Capture-3.jpg"></div>
<div class="img-thumbnail"><img src="content/install/Capture-4.jpg"></div>
<div class="img-thumbnail"><img src="content/install/Capture-6.jpg"></div>
<p>Once the installation scripts complete, you should choose option 1 to load the XAMPP Control Panel. It should open up, and if everything went to plan, look like the screenshot.
</p>
<div class="img-thumbnail"><img src="content/install/Control_Panel_Running.jpg"></div>
<p>At this point, you need to check the boxes next to "Svc" for both Apache and MySql.  As you click each, it will prompt you to install the service for that item.  Click OK.  Then click the "Start" button on each of these items as well.
Your screen should now look like the below:
</p>
<div class="img-thumbnail"><img src="content/install/Configured_Control_Panel.jpg"></div>
<p><br />
Before we install Xibo, we need to configure a few things on XAMPP to make it a bit more secure. From the XAMPP Control Panel, click the "Admin" button next to MySQL. This will load a web browser and take you to an application called PHPMyAdmin that was installed along with XAMPP. It will let us setup a password for the "root" MySQL account. The "root" account on MySQL has privileges to add new users, create databases etc so needs to have a strong password.
</p><p>From the PHPMyAdmin screen, click "Privileges" at the top of the screen. You'll see the database users that exist already listed. We're interested in the one called "root" that has "localhost" in the "Host" column. Click the blue "Edit Privileges" symbol to the right of the word "Yes".
</p>
<div class="img-thumbnail"><img src="content/install/Win32_phpmyadmin_privileges.png"></div>
<p>Scroll down the page until you find the "Change Password" box. Enter a new password in both the password boxes and click "Go". On a piece of paper, write down "MySQL Admin User details. Username: root Password:" followed by the password you just chose. We'll need these later!
</p>
<div class="img-thumbnail"><img src="content/install/Win32_phpmyadmin_password.png"></div>
