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
        <link href="theme/default/libraries/bootstrap/css/bootstrap-datetimepicker.min.css" rel="stylesheet">
        <link href="theme/default/libraries/bootstrap-select/css/bootstrap-select.css" rel="stylesheet">
        <link href="theme/default/libraries/calendar/css/calendar.css" rel="stylesheet">
        <link href="theme/default/libraries/jquery/jquery.tablesorter.pager.css" rel="stylesheet">
        <link href="theme/default/libraries/jquery/jquery-ui/css/ui-lightness/jquery-ui-1.10.2.custom.min.css" rel="stylesheet">
        <link href="theme/default/libraries/jquery-file-upload/css/jquery.fileupload-ui.css" rel="stylesheet">
        <link href="theme/default/css/dashboard.css" rel="stylesheet" media="screen">
        <link href="theme/default/css/xibo.css" rel="stylesheet" media="screen">
        <link href="theme/default/css/timeline.css" rel="stylesheet" media="screen">
        <link href="theme/default/css/calendar.css" rel="stylesheet" media="screen">
        <link href="theme/default/css/override.css" rel="stylesheet" media="screen">
    </head>
    <body>
        <!-- Copyright 2006-2013 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. -->
        <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                        <span class="sr-only"><?php echo Theme::Translate('Toggle navigation'); ?></span>
                        <span class="glyphicon glyphicon-bar"></span>
                        <span class="glyphicon glyphicon-bar"></span>
                        <span class="glyphicon glyphicon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="#"><?php echo Theme::ApplicationName(); ?></a>
                </div>
                <div class="navbar-collapse collapse">
                    <ul class="nav navbar-nav navbar-right">
                        <li><a id="XiboClock" class="XiboFormButton" href="index.php?p=clock&q=ShowTimeInfo" title="<?php echo Theme::Translate('Click to show more time information'); ?>"><?php echo Theme::GetClock(); ?></a></li>
                        <li><a class="XiboFormButton" href="index.php?p=user&q=ChangePasswordForm" title="<?php echo Theme::Translate('Change Password') ?>"><?php echo Theme::Translate('Change Password') ?></a></li>
                        <li><a class="XiboFormButton" href="index.php?p=index&q=About" title="<?php echo Theme::Translate('About the CMS'); ?>"><?php echo Theme::Translate('About'); ?></a></li>
                        <li><a title="Show Help" class="XiboHelpButton" href="<?php echo Theme::GetPageHelpLink(); ?>"><?php echo Theme::Translate('Help'); ?></a></li>
                        <li><a href="manual/" target="_blank" title="<?php echo Theme::Translate('Open the Manual in a new Window'); ?>"><?php echo Theme::Translate('Manual'); ?></a></li>
                        <li><a title="Logout" href="index.php?q=logout"><?php echo Theme::Translate("Logout"); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-3 col-md-2 sidebar">
                    <?php if (is_array(Theme::Get('action_menu'))) { ?>
                        <ul class="nav nav-sidebar">
                            <h4><?php echo Theme::Translate('Action Menu'); ?></h4>

                        <?php foreach (Theme::Get('action_menu') as $item) {
                            echo '<li class="' . (($item['selected']) ? ' active' : '') . '"><a title="' . $item['help'] . '" href="' . $item['link'] . '" class="' . $item['class'] . (($item['selected']) ? ' active' : '') . '" onclick="' . $item['onclick'] . '">' . $item['title'] . '</a></li>';
                        }
                        echo '</ul>';
                    }  
                    if (Theme::Get('sidebar_html' != NULL)) {
                        echo Theme::Get('sidebar_html');
                    }
                    ?>
                    <ul class="nav nav-sidebar">
                        <h4><?php echo Theme::Translate('Navigation Menu'); ?></h4>
                        <li><a href="<?php echo Theme::GetUserHomeLink(); ?>"><?php echo Theme::Translate('Dashboard'); ?></a></li>
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
                                    echo $item['li'];
                                else
                                    echo '<li><a>' . $item['title'] . '</a></li>';

                                if (!empty($menu)) {
                                    echo '<ul class="nav nav-sidebar-sub">';
                                    foreach ($menu as $sub_item) {
                                        echo $sub_item['li'];
                                    }
                                    echo '</ul>';
                                }
                            }
                        ?>
                    </ul>
                    <div class="text-center">
                        <img class="xibo-logo" src='<?php echo Theme::ImageUrl('xibologo.png'); ?>'>
                    </div>
                </div>
                <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
