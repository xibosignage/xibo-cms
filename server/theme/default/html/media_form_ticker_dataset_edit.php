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
 *
 * Theme variables:
 *  buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="well">
    <div class="text-center text-info"><?php echo Theme::Translate('Available Substitutions'); ?></div>
    <ul id="TickerDataSetColumns">
        <?php foreach(Theme::Get('columns') as $column) { ?>
        <li class="ckeditor_snippits" linkedto="ta_text" datasetcolumnid="<?php echo $column['DataSetColumnID']; ?>"><?php echo $column['Heading']; ?></li>
        <?php } ?>
    </ul>
</div>
