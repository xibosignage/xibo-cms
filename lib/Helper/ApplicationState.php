<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Helper;

/**
 * Class ApplicationState
 * @package Xibo\Helper
 */
class ApplicationState
{
    public $httpStatus = 200;
    public $template;
    public $message;
    public $success;
    public $html;
    public $buttons;
    public $fieldActions;
    public $dialogTitle;
    public $callBack;
    public $autoSubmit;

    public $login;
    public $clockUpdate;

    public $id;
    private $data;
    public $extra;
    public $recordsTotal;
    public $recordsFiltered;
    private $commit = true;

    public function __construct()
    {
        // Assume success
        $this->success = true;
        $this->buttons = '';
        $this->fieldActions = '';
        $this->extra = array();
    }

    /**
     * Sets the Default response if for a login box
     */
    public static function asRequiresLogin()
    {
        return [
            'login' => true,
            'success' => false
        ];
    }

    /**
     * Add a Field Action to a Field
     * @param string $field The field name
     * @param string $action The action name
     * @param string $value The value to trigger on
     * @param string $actions The actions (field => action)
     * @param string $operation The Operation (optional)
     */
    public function addFieldAction($field, $action, $value, $actions, $operation = "equals")
    {
        $this->fieldActions[] = array(
            'field' => $field,
            'trigger' => $action,
            'value' => $value,
            'operation' => $operation,
            'actions' => $actions
        );
    }

    /**
     * Response JSON
     * @return array
     */
    public function asArray()
    {
        // Construct the Response
        $response = array();

        // General
        $response['html'] = $this->html;
        $response['buttons'] = $this->buttons;
        $response['fieldActions'] = $this->fieldActions;
        $response['dialogTitle'] = $this->dialogTitle;
        $response['callBack'] = $this->callBack;
        $response['autoSubmit'] = $this->autoSubmit;

        $response['success'] = $this->success;
        $response['message'] = $this->message;
        $response['clockUpdate'] = $this->clockUpdate;

        // Login
        $response['login'] = $this->login;

        // Extra
        $response['id'] = intval($this->id);
        $response['extra'] = $this->extra;
        $response['data'] = $this->data;

        return $response;
    }

    /**
     * @return false|string
     */
    public function asJson()
    {
        return json_encode($this->asArray());
    }

    /**
     * Set Data
     * @param array $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Get Data
     * @return array|mixed
     */
    public function getData()
    {
        if ($this->data == null) {
            $this->data = [];
        }
        
        return $this->data;
    }

    /**
     * Hydrate with properties
     *
     * @param array $properties
     *
     * @return self
     */
    public function hydrate(array $properties)
    {
        foreach ($properties as $prop => $val) {
            if (property_exists($this, $prop)) {
                $this->{$prop} = $val;
            }
        }

        return $this;
    }

    /**
     * Called in the Storage Middleware to determine whether or not we should commit this transaction.
     * @return bool
     */
    public function getCommitState()
    {
        return $this->commit;
    }

    /**
     * Set the commit state
     * @param bool $state
     * @return bool
     */
    public function setCommitState(bool $state)
    {
        return $this->commit = $state;
    }
}
