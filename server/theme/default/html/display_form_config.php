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
            <div class="span12">
                <?php foreach(Theme::Get('form_fields') as $field) {

                    if ($field['fieldType'] == 'text') {
                        ?>
                        <div class="control-group">
                            <label class="control-label" for="<?php echo $field['name']; ?>" title="<?php echo $field['helpText']; ?>"><?php echo $field['title']; ?></label>
                            <div class="controls">
                                <input class="required" name="<?php echo $field['name']; ?>" type="text" id="<?php echo $field['name']; ?>" value="<?php echo $field['value']; ?>" />
                            </div>
                        </div>
                        <?php
                    }

                } ?>
            </div>
        </div>
    </form>
</div>