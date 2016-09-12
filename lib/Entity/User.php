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

use League\OAuth2\Server\Entity\ScopeEntity;
use Respect\Validation\Validator as v;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\LibraryFullException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PageFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserOptionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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

/**
 * Class User
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class User implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this User")
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="The user name")
     * @var string
     */
    public $userName;

    /**
     * @SWG\Property(description="The user type ID")
     * @var int
     */
    public $userTypeId;

    /**
     * @SWG\Property(description="Flag indicating whether this user is logged in or not")
     * @var int
     */
    public $loggedIn;

    /**
     * @SWG\Property(description="Email address of the user used for email alerts")
     * @var string
     */
    public $email;

    /**
     * @SWG\Property(description="The pageId of the Homepage for this User")
     * @var int
     */
    public $homePageId;

    /**
     * @SWG\Property(description="A timestamp indicating the time the user last logged into the CMS")
     * @var int
     */
    public $lastAccessed;

    /**
     * @SWG\Property(description="A flag indicating whether this user has see the new user wizard")
     * @var int
     */
    public $newUserWizard;

    /**
     * @SWG\Property(description="A flag indicating whether the user is retired")
     * @var int
     */
    public $retired;

    private $CSPRNG;
    private $password;

    /**
     * @SWG\Property(description="The users user group ID")
     * @var int
     */
    public $groupId;

    /**
     * @SWG\Property(description="The users group name")
     * @var int
     */
    public $group;

    /**
     * @SWG\Property(description="The users library quota in bytes")
     * @var int
     */
    public $libraryQuota;

    /**
     * @SWG\Property(description="First Name")
     * @var string
     */
    public $firstName;

    /**
     * @SWG\Property(description="Last Name")
     * @var string
     */
    public $lastName;

    /**
     * @SWG\Property(description="Phone Number")
     * @var string
     */
    public $phone;

    /**
     * @SWG\Property(description="Reference field 1")
     * @var string
     */
    public $ref1;

    /**
     * @SWG\Property(description="Reference field 2")
     * @var string
     */
    public $ref2;

    /**
     * @SWG\Property(description="Reference field 3")
     * @var string
     */
    public $ref3;

    /**
     * @SWG\Property(description="Reference field 4")
     * @var string
     */
    public $ref4;

    /**
     * @SWG\Property(description="Reference field 5")
     * @var string
     */
    public $ref5;

    /**
     * @SWG\Property(description="An array of user groups this user is assigned to")
     * @var UserGroup[]
     */
    public $groups = [];

    /**
     * @SWG\Property(description="An array of Campaigns for this User")
     * @var Campaign[]
     */
    public $campaigns = [];

    /**
     * @SWG\Property(description="An array of Layouts for this User")
     * @var Layout[]
     */
    public $layouts = [];

    /**
     * @SWG\Property(description="An array of Media for this user")
     * @var Media[]
     */
    public $media = [];

    /**
     * @SWG\Property(description="An array of Scheduled Events for this User")
     * @var Schedule[]
     */
    public $events = [];

    /**
     * @SWG\Property(description="The name of home page")
     * @var string
     */
    public $homePage;

    /**
     * @SWG\Property(description="The user options")
     * @var UserOption[]
     */
    public $userOptions = [];

    /**
     * @SWG\Property(description="Does this Group receive system notifications.")
     * @var int
     */
    public $isSystemNotification = 0;

    /**
     * Cached Permissions
     * @var array[Permission]
     */
    private $permissionCache = array();

    /**
     * Cached Page Permissions
     * @var array[Page]
     */
    private $pagePermissionCache = null;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var UserOptionFactory
     */
    private $userOptionFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  ApplicationScopeFactory */
    private $applicationScopeFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $configService
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     * @param UserOptionFactory $userOptionFactory
     * @param ApplicationScopeFactory $applicationScopeFactory
     */
    public function __construct($store,
                                $log,
                                $configService,
                                $userFactory,
                                $permissionFactory,
                                $userOptionFactory,
                                $applicationScopeFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->configService = $configService;
        $this->userFactory = $userFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userOptionFactory = $userOptionFactory;
        $this->applicationScopeFactory = $applicationScopeFactory;
    }

    /**
     * Set the user group factory
     * @param UserGroupFactory $userGroupFactory
     * @param PageFactory $pageFactory
     * @return $this
     */
    public function setChildAclDependencies($userGroupFactory, $pageFactory)
    {
        // Assert myself on these factories
        $userGroupFactory->setAclDependencies($this, $this->userFactory);
        $pageFactory->setAclDependencies($this, $this->userFactory);
        $this->userFactory->setAclDependencies($this, $this->userFactory);

        $this->userGroupFactory = $userGroupFactory;
        $this->pageFactory = $pageFactory;
        return $this;
    }

    /**
     * Set Child Object Depencendies
     *  must be set before calling Load with all objects
     * @param CampaignFactory $campaignFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayFactory $displayFactory
     * @return $this
     */
    public function setChildObjectDependencies($campaignFactory, $layoutFactory, $mediaFactory, $scheduleFactory, $displayFactory)
    {
        $this->campaignFactory = $campaignFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayFactory = $displayFactory;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('User %s. userId: %d, UserTypeId: %d, homePageId: %d, email = %s', $this->userName, $this->userId, $this->userTypeId, $this->homePageId, $this->email);
    }

    /**
     * @return string
     */
    private function hash()
    {
        return md5(json_encode($this));
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->getId();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->userId;
    }

    /**
     * Get Option
     * @param string $option
     * @return UserOption
     * @throws NotFoundException
     */
    public function getOption($option)
    {
        $this->load();

        foreach ($this->userOptions as $userOption) {
            /* @var UserOption $userOption */
            if ($userOption->option == $option)
                return $userOption;
        }

        $this->getLog()->debug('UserOption %s not found', $option);

        throw new NotFoundException('User Option not found');
    }

    /**
     * Get User Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOptionValue($option, $default)
    {
        $this->load();

        try {
            $userOption = $this->getOption($option);
            return $userOption->value;
        }
        catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Set User Option Value
     * @param string $option
     * @param mixed $value
     */
    public function setOptionValue($option, $value)
    {
        try {
            $this->getOption($option)->value = $value;
        }
        catch (NotFoundException $e) {
            $this->userOptions[] = $this->userOptionFactory->create($this->userId, $option, $value);
        }
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

        $this->password = $this->createHash($password);
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

        if ($this->CSPRNG == 0 || $this->configService->Version('DBVersion') < 62) {
            // Password is tested using a plain MD5 check
            if ($this->password != md5($password))
                throw new AccessDeniedException();
        }
        else {
            $params = explode(":", $this->password);
            if (count($params) < HASH_SECTIONS) {
                $this->getLog()->warning('Invalid password hash stored for userId %d', $this->userId);
                throw new AccessDeniedException();
            }

            $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);

            // Check to see if the hash created from the provided password is the same as the hash we have stored already
            if (!$this->slowEquals($pbkdf2, $this->pbkdf2($params[HASH_ALGORITHM_INDEX], $password, $params[HASH_SALT_INDEX], (int)$params[HASH_ITERATION_INDEX], strlen($pbkdf2), true))) {
                $this->getLog()->debug('Password failed Hash Check.');
                throw new AccessDeniedException();
            }
        }

        $this->getLog()->debug('Password checked out OK');
    }

    /**
     * Check to see if a user id is in the session information
     * @return bool
     */
    public function hasIdentity()
    {
        $userId = isset($_SESSION['userid']) ? intval($_SESSION['userid']) : 0;

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
        if ($this->userId == null || $this->loaded)
            return;

        if ($this->userGroupFactory == null)
            throw new \RuntimeException('Cannot load user without first calling setUserGroupFactory');

        $this->getLog()->debug('Loading %d. All Objects = %d', $this->userId, $all);

        $this->groups = $this->userGroupFactory->getByUserId($this->userId);

        if ($all) {
            if ($this->campaignFactory == null || $this->layoutFactory == null || $this->mediaFactory == null || $this->scheduleFactory == null)
                throw new \RuntimeException('Cannot load user with all objects without first calling setChildObjectDependencies');

            $this->campaigns = $this->campaignFactory->getByOwnerId($this->userId);
            $this->layouts = $this->layoutFactory->getByOwnerId($this->userId);
            $this->media = $this->mediaFactory->getByOwnerId($this->userId);
            $this->events = $this->scheduleFactory->getByOwnerId($this->userId);
        }

        $this->userOptions = $this->userOptionFactory->getByUserId($this->userId);

        // Set the hash
        $this->hash = $this->hash();

        $this->loaded = true;
    }

    /**
     * Does this User have any children
     * @return int
     */
    public function countChildren()
    {
        $this->load(true);

        $count = count($this->campaigns) + count($this->layouts) + count($this->media) + count($this->events);
        $this->getLog()->debug('Counted Children on %d, there are %d', $this->userId, $count);

        return $count;
    }

    /**
     * Reassign all
     * @param User $user
     */
    public function reassignAllTo($user)
    {
        $this->getLog()->debug('Reassign all to %s', $user->userName);

        $this->load(true);

        $this->getLog()->debug('There are %d children', $this->countChildren());

        // Go through each item and reassign the owner to the provided user.
        foreach ($this->media as $media) {
            /* @var Media $media */
            $media->setOwner($user->getOwnerId());
            $media->save();
        }
        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->setOwner($user->getOwnerId());
            $event->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
            $event->save(['generate' => false]);
        }
        foreach ($this->layouts as $layout) {
            /* @var Layout $layout */
            $layout->setOwner($user->getOwnerId());
            $layout->save();
        }
        foreach ($this->campaigns as $campaign) {
            /* @var Campaign $campaign */
            $campaign->setOwner($user->getOwnerId());
            $campaign->save();
        }

        // Load again
        $this->loaded = false;
        $this->load(true);

        $this->getLog()->debug('Reassign and reload complete, there are %d children', $this->countChildren());
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
            $user = $this->userFactory->getByName($this->userName);

            if ($this->userId == null || $this->userId != $user->userId)
                throw new \InvalidArgumentException(__('There is already a user with this name. Please choose another.'));
        }
        catch (NotFoundException $e) {

        }

        try {
            $this->pageFactory->getById($this->homePageId);
        }
        catch (NotFoundException $e) {
            throw new \InvalidArgumentException(__('Selected home page does not exist'));
        }
    }

    /**
     * Save User
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'passwordUpdate' => false,
            'saveUserOptions' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        $this->getLog()->debug('Saving user. %s', $this);

        if ($this->userId == 0)
            $this->add();
        else if ($options['passwordUpdate'])
            $this->updatePassword();
        else if ($this->hash() != $this->hash)
            $this->update();

        // Save user options
        if ($options['saveUserOptions']) {
            // Save all Options
            foreach ($this->userOptions as $userOption) {
                /* @var RegionOption $userOption */
                $userOption->save();
            }
        }
    }

    /**
     * Delete User
     */
    public function delete()
    {
        $this->getLog()->debug('Deleting %d', $this->userId);

        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load(true);

        // Remove the user specific group
        $group = $this->userGroupFactory->getById($this->groupId);
        $group->delete();

        // Delete all user options
        foreach ($this->userOptions as $userOption) {
            /* @var RegionOption $userOption */
            $userOption->delete();
        }

        // Remove any assignments to groups
        foreach ($this->groups as $group) {
            /* @var UserGroup $group */
            $group->unassignUser($this);
            $group->save(['validate' => false]);
        }

        // Delete any layouts
        foreach ($this->layouts as $layout) {
            /* @var Layout $layout */
            $layout->delete();
        }

        // Delete any Campaigns
        foreach ($this->campaigns as $campaign) {
            /* @var Campaign $campaign */
            $campaign->delete();
        }

        // Delete any scheduled events
        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->delete();
        }

        // Delete any media
        foreach ($this->media as $media) {
            /* @var Media $media */
            $media->delete();
        }

        // Delete user specific entities
        $this->getStore()->update('DELETE FROM `session` WHERE userId = :userId', ['userId' => $this->userId]);
        $this->getStore()->update('DELETE FROM `user` WHERE userId = :userId', ['userId' => $this->userId]);
    }

    /**
     * Add user
     */
    private function add()
    {
        $sql = 'INSERT INTO `user` (UserName, UserPassword, usertypeid, email, homePageId, CSPRNG, firstName, lastName, phone, ref1, ref2, ref3, ref4, ref5)
                     VALUES (:userName, :password, :userTypeId, :email, :homePageId, :CSPRNG, :firstName, :lastName, :phone, :ref1, :ref2, :ref3, :ref4, :ref5)';

        // Get the ID of the record we just inserted
        $this->userId = $this->getStore()->insert($sql, [
            'userName' => $this->userName,
            'password' => $this->password,
            'userTypeId' => $this->userTypeId,
            'email' => $this->email,
            'homePageId' => $this->homePageId,
            'CSPRNG' => $this->CSPRNG,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'phone' => $this->phone,
            'ref1' => $this->ref1,
            'ref2' => $this->ref2,
            'ref3' => $this->ref3,
            'ref4' => $this->ref4,
            'ref5' => $this->ref5
        ]);

        // Add the user group
        /* @var UserGroup $group */
        $group = $this->userGroupFactory->create($this->userName, $this->libraryQuota);
        $group->setOwner($this);
        $group->isSystemNotification = $this->isSystemNotification;
        $group->save();
    }

    /**
     * Update user
     */
    private function update()
    {
        $this->getLog()->debug('Update user. %d. homePageId', $this->userId);

        $sql = 'UPDATE `user` SET UserName = :userName,
                  homePageId = :homePageId,
                  Email = :email,
                  Retired = :retired,
                  userTypeId = :userTypeId,
                  loggedIn = :loggedIn,
                  lastAccessed = :lastAccessed,
                  newUserWizard = :newUserWizard,
                  CSPRNG = :CSPRNG,
                  `UserPassword` = :password,
                  `firstName` = :firstName,
                  `lastName` = :lastName,
                  `phone` = :phone,
                  `ref1` = :ref1,
                  `ref2` = :ref2,
                  `ref3` = :ref3,
                  `ref4` = :ref4,
                  `ref5` = :ref5
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
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'phone' => $this->phone,
            'ref1' => $this->ref1,
            'ref2' => $this->ref2,
            'ref3' => $this->ref3,
            'ref4' => $this->ref4,
            'ref5' => $this->ref5,
            'userId' => $this->userId
        );

        $this->getStore()->update($sql, $params);

        // Update the group
        // This is essentially a dirty edit (i.e. we don't touch the group assignments)
        $group = $this->userGroupFactory->getById($this->groupId);
        $group->group = $this->userName;
        $group->libraryQuota = $this->libraryQuota;
        $group->isSystemNotification = $this->isSystemNotification;
        $group->save(['linkUsers' => false]);
    }

    /**
     * Update user
     */
    private function updatePassword()
    {
        $this->getLog()->debug('Update user password. %d', $this->userId);

        $sql = 'UPDATE `user` SET CSPRNG = :CSPRNG,
                  `UserPassword` = :password
               WHERE userId = :userId';

        $params = array(
            'CSPRNG' => $this->CSPRNG,
            'password' => $this->password,
            'userId' => $this->userId
        );

        $this->getStore()->update($sql, $params);
    }

    /**
     * Update the Last Accessed date
     */
    public function touch()
    {
        // This needs to happen on a separate connection
        $this->getStore()->update('UPDATE `user` SET lastAccessed = :time, loggedIn = 1, newUserWizard = :newUserWizard WHERE userId = :userId', [
            'userId' => $this->userId,
            'newUserWizard' => $this->newUserWizard,
            'time' => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * Authenticates the route given against the user credentials held
     * @param $route string
     * @param $method string
     * @param $scopes array[ScopeEntity]
     * @throws AccessDeniedException if the user doesn't have access
     */
    public function routeAuthentication($route, $method = null, $scopes = null)
    {
        // Scopes provided?
        if ($scopes !== null && is_array($scopes)) {
            //$this->getLog()->debug('Scopes: %s', json_encode($scopes));
            foreach ($scopes as $scope) {
                /** @var ScopeEntity $scope */

                // Valid routes
                if ($scope->getId() != 'all') {
                    $this->getLog()->debug('Test authentication for route %s %s against scope %s', $method, $route, $scope->getId());

                    // Check the route and request method
                    $this->applicationScopeFactory->getById($scope->getId())->checkRoute($method, $route);
                }
            }
        }

        // Check route
        if (!$this->routeViewable($route)) {
            $this->getLog()->debug('Blocked assess to unrecognised page: ' . $route . '.');
            throw new AccessDeniedException();
        }
    }

    /**
     * Authenticates the route given against the user credentials held
     * @param $route string
     * @return bool
     * @throws ConfigurationException
     */
    public function routeViewable($route)
    {
        if ($this->pageFactory == null)
            throw new ConfigurationException('routeViewable called before user object has been initialised');

        if ($this->userTypeId == 1)
            return true;

        try {
            if ($this->pagePermissionCache == null) {
                // Load all viewable pages into the permissions cache
                $this->pagePermissionCache = $this->pageFactory->query();
            }
        }
        catch (\PDOException $e) {
            $this->getLog()->info('SQL Error getting permissions: %s', $e->getMessage());

            return false;
        }

        // Home route
        if ($route === '/')
            return true;

        $route = explode('/', ltrim($route, '/'));

        // See if our route is in the page permission cache
        foreach ($this->pagePermissionCache as $page) {
            /* @var Page $page */
            if ($page->name == $route[0])
                return true;
        }

        $this->getLog()->debug('Route %s not viewable', $route[0]);
        return false;
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
            foreach ($this->permissionFactory->getByUserId($entity, $this->userId) as $permission) {
                /* @var \Xibo\Entity\Permission $permission */
                // Always take the max
                if (array_key_exists($permission->objectId, $this->permissionCache[$entity])) {
                    $old = $this->permissionCache[$entity][$permission->objectId];
                    // Create a new permission record with the max of current and new
                    $new = $this->permissionFactory->createEmpty();
                    $new->view = max($permission->view, $old->view);
                    $new->edit = max($permission->view, $old->view);
                    $new->delete = max($permission->view, $old->view);
                }
                else
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
            return $this->permissionFactory->getFullPermissions();
        }

        // Group Admins
        if ($this->userTypeId == 2 && count(array_intersect($this->groups, $this->userGroupFactory->getByUserId($object->getOwnerId()))))
            // Group Admin and in the same group as the owner.
            return $this->permissionFactory->getFullPermissions();

        // Get the permissions for that entity
        $permissions = $this->loadPermissions(get_class($object));

        // Check to see if our object is in the list
        if (array_key_exists($object->getId(), $permissions))
            return $permissions[$object->getId()];
        else
            return $this->permissionFactory->createEmpty();
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

        // Group Admins
        if ($this->userTypeId == 2 && count(array_intersect($this->groups, $this->userGroupFactory->getByUserId($object->getOwnerId()))))
            // Group Admin and in the same group as the owner.
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

        // Group Admins
        if ($this->userTypeId == 2 && count(array_intersect($this->groups, $this->userGroupFactory->getByUserId($object->getOwnerId()))))
            // Group Admin and in the same group as the owner.
            return true;

        // Get the permissions for that entity
        $permissions = $this->loadPermissions($object->permissionsClass());

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

        // Group Admins
        if ($this->userTypeId == 2 && count(array_intersect($this->groups, $this->userGroupFactory->getByUserId($object->getOwnerId()))))
            // Group Admin and in the same group as the owner.
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
        // Group Admins
        else if ($this->userTypeId == 2 && count(array_intersect($this->groups, $this->userGroupFactory->getByUserId($object->getOwnerId()))))
            // Group Admin and in the same group as the owner.
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
     * Is a super admin
     * @return bool
     */
    public function isSuperAdmin()
    {
        return ($this->getUserTypeId() == 1);
    }

    /**
     * Is Group Admin
     * @return bool
     */
    public function isGroupAdmin()
    {
       return ($this->getUserTypeId() == 2);
    }

    /**
     * Is this users library quota full
     * @throws LibraryFullException when the library is full or cannot be determined
     */
    public function isQuotaFullByUser()
    {
        $dbh = $this->getStore()->getConnection();
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

        if ($userQuota > 0) {

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

            $fileSize = intval($row['SumSize']);

            if (($fileSize / 1024) <= $userQuota)
                throw new LibraryFullException(__('You have exceeded your library quota'));
        }
    }

    /**
     * Password hashing with PBKDF2.
     * Author: havoc AT defuse.ca
     * www: https://defuse.ca/php-pbkdf2.htm
     * @param string $password
     * @return string
     */
    private function createHash($password)
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

    /**
     * Compares two strings $a and $b in length-constant time.
     * @param string $a
     * @param string $b
     * @return bool
     */
    private function slowEquals($a, $b)
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
        $policy = $this->configService->GetSetting('USER_PASSWORD_POLICY');

        if ($policy != '')
        {
            $policyError = $this->configService->GetSetting('USER_PASSWORD_ERROR');
            $policyError = ($policyError == '') ? __('Your password does not meet the required complexity') : $policyError;

            if(!preg_match($policy, $password, $matches))
                throw new \InvalidArgumentException($policyError);
        }
    }

    /**
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by https://defuse.ca
     * With improvements by http://www.variations-of-shadow.com
     *
     * @param string $algorithm The hash algorithm to use. Recommended: SHA256
     * @param string $password The password.
     * @param string $salt A salt that is unique to the password.
     * @param int $count Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * @param int $key_length The length of the derived key in bytes.
     * @param bool $raw_output If true, the key is returned in raw binary format. Hex encoded otherwise.
     * @return string A $key_length-byte key derived from the password and salt.
     */
    public function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        $algorithm = strtolower($algorithm);
        if (!in_array($algorithm, hash_algos(), true))
            throw new \InvalidArgumentException('PBKDF2 ERROR: Invalid hash algorithm.');
        if ($count <= 0 || $key_length <= 0)
            throw new \InvalidArgumentException('PBKDF2 ERROR: Invalid parameters.');

        $hash_length = strlen(hash($algorithm, "", true));
        $block_count = ceil($key_length / $hash_length);

        $output = "";
        for ($i = 1; $i <= $block_count; $i++) {
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

        if ($raw_output)
            return substr($output, 0, $key_length);
        else
            return bin2hex(substr($output, 0, $key_length));
    }
}
