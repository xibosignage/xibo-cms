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
<h1 id="File_Associations">File Associations</h1>
<p>Occasionally it may be necessary to associate a file directly with a display group or display so that the file is transferred to the display for use locally. It may also be desirable to do this without having the media file assigned to a layout.</p>

<p>The CMS fully caters for this requirement using the File Associations functionality. This functionality enables a simple "Assign Files" menu on the Display and Display Group Administration pages.</p>

<p><img class="img-thumbnail" alt="Display Administration" src="content/admin/file_associations_menu.png"></p>

<p>Selecting the Assign Files menu item will open a form showing all stored menu items (video, jpg, etc) which can be selected for assignment.</p>
<p><img class="img-thumbnail" alt="Display Administration" src="content/admin/file_associations_form.png"></p>

<p class="alert alert-info">Associating a file in this manner will automatically download that file to the client at the next collection interval.</p>