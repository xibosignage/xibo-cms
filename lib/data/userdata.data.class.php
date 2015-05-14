<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
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
defined('XIBO') or die(__('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.'));

// These constants may be changed without breaking existing hashes.
define("PBKDF2_HASH_ALGORITHM", "sha256");
define("PBKDF2_ITERATIONS", 1000);
define("PBKDF2_SALT_BYTES", 24);
define("PBKDF2_HASH_BYTES", 24);

define("HASH_SECTIONS", 4);
define("HASH_ALGORITHM_INDEX", 0);
define("HASH_ITERATION_INDEX", 1);
define("HASH_SALT_INDEX", 2);
define("HASH_PBKDF2_INDEX", 3);

class Userdata extends Data
{
    public $userId;
    public $userName;
    public $userTypeId;
    public $loggedIn;
    public $email;
    public $homePage;
    public $lastAccessed;
    public $newUserWizard;
    public $retired;

    // Group Specific
    public $groupId;
    public $libraryQuota;

    public static function entries($sortOrder = array(), $filterBy = array())
    {
        $entries = array();

        // Default sort order
        if (count($sortOrder) <= 0)
            $sortOrder = array('username');

        try {
            $dbh = PDOConnect::init();

            $params = array();
            $SQL  = '
              SELECT `user`.userId, userName, userTypeId, loggedIn, email, homePage, lastAccessed, newUserWizard, retired, `userGroups`.groupId, `userGroups`.libraryQuota
                FROM `user`
                  LEFT OUTER JOIN (
                    SELECT `group`.groupId, `group`.libraryQuota, `lkusergroup`.userId
                      FROM `lkusergroup`
                        INNER JOIN `group`
                        ON `group`.groupId = `lkusergroup`.groupId
                          AND `group`.isUserSpecific = 1
                  ) userGroups
                  ON userGroups.userId = `user`.userId
               WHERE 1 = 1
            ';

            // User Id Provided?
            if (Kit::GetParam('userId', $filterBy, _INT) != 0) {
                $SQL .= " AND user.userId = :userId ";
                $params['userId'] = Kit::GetParam('userId', $filterBy, _INT);
            }

            // User Type Provided
            if (Kit::GetParam('userTypeId', $filterBy, _INT) != 0) {
                $SQL .= " AND user.userTypeId = :userTypeId ";
                $params['userTypeId'] = Kit::GetParam('userTypeId', $filterBy, _INT);
            }

            // User Name Provided
            if (Kit::GetParam('userName', $filterBy, _STRING) != '') {
                $SQL .= " AND user.userName LIKE :userName ";
                $params['userName'] = '%' . Kit::GetParam('userName', $filterBy, _STRING) . '%';
            }

            // Groups Provided
            $groups = Kit::GetParam('groupIds', $filterBy, _ARRAY_INT);

            if (count($groups) > 0) {
                $SQL .= " AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupid IN (" . implode($groups, ',') . ")) ";
            }

            // Retired users?
            if (Kit::GetParam('retired', $filterBy, _INT) != -1) {
                $SQL .= " AND user.retired = :retired ";
                $params['retired'] = Kit::GetParam('retired', $filterBy, _INT);
            }

            // Sorting?
            if (is_array($sortOrder))
                $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

            Debug::sql($SQL, $params);
        
            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            foreach ($sth->fetchAll() as $row) {
                $user = new Userdata();
                $user->userId = Kit::ValidateParam($row['userId'], _INT);
                $user->userName = Kit::ValidateParam($row['userName'], _STRING);
                $user->userTypeId = Kit::ValidateParam($row['userTypeId'], _INT);
                $user->loggedIn = Kit::ValidateParam($row['loggedIn'], _INT);
                $user->email = Kit::ValidateParam($row['email'], _STRING);
                $user->homePage = Kit::ValidateParam($row['homePage'], _STRING);
                $user->lastAccessed = Kit::ValidateParam($row['lastAccessed'], _INT);
                $user->newUserWizard = Kit::ValidateParam($row['newUserWizard'], _INT);
                $user->retired = Kit::ValidateParam($row['retired'], _INT);

                $user->groupId = Kit::ValidateParam($row['groupId'], _INT);
                $user->libraryQuota = Kit::ValidateParam($row['libraryQuota'], _INT);

                $entries[] = $user;
            }

            return $entries;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            return false;
        }
    }

