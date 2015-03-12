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
    
class FormManager {

    public static function AddMessage($message, $groupClass = '') {
        return array(
            'name' => NULL,
            'title' => NULL,
            'value' => NULL,
            'helpText' => $message,
            'fieldType' => 'message',
            'options' => NULL,
            'validation' => NULL,
            'accesskey' => NULL,
            'groupClass' => $groupClass,
            'enabled' => true
        );
    }

    public static function AddRaw($raw) {
        return array(
            'name' => NULL,
            'title' => NULL,
            'value' => NULL,
            'helpText' => $raw,
            'fieldType' => 'raw',
            'options' => NULL,
            'validation' => NULL,
            'accesskey' => NULL,
            'groupClass' => '',
            'enabled' => true
        );
    }

    public static function AddHidden($name, $value) {
        return array(
            'name' => $name,
            'value' => $value,
            'fieldType' => 'hidden',
            'enabled' => true
        );
    }

    public static function AddButton($title, $type = 'submit', $link = '', $groupClass = '') {
        return array(
            'title' => $title,
            'type' => $type,
            'link' => $link,
            'groupClass' => $groupClass,
            'fieldType' => 'button',
            'enabled' => true
        );
    }

    public static function AddText($name, $title, $value, $helpText, $accessKey, $validation = '', $groupClass = '', $enabled = true) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'text',
            'options' => NULL,
            'validation' => $validation,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled
        );
    }

    public static function AddMultiText($name, $title, $value, $helpText, $accessKey, $rows, $validation = '', $groupClass = '', $enabled = true) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'textarea',
            'options' => NULL,
            'validation' => $validation,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled,
            'rows' => $rows
        );
    }

    public static function AddNumber($name, $title, $value, $helpText, $accessKey, $validation = '', $groupClass = '', $enabled = true) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'number',
            'options' => NULL,
            'validation' => $validation,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled
        );
    }

    public static function AddEmail($name, $title, $value, $helpText, $accessKey, $validation = '', $groupClass = '', $enabled = true) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'email',
            'options' => NULL,
            'validation' => $validation,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled
        );
    }

    public static function AddCheckbox($name, $title, $value, $helpText, $accessKey, $groupClass = '', $enabled = true) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'checkbox',
            'options' => NULL,
            'validation' => NULL,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled
        );
    }

    public static function AddRadio($name, $id, $title, $value, $setValue, $helpText, $accessKey, $groupClass = '', $enabled = true) {
        return array(
            'id' => $id,
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'setValue' => $setValue,
            'helpText' => $helpText,
            'fieldType' => 'radio',
            'options' => NULL,
            'validation' => NULL,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled
        );
    }

    public static function AddPassword($name, $title, $value, $helpText, $accessKey, $groupClass = '', $enabled = true) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'password',
            'options' => NULL,
            'validation' => NULL,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled
        );
    }

    public static function AddCombo($name, $title, $value, $options, $optionId, $optionValue, $helpText, $accessKey, $groupClass = '', $enabled = true, $callBack = '', $classColumn = '', $styleColumn = '', $optionGroups = '', $attributes = array()) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'dropdown',
            'options' => $options,
            'optionId' => $optionId,
            'optionValue' => $optionValue,
            'validation' => NULL,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled,
            'callBack' => $callBack,
            'classColumn' => $classColumn,
            'styleColumn' => $styleColumn,
            'optionGroups' => $optionGroups,
            'dataAttributes' => $attributes
        );
    }

    public static function AddMultiCombo($name, $title, $value, $options, $optionId, $optionValue, $helpText, $accessKey, $groupClass = '', $enabled = true, $callBack = '', $classColumn = '', $styleColumn = '', $optionGroups = '', $attributes = array()) {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'dropdownmulti',
            'options' => $options,
            'optionId' => $optionId,
            'optionValue' => $optionValue,
            'validation' => NULL,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass,
            'enabled' => $enabled,
            'callBack' => $callBack,
            'classColumn' => $classColumn,
            'styleColumn' => $styleColumn,
            'optionGroups' => $optionGroups,
            'dataAttributes' => $attributes
        );
    }

    public static function AddPermissions($name, $options) {
        return array(
            'name' => $name,
            'fieldType' => 'permissions',
            'options' => $options,
            'groupClass' => NULL,
            'enabled' => true
        );
    }

    public static function AddTab($id, $name, $dataAttributes = array()) {
        return array(
            'id' => $id, 'name' => $name, 'dataAttributes' => $dataAttributes
        );
    }

    public static function AddDatePicker($name, $title, $value, $helpText, $accessKey, $validation = '', $groupClass = '') {
        return array(
            'name' => $name,
            'title' => $title,
            'value' => $value,
            'helpText' => $helpText,
            'fieldType' => 'datePicker',
            'options' => NULL,
            'validation' => $validation,
            'accesskey' => $accessKey,
            'groupClass' => $groupClass
        );
    }
}
?>
