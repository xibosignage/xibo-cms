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
<h1>Image</h1>
<p>GIF/JPG and PNG images are supported. There are some display client specific caveats listed below.</p>

<p class="alert alert-warning">Images are sampled/resized before they are loaded on screen. It is preferable to upload an image as small as possible to reduce the time it takes to load an image (if a noticable gap is present) and to reduce the resources that are consumed while that image is in screen.</p>

<h3>Windows Display Client</h3>
<p>Images used as background images must be JPG</p>

<h3>Android Display Client</h3>
<p>Animated GIFs are not supported</p>
