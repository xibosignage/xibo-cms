<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (User.php) is part of Xibo.
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
namespace Xibo\Entity;

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\LibraryFullException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuFactory;
use Xibo\Factory\PageFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;
use Respect\Validation\Validator as v;

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

class User
{
    use EntityTrait;
    public $userId;
    public $userName;
    public $userTypeId;
    public $loggedIn;
    public $email;
    public $homePageId;
    public $lastAccessed;
    public $newUserWizard;
    public $retired;

    private $CSPRNG;
    private $password;

    // Users own group
    public $groupId;
    public $group;
    public $libraryQuota;

    // Groups assigned to
    public $groups;

    // Things Users can own
    public $campaigns;
    public $layouts;
    public $media;
    public $events;

    // Readonly information
    public $homePage;

    /**
     * Cached Permissions
     * @var array[Permission]
     */
    private $permissionCache = array();

    /**
     * Constructor
     * Set default values
     */
    public function __construct()
    {
        $this->hash = null;
        $this->groups = [];
        $this->layouts = [];
        $this->media = [];
        $this->events = [];
        $this->campaigns = [];
    }

    public function __toString()
    {
        return sprintf('User %s. userId: %d, UserTypeId: %d, homePageId: %d, email = %s', $this->userName, $this->userId, $this->userTypeId, $this->homePageId, $this->email);
    }

    private function hash()
    {
        return md5(json_encode($this));
    }

    public function getOwnerId()
    {
        return $this->getId();
    }

    public function getId()
    {
        return $this->userId;
    }

    /**
     * Set a new password
     * @param string $password
     * @param string[Optional] $oldPassword
     */
    public function setNewPassword($password, $oldPassword = null)
    {
        if ($oldPassword != null) {
            $this->checkPassword($oldPassword);
        }

        $this->testPasswordAgainstPolicy($password);

        $this->password = $this->create_hash($password);
        $this->CSPRNG = 1;
    }

    /**
     * Is the user salted?
     * @return bool
     */
    public function isSalted()
    {
        return ($this->CSPRNG == 1);
    }

    /**
     * Check password
     * @param string $password
     * @throws NotFoundException if the user has not been loaded
     * @throws AccessDeniedException if the passwords don't match
     */
    public function checkPassword($password)
    {
        if ($this->userId == 0)
            throw new NotFoundException(__('User not found'));

        if ($this->CSPRNG == 0 || Config::Version('DBVersion') < 62) {
            // Password is tested using a plain MD5 check
            if ($this->password != md5($password))
                throw new AccessDeniedException();
        }
        else {
            $params = explode(":", $this->password);
            if (count($params) < HASH_SECTIONS) {
                Log::warning('Invalid password hash stored for userId %d', $this->userId);
                throw new AccessDeniedException();
            }

            $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);

            // Check to see if the hash created from the provided password is the same as the hash we have stored already
            if (!$this->slow_equals($pbkdf2, $this->pbkdf2($params[HASH_ALGORITHM_INDEX], $password, $params[HASH_SALT_INDEX], (int)$params[HASH_ITERATION_INDEX], strlen($pbkdf2), true))) {
                Log::debug('Password failed Hash Check.');
                throw new AccessDeniedException();
            }
        }

