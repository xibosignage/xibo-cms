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
<?php if (Theme::Get('step') == 1) { ?>
<div class="jumbotron">
        <div class="container">
        <h1><?php echo Theme::Translate('Welcome to the %s Upgrade!', Theme::GetConfig('app_name')); ?></h1>
        <p><?php echo sprintf(Theme::Translate('Thank you for upgrading %s. This upgrade wizard will take you through the %s upgrade process one step at a time. There are only 2 steps, the first one is below.'), Theme::GetConfig('app_name'), Theme::GetConfig('app_name')); ?></p>
        <p><?php echo Theme::Translate('Please read through the release notes before you begin as they contain important information about this new release.'); ?></p>
        <p><a class="btn btn-primary btn-lg" role="button" href="<?php echo Theme::GetConfig('cms_release_notes_url'); ?>" target="_blank"><?php echo sprintf(Theme::Translate('Release Notes %s'), '&raquo;'); ?></a></p>
        </div>
</div>
<?php } else { ?>
<h1 class="page-header">Upgrade</h1>
<?php }
echo Theme::Get('page_content'); ?>
