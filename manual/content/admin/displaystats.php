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
<h1 id="Display_Statistic">Display Statistics <small>Proof of Play</small></h1>

<p>Display clients collect statistics for layouts and media shown and their play durations. These statistics can be viewed in the Displays section using the Statistics sub menu.</p>

<p>A specific display can be viewed across a date range.</p>

<p><img class="img-thumbnail" alt="Display Statistics" src="content/admin/sa_display_statistic.png"></p>

<dl class="dl-horizontal">
	<dt>Layouts Shown</dt>
	<dd>All layouts shown in the range selected.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Library Media Shown</dt>
	<dd>All media shown in the range selected.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Media on Layouts Shown</dt>
	<dd>All Layouts shown with a breakdown of the associated media on those Layouts.</dd>
</dl>

<p class="alert alert-warning">Statistics reporting may need to be enabled on the display client application.</p>