        Log::debug('Password checked out OK');
    }

    /**
     * Check to see if a user id is in the session information
     * @return bool
     */
    public function hasIdentity()
    {
        $userId = isset($_SESSION['userid']) ? Sanitize::int($_SESSION['userid']) : 0;

        // Checks for a user ID in the session variable
        if ($userId == 0) {
            unset($_SESSION['userid']);
            return false;
        }
        else {
            $this->userId = $userId;
            return true;
        }
    }

    /**
     * Load this User
     * @param bool $all Load everything this user owns
     */
    public function load($all = false)
    {
        Log::debug('Loading %d. All Objects = %d', $this->userId, $all);

        $this->groups = UserGroupFactory::getByUserId($this->userId);

        if ($all) {
            $this->campaigns = CampaignFactory::getByOwnerId($this->userId);
            $this->layouts = LayoutFactory::getbyOwnerId($this->userId);
            $this->media = MediaFactory::getByOwnerId($this->userId);
            $this->events = ScheduleFactory::getByOwnerId($this->userId);
        }

        // Set the hash
        $this->hash = $this->hash();
    }

    /**
     * Does this User have any children
     * @return int
     */
    public function countChildren()
    {
        $this->load(true);

        $count = count($this->campaigns) + count($this->layouts) + count($this->campaigns) + count($this->events);
        Log::debug('Counted Children on %d, there are %d', $this->userId, $count);

        return $count;
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (!v::alnum('_')->length(1, 50)->validate($this->userName))
            throw new \InvalidArgumentException(__('User name must be between 1 and 50 characters.'));

        if (!v::string()->notEmpty()->validate($this->password))
            throw new \InvalidArgumentException(__('Please enter a Password.'));

        if (!v::int()->validate($this->libraryQuota))
            throw new \InvalidArgumentException(__('Library Quota must be a whole number.'));

        if (!empty($this->email) && !v::email()->validate($this->email))
            throw new \InvalidArgumentException(__('Please enter a valid email address or leave it empty.'));

        try {
            $user = UserFactory::getByName($this->userName);

            if ($this->userId == null || $this->userId != $user->userId)
                throw new \InvalidArgumentException(__('There is already a user with this name. Please choose another.'));
        }
        catch (NotFoundException $e) {

        }
    }

    /**
     * Save User
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        Log::debug('Saving user. %s', $this);

        if ($this->userId == 0)
            $this->add();
        else if ($this->hash() != $this->hash)
            $this->update();
    }

    /**
     * Delete User
     */
    public function delete()
    {
        Log::debug('Deleting %d', $this->userId);

        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load(true);

        // Delete everything
        foreach ($this->groups as $group) {
            /* @var UserGroup $group */
            $group->delete();
        }

        foreach ($this->layouts as $layout) {
            /* @var Layout $layout */
            $layout->delete();
        }

        foreach ($this->campaigns as $event) {
            /* @var Campaign $event */
            $event->delete();
        }

        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->delete();
        }

        // Delete user specific entities
        PDOConnect::update('DELETE FROM `session` WHERE userId = :userId', ['userId' => $this->userId]);
        PDOConnect::update('DELETE FROM `user` WHERE userId = :userId', ['userId' => $this->userId]);
    }

    /**
     * Add user
     */
    private function add()
    {
        $sql = 'INSERT INTO `user` (UserName, UserPassword, usertypeid, email, homePageId)
                     VALUES (:userName, :password, :userTypeId, :email, :homePageId)';

        // Get the ID of the record we just inserted
        $this->userId = PDOConnect::insert($sql, [
            'userName' => $this->userName,
            'password' => $this->password,
            'userTypeId' => $this->userTypeId,
            'email' => $this->email,
            'homePageId' => $this->homePageId
        ]);

        // Add the user group
        $group = new UserGroup();
        $group->group = $this->userName;
        $group->libraryQuota = $this->libraryQuota;
        $group->setOwner($this->userId);
        $group->save();
    }

    /**
     * Update user
     */
    private function update()
    {
        Log::debug('Update user. %d. homePageId', $this->userId);

        $sql = 'UPDATE `user` SET UserName = :userName,
                  homePageId = :homePageId,
                  Email = :email,
                  Retired = :retired,
                  userTypeId = :userTypeId,
                  loggedIn = :loggedIn,
                  lastAccessed = :lastAccessed,
                  newUserWizard = :newUserWizard,
                  CSPRNG = :CSPRNG,
                  `UserPassword` = :password
               WHERE userId = :userId';

        $params = array(
            'userName' => $this->userName,
            'userTypeId' => $this->userTypeId,
            'email' => $this->email,
            'homePageId' => $this->homePageId,
            'retired' => $this->retired,
            'lastAccessed' => $this->lastAccessed,
            'loggedIn' => $this->loggedIn,
            'newUserWizard' => $this->newUserWizard,
            'CSPRNG' => $this->CSPRNG,
            'password' => $this->password,
            'userId' => $this->userId
        );

        PDOConnect::update($sql, $params);

        // Update the group
        $group = UserGroupFactory::getById($this->groupId);
        $group->group = $this->userName;
        $group->libraryQuota = $this->libraryQuota;
        $group->save();
    }

    /**
     * Update the Last Accessed date
     */
    public function touch()
    {
        PDOConnect::update('UPDATE `user` SET lastAccessed = :time WHERE userId = :userId', [
            'userId' => $this->userId,
            'time' => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * Authenticates the route given against the user credentials held
     * @param $route string
     * @throws AccessDeniedException if the user doesn't have access
     */
    public function routeAuthentication($route)
    {
        // Look at the route and see if we are permission for it.
        $page = PageFactory::getByRoute($route);

        if (!$this->checkViewable($page)) {
            Log::debug('Blocked assess to unrecognised page: ' . $page->page . '.', 'index', 'PageAuth');
            throw new AccessDeniedException();
        }
    }

    /**
     * Return a Menu for this user
     * @param string $menu
     * @return array[Menu]
     */
    public function menuList($menu)
    {
        $menu = MenuFactory::getByMenu($menu);

        if ($this->userTypeId == 1)
            return $menu;

        foreach ($menu as $key => $menuItem) {
            /* @var \Xibo\Entity\Menu $menuItem */

            // Check to see if we are the owner
            if ($menuItem->getOwnerId() == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($menuItem))
                unset($menu[$key]);
        }

        return $menu;
    }

    /**
     * Load permissions for a particular entity
     * @param string $entity
     * @return array[Permission]
     */
    private function loadPermissions($entity)
    {
        // Check our cache to see if we have permissions for this entity cached already
        if (!isset($this->permissionCache[$entity])) {

            // Store the results in the cache (default to empty result)
            $this->permissionCache[$entity] = array();

            // Turn it into a ID keyed array
            foreach (\Xibo\Factory\PermissionFactory::getByUserId($entity, $this->userId) as $permission) {
                /* @var \Xibo\Entity\Permission $permission */
                $this->permissionCache[$entity][$permission->objectId] = $permission;
            }
        }

        return $this->permissionCache[$entity];
    }

    /**
     * Check that this object can be used with the permissions sytem
     * @param object $object
     */
    private function checkObjectCompatibility($object)
    {
        if (!method_exists($object, 'getId') || !method_exists($object, 'getOwnerId'))
            throw new \InvalidArgumentException(__('Provided Object not under permission management'));
    }

    /**
     * Get a permission object
     * @param object $object
     * @return \Xibo\Entity\Permission
     */
    public function getPermission($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        if ($this->userTypeId == 1 || $this->userId == $object->getOwnerId()) {
            return \Xibo\Factory\PermissionFactory::getFullPermissions();
        }

        // Get the permissions for that entity
        $permissions = $this->loadPermissions(get_class($object));

        // Check to see if our object is in the list
        if (array_key_exists($object->getId(), $permissions))
            return $permissions[$object->getId()];
        else
            return new \Xibo\Entity\Permission();
    }

    /**
     * Check the given object is viewable
     * @param object $object
     * @return bool
     */
    public function checkViewable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        if ($this->userTypeId == 1 || $this->userId == $object->getOwnerId())
            return true;

        // Get the permissions for that entity
        $permissions = $this->loadPermissions(get_class($object));

        // Check to see if our object is in the list
        if (array_key_exists($object->getId(), $permissions))
            return ($permissions[$object->getId()]->view == 1);
        else
            return false;
    }

    /**
     * Check the given object is editable
     * @param object $object
     * @return bool
     */
    public function checkEditable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        if ($this->userTypeId == 1 || $this->userId == $object->getOwnerId())
            return true;

        // Get the permissions for that entity
        $permissions = $this->loadPermissions(get_class($object));

        // Check to see if our object is in the list
        if (array_key_exists($object->getId(), $permissions))
            return ($permissions[$object->getId()]->edit == 1);
        else
            return false;
    }

    /**
     * Check the given object is delete-able
     * @param object $object
     * @return bool
     */
    public function checkDeleteable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        if ($this->userTypeId == 1 || $this->userId == $object->getOwnerId())
            return true;

        // Get the permissions for that entity
        $permissions = $this->loadPermissions(get_class($object));

        // Check to see if our object is in the list
        if (array_key_exists($object->getId(), $permissions))
            return ($permissions[$object->getId()]->delete == 1);
        else
            return false;
    }

    /**
     * Check the given objects permissions are modify-able
     * @param object $object
     * @return bool
     */
    public function checkPermissionsModifyable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        if ($this->userTypeId == 1 || $this->userId == $object->getOwnerId())
            return true;
        else
            return false;
    }

    /**
     * Returns the usertypeid for this user object.
     * @return int
     */
    public function getUserTypeId()
    {
        return $this->userTypeId;
    }

    /**
     * Authenticates a user against a fileId
     * @param int $fileId
     * @return bool true on granted
     * @throws \Xibo\Exception\NotFoundException
     */
    public function FileAuth($fileId)
    {
        $results = \Xibo\Storage\PDOConnect::select('SELECT UserID FROM file WHERE FileID = :fileId', array('fileId' => $fileId));

        if (count($results) <= 0)
            throw new NotFoundException('File not found');

        $userId = Sanitize::int($results[0]['UserID']);

        return ($userId == $this->userId);
    }

    /**
     * Returns an array of Media the current user has access to
     * @param array $sort_order
     * @param array $filter_by
     * @return array[Media]
     */
    public function MediaList($sort_order = array('name'), $filter_by = array())
    {
        // Get the Layouts
        $media = \Xibo\Factory\MediaFactory::query($sort_order, $filter_by);

        if ($this->userTypeId == 1)
            return $media;

        foreach ($media as $key => $mediaItem) {
            /* @var \Xibo\Entity\Media $mediaItem */

            // Check to see if we are the owner
            if ($mediaItem->ownerId == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($mediaItem))
                unset($media[$key]);
        }

        return $media;
    }

    /**
     * List of Layouts this user can see
     * @param array $sort_order
     * @param array $filter_by
     * @return array[Layout]
     * @throws \Xibo\Exception\NotFoundException
     */
    public function LayoutList($sort_order = array('layout'), $filter_by = array())
    {
        // Get the Layouts
        $layouts = \Xibo\Factory\LayoutFactory::query($sort_order, $filter_by);

        if ($this->userTypeId == 1)
            return $layouts;

        foreach ($layouts as $key => $layout) {
            /* @var \Xibo\Entity\Layout $layout */

            // Check to see if we are the owner
            if ($layout->ownerId == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($layout))
                unset($layouts[$key]);
        }

        return $layouts;
    }

    /**
     * A List of Templates
     * @param array $sort_order
     * @param array $filter_by
     * @return array[Layout]
     */
    public function TemplateList($sort_order = array('layout'), $filter_by = array())
    {
        $filter_by['excludeTemplates'] = 0;
        $filter_by['tags'] = 'template';

        return $this->LayoutList($sort_order, $filter_by);
    }

    /**
     * A list of Resolutions
     * @param array $sort_order
     * @param array $filter_by
     * @return array[Resolution]
     */
    public function ResolutionList($sort_order = array('resolution'), $filter_by = array())
    {
        // Get the Layouts
        $resolutions = \Xibo\Factory\ResolutionFactory::query($sort_order, $filter_by);

        if ($this->userTypeId == 1)
            return $resolutions;

        foreach ($resolutions as $key => $resolution) {
            /* @var \Xibo\Entity\Resolution $resolution */

            // Check to see if we are the owner
            if ($resolution->getOwnerId() == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($resolution))
                unset($resolutions[$key]);
        }

        return $resolutions;
    }

    /**
     * Authorises a user against a dataSetId
     * @param <type> $dataSetId
     * @return <type>
     */
    public function DataSetAuth($dataSetId, $fullObject = false)
    {
        $auth = new PermissionManager($this);

        $SQL = '';
        $SQL .= 'SELECT UserID ';
        $SQL .= '  FROM dataset ';
        $SQL .= ' WHERE dataset.DataSetID = %d ';

        if (!$ownerId = $this->db->GetSingleValue(sprintf($SQL, $dataSetId), 'UserID', _INT))
            return $auth;

        // If we are the owner, or a super admin then give full permissions
        if ($this->userTypeId == 1 || $ownerId == $this->userId) {
            $auth->FullAccess();
            return $auth;
        }

        // Permissions for groups the user is assigned to, and Everyone
        $SQL = '';
        $SQL .= 'SELECT UserID, MAX(IFNULL(View, 0)) AS View, MAX(IFNULL(Edit, 0)) AS Edit, MAX(IFNULL(Del, 0)) AS Del ';
        $SQL .= '  FROM dataset ';
        $SQL .= '   INNER JOIN lkdatasetgroup ';
        $SQL .= '   ON lkdatasetgroup.DataSetID = dataset.DataSetID ';
        $SQL .= '   INNER JOIN `group` ';
        $SQL .= '   ON `group`.GroupID = lkdatasetgroup.GroupID ';
        $SQL .= ' WHERE dataset.DataSetID = %d ';
        $SQL .= '   AND (`group`.IsEveryone = 1 OR `group`.GroupID IN (%s)) ';
        $SQL .= 'GROUP BY dataset.UserID ';

        $SQL = sprintf($SQL, $dataSetId, implode(',', $this->GetUserGroups($this->userId, true)));
        //Log::debug($SQL);

        if (!$row = $this->db->GetSingleRow($SQL))
            return $auth;

        // There are permissions to evaluate
        $auth->Evaluate($row['UserID'], $row['View'], $row['Edit'], $row['Del']);

        if ($fullObject)
            return $auth;

        return $auth->edit;
    }

    /**
     * Returns an array of layouts that this user has access to
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DataSet]
     */
    public function DataSetList($sortOrder = array('dataSet'), $filterBy = array())
    {
        $dataSets = DataSetFactory::query($sortOrder, $filterBy);

        if ($this->userTypeId == 1)
            return $dataSets;

        foreach ($dataSets as $key => $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */

            // Check to see if we are the owner
            if ($dataSet->getOwnerId() == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($dataSet))
                unset($dataSets[$key]);
        }

        return $dataSets;
    }

    /**
     * List of Displays this user has access to view
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Display]
     */
    public function DisplayGroupList($sortOrder = array('displayGroupId'), $filterBy = array())
    {
        // Get the Layouts
        $displayGroups = DisplayGroupFactory::query($sortOrder, $filterBy);

        if ($this->userTypeId == 1)
            return $displayGroups;

        foreach ($displayGroups as $key => $group) {
            /* @var \Xibo\Entity\DisplayGroup $group */

            // Check to see if we are the owner
            if ($group->getOwnerId() == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($group))
                unset($displayGroups[$key]);
        }

        return $displayGroups;
    }

    /**
     * List of Displays this user has access to view
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Display]
     */
    public function DisplayList($sortOrder = array('displayid'), $filterBy = array())
    {
        // Get the Layouts
        $displays = DisplayFactory::query($sortOrder, $filterBy);

        if ($this->userTypeId == 1)
            return $displays;

        foreach ($displays as $key => $layout) {
            /* @var \Xibo\Entity\Layout $layout */

            // Check to see if we are the owner
            if ($layout->ownerId == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($layout))
                unset($displays[$key]);
        }

        return $displays;
    }

    /**
     * Campaigns viewable by the user
     * @param array $sort_order
     * @param array $filter_by
     * @return array[Campaign]
     */
    public function CampaignList($sort_order = null, $filter_by = null)
    {
        // Get the Layouts
        $campaigns = \Xibo\Factory\CampaignFactory::query($sort_order, $filter_by);

        if ($this->userTypeId == 1)
            return $campaigns;

        foreach ($campaigns as $key => $campaign) {
            /* @var \Xibo\Entity\Campaign $campaign */

            // Check to see if we are the owner
            if ($campaign->ownerId == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($campaign))
                unset($campaigns[$key]);
        }

        return $campaigns;
    }

    /**
     * Get a list of transitions
     * @param string $type in/out
     * @param string $code transition code
     * @return boolean
     */
    public function TransitionAuth($type = '', $code = '')
    {
        // Return a list of in/out transitions (or both)
        $SQL = 'SELECT TransitionID, ';
        $SQL .= '   Transition, ';
        $SQL .= '   Code, ';
        $SQL .= '   HasDuration, ';
        $SQL .= '   HasDirection, ';
        $SQL .= '   AvailableAsIn, ';
        $SQL .= '   AvailableAsOut ';
        $SQL .= '  FROM `transition` ';
        $SQL .= ' WHERE 1 = 1 ';

        if ($type != '') {
            // Filter on type
            if ($type == 'in')
                $SQL .= '  AND AvailableAsIn = 1 ';

            if ($type == 'out')
                $SQL .= '  AND AvailableAsOut = 1 ';
        }

        if ($code != '') {
            // Filter on code
            $SQL .= sprintf("AND Code = '%s' ", $this->db->escape_string($code));
        }

        $SQL .= ' ORDER BY Transition ';

        $rows = $this->db->GetArray($SQL);

        if (!is_array($rows)) {
            trigger_error($this->db->error());
            return false;
        }

        $transitions = array();

        foreach ($rows as $transition) {
            $transitionItem = array();

            $transitionItem['transitionid'] = Sanitize::int($transition['TransitionID']);
            $transitionItem['transition'] = Sanitize::string($transition['Transition']);
            $transitionItem['code'] = \Kit::ValidateParam($transition['Code'], _WORD);
            $transitionItem['hasduration'] = Sanitize::int($transition['HasDuration']);
            $transitionItem['hasdirection'] = Sanitize::int($transition['HasDirection']);
            $transitionItem['enabledforin'] = Sanitize::int($transition['AvailableAsIn']);
            $transitionItem['enabledforout'] = Sanitize::int($transition['AvailableAsOut']);
            $transitionItem['class'] = (($transitionItem['hasduration'] == 1) ? 'hasDuration' : '') . ' ' . (($transitionItem['hasdirection'] == 1) ? 'hasDirection' : '');

            $transitions[] = $transitionItem;
        }

        return $transitions;
    }

    /**
     * List of Displays this user has access to view
     */
    public function DisplayProfileList($sortOrder = array('name'), $filterBy = array())
    {
        // Get the Layouts
        $profiles = DisplayProfileFactory::query($sortOrder, $filterBy);

        if ($this->userTypeId == 1)
            return $profiles;

        foreach ($profiles as $key => $profile) {
            /* @var \Xibo\Entity\DisplayProfile $profile */

            // Check to see if we are the owner
            if ($profile->getOwnerId() == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($profile))
                unset($profiles[$key]);
        }

        return $profiles;
    }

    public function userList($sortOrder = array('username'), $filterBy = array())
    {
        if ($this->userTypeId == 3) {
            // Normal users can only see themselves
            $filterBy['userId'] = $this->userId;
        }
        else if ($this->userTypeId == 2) {
            // Group admins can only see users from their groups.
            $filterBy['groupIds'] = (isset($filterBy['groupIds'])) ? array_merge($filterBy['groupIds'], $this->groups) : $this->groups;
        }

        $users = UserFactory::query($sortOrder, $filterBy);

        if ($this->userTypeId == 1)
            return $users;

        foreach ($users as $key => $user) {
            /* @var \Xibo\Entity\User $user */

            // Check to see if we are the user
            if ($user->userId == $this->userId)
                continue;

            // Check we are viewable
            if (!$this->checkViewable($user))
                unset($users[$key]);
        }

        return $users;
    }

    public function GetPref($key, $default = NULL)
    {
        $storedValue = Session::Get($key);

        return ($storedValue == NULL) ? $default : $storedValue;
    }

    public function SetPref($key, $value)
    {
        Session::Set($key, $value);
    }

    /**
     * Is this users library quota full
     * @return bool true if the quota is full otherwise false
     * @throws LibraryFullException when the library is full or cannot be determined
     */
    public function isQuotaFullByUser()
    {
        $dbh = PDOConnect::init();
        $groupId = 0;
        $userQuota = 0;

        // Get the maximum quota of this users groups and their own quota
        $quotaSth = $dbh->prepare('
            SELECT group.groupId, IFNULL(group.libraryQuota, 0) AS libraryQuota
              FROM `group`
                INNER JOIN `lkusergroup`
                ON group.groupId = lkusergroup.groupId
             WHERE lkusergroup.userId = :userId
            ORDER BY `group`.isUserSpecific DESC, IFNULL(group.libraryQuota, 0) DESC
        ');

        $quotaSth->execute(['userId' => $this->userId]);

        // Loop over until we have a quota
        $rows = $quotaSth->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) <= 0) {
            throw new LibraryFullException('Problem calculating this users library quota.');
        }

        foreach ($rows as $row) {

            if ($row['libraryQuota'] > 0) {
                $groupId = $row['groupId'];
                $userQuota = $row['libraryQuota'];
                break;
            }
        }

        if ($userQuota <= 0)
            return true;

        // If there is a quota, then test it against the current library position for this user.
        //   use the groupId that generated the quota in order to calculate the usage
        $sth = $dbh->prepare('
          SELECT IFNULL(SUM(FileSize), 0) AS SumSize
            FROM `media`
              INNER JOIN `lkusergroup`
              ON lkusergroup.userId = media.userId
           WHERE lkusergroup.groupId = :groupId
          ');
        $sth->execute(['groupId' => $groupId]);

        if (!$row = $sth->fetch())
            throw new LibraryFullException("Error Processing Request", 1);

        $fileSize = Sanitize::int($row['SumSize']);

        return (($fileSize / 1024) <= $userQuota);
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

    /**
     * Tests the supplied password against the password policy
     * @param string $password
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
                throw new \InvalidArgumentException($policyError);
        }
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
