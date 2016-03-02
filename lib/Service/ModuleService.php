<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ModuleService.php)
 */


namespace Xibo\Service;


use Xibo\Exception\NotFoundException;

/**
 * Class ModuleService
 * @package Xibo\Service
 */
class ModuleService implements ModuleServiceInterface
{
    public $app;

    /**
     * @inheritdoc
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @inheritdoc
     */
    public function get($module)
    {
        $className = $module->class;

        if (!\class_exists($className))
            throw new NotFoundException(__('Class %s not found', $className));

        /* @var \Xibo\Widget\ModuleWidget $object */
        $object = new $className();
        $object->setApp($this->app);
        $object->setModule($module);

        return $object;
    }
}