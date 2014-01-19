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
<h1 id="embedded">Embedded Content</h1>
<p>The Embedded Content module allows HTML and JavaScript to be embedded into a Layout Region. This allows for custom enhancements to be made to <?php echo PRODUCT_NAME; ?> without modifying the core application. Examples of where this might be useful are displaying a Clock or Weather region.</p>

<p><img class="img-thumbnail" alt="Embedded Content Form" src="content/layout/Ss_layout_designer_add_embedded.png"></p>

<dl class="dl-horizontal">
	<dt>Duration</dt>
	<dd>The duration in seconds that this item should remain in the Region.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Transparent?</dt>
	<dd>Should the item be rendered with a transparent background? <?php echo PRODUCT_NAME; ?> will try its best to do this when checked, however it may be overridden by the custom content.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>HTML Content</dt>
	<dd>The HTML that should be loaded into the Region.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>HEAD content</dt>
	<dd>Any content to put in the HEAD of the document - JavaScript should be wrapped in <code>script</code> tags. <?php echo PRODUCT_NAME; ?> will automatically add jQuery.</dd>
</dl>

<p>The <code>EmbedInit()</code> method will be called by the Display Client and can be used to safely start any custom JavaScript at the appropriate time. The method is defaulted on any new Embedded Media Item.</p>

<pre>
	&lt;script type="text/javascript"&gt;
	function EmbedInit()
	{
		// Init will be called when this page is loaded in the client.
		
		return;
	}
	&lt;/script&gt;
</pre>

<p class="alert alert-warning">Show embedded HTML with Active-X content on the Windows Display Client the security settings of IE so that local files were allowed to run active content by default. This can be done in Tools -> Internet Options -> Advanced -> Security -> "Allow Active content to run in files on My Computer"</p>
 
<h3>Digital Clock Example</h3>
<pre>
&lt;script src="http://www.clocklink.com/embed.js">&lt;/script&gt;
&lt;script type="text/javascript" language="JavaScript"&gt;
obj=new Object;
obj.clockfile="5001-blue.swf";
obj.TimeZone="GMT0800";
obj.width=300;
obj.height=25;
obj.Place="";
obj.DateFormat="mm-DD-YYYY";
obj.wmode="transparent";
showClock(obj);
&lt;/script&gt;
</pre>

<h3>Analogue Clock Example</h3>

<pre>
	&lt;embed src="http://www.worldtimeserver.com/clocks/wtsclock001.swf?color=FF9900&wtsid=SG" width="200" height="200" wmode="transparent" type="application/x-shockwave-flash" /&gt;
</pre>
<p class="alert alert-danger">This example uses Flash</p>
