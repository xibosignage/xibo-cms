<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Log.php)
 */


namespace Xibo\Entity;


class Log
{
    use EntityTrait;

    public $logId;
    public $runNo;
    public $logDate;
    public $channel;
    public $page;
    public $function;
    public $message;
    public $displayId;
    public $type;

    public $display;
}