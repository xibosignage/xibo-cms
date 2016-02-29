<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (SanitizerInterface.php)
 */


namespace Xibo\Helper;


interface SanitizerInterface
{
    public function __construct($app);

    public function getParam($param, $default, $source = null);

    public function getInt($param, $default = null, $source = null);

    public function int($param);

    public function getDouble($param, $default = null, $source = null);

    public function double($param);

    public function getString($param, $default = null, $source = null);

    public function string($param);

    public function getUserName($param, $default = null, $source = null);

    public function getPassword($param, $default = null, $source = null);

    public function getCheckbox($param, $default = null, $source = null);

    public function checkbox($param);

    public function bool($param);

    public function htmlString($param);

    /**
     * Get an array of ints
     * @param string $param
     * @param mixed[Optional] $default
     * @param mixed[Optional] $source
     * @return array[mixed]|null
     */
    public function getStringArray($param, $default = null, $source = null);

    /**
     * Get an array of ints
     * @param string $param
     * @param mixed[Optional] $default
     * @param mixed[Optional] $source
     * @return array[mixed]|null
     */
    public function getIntArray($param, $default = null, $source = null);

    /**
     * Get a date from input.
     * @param $param
     * @param mixed[Optional] $default
     * @param mixed[Optional] $source
     * @return Date
     */
    public function getDate($param, $default = null, $source = null);
}