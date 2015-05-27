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
 *
 * Theme variables:
 *  table_cols = Array containing the table columns
 *  table_rows = Array containing the table rows
 *    buttons = The buttons enabled for the layout
 *      id = The ID of the button
 *      text = The Text for the button
 *      url = The URL of the button
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

// Row class defined?
$rowClass = (Theme::Get('rowClass') != '') ? Theme::Get('rowClass') : '';
// Any buttons multi-select?
$hasButtons = false;
$multiSelect = false;
$multiSelectButtons = array();
foreach(Theme::Get('table_rows') as $row) {
    if (isset($row['buttons']) && is_array($row['buttons']) && count($row['buttons'] > 0)) {
        $hasButtons = true;
        foreach($row['buttons'] as $button) {
            if (isset($button['multi-select']) && $button['multi-select']) {
                $multiSelect = true;
                if (!array_key_exists($button['id'], $multiSelectButtons)) {
                    $multiSelectButtons[$button['id']] = $button;
                }
            }
        }
    }
}
?>
<table class="table">
    <thead>
        <tr>
            <?php if ($multiSelect) { ?><th data-sorter="false" class="group-false"><input class="selectAllCheckbox" type="checkbox"></th><?php } ?>
            <?php foreach(Theme::Get('table_cols') as $col) { 
                if (isset($col['hidden']) && $col['hidden'])
                    continue;
            ?>
            <th<?php echo ' class="' . ((isset($col['colClass']) && $col['colClass'] != '') ? $col['colClass'] : 'group-false') . '"'; ?><?php if (isset($col['helpText']) && $col['helpText'] != '') { echo ' title="' . $col['helpText'] . '"'; } ?><?php if (isset($col['icons']) && $col['icons']) { ?> data-sorter="tickcross"<?php } else if (isset($col['sorter']) && $col['sorter'] != '') { ?> data-sorter="<?php echo $col['sorter'] ?>"<?php } ?>><?php echo $col['title']; ?></th>
            <?php } ?>
            <?php if ($hasButtons) { ?>
            <th data-sorter="false"></th>
            <?php } ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach(Theme::Get('table_rows') as $row) { ?>
        <tr<?php if ($rowClass != '') { echo ' class="' . $row[$rowClass] . '"';} ?> <?php if (isset($row['dataAttributes']) && is_array($row['dataAttributes'])) {
                foreach ($row['dataAttributes'] as $attribute) { 
                    echo 'data-' . $attribute['name'] . '="' . $attribute['value'] . '"'; 
                }
            } ?>>
            <?php if ($multiSelect) { ?><td><input type="checkbox"></td><?php } ?>
            <?php foreach(Theme::Get('table_cols') as $col) { 
                if (isset($col['hidden']) && $col['hidden'])
                    continue;
            ?>
            <?php if (isset($col['icons']) && $col['icons']) { ?>
            <td><span <?php if (isset($col['iconDescription']) && isset($row[$col['iconDescription']]) && $row[$col['iconDescription']] != '') echo 'title="' . $row[$col['iconDescription']] . '"'; ?> class="<?php echo ($row[$col['name']] == 1) ? 'glyphicon glyphicon-ok' : (($row[$col['name']] == 0) ? 'glyphicon glyphicon-remove' : 'glyphicon glyphicon-exclamation-sign'); ?>"></span></td>
            <?php } else if (isset($col['array']) && $col['array'] && is_array($row[$col['name']])) { ?>
            <td>
                <a class="arrayViewerToggle" href="#"><span class="fa fa-search"></span></a>
                <table class="arrayViewer">
                    <?php foreach ($row[$col['name']] as $key => $item) { ?>
                    <tr>
                        <td><?php echo $key; ?></td>
                        <td><?php echo $item; ?></td>
                    </tr>
                    <?php } ?>
                </table>
            </td>
            <?php } else { ?>
            <td><?php echo $row[$col['name']]; ?></td>
            <?php } ?>
            <?php } ?>
            <?php if (isset($row['buttons']) && is_array($row['buttons']) && count($row['buttons'] > 0)) { ?>
            <td>
                <div class="btn-group pull-right">
                    <button class="btn dropdown-toggle" data-toggle="dropdown">
                        <span class="fa fa-caret-down"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach($row['buttons'] as $button) {
                            if (isset($button['linkType']) && $button['linkType'] == 'divider') { ?>
                                <li class="divider"></li>
                            <?php } else if (isset($button['linkType']) && $button['linkType'] != '') { ?>
                                <li class="<?php echo $button['id']; ?>"><a tabindex="-1" target="<?php echo $button['linkType']; ?>" href="<?php echo $button['url']; ?>"><?php echo $button['text']; ?></a></li>
                            <?php } else { ?>
                                <li <?php if (isset($button['dataAttributes']) && is_array($button['dataAttributes'])) {
                                    foreach ($button['dataAttributes'] as $attribute) { 
                                        echo 'data-' . $attribute['name'] . '="' . $attribute['value'] . '"'; 
                                    }
                                } ?>class="<?php echo (isset($button['class']) ? $button['class'] : 'XiboFormButton'); ?> <?php echo $button['id']; ?>" href="<?php echo $button['url']; ?>"><a tabindex="-1" href="#"><?php echo $button['text']; ?></a></li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
                </div>
            </td>
            <?php } else if (isset($row['assign_icons']) && is_array($row['assign_icons']) && count($row['assign_icons'] > 0)) { 
                    foreach ($row['assign_icons'] as $icon) { ?>
            <td><span class="<?php echo $icon['assign_icons_class']; ?> glyphicon glyphicon-plus-sign"></span></td>
                <?php } ?>
            <?php } ?>
        </tr>
        <?php } ?>
    </tbody>
</table>
<?php
if ($multiSelect) {
    $token = Kit::Token('gridToken', false);
    ?>
    <div class="btn-group">
        <button class="btn dropdown-toggle" data-toggle="dropdown">
            <span class="fa fa-long-arrow-up"></span> 
            <?php echo Theme::Translate('With Selected'); ?>
        </button>
        <ul class="dropdown-menu">
    <?php
    // Get the buttons that are multi-select
    foreach ($multiSelectButtons as $key => $button) {
        ?>
            <li class="XiboMultiSelectFormButton" data-button-id="<?php echo $key; ?>" data-grid-id="<?php echo Theme::Get('gridId'); ?>" data-grid-token="<?php echo $token; ?>"><a tabindex="-1" href="#"><?php echo $button['text']; ?></a></li>
        <?php
    }
    ?>
        </ul>
    </div>
    <?php
}
?>
