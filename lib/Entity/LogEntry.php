<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Log.php)
 */


namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class LogEntry
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class LogEntry
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Log ID")
     * @var int
     */
    public $logId;

    /**
     * @SWG\Property(description="A unique run number for a set of Log Messages.")
     * @var string
     */
    public $runNo;

    /**
     * @SWG\Property(description="A timestamp representing the CMS date this log message occured")
     * @var int
     */
    public $logDate;

    /**
     * @SWG\Property(description="The Channel that generated this message. WEB/API/MAINT/TEST")
     * @var string
     */
    public $channel;

    /**
     * @SWG\Property(description="The requested route")
     * @var string
     */
    public $page;

    /**
     * @SWG\Property(description="The request method, GET/POST/PUT/DELETE")
     * @var string
     */
    public $function;

    /**
     * @SWG\Property(description="The log message")
     * @var string
     */
    public $message;

    /**
     * @SWG\Property(description="The display ID this message relates to or NULL for CMS")
     * @var int
     */
    public $displayId;

    /**
     * @SWG\Property(description="The Log Level")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The display this message relates to or CMS for CMS.")
     * @var string
     */
    public $display;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }
}