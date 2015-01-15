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
define('XIBO', true);
include_once('template.php');
include_once('content/routes.php');
@include_once('content/routes_custom.php');

// Decide what we need to show
// p=<<page name>>
$raw_page = isset($_GET['p']) ? $_GET['p'] : DEFAULT_PAGE;

if (!in_array($raw_page, $allowed_routes) || !is_file('content/' . $raw_page . '.php'))
	$raw_page = 'error';

$page = 'content/' . $raw_page . '.php';

// toc=<<table of contents>>
$raw_toc = isset($_GET['toc']) ? $_GET['toc'] : DEFAULT_TOC;

if (!in_array($raw_toc, $allowed_toc) || !is_file('content/toc_' . $raw_toc . '.php'))
	$raw_toc = 'error';

$toc = 'content/toc_' . $raw_toc . '.php';

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
		<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
			<div class="container">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#ss-navbar">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a href="#" class="navbar-brand"><?php echo PRODUCT_NAME; ?> Documentation</a>
				</div>
			
				<div class="collapse navbar-collapse" id="ss-navbar">
		    		<ul class="nav navbar-nav">
						<li class="<?php echo ($raw_toc == 'getting_started') ? 'active' : ''; ?>"><a href="index.php?toc=getting_started&p=intro">Getting Started</a></li>
						<li class="<?php echo ($raw_toc == 'app_overview') ? 'active' : ''; ?>"><a href="index.php?toc=app_overview&p=coreconcepts/overview">Overview</a></li>
						<li class="<?php echo ($raw_toc == 'library') ? 'active' : ''; ?>"><a href="index.php?toc=library&p=content/overview">Library</a></li>
						<li class="<?php echo ($raw_toc == 'layouts') ? 'active' : ''; ?>"><a href="index.php?toc=layouts&p=layout/overview">Layouts</a></li>
						<li class="<?php echo ($raw_toc == 'scheduling') ? 'active' : ''; ?>"><a href="index.php?toc=scheduling&p=schedule/overview">Scheduling</a></li>
						<li class="<?php echo ($raw_toc == 'user_and_display') ? 'active' : ''; ?>"><a href="index.php?toc=user_and_display&p=users/overview">Users &amp; Displays</a></li>
						
						<?php if (INCLUDE_DEVELOPER) { ?>
						<li class="<?php echo ($raw_toc == 'developer') ? 'active' : ''; ?>"><a href="index.php?toc=developer&p=admin/overview">Developer</a></li>
						<?php } ?>

						<?php @include('content/nav_extra_custom.php'); ?>
		    		</ul>
		    	</div>
	    	</div>
		</nav>

		<div class="ss_body container">
			<div class="row">
				<div class="col-md-3">
					<div class="ss-sidebar sidebar" role="complementary" data-spy="affix" data-offset-top="200">
						<?php include($toc); ?>
					</div>
				</div>
				<div class="col-md-9" role="main">
					<?php include($page); ?>
				</div>
			</div>
		</div> <!-- container-->

		<footer class="ss_footer" role="contentinfo">
			<div class="container">
				<?php if (! @include('footer_custom.php')) { ?>

				<?php
				if (is_file('img/logo_custom.png')) {
				    $logo = 'img/logo_custom.png';
				}
				else {
				    $logo = 'img/logo.png';
				}
				?>
				<div class="pull-right">
					<img class="img-responsive logo" src="<?php echo $logo; ?>" alt="<?php echo PRODUCT_NAME; ?> Logo" />
				</div>

				<p>Designed and built by <a href="http://springsignage.com/">Spring Signage</a> for the <a href="http://www.xibo.org.uk" target="_blank">Xibo Project</a>.</p>
				<p>Open Source Code licensed under <a href="" target="_blank">GNU Affero General Public License v3 or later</a>, documentation under <a rel="license" href="http://creativecommons.org/licenses/by-sa/2.0/uk/" target="_blank">Creative Commons Licence</a>.</p>

				<p class="ss_footer_links">Currently v<?php echo PRODUCT_VERSION; ?></p>
				<?php } ?>
			</div>
		</footer>

		<!-- JavaScript -->
		<script src="lib/jquery/jquery-1.9.1.js"></script>
    	<script src="lib/bootstrap/js/bootstrap.min.js"></script>
    	<script src="lib/manual.js"></script>
	</body>
</html>
