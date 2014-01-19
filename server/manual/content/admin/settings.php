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
<h1>Settings</h1>
<p>This page provides you with all the settings options to configure Xibo to your environment. Each tab provides a set of forms where you can alter default settings and paths.</p>

<p>The items in the boxes provide you with details of your PHP settings on the server with Xibo installed.</p>

<p>Settings should only be modified by the administrator of the system, as incorrect settings could cause system stablility issues.</p>

<h2>Content</h2>    
<p>This tab allows you to set default content item settings - default length. This helps Xibo if the file does not contain duration information.</p>

<p><img class="img-thumbnail" alt="Setting_Content" src="content/admin/sa_setting_content.png"></p>

<h3>Library Size &amp; Bandwidth</h3> 
<p>To manage limits on the library file size and monthly bandwidth usage. This is done by virtue of two settings in the database
table (LIBRARY_SIZE_LIMIT_KB &amp; MONTHLY_XMDS_TRANSFER_LIMIT_KB).</p> 

<p>If no limits are entered in database, everything continues to work without restriction; however once a limit is entered Xibo will start 
validating against these limits, and when they are exceeded will prevent the upload of new media / updates to clients.</p>

<p>However these two settings are currently not available through the user interface; Only the statistical data is shown in Content page</p>
 

<a name="Default_Settings" id="Default_Settings"></a><h2>Default Settings</h2> 
<p>Default Settings set the default options on some forms to always appear and be applied unless altered. You can also change
your time zone in this section.</p>

<p><img class="img-thumbnail" alt="Setting_Default" src="content/admin/sa_setting_default.png"></p>
 

<a name="Error" id="Error"></a><h2>Error</h2> 
<p>Tab allows you to alter how Xibo handles errors. You can turn the error and audit logs on and off. As logs records 
size growth rapidly, you should take care to enable it only when necessary e.g. during system debug.</p>

<p><img class="img-thumbnail" alt="Setting_Error" src="content/admin/sa_setting_error.png"></p>
 

<a name="General_Setting" id="General_Setting"></a><h2>General Settings</h2> 
<p>General setting provide details of your current settings in your PHP.ini file on your server. It also allows you to setup
whether you are collecting statics off your Xibo displays.</p>

<p><img class="img-thumbnail" alt="Setting_General" src="content/admin/sa_setting_general.png"></p>

<a name="Server_Key" id="Server_Key"></a><h3>Server Key (SERVER_KEY)</h3>
<p>The Server Key must be provided and required by the Client during the initial Client Display setup.</p>

<a name="Required_Files_LookAhead" id="Required_Files_LookAhead"></a><h3>Required Files Look Ahead (REQUIRED_FILES_LOOKAHEAD)</h3>
<p>The local client library may get larger and larger if its obsoleted contents are not deleted as time goes by.</p>
<p><strong>Clearing up old and unused files:</strong><br />
The client manages its content library automatically, clearing up files that are no longer needed (including temporary files).</p>
<p>This is managed by using the "Required Files Look Ahead" setting in the server; increasing this value causes the client
to consider media to be required for longer period of time.</p>

 

<a name="Maintenance" id="Maintenance"></a><h2>Maintenance</h2>    
<p>Tab allows you configure Xibo maintenance.</p>

<a name="Introduction" id="Introduction"></a><h3>Introduction</h3>
<p>When Xibo is running, logs and statistics slowly accumulate on the server and consume disk space. In extreme cases the sheer 
volume of those records can cause the server interface to slow and become unresponsive.</p>

<p>It is also reassuring to know that if there is a problem with a display and it stops checking in with the Xibo server, you will 
be notified by email so you can take action to resolve the problem.</p>

<p>The maintenance script can be scheduled to run periodically and perform background cleanup tasks such as deleting old logs and 
statistics, and checking the status of the displays.</p>

<a name="Prerequisites" id="Prerequisites"></a><h3>Prerequisites</h3>
<p>In order to send email notifications, your PHP must have a working mail() command.</p>
<p>You need to ensure your PHP installation is configured to send mail via a 
<a href="http://email.about.com/od/emailprogrammingtips/qt/Configure_PHP_to_Use_a_Local_Mail_Server_for_Sending_Mail.htm" class="external text" 
title="http://email.about.com/od/emailprogrammingtips/qt/Configure_PHP_to_Use_a_Local_Mail_Server_for_Sending_Mail.htm" rel="nofollow">local</a>
or <a href="http://email.about.com/od/emailprogrammingtips/qt/Configure_PHP_to_Use_a_Remote_SMTP_Server_for_Sending_Mail.htm" class="external text" 
title="http://email.about.com/od/emailprogrammingtips/qt/Configure_PHP_to_Use_a_Remote_SMTP_Server_for_Sending_Mail.htm" rel="nofollow">remote</a>
SMTP server.</p>

