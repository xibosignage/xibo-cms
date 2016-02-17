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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Session {
	private $max_lifetime;
	private $key;
	
	public $isExpired = 1;

	/**
	 * Expiry time
	 * @var int
	 */
	private $sessionExpiry = 0;

    /**
     * Security Token
     * @var string
     */
    private $securityToken = null;

    /**
     * Last Page
     * @var string
     */
    private $lastPage = null;

	/**
	 * The UserId whom owns this session
	 * @var int
	 */
	private $userId = 0;

	/**
	 * @var bool Whether gc() has been called
	 */
	private $gcCalled = false;

	/**
	 * Prune this key?
	 * @var bool
	 */
	private $pruneKey = false;

	/**
	 * The database connection
	 * @var \PDO
	 */
	private $pdo = null;

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
		session_start();
	}
	
	function open($save_path, $session_name) 
	{
		$this->max_lifetime = ini_get('session.gc_maxlifetime');
		return true;
	}

	function close() 
	{
        try {
            // Commit
            $this->commit();
        } catch (PDOException $e) {
            Debug::LogEntry('error', 'Error Committing Session' . $e->getMessage());
        }

        try {
            $dbh = $this->getDb();

            // Prune this session if necessary
            if ($this->pruneKey) {
                $sth = $dbh->prepare('DELETE FROM session WHERE session_id = :session_id');
                $sth->execute(array('session_id' => $this->key));
            }

            if ($this->gcCalled) {
                // Delete sessions older than 10 times the max lifetime
                PDOConnect::update('DELETE FROM `session` WHERE IsExpired = 1 AND session_expiration < :expiration', array('expiration' => (time() - ($this->maxLifetime * 10))), $this->getDb());

                // Update expired sessions as expired
                PDOConnect::update('UPDATE `session` SET IsExpired = 1 WHERE session_expiration < :expiration', array('expiration' => time()), $this->getDb());
            }

        } catch (PDOException $e) {
            Debug::LogEntry('error', 'Error Committing Session' . $e->getMessage());
        }

		// Close
		$this->pdo = null;

		return true;
	}

	function read($key) 
	{
		$empty = '';
		$this->key = $key;

		$userAgent	= substr(Kit::GetParam('HTTP_USER_AGENT', $_SERVER, _STRING, 'No user agent'), 0, 253);
		$remoteAddr	= Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING);
		$securityToken	= Kit::GetParam('SecurityToken', _POST, _STRING, null);

		try {
			$dbh = $this->getDb();

			// Start a transaction
			$this->beginTransaction();
		
			// Get this session		
			$sth = $dbh->prepare('SELECT session_data, isexpired, securitytoken, useragent, `session_expiration`, `userId`, `lastPage` FROM `session` WHERE session_id = :session_id');
			$sth->execute(array('session_id' => $key));

			if (!$row = $sth->fetch()) {
				// Key doesn't exist yet
				$this->isExpired = 0;
				return settype($empty, "string");
			}

			// What happens if the UserAgent has changed?
			if ($row['useragent'] != $userAgent) {
				// Make sure we are logged out (delete all data)
				$this->pruneKey = true;
				throw new Exception('Different UserAgent');
			}
			
			// We have the Key and the Remote Address.
			if ($securityToken == null)
			{
                // Check the session hasn't expired
                if ($row['session_expiration'] < time())
                    $this->isExpired = 1;
                else
                    $this->isExpired = $row['isexpired'];
			}
			elseif ($securityToken == $row['securitytoken'])
			{
				// We have a security token, so don't require a login
				$this->isExpired = 0;
			}
			else
			{
				// Its set - but its wrong - not good
				Debug::LogEntry('error', 'Incorrect SecurityToken from ' . $remoteAddr);
				
				$this->isExpired = 1;
			}

            $this->userId = $row['userId'];
            $this->lastPage = $row['lastPage'];
            $this->sessionExpiry = $row['session_expiration'];

			// Either way - update this SESSION so that the security token is NULL
			$this->securityToken = null;

			return($row['session_data']);
		}
		catch (Exception $e) {
			Debug::LogEntry('error', 'Error reading session: ' . $e->getMessage());

			return settype($empty, "string");
		}
	}
	
	function write($key, $val) 
	{
		$newExp = time() + $this->max_lifetime;
		$lastAccessed = date("Y-m-d H:i:s");

		try {
			$dbh = $this->getDb();

            $sql = '
                  INSERT INTO `session` (session_id, session_data, session_expiration, lastaccessed, lastpage, userid, isexpired, useragent, remoteaddr, `securityToken`)
                    VALUES (:session_id, :session_data, :session_expiration, :lastAccessed, :lastpage, :userId, :expired, :useragent, :remoteaddr, :securityToken)
                    ON DUPLICATE KEY UPDATE
                      `session_data` = :session_data2,
                      `userId` = :userId2,
                      `session_expiration` = :session_expiration2,
                      `isExpired` = :expired2,
                      `lastaccessed` = :lastAccessed2,
                      `lastpage` = :lastpage2,
                      `securityToken` = :securityToken
                ';

            $page = Kit::GetParam('p', _REQUEST, _WORD);
            $query = Kit::GetParam('q', _REQUEST, _WORD);
            $autoRefresh = (isset($_REQUEST['autoRefresh']) && Kit::GetParam('autoRefresh', _REQUEST, _WORD, 'false') == 'true');

            $refreshExpiry = ($autoRefresh || ($page == 'clock' && $query == 'GetClock') || ($page == 'index' && $query == 'PingPong') || ($page == 'layout' && $query == 'LayoutStatus'));

            $params = [
                'session_id' => $key,
                'session_data' => $val,
                'session_data2' => $val,
                'session_expiration' => $newExp,
                'session_expiration2' => ($refreshExpiry) ? $newExp : $this->sessionExpiry,
                'lastAccessed' => $lastAccessed,
                'lastAccessed2' => $lastAccessed,
                'lastpage' => $this->lastPage,
                'lastpage2' => $this->lastPage,
                'securityToken' => $this->securityToken,
                'securityToken2' => $this->securityToken,
                'userId' => $this->userId,
                'userId2' => $this->userId,
                'expired' => $this->isExpired,
                'expired2' => $this->isExpired,
                'useragent' => substr(Kit::GetParam('HTTP_USER_AGENT', $_SERVER, _STRING, 'No user agent'), 0, 253),
                'remoteaddr' => Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING)
            ];
			
			$sth = $dbh->prepare($sql);
			$sth->execute($params);
		}
		catch (Exception $e) {
			Debug::LogEntry('error', 'Error writing session: ' . $e->getMessage());
			return false;
		}
		
		return true;
	}

	function destroy($key) 
	{
		try {
			$dbh = $this->getDb();

			$sth = $dbh->prepare('DELETE FROM `session` WHERE session_id = :session_id');
			$sth->execute(array('session_id', $key));
		}
		catch (Exception $e) {
			Debug::LogEntry('error', 'Session Destroy' . $e->getMessage());
		}

		return true;
	}

	function gc($max_lifetime)
	{
        $this->gcCalled = true;
        return true;
	}
	
	function set_user($key, $userid) 
	{
        $this->userId = $userid;
	}
	
	/**
	 * Updates the session ID with a new one
	 * @return 
	 */
	public function RegenerateSessionID($oldSessionID) {

        session_regenerate_id(false);

        $new_sess_id = session_id();

        $this->key = $new_sess_id;

        try {
			$dbh = $this->getDb();

			// Delete sessions older than 10 times the max lifetime
			$sth = $dbh->prepare('UPDATE `session` SET session_id = :new_session_id WHERE session_id = :session_id');
			$sth->execute(array('session_id' => $oldSessionID, 'new_session_id' => $new_sess_id));
		}
		catch (Exception $e) {
			Debug::LogEntry('error', 'Session Regenerate' . $e->getMessage());
			return false;
		}
    }
	
	function set_page($key, $lastpage) {
		$_SESSION['pagename'] = $lastpage;
        $this->lastPage = $lastpage;
	}
	
	function setIsExpired($isExpired) {
		$this->isExpired = $isExpired;
	}
	
	public function setSecurityToken($token)
	{
        $this->securityToken = $token;
	}
	
	public static function Set($key, $value)
	{
		$_SESSION[$key] = $value;
	}
        
    /**
     * Get the Value from the position denoted by the 2 keys provided
     * @param type $key
     * @param type $secondKey
     * @return boolean
     */
    public static function Get($key, $secondKey = NULL)
    {
    	if ($secondKey != NULL) {
        	if (isset($_SESSION[$key][$secondKey]))
	            return $_SESSION[$key][$secondKey];
	    }
	    else {
	    	if (isset($_SESSION[$key]))
	            return $_SESSION[$key];	
	    }
        
        return false;
    }

	/**
	 * Get a Database
	 * @return \PDO
	 */
	private function getDb()
	{
		if ($this->pdo == null)
			$this->pdo = PDOConnect::newConnection();

		return $this->pdo;
	}

	/**
	 * Helper method to begin a transaction.
	 *
	 * MySQLs default isolation, REPEATABLE READ, causes deadlock for different sessions
	 * due to http://www.mysqlperformanceblog.com/2013/12/12/one-more-innodb-gap-lock-to-avoid/ .
	 * So we change it to READ COMMITTED.
	 */
	private function beginTransaction()
	{
		if (!$this->getDb()->inTransaction()) {
			$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
			$this->pdo->beginTransaction();
		}
	}

	/**
	 * Commit
	 */
	private function commit()
	{
		if ($this->getDb()->inTransaction())
			$this->getDb()->commit();
	}
}
