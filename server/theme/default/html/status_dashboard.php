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
<h1 class="page-header"><?php echo Theme::Translate('Dashboard'); ?></h1>

<div class="row">
	<div class="col-md-6">
		<h3 class="text-center"><?php echo Theme::Translate('Bandwidth Usage'); ?></h3>
		<div id="flot_bandwidth_chart" style="height: 400px;" class="flot-chart"></div>
	</div>
	<div class="col-md-6">
		<h3 class="text-center"><?php echo Theme::Translate('Library Usage'); ?></h3>
		<div id="flot_library_chart" style="height: 400px;" class="flot-chart"></div>
	</div>
</div>
<div class="row">
	<div class="col-md-6">
		<h3 class="text-center"><?php echo Theme::Translate('Display Activity'); ?></h3>
		<table class="table">
			<thead>
				<tr>
					<th><?php echo Theme::Translate('Display'); ?></th>	
					<th><?php echo Theme::Translate('Logged In'); ?></th>	
					<th><?php echo Theme::Translate('Licence'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach(Theme::Get('display-widget-rows') as $row) { ?>
				<tr class="<?php echo $row['mediainventorystatus']; ?>">
					<td><?php echo $row['display']; ?></td>
					<td><span class="<?php echo ($row['loggedin'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove'; ?>"></span></td>
					<td><span class="<?php echo ($row['licensed'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove'; ?>"></span></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
	<div class="col-md-6">
		<?php echo Theme::Get('embedded-widget'); ?>
	</div>
</div>
<script type="text/javascript">
	<?php echo Theme::Get('bandwidth-widget'); ?>
	<?php echo Theme::Get('library-widget-js'); ?>
</script>