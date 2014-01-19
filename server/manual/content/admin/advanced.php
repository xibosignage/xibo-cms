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

<h1>Troubleshooting <small>The Advanced Menu</small></h1>
<p>The CMS contains a number of useful tools for first line debugging and reporting faults to technical support.</p>

<h2 id="Report_Fault">Report Fault</h2>
<p>The Report Fault Wizard is designed to be enabled by the CMS administrator to recreate the problem and collect logging information that can be analysed or submitted to the Technical Support team for further analysis. The Wizard lists 6 steps which should be followed in order.</p>

<p><img class="img-thumbnail" alt="Report Fault Wizard" src="content/admin/report_fault_wizard.png"></p>


<h2>System Log</h2>
<p>The CMS keeps a detailed log of all errors that have been recorded as well as detailed debugging information. This is intended for a more technical user to analyse and fix issues.</p>

<p>The System Log is available from the Advanced Menu.</p>

<p><img class="img-thumbnail" alt="System Log" src="content/admin/sa_advanced.png"></p>

<p class="alert alert-warning">The system log can get quite large over time and should be manually truncated after any debugging session. This is done from the System Log page using the "Truncate" menu item in the top right corner of the Log Table.</p>

<h2>Sessions</h2>
<p>Sessions provide details of the current user activity on the network</p>
<p><img class="img-thumbnail" alt="Sessions" src="content/admin/sa_advanced_sessions.png"></p>