<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (IconDashboard.php)
 */


namespace Xibo\Controller;

use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class IconDashboard
 * @package Xibo\Controller
 */
class IconDashboard extends Base
{
    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
    }

    public function displayPage()
    {
        $this->getState()->template = 'dashboard-icon-page';
    }
}