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
<h1 id="Text">Text</h1>
<p>The Text Media Module allows rich text to be added to a Region Timeline on a Layout.</p>

<p>Text is specific to a layout rather than saved in the library - this means that text items are not reusable and will need to be copied / pasted between Layouts if the same text is required on more than one.</p>

<p>When adding a Text Media Item the CMS provides a text editor embedded directly in a form. This editor has the same background colour as the Layout so that it is easier to get the foreground text colouring correct.</p>
<p><img class="img-thumbnail" alt="Add Text Form" src="content/layout/Ss_layout_designer_add_text.png"></p>

<h2>Options</h2>

<dl class="dl-horizontal">
	<dt>Direction</dt>
	<dd>Text can be scrolling left / right / top and bottom. Text can also be static using the "None" direction.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Duration</dt>
	<dd>The duration in seconds that this text item should remain in the Region.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Scroll Speed</dt>
	<dd>Speed up or slow down the scroll rate - assuming you have a Direction selected.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Fix text to region?</dt>
	<dd>Should the text resize to fill the entire available space in the Region? This option should only be used for a single line text item. The default behaviour is to scale the text to fill the Display Client resolution.</dd>
</dl>


<p class="alert alert-info"><?php echo PRODUCT_NAME; ?> uses CKEditor for text input. Complete documentation for all the buttons is available over at <a href="http://docs.cksource.com/CKEditor_3.x/Users_Guide" target="_blank">CKEditor's website</a>.</p>

<p class="alert alert-info">Text formatting is actually HTML and you can "view source" on the text editor to adjust the HTML manually if you choose.</p>
