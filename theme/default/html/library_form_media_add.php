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
<form id="<?php echo Theme::Get('form_upload_id'); ?>" action="<?php echo Theme::Get('form_action'); ?>" method="POST" enctype="multipart/form-data" data-max-file-size="<?php echo Theme::Get('form_max_size'); ?>" data-accept-file-types="<?php echo Theme::Get('form_valid_ext'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
	<div class="row fileupload-buttonbar">
	    <div class="col-md-7">
			<div class="well">
				<?php echo Theme::Get('valid_extensions'); ?>
			</div>
	        <!-- The fileinput-button span is used to style the file input field as button -->
	        <span class="btn btn-success fileinput-button">
	            <i class="glyphicon glyphicon-plus glyphicon glyphicon-white"></i>
	            <span><?php echo Theme::Translate('Add files'); ?></span>
	            <input type="file" name="files[]" multiple>
	        </span>
	        <button type="submit" class="btn btn-primary start">
	            <i class="glyphicon glyphicon-upload glyphicon glyphicon-white"></i>
	            <span><?php echo Theme::Translate('Start upload'); ?></span>
	        </button>
	        <button type="reset" class="btn btn-warning cancel">
	            <i class="glyphicon glyphicon-ban-circle glyphicon glyphicon-white"></i>
	            <span><?php echo Theme::Translate('Cancel upload'); ?></span>
	        </button>
	        <!-- The loading indicator is shown during file processing -->
	        <span class="fileupload-loading"></span>
	    </div>
	    <!-- The global progress information -->
	    <div class="col-md-4 fileupload-progress fade">
	        <!-- The global progress bar -->
            <div class="progress">
    	        <div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width:0%;">
    	            <div class="sr-only"></div>
    	        </div>
            </div>
	        <!-- The extended global progress information -->
	        <div class="progress-extended">&nbsp;</div>
	    </div>
	</div>
	<!-- The table listing the files available for upload/download -->
	<table role="presentation" class="table table-striped"><tbody class="files"></tbody></table>
</form>
<!-- The template to display files available for upload -->
<script id="template-upload" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <tr class="template-upload fade">
        <td>
            <span class="fileupload-preview"></span>
        </td>
        <td class="title">
            <label for="name[]" title="<?php echo Theme::Translate('The Name of this item - Leave blank to use the file name'); ?>"><?php echo Theme::Translate('Name'); ?>: <input name="name[]" type="text" id="name" /></label>
            {% if (file.error) { %}
                <div><span class="label label-important">Error</span> {%=file.error%}</div>
            {% } %}
        </td>
        <td class="title">
			<label for="duration[]" title="<?php echo Theme::Translate('The duration in seconds this image should be displayed (may be overridden on each layout)'); ?>"><?php echo Theme::Translate('Duration'); ?>: <input name="duration[]" type="text" id="duration" value="<?php echo Theme::Get('default_duration'); ?>" required /></label>
        </td>
        <td>
            <p class="size">{%=o.formatFileSize(file.size)%}</p>
            {% if (!o.files.error) { %}
                <div class="progress">
                    <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width:0%;">
                        <div class="sr-only"></div>
                    </div>
                </div>
            </div>
            {% } %}
        </td>
        <td>
            {% if (!o.files.error && !i && !o.options.autoUpload) { %}
                <button class="btn btn-primary start">
                    <i class="glyphicon glyphicon-upload glyphicon glyphicon-white"></i>
                    <span><?php echo Theme::Translate('Start'); ?></span>
                </button>
            {% } %}
            {% if (!i) { %}
                <button class="btn btn-warning cancel">
                    <i class="glyphicon glyphicon-ban-circle glyphicon glyphicon-white"></i>
                    <span><?php echo Theme::Translate('Cancel'); ?></span>
                </button>
            {% } %}
        </td>
    </tr>
{% } %}
</script>
<!-- The template to display files available for download -->
<script id="template-download" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <tr class="template-download fade">
       <td>
            <p class="name" id="{%=file.storedas%}" status="{% if (file.error) { %}error{% } %}">
                {%=file.name%}
            </p>
            {% if (file.error) { %}
                <div><span class="label label-important"><?php echo Theme::Translate('Error'); ?></span> {%=file.error%}</div>
            {% } %}
        </td>
        <td>
            <span class="size">{%=o.formatFileSize(file.size)%}</span>
        </td>
        <td>
			<?php if (Theme::Get('background_override_url') != '') { ?>
            	<button class="btn XiboFormButton" href="#" onclick="XiboSwapDialog('<?php echo Theme::Get('background_override_url'); ?>{%=file.storedas%}')">
                    <i class="glyphicon glyphicon-ban-circle glyphicon glyphicon-white"></i>
                    <span><?php echo Theme::Translate('Set Background'); ?></span>
                </button>
        	<?php } ?>
        </td>
    </tr>
{% } %}
</script>