    /**
     * Adds a user
     * @param string $password
     * @param int $initialGroupId
     * @return bool
     */
    public function add($password, $initialGroupId)
    {
        // Validation
        if ($this->userName == '' || strlen($this->userName) > 50)
            return $this->SetError(__('User name must be between 1 and 50 characters.'));

        if ($password == '')
            return $this->SetError(__('Please enter a Password.'));

        if ($this->homePage == '')
            $this->homePage = "dashboard";

        // Test the password
        if (!$this->testPasswordAgainstPolicy($password))
            return false;

        try {
            $dbh = PDOConnect::init();

            // Check for duplicate user name
            $sth = $dbh->prepare('SELECT UserName FROM `user` WHERE UserName = :userName');
            $sth->execute(array('userName' => $this->userName));

            $results = $sth->fetchAll();
            if (count($results) > 0)
                $this->ThrowError(__('There is already a user with this name. Please choose another.'));

            // Ready to enter the user into the database
            $password = md5($password);

            // Run the INSERT statement
            $SQL = 'INSERT INTO user (UserName, UserPassword, usertypeid, email, homepage)
                     VALUES (:userName, :password, :userTypeId, :email, :homePage)';

            $insertSth = $dbh->prepare($SQL);
            $insertSth->execute(array(
                'userName' => $this->userName,
                'password' => $password,
                'userTypeId' => $this->userTypeId,
                'email' => $this->email,
                'homePage' => $this->homePage
            ));

            // Get the ID of the record we just inserted
            $this->userId = $dbh->lastInsertId();

            // Add the user group
            $userGroupObject = new UserGroup();
            $groupId = $userGroupObject->Add($this->userName, 1);

            // Link them
            $userGroupObject->Link($groupId, $this->userId);

            // Link the initial group
            $userGroupObject->Link($initialGroupId, $this->userId);

            return true;
        }
        catch (Exception $e) {

            Debug::Error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }
    }

    public function edit()
    {
        // Validation
        if ($this->userName == '' || strlen($this->userName) > 50)
            return $this->SetError(__('User name must be between 1 and 50 characters.'));

        if ($this->homePage == '')
            $this->homePage = "dashboard";

        try {
            $dbh = PDOConnect::init();

            // Check for duplicate user name
            $sth = $dbh->prepare('SELECT UserName FROM `user` WHERE UserName = :userName AND userId <> :userId');
            $sth->execute(array('userName' => $this->userName, 'userId' => $this->userId));

            $results = $sth->fetchAll();
            if (count($results) > 0)
                $this->ThrowError(__('There is already a user with this name. Please choose another.'));

            // Run the UPDATE statement
            $SQL = 'UPDATE user SET UserName = :userName, HomePage = :homePage, Email = :email, Retired = :retired, userTypeId = :userTypeId
                     WHERE userId = :userId ';

            $updateSth = $dbh->prepare($SQL);
            $updateSth->execute(array(
                'userName' => $this->userName,
                'userTypeId' => $this->userTypeId,
                'email' => $this->email,
                'homePage' => $this->homePage,
                'retired' => $this->retired,
                'userId' => $this->userId
            ));

            // Update the user group
            $userGroup = new UserGroup();
            if (!$userGroup->EditUserGroup($this->userId, $this->userName))
                $this->ThrowError($userGroup->GetErrorNumber(), $userGroup->GetErrorMessage());

            return true;
        }
        catch (Exception $e) {

            Debug::Error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }
    }

