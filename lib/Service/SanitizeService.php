<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Sanitize.php) is part of Xibo.
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


namespace Xibo\Service;


use Jenssegers\Date\Date;
use Slim\Http\Request;

/**
 * Class SanitizeService
 * @package Xibo\Service
 */
class SanitizeService implements SanitizerServiceInterface
{
    /**
     * @var DateServiceInterface
     */
    private $date;

    /**
     * @var Request
     */
    private $request;

    /**
     * @inheritdoc
     */
    public function __construct($date)
    {
        $this->date = $date;
    }

    /**
     * @inheritdoc
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * Get Date
     * @return DateServiceInterface
     */
    private function getDateService()
    {
        if ($this->date == null)
            throw new \RuntimeException('Sanitizer called before DateService has been set');

        return $this->date;
    }

    /**
     * Get Request
     * @return Request
     */
    private function getRequest()
    {
        if ($this->request == null)
            throw new \RuntimeException('Sanitizer called before Request has been set');

        return $this->request;
    }

    /**
     * @inheritdoc
     */
    public function getParam($param, $default, $source = null, $emptyAsNull = true)
    {
        if (is_array($default)) {
            return isset($default[$param]) ? $default[$param] : null;
        }
        else if ($source === null) {

            switch ($this->getRequest()->getMethod()) {
                case 'GET':
                    $return = $this->getRequest()->get($param, $default);
                    break;
                case 'POST':
                    $return = $this->getRequest()->post($param, $default);
                    break;
                case 'PUT':
                    $return = $this->getRequest()->put($param, $default);
                    break;
                case 'DELETE':
                    $return = $this->getRequest()->delete($param, $default);
                    break;
                default:
                    $return = $default;
            }

            return ($return === null || ($emptyAsNull && $return === '')) ? $default : $return;
        }
        else
            return isset($source[$param]) ? $source[$param] : $default;
    }

    /**
     * @inheritdoc
     */
    public function hasParam($param, $source = null)
    {
        if ($source !== null && is_array($source)) {
            return array_key_exists($param, $source);
        } else {
            return $this->getParam($param, null, null, false) !== null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getInt($param, $default = null, $source = null)
    {
        return $this->int($this->getParam($param, $default, $source));
    }

    /**
     * @inheritdoc
     */
    public function int($param)
    {
        if ($param === null)
            return null;

        return intval(filter_var($param, FILTER_SANITIZE_NUMBER_INT));
    }

    /**
     * @inheritdoc
     */
    public function getDouble($param, $default = null, $source = null)
    {
        return $this->double($this->getParam($param, $default, $source));
    }

    /**
     * @inheritdoc
     */
    public function double($param)
    {
        if ($param === null)
            return null;

        return doubleval(filter_var($param, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    }

    /**
     * @inheritdoc
     */
    public function getString($param, $default = null, $source = null)
    {
        return $this->string($this->getParam($param, $default, $source));
    }

    /**
     * @inheritdoc
     */
    public function string($param)
    {
        if ($param === null)
            return null;

        return filter_var($param, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    }

    /**
     * @inheritdoc
     */
    public function getUserName($param, $default = null, $source = null)
    {
        $param = $this->getParam($param, $default, $source);

        if ($param === null)
            return null;

        $param = filter_var($param, FILTER_SANITIZE_STRING);
        $param = (string) preg_replace( '/[\x00-\x1F\x7F<>"\'%&]/', '', $param);
        return strtolower($param);
    }

    /**
     * @inheritdoc
     */
    public function getPassword($param, $default = null, $source = null)
    {
        return $this->getString($param, $default, $source);
    }

    /**
     * @inheritdoc
     */
    public function getCheckbox($param, $default = null, $source = null)
    {
        $checkbox = $this->getParam($param, $default, $source);
        return $this->checkbox($checkbox);
    }

    /**
     * @inheritdoc
     */
    public function checkbox($param)
    {
        return ($param === 'on' || $param === 1 || $param === '1' || $param === 'true' || $param === true) ? 1 : 0;
    }

    /**
     * @inheritdoc
     */
    public function bool($param)
    {
        return filter_var($param, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @inheritdoc
     */
    public function htmlString($param)
    {
        // decimal notation
        $return = preg_replace_callback('/&#(\d+);/m', function($m){
            return chr($m[1]);
        }, $param);

        // convert hex
        $return = preg_replace_callback('/&#x([a-f0-9]+);/mi', function($m){
            return chr("0x".$m[1]);
        }, $return);

        return (string) $return;
    }

    /**
     * @inheritdoc
     */
    public function getStringArray($param, $default = null, $source = null)
    {
        $array = $this->getParam($param, $default, $source);

        if ($array == null)
            return [];

        return $array;
    }

    /**
     * @inheritdoc
     */
    public function getIntArray($param, $default = null, $source = null)
    {
        $array = $this->getParam($param, $default, $source);

        if ($array == null || !is_array($array))
            return [];

        return array_map('intval', $array);
    }

    /**
     * @inheritdoc
     */
    public function getDate($param, $default = null, $source = null)
    {
        $date = $this->getString($param, $default, $source);

        if ($date === null)
            return null;

        // $date should be a ISO formatted date string.
        try {
            if ($date instanceof Date)
                return $date;

            return $this->getDateService()->parse($date);
        }
        catch (\Exception $e) {
            throw new \InvalidArgumentException(__('Expecting a date in %s but received %s.', $param, $date));
        }
    }
}