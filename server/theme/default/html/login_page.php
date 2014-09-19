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
    <meta charset="utf-8">
    <title><?php echo Theme::GetConfig('theme_title'); ?> - <?php echo Theme::Translate('Please Login'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Style Sheets -->
    <link href="theme/default/libraries/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
    <style type="text/css">
      body {
        padding-top: 40px;
        padding-bottom: 40px;
        background-color: #f5f5f5;
      }

      .form-signin {
        max-width: 300px;
        padding: 19px 29px 29px;
        margin: 0 auto 20px;
        background-color: #fff;
        border: 1px solid #e5e5e5;
        -webkit-border-radius: 5px;
           -moz-border-radius: 5px;
                border-radius: 5px;
        -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.05);
           -moz-box-shadow: 0 1px 2px rgba(0,0,0,.05);
                box-shadow: 0 1px 2px rgba(0,0,0,.05);
      }
      .form-signin .form-signin-heading,
      .form-signin .checkbox {
        margin-bottom: 10px;
      }
      .form-signin input[type="text"],
      .form-signin input[type="password"] {
        font-size: 16px;
        height: auto;
        margin-bottom: 15px;
        padding: 7px 9px;
      }

    </style>
    <link rel="shortcut icon" href="<?php echo Theme::ImageUrl('favicon.ico'); ?>" />
  </head>

	<body>

	    <div class="container">

			<form id="login-form" class="form-signin text-center" action="<?php echo Theme::Get('form_action'); ?>" method="post">
        <?php echo Theme::Get('form_meta'); ?>
        <p><a href="<?php echo Theme::GetConfig('theme_url'); ?>"><img src='<?php echo Theme::ImageUrl('xibologo.png'); ?>'></a></p>

        <p><?php echo Theme::Translate('Please provide your credentials'); ?></p>

        <input id="username" class="form-control" name="username" type="text" class="input-block-level" placeholder="<?php echo Theme::Translate('User'); ?>" autofocus>
        <input id="password" class="form-control" name="password" type="password" class="input-block-level" placeholder="<?php echo Theme::Translate('Password'); ?>">

        <?php if (Theme::Get('login_message') != '') { ?>
        <div class="alert alert-danger"><?php echo Theme::Get('login_message'); ?></div>
        <?php } ?>

        <button class="btn btn-large btn-primary" type="submit"><?php echo Theme::Translate('Login'); ?></button>
			</form>

			<p class="text-center"><?php echo Theme::Translate('Version %s', VERSION); ?> | <a id="source-link" href="<?php echo Theme::Get('source_url'); ?>"><?php echo Theme::Translate('Source'); ?></a> | <a id="about-link" class="XiboFormButton" href="<?php echo Theme::Get('about_url'); ?>" title="<?php echo Theme::Translate('About'); ?>"><?php echo Theme::Translate('About'); ?></a></p>
	    </div> <!-- /container -->


    <script src="theme/default/libraries/jquery/jquery-1.9.1.js"></script>
    <script src="theme/default/libraries/jquery/jquery.validate.min.js"></script>
    <script src="theme/default/libraries/jquery/additional-methods.min.js"></script>
    <script src="theme/default/libraries/bootstrap/js/bootstrap.min.js"></script>
    <script src="theme/default/libraries/bootstrap/js/bootbox.min.js"></script>
    <script src="theme/default/js/xibo-cms.js"></script>
	</body>
</html>