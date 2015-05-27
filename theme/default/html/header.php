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
        <link href="theme/default/libraries/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link href="theme/default/libraries/bootstrap-select/css/bootstrap-select.css" rel="stylesheet">
        <link href="theme/default/libraries/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css" rel="stylesheet">
        <link href="theme/default/libraries/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css" rel="stylesheet">
        <link href="theme/default/libraries/bootstrap-ekko-lightbox/ekko-lightbox.min.css" rel="stylesheet">
        <link href="theme/default/libraries/calendar/css/calendar.css" rel="stylesheet">
        <link href="theme/default/libraries/morrisjs/morris.css" rel="stylesheet">
        <link href="theme/default/libraries/jquery-tablesorter/css/theme.bootstrap.css" rel="stylesheet">
        <link href="theme/default/libraries/jquery/jquery-ui/css/ui-lightness/jquery-ui-1.10.2.custom.min.css" rel="stylesheet">
        <link href="theme/default/libraries/jquery-file-upload/css/jquery.fileupload-ui.css" rel="stylesheet">
        <link href="modules/preview/fonts.css" rel="stylesheet">
        <link href="<?php echo Theme::ItemPath('css/dashboard.css'); ?>" rel="stylesheet" media="screen">
        <link href="<?php echo Theme::ItemPath('css/timeline.css'); ?>" rel="stylesheet" media="screen">
        <link href="<?php echo Theme::ItemPath('css/calendar.css'); ?>" rel="stylesheet" media="screen">
        <link href="<?php echo Theme::ItemPath('css/xibo.css'); ?>" rel="stylesheet" media="screen">
        <link href="<?php echo Theme::ItemPath('css/override.css'); ?>" rel="stylesheet" media="screen">
        <!-- Copyright 2006-2013 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. -->
    </head>
    <body>
    	
       	 <div id="page-wrapper" class="active">
          <div class="collapse navbar-collapse" id="navbar-collapse-1">
            <div id="sidebar-wrapper">
                <?php
                if (Theme::Get('sidebar_html') != NULL) {
                    echo Theme::Get('sidebar_html');
                }
                ?>
                
			    
                <ul class="sidebar">
                    <li class="sidebar-main"><a href="<?php echo Theme::GetUserHomeLink(); ?>"><?php echo Theme::Translate('Dashboard'); ?></a></li>
                    <?php
                        foreach (Theme::GetMenu('Top Nav') as $item) {
                            
                            // Sub menu?
                            $menu = NULL;
                            switch ($item['page']) {
                                case 'layout':
                                    $menu = Theme::GetMenu('Design Menu');
                                    break;

                                case 'content':
                                    $menu = Theme::GetMenu('Library Menu');
                                    break;

                                case 'display':
                                    $menu = Theme::GetMenu('Display Menu');
                                    break;

                                case 'user':
                                    $menu = Theme::GetMenu('Administration Menu');
                                    break;
                                    
                                case 'log':
                                    $menu = Theme::GetMenu('Advanced Menu');
                                    break;
                            }

                            if (empty($menu))
                                echo '<li class="sidebar-list ' . $item['class'] . (($item['selected']) ? ' active' : '') . '"><a href="' . $item['link'] . '" class="' . $item['class'] . (($item['selected']) ? ' active' : '') . '">' . $item['title'] . '</a></li>';
                            else
                                echo '<li class="sidebar-title"><a>' . $item['title'] . '</a></li>';

                            if (!empty($menu)) {
                                foreach ($menu as $sub_item) {
                                    echo '<li class="sidebar-list ' . $sub_item['class'] . (($sub_item['selected']) ? ' active' : '') . '"><a href="' . $sub_item['link'] . '" class="' . $sub_item['class'] . (($sub_item['selected']) ? ' active' : '') . '">' . $sub_item['title'] . '</a></li>';
                                }
                            }
                        }
                    ?>
                </ul>
	              
                <div class="sidebar-footer">
                    <div class="col-sm-6">
                        <a class="XiboFormButton" href="index.php?p=index&q=About" title="<?php echo Theme::Translate('About the CMS'); ?>"><?php echo Theme::Translate('About'); ?></a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?php echo Config::GetSetting('HELP_BASE'); ?>" target="_blank" title="<?php echo Theme::Translate('Open the Manual in a new Window'); ?>"><?php echo Theme::Translate('Manual'); ?></a>
                    </div>
                </div>
             </div>
            </div>  
            <div id="content-wrapper">
                <div class="page-content">
                    <div class="row header">
                        <div class="col-sm-12">
                            <div class="meta pull-left">
                            	
                                <div class="page"><img class="xibo-logo" src='<?php echo Theme::ImageUrl('xibologo.png'); ?>'></div>
                            </div>
                            <div class="user pull-right">
                            	<button type="button"  class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1">
							        <span class="sr-only">Toggle navigation</span>
							        <span class="icon-bar"></span>
							        <span class="icon-bar"></span>
							        <span class="icon-bar"></span>
							    </button>
                                <div class="item dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                        <img src="<?php echo Theme::ImageUrl('avatar.jpg'); ?>" />
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-right">
                                        <li class="dropdown-header"><?php echo Theme::GetUsername(); ?><br/>
                                            <a id="XiboClock" class="XiboFormButton" href="index.php?p=clock&q=ShowTimeInfo" title="<?php echo Theme::Translate('Click to show more time information'); ?>"><?php echo Theme::GetClock(); ?></a>
                                        </li>
                                        <li class="divider"></li>
                                        <li><a class="XiboFormButton" href="index.php?p=user&q=ChangePasswordForm" title="<?php echo Theme::Translate('Change Password') ?>"><?php echo Theme::Translate('Change Password') ?></a></li>
                                        <li><a href="index.php?p=index&sp=welcome"><?php echo Theme::Translate('Reshow welcome'); ?></a></li>
                                        <li><a class="XiboHelpButton" href="<?php echo Theme::GetPageHelpLink(); ?>"><?php echo Theme::Translate('Help'); ?></a></li>
                                        <li class="divider"></li>
                                        <li><a title="Logout" href="index.php?q=logout"><?php echo Theme::Translate("Logout"); ?></a></li>
                                    </ul>
                                </div>
                            </div>
                            <?php if (count(Theme::Get('notifications')) > 0) { ?>
                            <div class="user pull-right">
                                <div class="item dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                        <i class="fa fa-exclamation-circle"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-right">
                                        <li class="dropdown-header"><?php echo Theme::Translate('Notifications'); ?><br/>

                                        </li>
                                        <li class="divider"></li>
                                        <?php foreach(Theme::Get('notifications') as $notification) { ?>
                                        <li><div><?php echo $notification; ?></div></li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                        <?php if (is_array(Theme::Get('action_menu'))) { ?>
                            <ul class="nav nav-pills pull-right">

                            <?php foreach (Theme::Get('action_menu') as $item) {
                                echo '<li class="' . (($item['selected']) ? ' active' : '') . '"><a title="' . $item['help'] . '" href="' . $item['link'] . '" class="' . $item['class'] . (($item['selected']) ? ' active' : '') . '" onclick="' . $item['onclick'] . '">' . $item['title'] . '</a></li>';
                            }
                            echo '</ul>';
                        } ?>