<p>Once you have verified that your PHP installation has a working mail() command, you can proceed to the next step.</p>

<a name="Setup_for_New_Xibo_Installations" id="Setup_for_New_Xibo_Installations"></a><h3>Setup for New Xibo Installations</h3>
<p>Xibo server 1.2.0 and later have the maintenance functionality.</p>
<p>New Xibo installations come pre-populated with some default values for the maintenance script, but with the entire system 
disabled. You can proceed to the configuration section.</p>

<a name="Setup_for_Xibo_Installations_.3C_1.2.0" id="Setup_for_Xibo_Installations_.3C_1.2.0"></a><h3>Setup for Xibo Installations &lt; 1.2.0</h3>
<p>Only Xibo server versions 1.2.0 and later have this functionality. If you're upgrading your older Xibo server installations to 1.2.0 
then you will be prompted to modify the default settings as part of the upgrade process.</p>
<p>If you decide to enable the maintenance script as part of your upgrade, it will automatically be configured to use "protected" 
mode as this is the most secure option. The other options are discussed in detail below. You should change your Maintenance Key immediately 
in protected mode as the default key is publicly available and offers no protection.</p>

<a name="Configuration" id="Configuration"></a><h3>Configuration</h3>
<p>Configuration for the maintenance script can be found in Xibo server at "Administration -&gt; Settings -&gt; Maintenance" tab.</p>

<p><img class="img-thumbnail" alt="Setting_Maintenance" src="content/admin/sa_setting_maintenance.png"></p> 

<p>There are several options associated with the maintenance script:</p>

<ul>
<li>Maintenance Enabled (MAINTENANCE_ENABLED):
<ul>
<li><b>Off</b> - All maintenance functionality is disabled.</li>
<li><b>On</b> - All maintenance functionality is enabled. You can use any of the methods below to call the maintenance script 
on a schedule without specifying a key.</li>
<li> <b>Protected</b> - All maintenance functionality is enabled. You must specify the correct key when calling the maintenance 
script. This is to prevent unauthorised persons from repeatedly calling the script and generating large amounts of alert email.</li>
</ul></li>

<li>Maintenance Key (MAINTENANCE_KEY)<br />
The secret key required to allow the maintenance script to run when "Maintenance Enabled" is set to "Protected" mode.</li>

<li>Email Alerts (MAINTENANCE_EMAIL_ALERTS)<br />
Globally enable or disable the sending of email alerts. You can enable/disable alerts for individual displays in Display Management.</li>

<li>Alert Timeout (MAINTENANCE_ALERT_TOUT)<br />
Globally configure how many minutes after a display lasts connects to the server we should consider it to have a problem and cause an 
alert to be sent. You can override this default for individual displays in Display Management. You should make sure this time is longer 
than the collection interval you have configured on your clients to avoid false positive alerts.</li>

<li>Email To (mail_to)<br />
Who should the alert emails be sent to?</li>

<li>Email From (mail_from)<br />
Who should the alert emails appear to be from?</li>

<li>Log Maximum Age (MAINTENANCE_LOG_MAXAGE)<br />
How many days worth of log messages to keep. Logs older than this will be deleted. Set to 0 to keep all logs indefinitely.</li>

<li>Statistics Maximum Age (MAINTENANCE_STAT_MAXAGE)<br />
How many days worth of statistics to keep. Statistics older than this will be deleted. Set to 0 to keep all statistics indefinitely.</li>
</ul>
<p>Once you have decided which of the options you want to enable and the parameters required, you need to setup some mechanism for calling 
the <b>maintenance.php</b> script on a schedule. Skip to the appropriate section for your server below.</p>
<p>If you do not have permission to setup scheduled tasks on your server, you could arrange for a remote computer to call the maintenance.php script.</p>

<a name="Windows_Scheduled_Task" id="Windows_Scheduled_Task"></a><h2>Windows Scheduled Task</h2>
<p>This section is broadly based upon the Moodle Cron documentation available <a href="http://docs.moodle.org/en/Cron#Managing_Cron_on_Windows_systems" 
class="external text" title="http://docs.moodle.org/en/Cron#Managing_Cron_on_Windows_systems" rel="nofollow">here</a>.</p>

