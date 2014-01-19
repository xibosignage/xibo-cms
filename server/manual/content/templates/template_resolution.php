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
<h1>Resolutions</h1>
<p>Layout design is always done according to the Target Resolution and Aspect Ratio. <?php echo PRODUCT_NAME; ?> has defined a set of the most common aspect ratios and their associated Resolutions (for the designer and for the target display).</p>

<p>Resolutions can be viewed from the Resolution Administration page under the Design menu, Resolutions sub menu.</p>

<p><img class="img-thumbnail" alt="Resolution Administration" src="content/templates/template_resolution.png"></p>

<p class="alert alert-info"><?php echo PRODUCT_NAME; ?> will natively scale all content based on the Aspect Ratio - it is not necessary to add a new Resolution for all hardware resolutions in your signage network.</p>

<h2>Add / Edit Resolution</h2>
<p>It is possible to add a new Resolution or edit an existing one.</p>
<p><img class="img-thumbnail" alt="Resolution Form" src="content/templates/template_edit_resolution.png"></p>

<p class="alert alert-warning">The Designer Width / Height will always be automatically calculated to fit within the Layout Designer.</p>