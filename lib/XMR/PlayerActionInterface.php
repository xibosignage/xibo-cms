<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerActionInterface.php)
 */


namespace Xibo\XMR;

/**
 * Interface PlayerActionInterface
 * @package Xibo\XMR
 */
interface PlayerActionInterface
{
    /**
     * Get the Message
     * @return mixed
     */
    public function getMessage();

    /**
     * Get Encrypted Message
     * @return mixed
     */
    public function getEncryptedMessage();

    /**
     * Set Display Identity for the Action
     * @param string $channel
     * @param string $key
     * @return mixed
     */
    public function setIdentity($channel, $key);

    /**
     * Send the message
     * @param $connection
     * @return mixed
     */
    public function send($connection);
}