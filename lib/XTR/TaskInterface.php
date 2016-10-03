<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TaskInterface.php)
 */


namespace Xibo\XTR;
use Xibo\Service\LogServiceInterface;

/**
 * Interface TaskInterface
 * @package Xibo\XTR
 */
interface TaskInterface
{
    /**
     * Set the task config options
     * @param array $config
     * @return $this
     */
    public function setConfig($config);

    /**
     * @param LogServiceInterface $logger
     * @return $this
     */
    public function setLogger($logger);

    /**
     * @return $this
     */
    public function run();

    /**
     * Get the run status
     * @return int
     */
    public function getRunStatus();

    /**
     * Get the run message
     * @return string
     */
    public function getRunMessage();
}