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
?><!DOCTYPE html>
<html lang="en">
	<head>
		<title><?php echo Theme::GetConfig('theme_title'); ?></title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="shortcut icon" href="<?php echo Theme::ImageUrl('favicon.ico'); ?>" />

		<link href="theme/default/libraries/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
		<link href="theme/default/libraries/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
		<link href="theme/default/libraries/bootstrap/css/bootstrap-datetimepicker.min.css" rel="stylesheet">
		<link href="theme/default/libraries/jquery/jquery.tablesorter.pager.css" rel="stylesheet">
		<link href="theme/default/libraries/jquery/jquery-ui/css/ui-lightness/jquery-ui-1.10.2.custom.min.css" rel="stylesheet">
		<link href="theme/default/libraries/jquery-file-upload/css/jquery.fileupload-ui.css" rel="stylesheet">
		<link href="theme/default/css/xibo.css" rel="stylesheet" media="screen">
		<link href="theme/default/css/timeline.css" rel="stylesheet" media="screen">
		<link href="theme/default/css/calendar.css" rel="stylesheet" media="screen">
		<link href="theme/default/css/override.css" rel="stylesheet" media="screen">
	</head>
	<body>
		<!-- Copyright 2006-2013 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. -->
		<div class="container">
			<div class="row">
				<div class="span3">
					<img class="xibo-logo" src='<?php echo Theme::ImageUrl('xibologo.png'); ?>'>
				</div>
				<div class="span2 pull-right">
					
				</div>
			</div>
			<div class="navbar">
				<div class="navbar-inner">
					<ul class="nav">
						<li><a href="<?php echo Theme::GetUserHomeLink(); ?>"><?php echo Theme::Translate('Dashboard'); ?></a></li>
						<?php
							foreach (Theme::GetMenu('Top Nav') as $item) {
								echo $item['li'];
							}
						?>
					</ul>
					<ul class="nav pull-right">
						<li><a id="XiboClock" class="XiboFormButton" href="index.php?p=clock&q=ShowTimeInfo" title="<?php echo Theme::Translate('Click to show more time information'); ?>"><?php echo Theme::GetClock(); ?></a></li>
						<li class="dropdown">
							<a href="#" id="prefs" role="button" class="dropdown-toggle" data-toggle="dropdown">
								<?php echo Theme::Translate("Preferences"); ?>
								<b class="caret"></b>
							</a>
							<ul class="dropdown-menu" role="menu" aria-labelledby="prefs">
								<li><a class="XiboFormButton" href="index.php?p=user&q=ChangePasswordForm" title="<?php echo Theme::Translate('Change Password') ?>"><?php echo Theme::Translate('Change Password') ?></a></li>
								<li><a class="XiboFormButton" href="index.php?p=index&q=About" title="<?php echo Theme::Translate('About the CMS'); ?>"><?php echo Theme::Translate('About'); ?></a></li>
								<li><a title="Show Help" class="XiboHelpButton" href="<?php echo Theme::GetPageHelpLink(); ?>"><?php echo Theme::Translate('Help'); ?></a></li>
								<li><a title="Logout" href="index.php?q=logout"><?php echo Theme::Translate("Logout"); ?></a></li>
							</ul>
						</li>
					</ul>
				</div>
			</div>

<?php //The remaining content follows here in the page that included this template file. The footer.php file then closes off any block elements that remain open ?>