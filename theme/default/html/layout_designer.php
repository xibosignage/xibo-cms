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
 * 	layout_form_edit_url = Layout Edit form URL
 * 	layout_form_edit_background_url = Layout Background edit form URL
 * 	layout_form_schedulenow_url = Layout Schedule Now URL
 * 	layout = Layout Name
 * 	jumplist_id = The ID of the Jump List
 * 	jumplist_form_meta = Form META required to show the jump list
 * 	jumplist_pager = The Paging Control on the Jump List
 * 	jumplist_filter_pinned = Whether the Jump List filter form is pinned
 * 	jumplist_list_pinned = Whether the Jump List filter form is pinned
 * 	jumplist_arrow_direction = The Arrow for the List popout
 * 	jumplist_filter_name = The jump list filter name
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="pull-right">
	<select id="layoutJumpList" data-live-search="true">
		<?php foreach(Theme::Get('layouts') as $layout) { ?>
		<option value="<?php echo $layout['layoutid']; ?>"<?php echo ($layout['layoutid'] == Theme::Get('layoutId') ? ' selected' : ''); ?>><?php echo $layout['layout']; ?></option> 
		<?php } ?>
	</select>
</div>
<div class="row">
	<div class="col-md-2">
		<div class="btn-group">
			<button class="btn dropdown-toggle" data-toggle="dropdown">
				<?php echo Theme::Translate('Options'); ?>
				<span class="caret"></span>
			</button>
			<ul class="dropdown-menu">
				<li><a class="XiboAjaxSubmit" href="<?php echo Theme::Get('layout_form_addregion_url'); ?>" title="<?php echo Theme::Translate('Add Region'); ?>"><span><?php echo Theme::Translate('Add Region'); ?></span></a></li>
				<?php if (Theme::Get('layoutVersion') >= 2) { ?>
				<li><a class="XiboFormButton" href="<?php echo Theme::Get('layout_form_edit_background_url'); ?>" title="<?php echo Theme::Translate('Background'); ?>"><span><?php echo Theme::Translate('Background'); ?></span></a></li>
				<?php } ?>
				<li><a class="XiboFormButton" href="<?php echo Theme::Get('layout_form_edit_url'); ?>" title="<?php echo Theme::Translate('Edit the Layout Properties'); ?>"><span><?php echo Theme::Translate('Properties'); ?></span></a></li>
				<li class="divider"></li>
                <li><a href="<?php echo Theme::Get('layout_form_preview_url'); ?>" title="<?php echo Theme::Translate('Preview Layout'); ?>" target="_blank"><span><?php echo Theme::Translate('Preview Layout'); ?></span></a></li>
				<li><a class="XiboFormButton" href="<?php echo Theme::Get('layout_form_schedulenow_url'); ?>" title="<?php echo Theme::Translate('Schedule Now'); ?>"><span><?php echo Theme::Translate('Schedule Now'); ?></span></a></li>
				<li><a class="XiboFormButton" href="<?php echo Theme::Get('layout_form_savetemplate_url'); ?>" title="<?php echo Theme::Translate('Save Template'); ?>"><span><?php echo Theme::Translate('Save Template'); ?></span></a></li>
				<?php if (Theme::Get('layoutVersion') >= 2) { ?>
				<li class="divider"></li>
				<li><a href="<?php echo Theme::Get('layout_zoom_in_url'); ?>"><span><?php echo Theme::Translate('Shrink Designer'); ?></span></a></li>
				<li><a href="<?php echo Theme::Get('layout_zoom_out_url'); ?>"><span><?php echo Theme::Translate('Enlarge Designer'); ?></span></a></li>
				<?php } else { ?>
				<li class="divider"></li>
				<li><a class="XiboFormButton" href="<?php echo Theme::Get('layout_upgrade_url'); ?>"><span><?php echo Theme::Translate('Upgrade Layout'); ?></span></a></li>
				<?php } ?>
			</ul>
		</div>
	</div>
	<div class="col-md-5">
		<h4><?php echo Theme::Translate('Layout Design'); ?> - <?php echo Theme::Get('layout'); ?></h4>
	</div>
	<div class="col-md-1 layout-status">
	</div>
	<div class="col-md-2 layout-meta">
	</div>
</div>
<div class="row">
	<div class="col-md-12">
		<?php echo Theme::Get('layout_designer_editor'); ?>
	</div>
</div>
<?php if (Theme::Get('layoutVersion') < 2) { ?>
<div class="row">
	<div class="col-md-offset-1 col-md-5">
		<p class="alert alert-danger"><?php echo Theme::Translate('This is an old format layout, please consider upgrading using the options menu'); ?></p>
	</div>
</div>
<?php } ?>
<?php if (Theme::Get('designerScale') < 0.41) { ?>
<div class="row">
	<div class="col-md-offset-1 col-md-5">
		<p class="alert alert-danger"><?php echo Theme::Translate('This Layout is very large, so we have disabled region drag and drop. You could enlarge the designer from the options menu or use Region Options to Manually Position your regions.'); ?></p>
	</div>
</div>
<?php } ?>
