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
<h1><?php echo PRODUCT_NAME; ?> Documentation</h1>
<p>Welcome to the user manual for <?php echo PRODUCT_NAME; ?> - this documentation applies to <?php echo PRODUCT_NAME; ?> Version <?php echo PRODUCT_VERSION; ?>. We would like to take this opportunity to thank you for using <?php echo PRODUCT_NAME; ?>.</p>

<blockquote>
	<p>Digital signage is a form of electronic display that shows information, advertising and other messages. Digital signs (such as LCD, LED, plasma displays, or projected images) can be found in public and private environments, such as retail stores and corporate buildings</p>
	<small>Various authors from <cite title"Wikipedia">Wikipedia</cite></small>
</blockquote>

<p><?php echo PRODUCT_NAME; ?> is a digital signage solution and is a suite of applications, including a:
	<ul>
		<li>Content Management System (CMS)</li>
		<li>Windows Display Client</li>
		<li>Ubuntu Display Client</li>
		<li>Ubuntu Offline Download Client</li>
		<li>Android Display Client (Commercial Software*)</li>
	</ul>
</p>

<p>With <?php echo PRODUCT_NAME; ?> the content is designed from anywhere using a web browser on the internet accessible CMS, scheduled to your Display clients and then downloaded automatically when appropriate. This manual will guide you through the application from installation to troubleshooting, from the CMS to the display clients.</p>

<small>* Commerical software provided by the project sponsors.</small>

<h2>Getting Help</h2>
<p>We understand that using complicated software like <?php echo PRODUCT_NAME; ?> is not always straight forward and that this manual may not always be sufficient. Therefore we have a suite of other resources and a <a href="<?php echo PRODUCT_SUPPORT_URL; ?>" title="<?php echo PRODUCT_NAME; ?> Support" target="_blank">question &amp; answer forum</a> to help!</p> 

<p>The official <?php echo PRODUCT_NAME; ?> FAQ is here: <a href="<?php echo PRODUCT_FAQ_URL; ?>" target="_blank"><?php echo PRODUCT_FAQ_URL; ?></a></p>

<p>If you would like any further help with the information contained in this document, or the software package
in general, please visit: <a href="<?php echo PRODUCT_SUPPORT_URL; ?>" title="<?php echo PRODUCT_NAME; ?> Support" target="_blank"><?php echo PRODUCT_SUPPORT_URL; ?></a>.


<h2><?php echo PRODUCT_NAME; ?> Client Features</h2>

<p>The <?php echo PRODUCT_NAME; ?> client comes in three flavours - the .NET Windows Client, the Python Ubuntu Client and an Android Client. The windows client was born first and is therefore the client of choice for a stable installation. The Python client has greater potential in the future and will eventually become the only client for Windows and Linux.</p>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Feature</th>
      <th>.Net Client</th>
      <th>Python</th>
      <th>Android</th>
    </tr>
  </thead>

  <tbody>
  <tr>
    <td>Schedule Layouts</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Priority Schedules</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Video</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Flash</td>
    <td class="y">Yes</td>
    <td class="partial-support">Some Support</td>
    <td class="n">No</td>
  </tr>
  <tr>
   <td>Images</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>PowerPoint</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Text</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>RSS</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Web Page</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Embedded HTML</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
  <td>Microblog</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>DataSets</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Background Image</td>
    <td class="y">Yes (jpg only)</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Media Stats</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Layout Stats</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Report Inventory</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>File Resume</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Counter Media</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Socket Listener</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Lift/Serial Interface Support</td>
    <td class="n">No</td>
    <td class="y">Yes (16 inputs / 4 per serial port)</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Client Runtime Information Screen</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
    <td class="y">Yes</td>
  </tr>
  <tr>
    <td>Offline Update via USB Drive</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Full Compositing (overlapping regions)</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Webpage Transparency</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Video Transparency</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  <tr>
    <td>Image Transparency</td>
    <td class="n">No</td>
    <td class="y">Yes</td>
    <td class="n">No</td>
  </tr>
  </tbody>
</table>

<h2>Closing thoughts <small>Think about your target audience...</small></h2>

<p>Digital signs are there to service a need for information. People will only look at a digital sign if there is some information being shown that they need, or are interested in. It's important then to ensure a good mix of information and targeted advertising (if desired) to meet the business's goals. For example, combine a list of upcoming events with a list of sports results, a news feed or bus times to draw attention.</p>

<p><?php echo PRODUCT_NAME; ?> can schedule different items to be shown at different times of the day. Be sure to target content to the times when your target audience are in the building (eg advertise events for young children when parents are arriving to drop of or collect children for an existing event).</p>

<p>People need to be able to read what is on the sign. Be sure to take a look at your completed work and make sure it's possible to read it in the time that it appears on the screen, and that the font size is large enough etc.</p>
