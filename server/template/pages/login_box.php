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
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Xibo Admin - Please Login</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" type="text/css" href="template/css/login_box.css" />
	<link rel="stylesheet" type="text/css" href="3rdparty/jQuery/ui-elements.css" />
	
	<link rel="shortcut icon" href="img/favicon.ico" />
	<script type="text/javascript" src="3rdparty/jQuery/jquery.min.js"></script>
	<script type="text/javascript" src="3rdparty/jQuery/jquery-ui.packed.js"></script>
	<script type="text/javascript" src="3rdparty/jQuery/jquery.form.js"></script>
	<script type="text/javascript" src="3rdparty/jQuery/jquery.validate.min.js"></script>
	<script type="text/javascript" src="3rdparty/jQuery/jquery.bgiframe.min.js"></script>
	
	<script type="text/javascript" src="lib/js/functions.js"></script>
	<script type="text/javascript" src="lib/js/core.js"></script>
	
	<script type="text/javascript">
		$(document).ready(function(){
		
			$('input[type=text]').eq(0).focus();
			
			$('#forgotten').click(function() {
				$('#forgotten_details').toggle();
			});
		
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
				<h1>Login</h1>
				<p>You must log in to access the Admin Interface!</p>
				<form method="post" action="index.php?q=login&referingPage=<?php echo $referingPage; ?>">
					<input type="hidden" name="token" value="<?php echo CreateFormToken() ?>" />
					<div class="login_table">
						<p><label for="username">User Name </label><input class="username" type="text" id="username" name="username" tabindex="1" size="12" /></p>
						
						<p><label for="password">Password </label><input class="password" id="password" type="password" name="password" tabindex="2" size="12" /></p>
						
						<div class="loginbuton"><button type="submit" tabindex="3">Log in</button></div>
						<!--<a href="#" id="forgotten">Forgotten Password?</a>-->
					</div>
				</form>
				
				<br />
				<?php displayMessage(); ?>
				<br />
				<p><a href="http://www.xibo.org.uk"><img src='img/login/complogo.png'></a></p>

				<p>Version <?php echo VERSION; ?> | <a href="https://launchpad.net/xibo/1.2">Source</a> | <a class="XiboFormButton" href="index.php?p=index&q=About" title="<?php echo __('About Xibo'); ?>"><?php echo __('About'); ?></a></p>
			</div>
			
			<div class="login_foot">
				<div class="login_foot_left">
				</div>
				<div class="login_foot_right">
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
