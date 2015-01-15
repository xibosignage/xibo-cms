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
?>
<div class="jumbotron">
    <div class="container">
        <h1><?php echo Theme::Translate('Welcome to the %s CMS!', Theme::GetConfig('app_name')); ?></h1>
        <p><?php echo Theme::Translate('Digital Signage for Everyone'); ?></p>
        <p><?php echo Theme::Translate('We hope you like %s and have given you some suggestions below to get you started.', Theme::GetConfig('app_name')); ?></p>
        <a class="btn btn-primary btn-lg" role="button" href="<?php echo HelpManager::Link('Dashboard', 'General'); ?>" target="_blank"><?php echo Theme::Translate('Getting Started Guide'); ?></a>
    </div>
</div>
<div class="row">
    <div class="col-sm-offset-1 col-sm-10">
        <div id="new-user-welcome-carousel" class="carousel slide" data-ride="carousel">
            <!-- Indicators -->
            <ol class="carousel-indicators">
                <li data-target="#new-user-welcome-carousel" data-slide-to="0" class="active"></li>
                <li data-target="#new-user-welcome-carousel" data-slide-to="1"></li>
                <li data-target="#new-user-welcome-carousel" data-slide-to="2"></li>
            </ol>

            <!-- Wrapper for slides -->
            <div class="carousel-inner">
                <div class="item active">
                    <img src="theme/default/img/screenshots/display_add_screenshot.png" alt="Slide 1">
                    <div class="carousel-caption">
                        <h3><?php echo Theme::Translate('Display'); ?></h3>
                        <p><?php echo Theme::Translate('Displays are your physical hardware players connected to your TV/Projector. Connect your first display to get started.'); ?></p>
                        <div class="btn-group">
                            <a class="btn btn-primary btn-lg" role="button" href="<?php echo HelpManager::Link('Dashboard', 'General'); ?>"><?php echo Theme::Translate('Manage Displays'); ?></a>
                            <a class="btn btn-default btn-lg" role="button" href="<?php echo HelpManager::rawLink('install_windows_client.html'); ?>" target="_blank"><?php echo Theme::Translate('Windows'); ?></a>
                            <a class="btn btn-default btn-lg" role="button" href="<?php echo HelpManager::rawLink('install_python_client.html'); ?>" target="_blank"><?php echo Theme::Translate('Ubuntu'); ?></a>
                        </div>
                    </div>
                </div>
                <div class="item">
                    <img src="theme/default/img/screenshots/layout_design_screenshot.png" alt="Slide 2">
                    <div class="carousel-caption">
                        <h3><?php echo Theme::Translate('Layout'); ?></h3>
                        <p><?php echo Theme::Translate('Screen design and presentation is managed on a Layout. You can have as many layouts as you want and design them in the CMS.'); ?></p>
                        <div class="btn-group">
                            <a class="btn btn-primary btn-lg" role="button" href="index.php?p=layout"><?php echo Theme::Translate('Design a Layout'); ?></a>
                            <a class="btn btn-default btn-lg" role="button" href="<?php echo HelpManager::Link('Layout', 'General'); ?>" target="_blank"><?php echo Theme::Translate('Read more'); ?></a>
                        </div>
                    </div>
                </div>
                <div class="item">
                    <img src="theme/default/img/screenshots/calendar_screenshot.png" alt="Slide 3">
                    <div class="carousel-caption">
                        <h3><?php echo Theme::Translate('Schedule'); ?></h3>
                        <p><?php echo Theme::Translate('Send something down to your display and watch %s come alive! Create events on Displays / Groups for Layouts / Campaigns, create repeat events and much more.', Theme::GetConfig('app_name')); ?></p>
                        <div class="btn-group">
                            <a class="btn btn-primary btn-lg" role="button" href="index.php?p=schedule"><?php echo Theme::Translate('Schedule Event'); ?></a>
                            <a class="btn btn-default btn-lg" role="button" href="<?php echo HelpManager::Link('Schedule', 'General'); ?>" target="_blank"><?php echo Theme::Translate('Read more'); ?></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <a class="left carousel-control" href="#new-user-welcome-carousel" role="button" data-slide="prev">
                <span class="glyphicon glyphicon-chevron-left"></span>
            </a>
            <a class="right carousel-control" href="#new-user-welcome-carousel" role="button" data-slide="next">
                <span class="glyphicon glyphicon-chevron-right"></span>
            </a>
        </div>
    </div>
</div>