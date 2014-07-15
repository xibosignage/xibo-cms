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
 * 	buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form class="form-horizontal" id="<?php echo Theme::Get('form_upload_id'); ?>" method="post" action="<?php echo Theme::Get('form_upload_action'); ?>" enctype="multipart/form-data" target="fileupload">
	<fieldset>
        <?php echo Theme::Get('form_upload_meta'); ?>
        <div class="control-group">
			<label class="control-label" for="media_file" accesskey="n" title="<?php echo Theme::Translate('Select the file to upload'); ?>"><?php echo Theme::Translate('File'); ?></label>
			<div class="controls">
				<input name="media_file" type="file" id="media_file" tabindex="1" onchange="fileFormSubmit();this.form.submit();" />
			</div>
		</div>
	</fieldset>	
</form>
<div id="uploadProgress" class="well" style="display:none">
    <span><?php echo Theme::Translate('You may fill in the form while your file is uploading.'); ?></span>
</div>
<div class="well">
	<p class="text-center"><?php echo Theme::Translate('Please choose a column number from the source CSV file to map to each DataSet Column. If no mapping exists, leave blank.'); ?></p>
</div>
<form class="XiboForm form-horizontal" id="<?php echo Theme::Get('form_id'); ?>" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <div class="control-group">
        <div class="controls">
    		<label class="checkbox" for="overwrite" accesskey="n" title="<?php echo Theme::Translate('Overwrite existing data'); ?>"><?php echo Theme::Translate('Overwrite existing data?'); ?>
                <input class="checkbox" name="overwrite" type="checkbox" id="overwrite" tabindex="1" />
            </label>
        </div>
    </div>
    <div class="control-group">
        <div class="controls">
    		<label class="checkbox" for="ignorefirstrow" accesskey="n" title="<?php echo Theme::Translate('Ignore the first row? Useful if the CSV has headings.'); ?>"><?php echo Theme::Translate('Ignore first row?'); ?>
                <input class="checkbox" name="ignorefirstrow" type="checkbox" id="ignorefirstrow" tabindex="2" checked />
            </label>
        </div>
    </div>
    <?php foreach(Theme::Get('fields') as $field) { ?>
	    <div class="control-group">
	    	<label class="control-label" for="<?php echo $field['formfieldid'] ?>" title="<?php echo $field['heading'] ?>"><?php echo $field['heading'] ?></label>
	        <div class="controls">
	            <input class="" name="<?php echo $field['formfieldid'] ?>" type="text" id="<?php echo $field['formfieldid'] ?>" value="<?php echo $field['auto_column_number'] ?>" />
	        </div>
	    </div>
	<?php } ?>
</form>
<div style="display:none">
	<iframe name="fileupload" width="1px" height="1px"></iframe>
</div>