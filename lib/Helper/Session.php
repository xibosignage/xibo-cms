<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Helper;

use Xibo\Storage\PDOConnect;

class Session
{
    private $max_lifetime;
    private $key;

    /**
     * Refresh expiry
     * @var bool
     */
    public $refreshExpiry = true;

    /**
     * Is the session expired?
     * @var int
     */
    public $isExpired = 1;

    function __construct()
    {
        session_set_save_handler(
            array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc')
        );

        register_shutdown_function('session_write_close');

        // Start the session
        session_cache_limiter(false);
        session_start();
    }

    function open($save_path, $session_name)
    {
        $this->max_lifetime = ini_get('session.gc_maxlifetime');
        return true;
    }

    function close()
    {

        $this->gc($this->max_lifetime);
        return true;
    }

    function read($key)
    {
        $userAgent = substr(Sanitize::string($_SERVER['HTTP_USER_AGENT']), 0, 253);
        $remoteAddr = Sanitize::string($_SERVER['REMOTE_ADDR']);
        $securityToken = Sanitize::getString('SecurityToken');

        $this->key = $key;
        $newExp = time() + $this->max_lifetime;

        $this->gc($this->max_lifetime);

        try {
            $dbh = PDOConnect::init();

            // Get this session
            $sth = $dbh->prepare('SELECT session_data, isexpired, securitytoken, useragent FROM session WHERE session_id = :session_id');
            $sth->execute(array('session_id' => $key));

            if (!$row = $sth->fetch())
                return settype($empty, "string");

            // What happens if the UserAgent has changed?
            if ($row['useragent'] != $userAgent) {
                // Make sure we are logged out (delete all data)
                $usth = $dbh->prepare('DELETE FROM session WHERE session_id = :session_id');
                $usth->execute(array('session_id' => $key));

                throw new \Exception('Different UserAgent');
            }

            // We have the Key and the Remote Address.
            if ($securityToken == null) {
                // If there is no security token then obey the IsExpired
                $this->isExpired = $row['isexpired'];
            } elseif ($securityToken == $row['securitytoken']) {
                // We have a security token, so dont require a login
                $this->isExpired = 0;

                $usth = $dbh->prepare('UPDATE session SET session_expiration = :expiry, isExpired = 0 WHERE session_id = :session_id');
                $usth->execute(array('session_id' => $key, 'expiry' => $newExp));
            } else {
                // Its set - but its wrong - not good
                Log::error('Incorrect SecurityToken from ' . $remoteAddr);

                $this->isExpired = 1;
            }

            // Either way - update this SESSION so that the security token is NULL
            $usth = $dbh->prepare('UPDATE `session` SET SecurityToken = NULL WHERE session_id = :session_id');
            $usth->execute(array('session_id' => $key));

            return ($row['session_data']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $empty = '';
            return settype($empty, "string");
        }
    }

    function write($key, $val)
    {
        $newExp = time() + $this->max_lifetime;
        $lastaccessed = date("Y-m-d H:i:s");

        $userAgent = substr(Sanitize::getString('HTTP_USER_AGENT', 'No user agent', $_SERVER), 0, 253);
        $remoteAddr = Sanitize::getString('REMOTE_ADDR');

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('SELECT session_id FROM session WHERE session_id = :session_id');
            $sth->execute(array('session_id' => $key));

            if (!$row = $sth->fetch()) {
                // Insert a new session
                $SQL = 'INSERT INTO `session` (session_id, session_data, session_expiration, lastaccessed, lastpage, userid, isexpired, useragent, remoteaddr)
                          VALUES (:session_id, :session_data, :session_expiration, :lastaccessed, :lastpage, :userid, :isexpired, :useragent, :remoteaddr) ';

                $isth = $dbh->prepare($SQL);

                $isth->execute(
                    array(
                        'session_id' => $key,
                        'session_data' => $val,
                        'session_expiration' => $newExp,
                        'lastaccessed' => $lastaccessed,
                        'lastpage' => 'login',
                        'userid' => NULL,
                        'isexpired' => 0,
                        'useragent' => $userAgent,
                        'remoteaddr' => $remoteAddr
                    )
                );
            } else {
                // Punch a very small hole in the authentication system
                // we do not want to update the expiry time of a session if it is the Clock Timer going off
                $autoRefresh = (isset($_REQUEST['autoRefresh']) && Sanitize::bool($_REQUEST['autoRefresh']));

                if (!$this->refreshExpiry || $autoRefresh) {

                    // Update the existing session without the expiry
                    $SQL = "UPDATE session SET session_data = :session_data WHERE session_id = :session_id ";

                    $isth = $dbh->prepare($SQL);

                    $isth->execute(
                        array('session_id' => $key, 'session_data' => $val)
                    );
                } else {
                    // Update the existing session
                    $SQL = "UPDATE `session` SET
                         	session_data = :session_data,
                         	session_expiration = :session_expiration,
                         	lastaccessed 	= :lastaccessed,
                         	remoteaddr 	= :remoteaddr
                         WHERE session_id = :session_id ";

                    $isth = $dbh->prepare($SQL);

                    $isth->execute(
                        array(
                            'session_id' => $key,
                            'session_data' => $val,
                            'session_expiration' => $newExp,
                            'lastaccessed' => $lastaccessed,
                            'remoteaddr' => $remoteAddr
                        )
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }

        return true;
    }

    function destroy($key)
    {
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('UPDATE session SET IsExpired = 1 WHERE session_id = :session_id');
            $sth->execute(array('session_id', $key));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return true;
    }

    function gc($max_lifetime)
    {
        try {
            $dbh = PDOConnect::init();

            // Delete sessions older than 10 times the max lifetime
            $sth = $dbh->prepare('DELETE FROM `session` WHERE IsExpired = 1 AND session_expiration < :expiration');
            $sth->execute(array('expiration' => (time() - ($max_lifetime * 10))));

            // Update expired sessions as expired
            $sth = $dbh->prepare('UPDATE `session` SET IsExpired = 1 WHERE session_expiration < :expiration');
            $sth->execute(array('expiration' => time()));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

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

        session_regenerate_id(false);

        $new_sess_id = session_id();

        $this->key = $new_sess_id;

        try {
            $dbh = PDOConnect::init();

            // Delete sessions older than 10 times the max lifetime
            $sth = $dbh->prepare('UPDATE session SET session_id = :new_session_id WHERE session_id = :session_id');
            $sth->execute(array('session_id' => $oldSessionID, 'new_session_id' => $new_sess_id));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    function setPage($key, $lastpage)
    {
        $_SESSION['pagename'] = $lastpage;

        try {
            $dbh = PDOConnect::init();

            // Delete sessions older than 10 times the max lifetime
            $sth = $dbh->prepare('UPDATE session SET lastpage = :lastpage WHERE session_id = :session_id');
            $sth->execute(array('session_id' => $key, 'lastpage' => $lastpage));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    function setIsExpired($isExpired)
    {
        $this->isExpired = $isExpired;

        try {
            $dbh = PDOConnect::init();

            // Delete sessions older than 10 times the max lifetime
            $sth = $dbh->prepare('UPDATE session SET isexpired = :isexpired WHERE session_id = :session_id');
            $sth->execute(array('session_id' => $this->key, 'isexpired' => $isExpired));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
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
            $_SESSION[$key] = $secondKey;
            return $secondKey;
        } else {
            if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key]))
                $_SESSION[$key] = [];

            $_SESSION[$key][(string) $secondKey] = $value;
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
        if ($secondKey != NULL) {
            if (isset($_SESSION[$key][$secondKey]))
                return $_SESSION[$key][$secondKey];
        } else {
            if (isset($_SESSION[$key]))
                return $_SESSION[$key];
        }

        return false;
    }
}

?>