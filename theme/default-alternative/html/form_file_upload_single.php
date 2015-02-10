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
 *
 * Theme variables:
 * 	buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form class="form-horizontal" id="<?php echo Theme::Get('form_upload_id'); ?>" method="post" action="<?php echo Theme::Get('form_upload_action'); ?>" enctype="multipart/form-data" target="fileupload">
	<fieldset>
        <?php echo Theme::Get('form_upload_meta'); ?>
        <div class="form-group">
			<label class="col-sm-2 control-label" for="media_file" accesskey="n" title="<?php echo Theme::Translate('Select the file to upload'); ?>"><?php echo Theme::Translate('File'); ?></label>
			<div class="col-sm-10">
				<input class="form-control" name="media_file" type="file" id="media_file" tabindex="1" onchange="fileFormSubmit();this.form.submit();" />
			</div>
		</div>
	</fieldset>	
</form>
<div id="uploadProgress" class="well" style="display:none">
    <span><?php echo Theme::Translate('You may fill in the form while your file is uploading.'); ?></span>
</div>
<div style="display:none">
	<iframe name="fileupload" width="1px" height="1px"></iframe>
</div>
