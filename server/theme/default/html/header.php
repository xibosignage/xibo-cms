<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
<!-- Copyright 2006-2013 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. -->
<!DOCTYPE html PUBLIC "-//W3C/DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>Xibo: Digital Signage</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="shortcut icon" href="img/favicon.ico" />

		<!-- Javascript Libraries -->
		<script type="text/javascript" src="3rdparty/jQuery/jquery.min.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery-ui.min.js"></script>
        <link rel="stylesheet" type="text/css" href="3rdparty/jQuery/css/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="3rdparty/jQuery/css/jquery.tablesorter.pager.css" />

		<script type="text/javascript" src="3rdparty/jQuery/jquery.form.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.validate.min.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.tablesorter.pack.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.tablesorter.pager.js"></script>
        <script type="text/javascript" src="3rdparty/jQuery/jquery.metadata.js"></script>
        <script type="text/javascript" src="3rdparty/jQuery/jquery.meiomask.js"  charset="utf-8"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.ifixpng.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.contextmenu.r2.packed.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.corner.js"></script>
		
		<!-- Our own -->
		<link rel="stylesheet" type="text/css" href="theme/default/css/presentation.css" />
        <!--[if gte IE 8]>
        <link rel="stylesheet" type="text/css" href="theme/default/css/ie8.css" />
        <![endif]-->

		<script type="text/javascript" src="theme/default/js/functions.js"></script>
		<script type="text/javascript" src="theme/default/js/ping.js"></script>
		<script type="text/javascript" src="theme/default/js/core.js"></script>
        <script type="text/javascript" src="theme/default/js/<?php echo Theme::GetPageName(); ?>.js"></script>
	</head>
	<body>
    
	<div id="container">
		<div id="headercontainer">
	  		<div id="header"></div>
			<div id="headerback">
				<ul>
					<li><a class="XiboFormButton" href="index.php?p=user&q=ChangePasswordForm" title="<?php echo Theme::Translate('Change Password') ?>"><?php echo Theme::GetUsername(); ?></a></li>
					<li><a id="XiboClock" class="XiboFormButton" href="index.php?p=clock&q=ShowTimeInfo" title="<?php echo Theme::Translate('Click to show more time information'); ?>"><?php echo Theme::GetClock(); ?></a></li>
					<li><a class="XiboFormButton" href="index.php?p=index&q=About" title="<?php echo Theme::Translate('About Xibo'); ?>"><?php echo Theme::Translate('About'); ?></a></li>
					<li><a title="Show Help" class="XiboHelpButton" href="<?php echo Theme::GetPageHelpLink(); ?>"><?php echo Theme::Translate('Help'); ?></a></li>
					<li><a title="Logout" href="index.php?q=logout"><?php echo Theme::Translate("Logout"); ?></a></li>
				</ul>
			</div>
		</div>
		<div id="navigation">
			<ul id="nav">
				<li><a href="<?php echo Theme::GetUserHomeLink(); ?>"><?php echo Theme::Translate('Dashboard'); ?></a></li>
				<?php
					foreach (Theme::GetMenu('Top Nav') as $item) {
						echo $item['li'];
					}
				?>
			</ul>
		</div>
		<div id="contentwrap">
			<div id="content">
			<?php //The remaining content follows here in the page that included this template file. The footer.php file then closes off any block elements that remain open ?>