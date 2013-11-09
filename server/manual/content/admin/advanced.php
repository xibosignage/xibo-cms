<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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

		<h1>Advanced</h1>
		<p>The Advanced page is mainly used by system administrator to help troubleshooting system problem or reporting fault 
		to the <?php echo PRODUCT_NAME; ?> xstreamedia team for assistance</p>
    	<p>Click Advanced from the Navigation Bar. System loads the Log page by default.</p>

 	<blockquote>
  		<a name="Error_Log_Help" id="Error_Log_Help"></a><h3>System Log</h3>
		 
    	<p><img alt="SA Advanced" src="content/admin/sa_advanced.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="803" height="161"></p>

    	<p>The system log is used to help troubleshoot problems with <?php echo PRODUCT_NAME; ?>. When you encounter an error it will be logged
    	and listed in the system here. These error messages can help the xstreamedia team solve your problem.</p>
   	 	<p>Truncating the log helps you to troubleshoot a problem by clearing the current error messages</p>
    	<p>The page and sub page items helps locate where the error has been generated from.</p>
    	<ul>
    		<li><strong>Log Date</strong><p> states the date and time the error message was logged.</p></li>
    		<li><strong>Page</strong><p> states the page that the error has been generated from.</p></li>
    		<li><strong>Function</strong><p>state function that the error message has been generated from.</p></li>
    		<li><strong>Message</strong><p> gives details of what error has occurred in the system.</p></li>
    	</ul>
   		<a name="Session" id="Session"></a><h3>Sessions</h3>
   		<p>Sessions provide details of the current user activity on the network</p>
    	<p><img alt="SA Advanced Session" src="content/admin/sa_advanced_sessions.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="805" height="208"></p>

   		<a name="Report_Fault" id="Report_Fault"></a><h3>Report Fault</h3>
   		<p>Simple instruaction on the collection of system error and report fault to <?php echo PRODUCT_NAME; ?> xsteamedia team.</p>

   		<a name="License" id="License"></a><h3>License Information</h3>
   		<p>The license page provides details of all the relevant licenses for the system.</p>
 </blockquote>
