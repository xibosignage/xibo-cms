<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (HelpServiceInterface.php)
 */


namespace Xibo\Service;
use Stash\Interfaces\PoolInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Interface HelpServiceInterface
 * @package Xibo\Service
 */
interface HelpServiceInterface
{
    /**
     * HelpServiceInterface constructor.
     * @param StorageServiceInterface $store
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     */
    public function __construct($store, $config, $pool);

    /**
     * Get Help Link
     * @param string $topic
     * @param string $category
     * @return string
     */
    public function link($topic = '', $category = "General");

    /**
     * Raw Link
     * @param $link
     * @return string
     */
    public function rawLink($link);
}