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
 */ 
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?><!DOCTYPE html>
<html lang="en">
	<head>
		<title><?php echo Theme::GetConfig('theme_title'); ?></title>
		<meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="shortcut icon" href="<?php echo Theme::ImageUrl('favicon.ico'); ?>" />
        
		<link href="theme/default/libraries/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
        <link href="theme/default/libraries/bootstrap/css/bootstrap-theme.min.css" rel="stylesheet" media="screen">
		<link rel="stylesheet" type="text/css" href="theme/default/css/install.css" />
        
	</head>

	<body>
		<!-- Copyright 2006-2014 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. -->

		<div class="navbar navbar-inverse navbar-fixed-top">
			<div class="container">
				<div class="navbar-header">
					<a class="navbar-brand" href="#"><?php echo sprintf(Theme::Translate('%s Installation'), Theme::GetConfig('app_name')); ?></a>
				</div>
			</div>
		</div>

		<?php if (Theme::Get('step') == 1) { ?>
    	<div class="jumbotron">
      		<div class="container">
        		<h1><?php echo sprintf(Theme::Translate('Welcome to the %s Installation!'), Theme::GetConfig('app_name')); ?></h1>
        		<p><?php echo sprintf(Theme::Translate('Thank you for choosing %s. This installation wizard will take you through setting up %s one step at a time. There are 6 steps in total, the first one is below.'), Theme::GetConfig('app_name'), Theme::GetConfig('app_name')); ?></p>
        		<p><a class="btn btn-primary btn-lg" role="button" href="<?php echo Theme::GetConfig('cms_install_url'); ?>" target="_blank"><?php echo sprintf(Theme::Translate('Installation guide %s'), '&raquo;'); ?></a></p>
      		</div>
    	</div>
    	<?php } ?>

		<div class="container main-container">
			<?php echo Theme::Get('stepContent'); ?>
