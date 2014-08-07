<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
<h1 id="displayProfiles">Display Setting Profiles <small>Configure your display from the CMS</small></h1>

<p>Display Clients are configured automatically from the CMS once they are connected. This is managed using Display Profiles.</p>

<p>A default display profile for each client type is provided and can be customised to the administrators preferences. It is also possible to create a new display profile and assign it directly to a display.</p>


<h2 id="grid">Viewing the available profiles</h2>
<p>Profiles can be viewed from the "Display Settings" sub-menu on the "Display" menu. A list of profiles (shown below) will be shown when the page loads. Each profile has an action button to bring up the Edit or Delete forms.</p>

<p><img class="img-thumbnail" alt="Display Profile Administration" src="content/admin/display_profile_grid.png"></p>

<p>Each profile has a name, a type and a flag indicating if it is the default or not. Default profiles are automatically assigned to displays of the corresponding type.</p>

<h2 id="edit">Editing a profile</h2>
<p>Once the Edit action is selected for a profile the below Edit Form is displayed. This form allows all the available settings to be adjusted.</p>
<p><img class="img-thumbnail" alt="Display Profile Edit" src="content/admin/display_profile_edit.png"></p>
<p>Each setting is explained on the form under each form field.</p>

<h2 id="delete">Deleting a profile</h2>
<p>Display profiles can be deleted, but please ensure there is one default remaining.</p>

<h2 id="set">Setting on the display</h2>
<p>The default profile will automatically apply to all displays of the same type. If a display should be overridden with a profile then one can be selected on the Display Edit Form on the Display Management page.</p>
<p><img class="img-thumbnail" alt="Set Display Profile" src="content/admin/display_profile_set_on_display.png"></p>