    /**
     * Delete User
     * @return bool
     */
    public function Delete()
    {
        if (!isset($this->userId) || $this->userId == 0)
            return $this->SetError(__('Missing userId'));

        try {
            $dbh = PDOConnect::init();

            // Delete all layouts
            $layout = new Layout();
            if (!$layout->deleteAllForUser($this->userId))
                return $this->SetError($layout->GetErrorMessage());

            // Delete all Campaigns
            $campaign = new Campaign();
            if (!$campaign->deleteAllForUser($this->userId))
                return $this->SetError($campaign->GetErrorMessage());

            // Delete all media
            $media = new Media();
            if (!$media->deleteAllForUser($this->userId))
                return $this->SetError($media->GetErrorMessage());

            // Delete all schedules that have not been caught by deleting layouts and campaigns
            // These would be schedules for other peoples layouts
            $schedule = new Schedule();
            if (!$schedule->deleteAllForUser($this->userId));

            // Delete the user itself
            $sth = $dbh->prepare('DELETE FROM `user` WHERE userid = :userid');
            $sth->execute(array('userid' => $this->userId));

            // Delete from the session table
            $sth = $dbh->prepare('DELETE FROM `session` WHERE userid = :userid');
            $sth->execute(array('userid' => $this->userId));

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Change a users password
     * @param <type> $userId
     * @param <type> $oldPassword
     * @param <type> $newPassword
     * @param <type> $retypedNewPassword
     * @return <type> 
     */
    public function ChangePassword($userId, $oldPassword, $newPassword, $retypedNewPassword, $forceChange = false)
    {
        try {
            $dbh = PDOConnect::init();
        
            // Validate
            if ($userId == 0)
                $this->ThrowError(26001, __('User not selected'));
    
            // We can force the users password to change without having to provide the old one.
            // Is this a potential security hole - we must have validated that we are an admin to get to this point
            if (!$forceChange)
            {
                // Get the stored hash
                $sth = $dbh->prepare('SELECT UserPassword FROM `user` WHERE UserID = :userid');
                $sth->execute(array(
                        'userid' => $userId
                    ));

                if (!$row = $sth->fetch())
                    $this->ThrowError(26000, __('Incorrect Password Provided'));

                $good_hash = Kit::ValidateParam($row['UserPassword'], _STRING);
    
                // Check the Old Password is correct
                if ($this->validate_password($oldPassword, $good_hash) === false)
                    $this->ThrowError(26000, __('Incorrect Password Provided'));
            }
            
            // Check the New Password and Retyped Password match
            if ($newPassword != $retypedNewPassword)
                $this->ThrowError(26001, __('New Passwords do not match'));
    
            // Check password complexity
            if (!$this->testPasswordAgainstPolicy($newPassword))
                throw new Exception("Error Processing Request", 1);
                
            // Generate a new SALT and Password
            $hash = $this->create_hash($newPassword);
    
            $sth = $dbh->prepare('UPDATE `user` SET UserPassword = :hash, CSPRNG = 1 WHERE UserID = :userid');
            $sth->execute(array(
                    'hash' => $hash,
                    'userid' => $userId
                ));
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25000, __('Could not edit Password'));
        
            return false;
        }
    }

    /**
     * Tests the supplied password against the password policy
     * @param string $password
     * @return bool
     */
    public function testPasswordAgainstPolicy($password)
    {
        // Check password complexity
        $policy = Config::GetSetting('USER_PASSWORD_POLICY');

        if ($policy != '')
        {
            $policyError = Config::GetSetting('USER_PASSWORD_ERROR');
            $policyError = ($policyError == '') ? __('Your password does not meet the required complexity') : $policyError;

            if(!preg_match($policy, $password, $matches))
                return $this->SetError(26001, $policyError);
        }

        return true;
    }

    /**
     * Returns an array containing the type of children owned by the user
     * @return array[string]
     * @throws Exception
     */
    public function getChildTypes()
    {
        if (!isset($this->userId) || $this->userId == 0)
            return $this->SetError(__('Missing userId'));

        try {
            $types = array();

            if (PDOConnect::exists('SELECT LayoutID FROM layout WHERE UserID = :userId', array('userId' => $this->userId)))
                $types[] = 'layouts';

            if (PDOConnect::exists('SELECT MediaID FROM media WHERE UserID = :userId', array('userId' => $this->userId)))
                $types[] = 'media';

            if (PDOConnect::exists('SELECT EventID FROM schedule WHERE UserID = :userId', array('userId' => $this->userId)))
                $types[] = 'scheduled layouts';

            if (PDOConnect::exists('SELECT Schedule_DetailID FROM schedule_detail WHERE UserID = :userId', array('userId' => $this->userId)))
                $types[] = 'schedule detail records';

            if (PDOConnect::exists('SELECT osr_id FROM oauth_server_registry WHERE osr_usa_id_ref = :userId', array('userId' => $this->userId)))
                $types[] = 'applications';

            return $types;
        }
        catch (Exception $e) {
            Debug::Error($e->getMessage());
            throw $e;
        }
    }

    /*
     * Password hashing with PBKDF2.
     * Author: havoc AT defuse.ca
     * www: https://defuse.ca/php-pbkdf2.htm
     */
    public function create_hash($password)
    {
        // format: algorithm:iterations:salt:hash
        $salt = base64_encode(mcrypt_create_iv(PBKDF2_SALT_BYTES, MCRYPT_DEV_URANDOM));
        return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" .
            base64_encode($this->pbkdf2(
                PBKDF2_HASH_ALGORITHM,
                $password,
                $salt,
                PBKDF2_ITERATIONS,
                PBKDF2_HASH_BYTES,
                true
            ));
    }

    public function validate_password($password, $good_hash)
    {
        $params = explode(":", $good_hash);
        if(count($params) < HASH_SECTIONS)
           return false;
        $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);
        return $this->slow_equals(
            $pbkdf2,
            $this->pbkdf2(
                $params[HASH_ALGORITHM_INDEX],
                $password,
                $params[HASH_SALT_INDEX],
                (int)$params[HASH_ITERATION_INDEX],
                strlen($pbkdf2),
                true
            )
        );
    }

