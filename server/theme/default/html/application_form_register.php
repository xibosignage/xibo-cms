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
 *  form_id = The ID of the Form
 * 	form_action = The URL for calling the Layout Add Transaction
 * 	template_field_list	= An array of fields for the template selector (templateid => template)
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
    <fieldset>
	<legend><?php echo Theme::Translate('About you'); ?></legend>

	<p>
	    <label for="requester_name"><?php echo Theme::Translate('Full Name'); ?></label><br/>
	    <input class="text required" id="requester_name"  name="requester_name" type="text" value="" />
	</p>

	<p>
	    <label for="requester_email"><?php echo Theme::Translate('Email Address'); ?></label><br/>
	    <input class="email required" id="requester_email"  name="requester_email" type="text" value="" />
	</p>
    </fieldset>

    <fieldset>
	<legend><?php echo Theme::Translate('Details'); ?></legend>

	<p>
	    <label for="application_uri"><?php echo Theme::Translate('The URL of your application homepage'); ?></label><br/>
	    <input id="application_uri" class="text" name="application_uri" type="text" value="" />
	</p>

	<p>
	    <label for="callback_uri"><?php echo Theme::Translate('The call back URL for requests'); ?></label><br/>
	    <input id="callback_uri" class="text" name="callback_uri" type="text" value="" />
	</p>
    </fieldset>
</form>