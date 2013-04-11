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
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo Theme::ApplicationName(); ?> Admin - Please Login</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" type="text/css" href="theme/default/css/login_box.css" />
	<link rel="stylesheet" type="text/css" href="3rdparty/jQuery/ui-elements.css" />
	
	<link rel="shortcut icon" href="<?php echo Theme::ImageUrl('favicon.ico'); ?>" />
	
    <!-- Javascript Libraries -->
    <script type="text/javascript" src="3rdparty/jQuery/jquery.min.js"></script>
    <script type="text/javascript" src="3rdparty/jQuery/jquery-ui.min.js"></script>
    <link rel="stylesheet" type="text/css" href="3rdparty/jQuery/css/jquery-ui.css" />

    <script type="text/javascript" src="3rdparty/jQuery/jquery.form.js"></script>
    <script type="text/javascript" src="3rdparty/jQuery/jquery.validate.min.js"></script>
    <script type="text/javascript" src="3rdparty/jQuery/jquery.tablesorter.pack.js"></script>
    <script type="text/javascript" src="3rdparty/jQuery/jquery.tablesorter.pager.js"></script>
    <script type="text/javascript" src="3rdparty/jQuery/jquery.ifixpng.js"></script>
    <script type="text/javascript" src="3rdparty/jQuery/jquery.contextmenu.r2.packed.js"></script>
    <script type="text/javascript" src="3rdparty/jQuery/jquery.corner.js"></script>
    <script type="text/javascript" src="theme/default/js/functions.js"></script>
	<script type="text/javascript" src="theme/default/js/core.js"></script>
	
	<script type="text/javascript">
		$(document).ready(function() {
			$('input[type=text]').eq(0).focus();
		});
	</script>
</head>

<body>

	<div id="container">

		<div id="content">

			<div class="login_box">
				
				<div class="login_header">
					<div class="login_header_left">
					</div>
					<div class="login_header_right">
					</div>
				</div>
				
				<div class="login_body">
					<h1><?php echo Theme::Translate('Login'); ?></h1>
					<p><?php echo Theme::Translate('Please enter a username and password to access the CMS'); ?></p>
					<form method="post" action="<?php echo Theme::Get('form_action'); ?>">
						<?php echo Theme::Get('form_meta'); ?>
						<div class="login_table">
							<p><label for="username"><?php echo Theme::Translate('User'); ?></label><input class="username" type="text" id="username" name="username" tabindex="1" size="12" /></p>
							
							<p><label for="password"><?php echo Theme::Translate('Password'); ?></label><input class="password" id="password" type="password" name="password" tabindex="2" size="12" /></p>
							
							<div class="loginbuton"><button type="submit" tabindex="3"><?php echo Theme::Translate('Login'); ?></button></div>
						</div>
					</form>

					<div class="login_message"><?php echo Theme::Get('login_message'); ?></div>

					<p><a href="<?php echo Theme::GetConfig('theme_url'); ?>"><img src='<?php echo Theme::ImageUrl('login/complogo.png'); ?>'></a></p>

					<p><?php echo Theme::Translate('Version %s', VERSION); ?> | <a href="<?php echo Theme::Get('source_url'); ?>"><?php echo Theme::Translate('Source'); ?></a> | <a class="XiboFormButton" href="<?php echo Theme::Get('about_url'); ?>" title="<?php echo Theme::Translate('About'); ?>"><?php echo Theme::Translate('About'); ?></a></p>
				</div>
				
				<div class="login_foot">
					<div class="login_foot_left">
					</div>
					<div class="login_foot_right">
					</div>
				</div>
			</div>
			
		</div>
	</div>
	<div id="system_working" style="display:none;"><img src="img/loading.gif"></div>

	<div id="system_message" style="text-align: center;">
		<span>Message</span>
	</div>

	<div id="div_dialog"></div>
</body>
</html>