<ul>
<li>Find the php.exe or php-win.exe program on your server. It will be in your PHP installation directory.</li>
<li>Setup a <b>Scheduled Task</b></li>
<ul>
<li>Go to Start -&gt; Control Panel -&gt; Scheduled Tasks -&gt; Add Scheduled Task.</li>
<li>Click "Next" to start the wizard:</li>
<li>Click the "Browse..." button and browse to your php.exe or php-win.exe and click "Open"</li>
<li>Type "Xibo Maintenance" as the name of the task and select "Daily" as the schedule. Click "Next".</li>
<li>Select "12:00 AM" as the start time, perform the task "Every Day" and choose today's date as the starting date. Click "Next".</li>
<li>Enter the username and password of the user the task will run under (it does not have to be a privileged account at all). 
Make sure you type the password correctly. Click "Next".</li>
<li>Mark the checkbox titled "Open advanced properties for this task when I click Finish" and click "Finish".</li>
<li>In the new dialog box, type the following in the "Run:" text box:
<pre>c:\php\php-win.exe -f c:\path\to\xibo\maintenance.php secret</pre>
Replace secret with your Maintenance Key if you are running in Protected Mode.</li>
<li>Click on the "Schedule" tab and there in the "Advanced..." button.</li>
<li>Mark the "Repeat task" checkbox and set "Every:" to 5 minutes, and set "Until:" to "Duration" and type "23" hours and "59" minutes. 
If you are Alert Timeouts are less than 5 minutes, you may want to run the maintenance script more often.</li>
<li>Click "OK".</li>
</ul>
<li><b>Test your scheduled task</b>.
<p>You can test that your scheduled task can run successfully by clicking it with the right button
and chosing "Run". If everything is correctly setup, you will briefly see a DOS command window while php executes and fetches the cron 
page and then it disappears. If you refresh the scheduled tasks folder, you will see the <i>Last Run Time column</i>
in detailed folder view) reflects the current time, and that the Last Result column displays "0x0" (everything went OK). 
If either of these is different, then you should recheck your setup.</p></li></ul>

<a name="Maintenance_on_Unix_Servers" id="Maintenance_on_Unix_Servers"></a><h2>Maintenance on Unix Servers</h2>
<p>This section is broadly based upon the Moodle Cron documentation available <a href="http://docs.moodle.org/en/Cron#Using_a_cron_command_line_in_Unix" 
class="external text" title="http://docs.moodle.org/en/Cron#Using_a_cron_command_line_in_Unix" rel="nofollow">here</a>.
There are different command line programs you can use to call the maintenance page from the command line. Not all of them may be available
on a given server.</p>

<p>For example, you can use a Unix utility like 'wget': </p>
<pre>wget -q -O /dev/null http://example.com/xibo/maintenance.php?key=changeme</pre>

<p>Note in this example that the output is thrown away (to /dev/null).</p>
<p>The same thing using lynx:</p>
<pre>lynx -dump http://example.com/xibo/maintenance.php changeme &gt; /dev/null</pre>

<p>Note in this example that the output is thrown away (to /dev/null).</p>
<p>Alternatively, you can use a standalone version of PHP, compiled to be run on the command line. The disadvantage is that you need to 
have access to a command-line version of php. The advantage is that your web server logs are not filled with constant requests to 
maintenance.php and you can run at a lower I/O and CPU priority.</p>
<pre> php /var/www/xibo/maintenance.php changeme</pre>

<p>Example command to run at lower priority:</p>
<pre> ionice -c3 -p$$;nice -n 10 /usr/bin/php /var/www/xibo/maintenance.php changeme &gt; /dev/null</pre>

<a name="Running_maintenance_with_crontab" id="Running_maintenance_with_crontab"></a><h3>Running maintenance with crontab</h3>
<p>This section is broadly based upon the Moodle Cron documentation available <a href="http://docs.moodle.org/en/Cron#Using_the_crontab_program_on_Unix"
class="external text" title="http://docs.moodle.org/en/Cron#Using_the_crontab_program_on_Unix" rel="nofollow">here</a>.
Most unix-based servers run a version of cron. Cron executes commands on a schedule.</p>
<p>Modern Linux distributions use a version of cron that reads its configuration from /etc/crontab. If you have an /etc/crontab, 
edit it with your favourite editor, otherwise run the following to edit the crontab:</p>
<pre>crontab -e</pre>