    // Compares two strings $a and $b in length-constant time.
    public function slow_equals($a, $b)
    {
        $diff = strlen($a) ^ strlen($b);
        for($i = 0; $i < strlen($a) && $i < strlen($b); $i++)
        {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0;
    }

    /*
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     * $algorithm - The hash algorithm to use. Recommended: SHA256
     * $password - The password.
     * $salt - A salt that is unique to the password.
     * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * $key_length - The length of the derived key in bytes.
     * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
     * Returns: A $key_length-byte key derived from the password and salt.
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by https://defuse.ca
     * With improvements by http://www.variations-of-shadow.com
     */
    public function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        $algorithm = strtolower($algorithm);
        if(!in_array($algorithm, hash_algos(), true))
            die('PBKDF2 ERROR: Invalid hash algorithm.');
        if($count <= 0 || $key_length <= 0)
            die('PBKDF2 ERROR: Invalid parameters.');

        $hash_length = strlen(hash($algorithm, "", true));
        $block_count = ceil($key_length / $hash_length);

        $output = "";
        for($i = 1; $i <= $block_count; $i++) {
            // $i encoded as 4 bytes, big endian.
            $last = $salt . pack("N", $i);
            // first iteration
            $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
            // perform the other $count - 1 iterations
            for ($j = 1; $j < $count; $j++) {
                $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
            }
            $output .= $xorsum;
        }

        if($raw_output)
            return substr($output, 0, $key_length);
        else
            return bin2hex(substr($output, 0, $key_length));
    }
}
?>
