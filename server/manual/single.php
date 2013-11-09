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
define('XIBO', true);
include_once('template.php');
?><!DOCTYPE html>
<html lang="en">
	<head>
		<title><?php echo PRODUCT_NAME; ?> Documentation</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

		<!-- favicon -->
		<?php if (is_file('img/favicon_custom.ico')) { ?>
		<link href="img/favicon_custom.ico" rel="shortcut icon"/>
		<?php } else { ?>
		<link href="img/favicon.ico" rel="shortcut icon"/>
		<?php } ?>

		<!-- Bootstrap -->
		<link href="lib/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
		
		<!-- Stylesheets -->
		<link href="css/manual.css" rel="stylesheet" media="screen">
		<link href="css/override.css" rel="stylesheet" media="screen">

		<!-- META -->
		<meta name="keywords" content="digital signage, signage, narrow-casting, xibo, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />
	</head>
	<body>
		<!-- Copyright 2006-2013 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. -->
		<div class="container">
			<div class="row">
				<div class="col-md-9" role="main">

					<?php 
					// Decide what we need to show
					// p=<<page name>>
					$page = isset($_GET['p']) ? $_GET['p'] : 'intro';

					if (is_file('content/' . $page . '.php')) {
						include('content/' . $page . '.php');
					}
					else {
						include('content/error.php');
					}
					?>
					
				</div>
			</div>

			<footer>
				<?php
				if (! @include('footer_custom.php')) {
				?>
				<div style="text-align:center; font-family:Arial, Helvetica, sans-serif; font-size:10pt">
					<br />
					<p><a rel="license" href="http://creativecommons.org/licenses/by-sa/2.0/uk/" target="_blank">
					<img alt="Creative Commons License" style="border-width:0" src="http://i.creativecommons.org/l/by-sa/2.0/uk/88x31.png" /></a><br /><br />
					The <?php echo PRODUCT_NAME; ?> Manual is licensed under a <a rel="license" href="http://creativecommons.org/licenses/by-sa/2.0/uk/" target="_blank">Creative Commons Licence</a></p>
				    <p>Manual by the <a href="http://www.xibo.org.uk" target="_blank">Xibo Project</a></p>
				</div>
				<?php
				}
				?>
			</footer>

		</div> <!-- container-->

		<!-- JavaScript -->
		<script src="lib/jquery/jquery-1.9.1.js"></script>
    	<script src="lib/bootstrap/js/bootstrap.min.js"></script>
    	<script src="lib/manual.js"></script>
	</body>
</html>
