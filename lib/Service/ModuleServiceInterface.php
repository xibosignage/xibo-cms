<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ModuleServiceInterface.php)
 */


namespace Xibo\Service;


use Slim\Slim;
use Xibo\Entity\Module;
use Xibo\Widget\ModuleWidget;

/**
 * Interface ModuleServiceInterface
 * @package Xibo\Service
 */
interface ModuleServiceInterface
{
    /**
     * ModuleServiceInterface constructor.
     * @param Slim $app
     */
    public function __construct($app);

    /**
     * @param Module $module
     * @return ModuleWidget
     */
    public function get($module);
}