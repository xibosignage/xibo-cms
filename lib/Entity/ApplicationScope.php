<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (ApplicationScope.php)
 */


namespace Xibo\Entity;

use Xibo\Exception\AccessDeniedException;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ApplicationScope
 * @package Xibo\Entity
 */
class ApplicationScope implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var
     */
    public $id;

    /**
     * @var
     */
    public $description;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * Get Id
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Check whether this scope has permission for this route
     * @param $method
     * @param $route
     */
    public function checkRoute($method, $route)
    {
        $route = $this->getStore()->select('
            SELECT *
              FROM `oauth_scope_routes`
             WHERE scopeId = :scope
              AND method = :method
              AND route = :route
        ', [
            'scope' => $this->getId(),
            'method' => $method,
            'route' => $route
        ]);

        if (count($route) <= 0)
            throw new AccessDeniedException(__('Access to this route is denied for this scope'));
    }
}