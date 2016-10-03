<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TaskTrait.php)
 */


namespace Xibo\XTR;

/**
 * Class TaskTrait
 * @package Xibo\XTR
 */
trait TaskTrait
{
    private $log;
    private $config;
    private $runStatus;
    private $runMessage;

    /** @inheritdoc */
    public function setConfig($config)
    {
        if (property_exists($this, 'defaultConfig'))
            $config = array_merge($this->defaultConfig, $config);

        $this->config = $config;
        return $this;
    }

    /**
     * @param $option
     * @param $default
     * @return mixed
     */
    private function getConfigOption($option, $default)
    {
        return isset($this->config[$option]) ? $this->config[$option] : $default;
    }

    /** @inheritdoc */
    public function setLogger($logger)
    {
        $this->log = $logger;
        return $this;
    }

    /** @inheritdoc */
    public function getRunStatus()
    {
        return $this->runStatus;
    }

    /** @inheritdoc */
    public function getRunMessage()
    {
        return $this->runMessage;
    }
}