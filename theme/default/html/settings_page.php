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
<div class="row">
    <div class="col-md-12">
        <ul class="nav nav-tabs" role="tablist">
            <?php 
            $first = true;
            foreach(Theme::Get('cats') as $cat) { ?>
            <li <?php echo ($first) ? 'class="active"' : ''; ?>><a href="#<?php echo $cat['tabId']; ?>" role="tab" data-toggle="tab"><span><?php echo Theme::Translate($cat['tabName']); ?></span></a></li>
            <?php 
                $first = false;
            } 
            ?>
        </ul>
        <form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
            <div class="tab-content">
            <?php echo Theme::Get('form_meta'); 
            
            $category = '';
            foreach(Theme::Get('form_fields') as $field) {
                // var_dump($field);
                
                    if ($category != $field['cat']) {
                        // Each new category has a new box.

                        if ($category == 'Network') {
                            ?>
                            <h3 class="section-heading"><?php echo Theme::Translate('Current Usage'); ?></h3>
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="librarySize"><?php echo Theme::Translate('Library Size'); ?></label>
                                <div class="col-sm-10">
                                    <input class="form-control" type="text" placeholder="<?php echo Theme::Get('library_info'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="librarySize"><?php echo Theme::Translate('Monthly Bandwidth'); ?></label>
                                <div class="col-sm-10">
                                    <input class="form-control" type="text" placeholder="<?php echo Theme::Get('bandwidth_info'); ?>" readonly>
                                </div>
                            </div>
                            <?php
                        }

                        if ($category != '') {
                            echo '</div>';
                        }

                        echo '<div class="tab-pane' . (($category == '') ? ' active' : '') . '" id="' . $field['catId'] . '">';
                        echo '<h3 class="section-heading">' . Theme::Translate($field['cat']) . '</h3>';
                        $category = $field['cat'];
                    }
                if ($field['enabled'] != 1) { ?>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>"><?php echo Theme::Translate($field['title']); ?></label>
                        <div class="col-sm-10">
                            <input class="form-control" type="text" placeholder="<?php echo $field['value']; ?>" readonly>
                            <span class="help-block"><?php echo Theme::Translate($field['helpText']); ?></span>
                            <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                        </div>
                    </div>
                <?php }
                else if ($field['fieldType'] == 'text' || $field['fieldType'] == 'number' || $field['fieldType'] == 'email') { ?>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>"><?php echo Theme::Translate($field['title']); ?></label>
                        <div class="col-sm-10">
                            <input class="form-control" name="<?php echo $field['name']; ?>" type="<?php echo $field['fieldType']; ?>" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> />
                            <span class="help-block"><?php echo Theme::Translate($field['helpText']); ?></span>
                            <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                        </div>
                    </div>
                <?php } 
                else if ($field['fieldType'] == 'checkbox') { ?>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <label class="checkbox" for="<?php echo $field['name']; ?>" title="<?php echo Theme::Translate($field['helpText']); ?>">
                                <input type="checkbox" id="<?php echo $field['name']; ?>" name="<?php echo $field['name']; ?>" <?php echo ($field['value'] == 1) ? ' checked' : '' ?>>
                                <?php echo Theme::Translate($field['title']); ?>
                            </label>
                            <span class="help-block"><?php echo Theme::Translate($field['helpText']); ?></span>
                            <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                        </div>
                    </div>
                <?php }
                else if ($field['fieldType'] == 'dropdown') { ?>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" title="<?php echo Theme::Translate($field['helpText']); ?>"><?php echo Theme::Translate($field['title']); ?></label>
                        <div class="col-sm-10">
                            <?php echo Theme::SelectList($field['name'], $field['options'], 'value', 'value', $field['value']); ?>
                            <span class="help-block"><?php echo Theme::Translate($field['helpText']); ?></span>
                            <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                        </div>
                    </div>
                <?php }
                else if ($field['fieldType'] == 'timezone') { ?>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" title="<?php echo Theme::Translate($field['helpText']); ?>"><?php echo Theme::Translate($field['title']); ?></label>
                        <div class="col-sm-10">
                            <select class="form-control" name="<?php echo $field['name']; ?>"><?php echo $field['options']; ?></select>
                            <span class="help-block"><?php echo Theme::Translate($field['helpText']); ?></span>
                            <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                        </div>
                    </div>
                <?php } ?>
                <?php } // End for loop ?>
            </div>
        </div>
        <button class="btn btn-save btn-block btn-success" title="<?php echo Theme::Translate('Save Settings'); ?>" href="#"><span><?php echo Theme::Translate('Save'); ?></span></button>
    </form>
</div>
