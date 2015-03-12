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

// Form class
$formClass = Theme::Get('form_class');
if ($formClass == '')
    $formClass = 'XiboForm';

// Buttons?
$form_buttons = Theme::Get('form_buttons');
$buttons = (is_array($form_buttons) && count($form_buttons > 0));

// Are we tabs?
$form_tabs = Theme::Get('form_tabs');
$tabs = (is_array($form_tabs) && count($form_tabs > 0));

if (!$tabs)
    $form_tabs = array(FormManager::AddTab('general', 'General'));

//var_dump($form_tabs);

// Are we columns?
//$form_cols = Theme::Get('form_cols');
//$cols = (is_array($form_cols) && count($form_cols > 0));

?>
<div class="row">
    <div class="col-md-12">
        <?php echo Theme::Get('prepend'); ?>
        <?php if ($tabs) { // Add tabs if they have been provided ?>
        <ul class="nav nav-tabs" role="tablist">
            <?php 
            $first = true;
            foreach ($form_tabs as $tab) { 
                if ($first) {
                    echo '<li class="active">';
                    $first = false;
                }
                else {
                    echo '<li>';
                }
            ?>
            <a href="#<?php echo $tab['id']; ?>" role="tab" data-toggle="tab" <?php if (isset($tab['dataAttributes']) && is_array($tab['dataAttributes'])) {
                foreach ($tab['dataAttributes'] as $attribute) { 
                    echo 'data-' . $attribute['name'] . '="' . $attribute['value'] . '"'; 
                }
            } ?>><?php echo $tab['name']; ?></a></li>
            <?php } ?>
        </ul>
        <?php } ?>
        <form id="<?php echo Theme::Get('form_id'); ?>" class="<?php echo $formClass; ?> form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
            <?php echo Theme::Get('form_meta'); 

            // Always add content container (it does no harm)
            echo '<div class="tab-content">';

            // Count fields
            $i = 0;

            // First tab?
            $first = true;

            // Each field
            foreach ($form_tabs as $tab) {
                    
                // Add tab containers if we have tabs
                if ($tabs) {
                    if ($first) {
                        echo '<div class="tab-pane active" id="' . $tab['id'] . '">';
                        $first = false;
                    }
                    else {
                        echo '<div class="tab-pane" id="' . $tab['id'] . '">';
                    }

                    // Reset field count
                    $i = 0;
                }

                // Each field
                if ($tabs)
                    $form_fields = Theme::Get('form_fields_' . $tab['id']);
                else
                    $form_fields = Theme::Get('form_fields');
                
                //var_dump($form_fields);

                foreach ($form_fields as $field) {
                    //var_dump($field);

                    $i++;
                    
                    if (isset($field['enabled']) && $field['enabled'] != 1) { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <label class="col-sm-2 control-label"><?php echo $field['title']; ?></label>
                            <div class="col-sm-10">
                                <p class="form-control-static"><?php echo $field['value']; ?></p>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'hidden') { ?>
                        <input name="<?php echo $field['name']; ?>" type="hidden" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" />
                    <?php } 
                    else if ($field['fieldType'] == 'message') { ?>
                        <div class="row">
                            <div class="col-sm-12 <?php echo $field['groupClass']; ?>">
                                <p><?php echo $field['helpText']; ?></p>
                            </div>
                        </div>
                    <?php } 
                    else if ($field['fieldType'] == 'button') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <div class="col-sm-offset-2 col-sm-10">
                                <?php if ($field['type'] == 'link') { ?>
                                <a class="btn btn-default" href="<?php echo $field['link']; ?>"><?php echo $field['title']; ?></a>
                                <?php } else { ?>
                                <button class="btn btn-default" type="<?php echo $field['type']; ?>"><?php echo $field['title']; ?></button>
                                <?php } ?>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'raw') { ?>
                        <div class="col-sm-12 <?php echo $field['groupClass']; ?>">
                            <?php echo $field['helpText']; ?>
                        </div>
                    <?php } 
                    else if ($field['fieldType'] == 'text' || $field['fieldType'] == 'number' || $field['fieldType'] == 'email' || $field['fieldType'] == 'password') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                            <div class="col-sm-10">
                                <input class="form-control" name="<?php echo $field['name']; ?>" type="<?php echo $field['fieldType']; ?>" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> />
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'textarea') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <div class="col-sm-12">
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                                <textarea class="form-control" name="<?php echo $field['name']; ?>" id="<?php echo $field['name']; ?>" rows="<?php echo $field['rows']; ?>" <?php echo $field['validation']; ?>><?php echo $field['value']; ?></textarea>
                            </div>
                        </div>
                    <?php } 
                    else if ($field['fieldType'] == 'checkbox') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <div class="col-sm-offset-2 col-sm-10">
                                <div class="checkbox">
                                    <label for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>" accesskey="<?php echo $field['accesskey']; ?>">
                                        <input type="checkbox" id="<?php echo $field['name']; ?>" name="<?php echo $field['name']; ?>" <?php echo ($field['value'] == 1) ? ' checked' : '' ?>>
                                        <?php echo $field['title']; ?>
                                    </label>
                                </div>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'radio') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <div class="col-sm-offset-2 col-sm-10">
                                <div class="radio">
                                    <label for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>" accesskey="<?php echo $field['accesskey']; ?>">
                                        <input type="radio" id="<?php echo $field['id']; ?>" name="<?php echo $field['name']; ?>" value="<?php echo $field['setValue']; ?>" <?php echo ($field['value'] == $field['setValue']) ? ' checked' : '' ?>>
                                        <?php echo $field['title']; ?>
                                    </label>
                                </div>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'dropdown' || $field['fieldType'] == 'dropdownmulti') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                            <div class="col-sm-10">
                                <select class="form-control" <?php echo (($field['fieldType'] == 'dropdownmulti') ? 'multiple' : ''); ?> name="<?php echo $field['name']; ?>" id="<?php echo $field['name']; ?>"<?php echo ((isset($field['callBack'])) ? $field['callBack'] : ''); ?> <?php 
                                if (isset($field['dataAttributes']) && is_array($field['dataAttributes'])) {
                                    foreach ($field['dataAttributes'] as $attribute) { 
                                        echo $attribute['name'] . '="' . $attribute['value'] . '"'; 
                                    }
                                } ?>>

                                <?php 
                                // Option groups?
                                $groups = (isset($field['optionGroups']) && is_array($field['optionGroups']) && count($field['optionGroups']) > 0);
                                if (!$groups)
                                    $field['optionGroups'] = array('label' => 'General');

                                foreach ($field['optionGroups'] as $group) {

                                    // If we are groups then output an option group node
                                    if ($groups) {
                                        echo '<optgroup label="' . $group['label'] . '">';
                                        $options = $field['options'][$group['id']];
                                    }
                                    else {
                                        $options = $field['options'];
                                    }

                                    foreach ($options as $item) { 

                                        $class = (!isset($field['classColumn']) || $field['classColumn'] == '') ? '' : ' class="' . $item[$field['classColumn']] . '"';
                                        $style = (!isset($field['styleColumn']) || $field['styleColumn'] == '') ? '' : ' style="' . $item[$field['styleColumn']] . '"';
                                        if ($field['fieldType'] == 'dropdownmulti')
                                            $selected = ((in_array($item[$field['optionId']], $field['value'])) ? 'selected' : '');
                                        else
                                            $selected = (($item[$field['optionId']] == $field['value']) ? 'selected' : '');
                                         ?>
                                        <option<?php echo $class . $style ?> value="<?php echo $item[$field['optionId']]; ?>" <?php echo $selected; ?>><?php echo $item[$field['optionValue']]; ?></option>
                                    <?php } 

                                    if ($groups)
                                        echo '</optgroup>';
                                }
                                ?>
                                </select>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'permissions') { ?>
                        <table class="table table-bordered">
                            <tr>
                                  <th><?php echo Theme::Translate('Group'); ?></th>
                                  <th><?php echo Theme::Translate('View'); ?></th>
                                  <th><?php echo Theme::Translate('Edit'); ?></th>
                                  <th><?php echo Theme::Translate('Delete'); ?></th>
                            </tr>
                            <?php foreach($field['options'] as $row) { ?>
                            <tr>
                                <td><span class="<?php echo $row['class']; ?>"><?php echo $row['name']; ?></span></td>
                                <td><input type="checkbox" name="<?php echo $field['name']; ?>" value="<?php echo $row['value_view']; ?>" <?php echo $row['value_view_checked']; ?>></td>
                                <td><input type="checkbox" name="<?php echo $field['name']; ?>" value="<?php echo $row['value_edit']; ?>" <?php echo $row['value_edit_checked']; ?>></td>
                                <td><input type="checkbox" name="<?php echo $field['name']; ?>" value="<?php echo $row['value_del']; ?>" <?php echo $row['value_del_checked']; ?>></td>
                            </tr>
                            <?php } ?>
                        </table>
                    <?php }
                    else if ($field['fieldType'] == 'datePicker') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                            <div class="col-sm-10">
                                <input class="form-control datePicker" name="<?php echo $field['name']; ?>" type="text" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> readonly/>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                    else if ($field['fieldType'] == 'timePicker') { ?>
                        <div class="form-group <?php echo $field['groupClass']; ?>">
                            <label class="col-sm-2 control-label" for="<?php echo $field['name']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                            <div class="col-sm-10">
                                <input class="form-control timePicker" name="<?php echo $field['name']; ?>" type="text" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> readonly/>
                                <span class="help-block"><?php echo $field['helpText']; ?></span>
                            </div>
                        </div>
                    <?php }
                } // End for loop
                echo '</div>';
            } // End for loop ?>
            <?php
            // Any buttons?
            if ($buttons) { ?>
                <div class="">
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <div class="btn-group">
                            <?php foreach ($form_buttons as $button) {
                                if ($button['type'] == 'link') { ?>
                                <a class="btn btn-default" href="<?php echo $button['link']; ?>"><?php echo $button['title']; ?></a>
                                <?php } else { ?>
                                <button class="btn btn-default" type="<?php echo $button['type']; ?>"><?php echo $button['title']; ?></button>
                                <?php }
                            } ?>
                            </div>
                        </div> 
                    </div> 
                </div> 
            <?php } ?>
            </div>
        </form>
        <?php echo Theme::Get('append'); ?>
    </div>
</div>