<p>and then adding one of the above commands like:</p>
<pre>*/5 * * * * wget -q -O /dev/null http://example.com/xibo/maintenance.php?key=changeme</pre>

<p>The first five entries are the times to run values, followed by the command to run. The asterisk is a wildcard, indicating any time. 
The above example means run the command <i>wget -q -O /dev/null...</i> every 5 minutes (*/5), every hour (*), every day of the month (*), 
every month (*), every day of the week (*).</p>
<p>The "O" of "-O" is the capital letter not zero, and refers the output file destination, in this case "/dev/null" which is a black 
hole and discards the output. If you want to see the output of your cron.php then enter its url in your browser.</p>

<ul>
<li> <a href="http://linuxweblog.com/node/24" class="external text" title="http://linuxweblog.com/node/24" rel="nofollow">A basic crontab tutorial</a></li>
<li> <a href="http://www.freebsd.org/cgi/man.cgi?query=crontab&amp;apropos=0&amp;sektion=5&amp;manpath=FreeBSD+6.0-RELEASE+and+Ports&amp;format=html" 
class="external text" title="http://www.freebsd.org/cgi/man.cgi?query=crontab&amp;apropos=0&amp;sektion=5&amp;manpath=FreeBSD+6.0-RELEASE+and+Ports&amp;format=html"
rel="nofollow">Online version of the man page</a></li>
</ul>

<p>For <b>beginners</b>, "EDITOR=nano crontab -e" will allow you to edit the crontab using the <a href="http://www.nano-editor.org/dist/v1.2/faq.html" 
class="external text" title="http://www.nano-editor.org/dist/v1.2/faq.html" rel="nofollow">nano</a> editor. Ubuntu defaults to using the nano editor.</p>
<p>Usually, the "crontab -e" command will put you into the 'vi' editor. You enter "insert mode" by pressing "i", then type in the line as above, then exit 
insert mode by pressing ESC. You save and exit by typing ":wq", or quit without saving using ":q!" (without the quotes). Here is an 
<a href="http://www.unix-manuals.com/tutorials/vi/vi-in-10-1.html" class="external text" title="http://www.unix-manuals.com/tutorials/vi/vi-in-10-1.html" 
rel="nofollow">intro</a> to the 'vi' editor.</p>


<a name="Path" id="Path"></a><h2>Path</h2>    
<p>Settings allows you to specify particular directory locations for where Xibo will store and look for items.</p>

<p><img class="img-thumbnail" alt="Setting_Path" src="content/admin/sa_setting_path.png"></p>
    

<a name="Permissiions" id="Permissiions"></a><h2>Permissions</h2>
 
<p>Settings allows you to define password policy and login error message.</p>

<h3>User Password Management</h3>
<p>This section will take a look at the User Password Management with three features/improvements:</p>
<ul>
<li>Users can change their passwords without having access to the user administration page</li>
<li>Administrators can set a regular expression to test password complexity</li>
<li>Administrators can override users passwords in a more�intuitive�way</li>
</ul>

<p><img class="img-thumbnail" alt="Setting_Permissions" src="content/admin/sa_setting_permissions.png"></p>


<h3>User Passwordd Policy</h3>
<p>This feature has the utmost flexibility given to the administrator to test passwords in which ever way they chose. 
This is done with a setting in the &#8220;Permissions&#8221; tab called USER_PASSWORD_POLICY.</p>

<p>An administrator can put any valid regular expression in this box, which will cause all users password change requests (and new users)
to be tested against this expression.</p>
<p>It is also important for the user to know what the policy actually is; the setting USER_PASSWORD_ERROR which will be presented 
to the user when they enter a password that does not validate against the regular expression.</p>

<h3>Changing Password</h3>

<p><img class="img-thumbnail" alt="Setting User Password" src="content/admin/sa_setting_pwd.png"></p>

<p>A user can now change their password by clicking on their user name in the top right corner of the admin interface. 
They will be presented with a form requiring them to enter their existing password, a new password and a retyped new password &#8211; 
all fields are required.</p>

<h3>Override User Password</h3>

<p><img class="img-thumbnail" alt="User Password Override" src="content/admin/user_pwd_override.png"></p>

<p>An administrator can now easily override / reset a users existing password by editing the the from the user admin screen and 
ticking the box to &#8220;override&#8221;.</p>
 
