<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014 Daniel Garner
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
<p class="alert alert-info">This documentation applies to <?php echo PRODUCT_NAME; ?> 1.7 and greater.</p>
<h1>Developing Modules</h1>
<p><?php echo PRODUCT_NAME; ?> benefits from a Modular "plug-in" architecture for designing and displaying content. There are "core" supported modules that ship with the product, but these can be extended with new community authored modules at any time.</p>

<p>This section of the manual will cover the development of a new <?php echo PRODUCT_NAME; ?> module using the <code>modules/module_template.php</code> blank module template file.</p>

<h2>Types of Module</h2>
<p><?php echo PRODUCT_NAME; ?> splits modules into two different types. Region specific Modules and Non-region specific Modules. Region specific modules are the only type of module supported by the plug-in architecture at the current time.</p>

<h3>Region Specific Modules</h3>
<p>These are modules that exist on a specific Layout and have their configuration and user options saved to the <abbr title="Xibo Layout Format">XLF</abbr> for the Layout. These modules are served to the client in HTML and rendered locally using an internal browser.</p>

<h3>Non-region Specific Modules</h3>
<p>These are modules that exist in the <code>media</code> table and have files associated with them. They are reusable across Layouts and exist in the Library.</p>

<h2>The Module Template</h2>
<p><?php echo PRODUCT_NAME; ?> includes a module template which is a fully commented example of a basic module. The template can be found in the modules folder of the <?php echo PRODUCT_NAME; ?> installation. <code>modules/module_template.php</code>.</p>

<p>All new <?php echo PRODUCT_NAME; ?> module developers should use this file as a starting point.</p>
