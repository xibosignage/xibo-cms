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
 *  form_action = The URL for calling the Add Transaction
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<h2>Xibo API - Authorization Requested</h2>

<p>Are you sure you want to authorize this application to have access to your CMS account?</p>
<p>
    <strong>Application Name</strong>: <?php echo Theme::Get('application_title'); ?><br />
    <strong>Application Description</strong>: <?php echo Theme::Get('application_descr'); ?><br />
    <strong>Application Site</strong>: <?php echo Theme::Get('application_uri'); ?>
</p>
<form method="post">
	<?php echo Theme::Get('form_meta'); ?>
    <input type="submit" name="Allow" value="Allow">
</form>
