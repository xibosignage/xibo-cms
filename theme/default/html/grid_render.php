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

// Are we tabs?
$form_tabs = Theme::Get('form_tabs');
$tabs = (is_array($form_tabs) && count($form_tabs > 0));

if (!$tabs)
    $form_tabs = array(FormManager::AddTab('general', 'General'));

?>
<?php if (Theme::Get('header_text') != '') { ?>
<?php } ?>
<div class="widget">
    <div class="widget-title"><?php echo Theme::Get('header_text'); ?></div>
    <div class="widget-body">
        <?php echo Theme::Get('prepend'); ?>
        <div class="XiboGrid" id="<?php echo Theme::Get('id'); ?>">
            <div class="XiboFilter">
                <div class="FilterDiv" id="Filter">
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
                        <a href="#<?php echo $tab['id']; ?>" role="tab" data-toggle="tab"><?php echo $tab['name']; ?></a></li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                    <form id="<?php echo Theme::Get('form_id'); ?>" class="form-inline" method="post" action="<?php echo Theme::Get('form_action'); ?>">
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
                                        <label class="control-label" for="<?php echo $field['name']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                                        <div>
                                            <input class="form-control" type="text" placeholder="<?php echo $field['value']; ?>" readonly>
                                            <span class="help-block"><?php echo $field['helpText']; ?></span>
                                        </div>
                                    </div>
                                <?php }
                                else if ($field['fieldType'] == 'hidden') { ?>
                                    <input name="<?php echo $field['name']; ?>" type="hidden" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" />
                                <?php } 
                                else if ($field['fieldType'] == 'message') { ?>
                                    <div class="col-sm-12 <?php echo $field['groupClass']; ?>">
                                        <p><?php echo $field['helpText']; ?></p>
                                    </div>
                                <?php } 
                                else if ($field['fieldType'] == 'raw') { ?>
                                    <div class="col-sm-12 <?php echo $field['groupClass']; ?>">
                                        <?php echo $field['helpText']; ?>
                                    </div>
                                <?php } 
                                else if ($field['fieldType'] == 'text' || $field['fieldType'] == 'number' || $field['fieldType'] == 'email' || $field['fieldType'] == 'password') { ?>
                                    <div class="form-group <?php echo $field['groupClass']; ?>">
                                        <label class="control-label" for="<?php echo $field['name']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                                        <div>
                                            <input class="form-control" name="<?php echo $field['name']; ?>" type="<?php echo $field['fieldType']; ?>" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> />
                                            <span class="help-block"><?php echo $field['helpText']; ?></span>
                                        </div>
                                    </div>
                                <?php }
                                else if ($field['fieldType'] == 'textarea') { ?>
                                    <div class="form-group <?php echo $field['groupClass']; ?>">
                                        <div>
                                            <span class="help-block"><?php echo $field['helpText']; ?></span>
                                            <textarea class="form-control" name="<?php echo $field['name']; ?>" id="<?php echo $field['name']; ?>" rows="<?php echo $field['rows']; ?>" <?php echo $field['validation']; ?>><?php echo $field['value']; ?></textarea>
                                        </div>
                                    </div>
                                <?php } 
                                else if ($field['fieldType'] == 'checkbox') { ?>
                                    <div class="form-group <?php echo $field['groupClass']; ?>">
                                        <div>
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
                                else if ($field['fieldType'] == 'dropdown' || $field['fieldType'] == 'dropdownmulti') { ?>
                                    <div class="form-group <?php echo $field['groupClass']; ?>">
                                        <label class="control-label" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                                        <div>
                                            <select class="form-control" <?php echo (($field['fieldType'] == 'dropdownmulti') ? 'multiple' : ''); ?> name="<?php echo $field['name']; ?>" id="<?php echo $field['name']; ?>"<?php echo $field['callBack']; ?> <?php 
                                            foreach ($field['dataAttributes'] as $attribute) { echo $attribute['name'] . '="' . $attribute['value'] . '"'; } ?>>

                                            <?php 
                                            // Option groups?
                                            $groups = is_array($field['optionGroups']) && count($field['optionGroups']) > 0;
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

                                                    $class = ($field['classColumn'] == '') ? '' : ' class="' . $item[$field['classColumn']] . '"';
                                                    $style = ($field['styleColumn'] == '') ? '' : ' style="' . $item[$field['styleColumn']] . '"';
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
                                else if ($field['fieldType'] == 'datePicker') { ?>
                                    <div class="form-group <?php echo $field['groupClass']; ?>">
                                        <label class="control-label" for="<?php echo $field['name']; ?>" accesskey="<?php echo $field['accesskey']; ?>"><?php echo $field['title']; ?></label>
                                        <div>
                                            <input class="form-control datePicker" name="<?php echo $field['name']; ?>" type="text" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" <?php echo $field['validation']; ?> readonly />
                                            <span class="help-block"><?php echo $field['helpText']; ?></span>
                                        </div>
                                    </div>
                                <?php }
                            } // End for loop
                            echo '</div>';
                        } // End for loop ?>
                    </form>
                </div>
            </div>
            <div class="XiboData"></div>
            <?php echo Theme::Get('pager'); ?>
        </div>
        <?php echo Theme::Get('append'); ?>
    </div>
</div>