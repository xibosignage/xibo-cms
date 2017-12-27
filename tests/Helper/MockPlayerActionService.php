<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (MockPlayerActionService.php)
 */


namespace Xibo\Tests\Helper;


use Xibo\Service\PlayerActionServiceInterface;

/**
 * Class MockPlayerActionService
 * @package Helper
 */
class MockPlayerActionService implements PlayerActionServiceInterface
{
    private $displays = [];

    /**
     * @inheritdoc
     */
    public function __construct($config, $log, $triggerPlayerActions)
    {

    }

    /**
     * @inheritdoc
     */
    public function sendAction($display, $action)
    {
        $this->displays[] = $display->displayId;
    }

    /**
     * @inheritdoc
     */
    public function processQueue()
    {
        return $this->displays;
    }
}