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
 *  form_id = The ID of the Form
 * 	form_action = The URL for calling the Layout Edit Transaction
 * 	form_meta = Additional META information required by Xibo in the form submit call
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <fieldset>
        <div class="control-group">
            <label class="control-label" for="heading" accesskey="h" title="<?php echo Theme::Translate('The heading for this Column'); ?>"><?php echo Theme::Translate('Heading'); ?></label>
            <div class="controls">
                <input class="required" name="heading" type="text" id="heading" tabindex="1" value="<?php echo Theme::Get('heading'); ?>" />
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="datasetcolumntypeid" accesskey="t" title="<?php echo Theme::Translate('Whether this column is a value or a formula'); ?>"><?php echo Theme::Translate('Column Type'); ?></label>
            <div class="controls">
                <?php echo Theme::SelectList('datasetcolumntypeid', Theme::Get('datasetcolumntype_field_list'), 'datasetcolumntypeid', 'datasetcolumntype', Theme::Get('datasetcolumntypeid')); ?>
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="datatypeid" accesskey="d" title="<?php echo Theme::Translate('The DataType of the Intended Data'); ?>"><?php echo Theme::Translate('Data Type'); ?></label>
            <div class="controls">
                <?php echo Theme::SelectList('datatypeid', Theme::Get('datatype_field_list'), 'datatypeid', 'datatype', Theme::Get('datatypeid')); ?>
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="listcontent" accesskey="l" title="<?php echo Theme::Translate('A comma seperated list of items to present in a combo box'); ?>"><?php echo Theme::Translate('List Content'); ?></label>
            <div class="controls">
                <input class="" name="listcontent" type="text" id="listcontent" tabindex="4" value="<?php echo Theme::Get('listcontent'); ?>" />
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="columnorder" accesskey="c" title="<?php echo Theme::Translate('The order this column should be displayed in when entering data'); ?>"><?php echo Theme::Translate('Column Order'); ?></label>
            <div class="controls">
                <input class="required number" name="columnorder" type="text" id="columnorder" tabindex="5" value="<?php echo Theme::Get('columnorder'); ?>" />
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="formula" accesskey="f" title="<?php echo Theme::Translate('A formular to use as a calculation for formula column types'); ?>"><?php echo Theme::Translate('Formula'); ?></label>
            <div class="controls">
                <input class="" name="formula" type="text" id="formula" tabindex="6" value="<?php echo Theme::Get('formula'); ?>" />
            </div>
        </div>
    </fieldset>
</form>