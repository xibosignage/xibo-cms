<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerActionInterface.php)
 */


namespace Xibo\XMR;


interface PlayerActionInterface
{
    public function getMessage();
    public function getEncryptedMessage();
    public function setIdentity($channel, $key);
    public function send($connection);
}