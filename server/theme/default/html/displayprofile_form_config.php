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
<div class="container-fluid">
    <form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    	<?php echo Theme::Get('form_meta'); ?>
        <div class="row-fluid">
            <div class="span6">
                <div class="control-group">
                    <label class="control-label" for="name" accesskey="n"><?php echo Theme::Translate('Name'); ?></label>
                    <div class="controls">
                        <input name="name" class="required" type="text" id="name" value="<?php echo Theme::Get('name'); ?>" tabindex="1" minlength="1" maxlength="50" required />
                        <span class="help-block"><?php echo Theme::Translate('The Name of the Profile - (1 - 50 characters)'); ?></span>
                    </div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <label class="checkbox" for="isdefault"><?php echo Theme::Translate('Default Profile'); ?>
                            <input class="checkbox" type="checkbox" id="isdefault" name="isdefault" <?php echo ((Theme::Get('isdefault') == 1) ? ' checked' : ''); ?>>
                        </label>
                        <span class="help-block"><?php echo Theme::Translate('Is this the default profile for all Displays of this type? Only 1 profile can be the default.'); ?></span>
                    </div>
                </div>
                <?php 
                $count = count(Theme::Get('form_fields')) + 2;
                $i = 0;
                foreach(Theme::Get('form_fields') as $field) {
                    
                    $i++;
                    if ($i > $count / 2) {
                        $i = 0;
                        ?>
                    </div>
                    <div class="span6">
                        <?php
                    }

                    if ($field['fieldType'] == 'text') { ?>
                        <div class="control-group">
                            <label class="control-label" for="<?php echo $field['name']; ?>"><?php echo $field['title']; ?></label>
                            <div class="controls">
                                <input name="<?php echo $field['name']; ?>" type="text" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> />
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php } 
                    else if ($field['fieldType'] == 'checkbox') { ?>
                        <div class="control-group">
                            <div class="controls">
                                <label class="checkbox" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>"><?php echo $field['title']; ?>
                                    <input type="checkbox" id="<?php echo $field['name']; ?>" name="<?php echo $field['name']; ?>" <?php echo ($field['value'] == 1) ? ' checked' : '' ?>>
                                </label>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'dropdown') { ?>
                        <div class="control-group">
                            <label class="control-label" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>"><?php echo $field['title']; ?></label>
                            <div class="controls">
                                <?php echo Theme::SelectList($field['name'], $field['options'], 'id', 'value', $field['value']); ?>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php } ?>
                <?php } // End for ?>
            </div>
        </div>
    </form>
</div>
