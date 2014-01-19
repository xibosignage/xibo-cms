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
<h1>PowerPoint</h1>
<p>PowerPoint media is only supported on the Windows Display Client.</p>

<p class="alert alert-info">From Office 2010 onwards PowerPoint presentations can be exported as Video files and played on the Ubuntu/Android display clients.</p>

<h3>Preparation of the PPT file</h3>
<p>First prepare the PowerPoint Presentation. PowerPoint will, by default, put scroll bars up the side of your presentation, unless you do the following for each PowerPoint file BEFORE you upload it:</p>
<ol>
	<li>Open your PowerPoint Document</li>
	<li>Slide Show -&gt; Setup Show</li>
	<li>Under "Show Type", choose "Browsed by an individual (window)" and then untick "Show scrollbar"</li>
	<li>Click OK</li>
	<li>Save the Presentation</li>
	<li>Note also that <?php echo PRODUCT_NAME; ?> will not advance the slides in a Presentation, so you should record automatic slide timings by going 
		to "Slide Show -&gt; Rehearse Timings" and then saving the presentation.</li>
</ol>

<p class="alert alert-warning">Remember to make the necessary <a href="index.php?toc=getting_started&p=install/install_client#windows_modifications">Windows Modifications</a> when playing PowerPoint.</p>