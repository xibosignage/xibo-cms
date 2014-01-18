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
<h1>Transitions</h1>
<p><?php echo PRODUCT_NAME; ?> supports basic transitions between Region Timeline items. These are supported in the Ubuntu and Android Display clients only.</p>

<p>Transitions are administered from the Transition Administration page - this area is used to determine which Transitions are available for assignment to Media Items.</p>
<p><img class="img-thumbnail" alt="Transition" src="content/layout/transition_admin.png"></p>

<dl class="dl-horizontal">
	<dt>Fade In</dt>
	<dd>Increase Opacity from 0 to 100.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Fade Out</dt>
	<dd>Decrease Opacity from 100 to 0.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Fly</dt>
	<dd>Fly in or out on a compass point.</dd>
</dl>

<h2>Timeline Transitions</h2>
<p>Transitions between two media items on a Timeline are called "Timeline Transitions". They are used to transition between two media items and are set as "In" and "Out" transitions.</p>

<p><img class="img-thumbnail" alt="Transition" src="content/layout/region_timeline_transition_menu.png"></p>

<p>The Transition form adapts depending on the transition selected and the options available for that transition. In most cases it is necessary to select a duration for the transition in Milliseconds and in the case of Fly, a direction must also be selected.</p>

<p><img class="img-thumbnail" alt="Transition" src="content/layout/region_timeline_transition_form.png"></p>

<h2>Region Exit Transition</h2>

<p>Region Exit Transitions happen when the last Media Item to be displayed in a Region is shown. This occurs only once all media items have expired in the other regions. This transition is set on the Region itself.</p>

<p><img class="img-thumbnail" alt="Layout Region Options" src="content/layout/Ss_layout_region_options.png"></p>