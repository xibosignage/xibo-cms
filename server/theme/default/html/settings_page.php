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
    <div class="col-md-2">
        <div class="well affix">
            <ul class="nav nav-list ">
                <li class="nav-header"><?php echo Theme::Translate('Library Size'); ?></li>
                <li><?php echo Theme::Get('library_info'); ?></li>

                <li class="nav-header"><?php echo Theme::Translate('Monthly Bandwidth'); ?></li>
                <li><?php echo Theme::Get('bandwidth_info'); ?></li>
                
                <li class="nav-header"><?php echo Theme::Translate('Categories'); ?></li>
                <?php foreach(Theme::Get('cats') as $cat) { ?>
                <li><a href="#<?php echo $cat['tabId']; ?>"><span><?php echo $cat['tabName']; ?></span><i class='glyphicon glyphicon-chevron-right pull-right'></i></a></li>
                <?php } ?>
            </ul>
            <button class="btn btn-save btn-block" title="<?php echo Theme::Translate('Save Settings'); ?>" href="#" onclick="$('#<?php echo Theme::Get('form_id'); ?>').submit()"><span><?php echo Theme::Translate('Save'); ?></span></button>
        </div>
    </div>
    <div class="col-md-8 col-md-offset-1">

        <form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
        <?php echo Theme::Get('form_meta'); 
        
        $category = '';
        foreach(Theme::Get('form_fields') as $field) {
            // var_dump($field);
            
                if ($category != $field['cat']) {
                    // Each new category has a new box.
                    if ($category != '')
                        echo '</div>';

                    echo '<div id="' . $field['catId'] . '">';
                    echo '<h3 class="section-heading">' . $field['cat'] . '</h3>';
                    $category = $field['cat'];
                }
            if ($field['enabled'] != 1) { ?>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>"><?php echo $field['title']; ?></label>
                    <div class="col-sm-10">
                        <input class="form-control" type="text" placeholder="<?php echo $field['value']; ?>" readonly>
                        <span class="help-block"><?php echo $field['helpText']; ?></span>
                        <span class="help-block">This setting is referred to as: <?php echo $field['name']; ?></span>
                    </div>
                </div>
            <?php }
            else if ($field['fieldType'] == 'text' || $field['fieldType'] == 'number' || $field['fieldType'] == 'email') { ?>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>"><?php echo $field['title']; ?></label>
                    <div class="col-sm-10">
                        <input class="form-control" name="<?php echo $field['name']; ?>" type="<?php echo $field['fieldType']; ?>" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> />
                        <span class="help-block"><?php echo $field['helpText']; ?></span>
                        <span class="help-block">This setting is referred to as: <?php echo $field['name']; ?></span>
                    </div>
                </div>
            <?php } 
            else if ($field['fieldType'] == 'checkbox') { ?>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <label class="checkbox" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>">
                            <input type="checkbox" id="<?php echo $field['name']; ?>" name="<?php echo $field['name']; ?>" <?php echo ($field['value'] == 1) ? ' checked' : '' ?>>
                            <?php echo $field['title']; ?>
                        </label>
                        <span class="help-block"><?php echo $field['helpText']; ?></span>
                        <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                    </div>
                </div>
            <?php }
            else if ($field['fieldType'] == 'dropdown') { ?>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>"><?php echo $field['title']; ?></label>
                    <div class="col-sm-10">
                        <?php echo Theme::SelectList($field['name'], $field['options'], 'value', 'value', $field['value']); ?>
                        <span class="help-block"><?php echo $field['helpText']; ?></span>
                        <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                    </div>
                </div>
            <?php }
            else if ($field['fieldType'] == 'timezone') { ?>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>"><?php echo $field['title']; ?></label>
                    <div class="col-sm-10">
                        <select name="<?php echo $field['name']; ?>"><?php echo $field['options']; ?></select>
                        <span class="help-block"><?php echo $field['helpText']; ?></span>
                        <span class="help-block"><?php echo sprintf(Theme::Translate('This setting is referred to as: %s'), $field['name']); ?></span>
                    </div>
                </div>
            <?php } ?>
            <?php } // End for loop ?>
        </div>
</div>
