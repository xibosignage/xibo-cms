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
 *
 * Theme variables:
 *  buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <div class="row">
		<div class="control-group">
			<label class="control-label" for="windowsCommand" accesskey="n" title="<?php echo Theme::Translate('Enter a Windows Command Line compatible command'); ?>"><?php echo Theme::Translate('Windows Command'); ?></label>
		    <div class="controls">
		        <input class="" name="windowsCommand" type="text" id="windowsCommand" tabindex="1" />
		    </div>
		</div>
		<div class="control-group">
			<label class="control-label" for="linuxCommand" accesskey="n" title="<?php echo Theme::Translate('Enter a Linux Shell compatible command'); ?>"><?php echo Theme::Translate('Linux Command'); ?></label>
		    <div class="controls">
		        <input class="" name="linuxCommand" type="text" id="linuxCommand" tabindex="1" />
		    </div>
		</div>
    </div>
</form>
