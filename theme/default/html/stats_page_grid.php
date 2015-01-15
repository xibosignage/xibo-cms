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
 *  table_rows = Array containing the table rows
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
    <div class="col-md-12">
        <p class="sub-heading"><?php echo Theme::Translate('Layouts Shown'); ?></p>
        <?php echo Theme::Get('table_layouts_shown'); ?>

        <p class="sub-heading"><?php echo Theme::Translate('Library Media Shown'); ?></p>
        <?php echo Theme::Get('table_media_shown'); ?>

        <p class="sub-heading"><?php echo Theme::Translate('Media on Layouts Shown'); ?></p>
        <?php echo Theme::Get('table_media_on_layouts_shown'); ?>
    </div>
</div>
