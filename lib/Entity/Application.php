<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Application.php)
 */


namespace Xibo\Entity;

/**
 * Class Application
 * @package Xibo\Entity
 *
 * @SWG\Definition
 */
class Application implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="Application Key"
     * )
     * @var string
     */
    public $key;

    /**
     * @SWG\Property(
     *  description="Private Secret Key"
     * )
     * @var string
     */
    public $secret;

    /**
     * @SWG\Property(
     *  description="Application Name"
     * )
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(
     *  description="Application Session Expiry"
     * )
     * @var int
     */
    public $expires;
}