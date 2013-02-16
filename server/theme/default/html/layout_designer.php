<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left">
		</div>
		<div id="form_header_right">
		</div>
	</div>
	
	<div id="form_body">
		<div class='buttons'>
			<a id="background_button" class="XiboFormButton" href="<?php echo Theme::Get('layout_form_edit_background_url'); ?>" title="<?php echo Theme::Translate('Background'); ?>"><div class="button_text"><?php echo Theme::Translate('Background'); ?></div></a> 
			<a id="edit_button" class="XiboFormButton" href="<?php echo Theme::Get('layout_form_edit_url'); ?>" title="<?php echo Theme::Translate('Edit the Layout Properties'); ?>"><div class="button_text"><?php echo Theme::Translate('Properties'); ?></div></a> 		
			<a id="schedulenow_button" class="XiboFormButton" href="<?php echo Theme::Get('layout_form_schedulenow_url'); ?>" title="<?php echo Theme::Translate('Schedule Now'); ?>"><div class="button_text"><?php echo Theme::Translate('Schedule Now'); ?></div></a>
		</div>
			
		<div class="slidetable">
			<div class="title">
				<h4><?php echo Theme::Translate('Layout Design'); ?> - <?php echo Theme::Get('layout'); ?></h4>
			</div>
			<div class="formbody">
				<?php echo Theme::Get('layout_designer_editor'); ?>

				<!-- Layout Jump list -->
				<div id="LayoutJumpList">
			        <div id="JumpListHeader" JumpListGridId="<?php echo Theme::Get('jumplist_id'); ?>">
			            <?php echo Theme::Translate('Layout Jump List'); ?><span id="JumpListOpenClose"><?php echo Theme::Get('jumplist_arrow_direction'); ?></span>
			        </div>
			        <div class="XiboGrid" id="<?php echo Theme::Get('jumplist_id'); ?>" style="display:<?php echo Theme::Get('jumplist_list_pinned'); ?>;">
			            <div class="XiboFilter">
			                <div class="XiboFilterInner">     
					        <form>
					        	<?php echo Theme::Get('jumplist_form_meta'); ?>
					            <input type="checkbox" class="XiboFilterPinned" style="display:none" checked />
					            <table>
					                <tr>
					                    <td><?php echo Theme::Translate('Layout'); ?></td>
					                    <td><input type="text" name="name" value="<?php echo Theme::Get('jumplist_filter_name'); ?>"></td>
					                    <td><label for="XiboJumpListPinned"><?php echo Theme::Translate('Pin?'); ?></label><input id="XiboJumpListPinned" name="XiboJumpListPinned" type="checkbox" class="XiboJumpListPinned" <?php echo Theme::Get('jumplist_filter_pinned'); ?> /></td>
					                </tr>
					            </table>
					        </form>
					        </div>
			            </div>
			            <?php echo Theme::Get('jumplist_pager'); ?>
			            <div class="XiboData"></div>
			        </div>
				</div>
			</div>
		</div>
	</div>
	
	<div id="form_footer">
		<div id="form_footer_left"></div>
		<div id="form_footer_right"></div>
	</div>
</div>	
<div id="tooltip_hover" style="position:absolute; top: 0px; left:0px; display:none"></div>
