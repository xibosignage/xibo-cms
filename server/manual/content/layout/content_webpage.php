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
<h1 id="Webpage">Web Page</h1>
<p>The Web Page module allows an entire Web Page to be embedded inside a Region on a Layout.</p>

<p>There is support for scaling and offsetting the target web page inside the Region so the particular section of the web page can be displayed.</p>

<p><img class="img-thumbnail" alt="Webpage Form" src="content/layout/Ss_layout_designer_add_webpage.png"></p>

<dl class="dl-horizontal">
	<dt>Link</dt>
	<dd>The URL of the Web Page - including http://</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Duration</dt>
	<dd>The duration in seconds that this item should remain in the Region.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Offset Top</dt>
	<dd>The top position for the page to start.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Offset Left</dt>
	<dd>The left position for the page to start.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Scale Percentage</dt>
	<dd>The percentage zoom to apply to the web page.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Transparent?</dt>
	<dd>Should the web page be rendered with a transparent background? <?php echo PRODUCT_NAME; ?> will try its best to do this when checked, however it cannot be supported on some web pages.</dd>
</dl>

<p class="alert alert-warning">Web Pages are not cached by the Display Client and will not operate when disconnected from the network.</p>
