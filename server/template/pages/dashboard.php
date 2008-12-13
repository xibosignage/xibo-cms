<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left">
		</div>
		<div id="form_header_right">
		</div>
	</div>

	<div id="form_body">
			<div id="dashbuttons">
				<?php button('schedule', 'month', 'Schedule a Playlist', "<img class='dash_button' src='img/dashboard/scheduleview.png'><span class='dash_text'>Schedule</span>", '', 'schedule_button', "dashicons") ?>

				<?php button('layout', 'view', 'Layout Manager', "<img class='dash_button' src='img/dashboard/presentations.png'><span class='dash_text'>Layouts</span>", '', 'playlist_button', "dashicons") ?>

				<?php button('content', 'view', 'View Content Library', "<img class='dash_button' src='img/dashboard/content.png'><span class='dash_text'>Library</span>", '', 'content_button', "dashicons") ?>

				<?php /*if (Config::GetSetting($db,"openflashchart_path")!="") {
					button('chart', '', 'View Graphs', "<img class='dash_button' src='img/dashboard/graph.png'><span class='dash_text'>Charts</span>", '', 'chart_button', "dashicons"); } ?>

				<?php button('dataset', '', 'View Dataset', "<img class='dash_button' src='img/dashboard/datasets.png'><span class='dash_text'>Datasets</span>", '', 'dataset_button', "dashicons") */?>

				<?php button('template', 'view', 'View templates', "<img class='dash_button' src='img/dashboard/layouts.png'><span class='dash_text'>Templates</span>", '', 'layout_button', "dashicons") ?>

				<?php button('user', '', 'User', "<img class='dash_button' src='img/dashboard/users.png'><span class='dash_text'>Users</span>", '', 'user_button', "dashicons") ?>

				<?php button('admin', '', 'settings', "<img class='dash_button' src='img/dashboard/settings.png'><span class='dash_text'>Settings</span>", '', 'settings_button', "dashicons") ?>

				<?php button('license', '', 'License', "<img class='dash_button' src='img/dashboard/license.png'><span class='dash_text'>License</span>", '', 'license_button', "dashicons") ?>
				
				<div class="dashicons">
					<a id="help_button" target="_blank" alt="The Manual" href="http://www.xibo.org.uk/manual/index.php?p=content/dashboard/overview">
					<img class="dash_button" src="img/dashboard/help.png"/>
					<span class="dash_text">Manual</span></a>
				</div>
			</div>	
	</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>