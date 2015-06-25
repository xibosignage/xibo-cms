<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (NullSession.php)
 */


namespace Xibo\Helper;


class NullSession
{
    function setUser($key, $userid)
    {
        $_SESSION['userid'] = $userid;

        try {
            $dbh = PDOConnect::init();

            // Delete sessions older than 10 times the max lifetime
            $sth = $dbh->prepare('UPDATE `session` SET userid = :userid WHERE session_id = :session_id');
            $sth->execute(array('session_id' => $key, 'userid' => $userid));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    /**
     * Updates the session ID with a new one
     */
    public function regenerateSessionId($oldSessionID)
    {

    }

    function setPage($key, $lastpage)
    {

    }

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

    }

    /**
     * Get the Value from the position denoted by the 2 keys provided
     * @param string $key
     * @param string [Optional] $secondKey
     * @return bool
     */
    public static function get($key, $secondKey = NULL)
    {

    }
}