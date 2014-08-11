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
 *
 * Theme variables:
 * 	id = The GridID for rendering AJAX layout table return
 * 	filter_id = The Filter Form ID
 * 	form_meta = Extra form meta that needs to be sent to the CMS to return the list of layouts
 * 	pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<div class="col-md-12">
		<h3><?php echo Theme::Translate('Modules available to Install'); ?></h3>
		<?php foreach(Theme::Get('to_install') as $row) { ?>
		<div class="row">
			<div class="col-md-12">
				<?php echo '<a href="' . Theme::Get('module_install_url') . $row . '">' . $row . '</a>'; ?>
			</div>
		</div>
		<?php } ?>
	</div>
</div>