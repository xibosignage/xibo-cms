<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (NullSession.php)
 */


namespace Xibo\Helper;


class NullSession
{
    /**
     * Set UserId
     * @param $userId
     */
    function setUser($userId)
    {
        $_SESSION['userid'] = $userId;
    }

    /**
     * Updates the session ID with a new one
     */
    public function regenerateSessionId()
    {

    }

    /**
     * Set Expired
     * @param $isExpired
     */
    function setIsExpired($isExpired)
    {

    }

    /**
     * Store a variable in the session
     * @param string $key
     * @param mixed $secondKey
     * @param mixed|null $value
     * @return mixed
     */
    public static function set($key, $secondKey, $value = null)
    {
        if (func_num_args() == 2) {
            return $secondKey;
        } else {
            return $value;
        }
    }

    /**
     * Get the Value from the position denoted by the 2 keys provided
     * @param string $key
     * @param string [Optional] $secondKey
     * @return bool
     */
    public static function get($key, $secondKey = NULL)
    {
        return false;
    }
}