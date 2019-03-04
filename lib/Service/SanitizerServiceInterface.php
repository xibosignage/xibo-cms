<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (SanitizerInterface.php)
 */


namespace Xibo\Service;


use Slim\Http\Request;

/**
 * Interface SanitizerServiceInterface
 * @package Xibo\Service
 */
interface SanitizerServiceInterface
{
    /**
     * SanitizerServiceInterface constructor.
     * @param DateServiceInterface $date
     */
    public function __construct($date);

    /**
     * Set Request
     * @param Request $request
     */
    public function setRequest($request);

    /**
     * Get Param
     * @param $param
     * @param $default
     * @param null $source
     * @param bool $emptyIsNull Should empty values be treated as NULL
     * @return mixed
     */
    public function getParam($param, $default, $source = null, $emptyIsNull = true);

    /**
     * Has Param
     * @param $param
     * @param null $source
     * @return mixed
     */
    public function hasParam($param, $source = null);

    /**
     * Get Int
     * @param $param
     * @param null $default
     * @param null $source
     * @return mixed
     */
    public function getInt($param, $default = null, $source = null);

    /**
     * Sanitize Int
     * @param $param
     * @return mixed
     */
    public function int($param);

    /**
     * Get Double
     * @param $param
     * @param null $default
     * @param null $source
     * @return mixed
     */
    public function getDouble($param, $default = null, $source = null);

    /**
     * Sanitize Double
     * @param $param
     * @return mixed
     */
    public function double($param);

    /**
     * Get String
     * @param $param
     * @param null $default
     * @param null $source
     * @return mixed
     */
    public function getString($param, $default = null, $source = null);

    /**
     * Sanitize String
     * @param $param
     * @return mixed
     */
    public function string($param);

    /**
     * Get UserName
     * @param $param
     * @param null $default
     * @param null $source
     * @return mixed
     */
    public function getUserName($param, $default = null, $source = null);

    /**
     * Get Passowrd
     * @param $param
     * @param null $default
     * @param null $source
     * @return mixed
     */
    public function getPassword($param, $default = null, $source = null);

    /**
     * Get Checkbox
     * @param $param
     * @param null $default
     * @param null $source
     * @return mixed
     */
    public function getCheckbox($param, $default = null, $source = null);

    /**
     * Sanitize Checkbox
     * @param $param
     * @return mixed
     */
    public function checkbox($param);

    /**
     * Sanitize Bool
     * @param $param
     * @return mixed
     */
    public function bool($param);

    /**
     * Sanitize HTML String
     * @param $param
     * @return mixed
     */
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
     * @return \Jenssegers\Date\Date
     */
    public function getDate($param, $default = null, $source = null);
}