<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Entity;

use League\OAuth2\Server\Entity\ScopeEntity;
use Respect\Validation\Validator as v;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\DuplicateEntityException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\LibraryFullException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PageFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserOptionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Pbkdf2Hash;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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
    public $newUserWizard = 0;

    /**
     * @SWG\Property(description="A flag indicating whether the user is retired")
     * @var int
     */
    public $retired;

    private $CSPRNG;
    private $password;

    /**
     * @SWG\Property(description="A flag indicating whether password change should be forced for this user")
     * @var int
     */
    public $isPasswordChangeRequired = 0;

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
     * @SWG\Property(description="An array of Playlists owned by this User")
     * @var Playlist[]
     */
    public $playlists = [];

    /**
     * @SWG\Property(description="An array of Display Groups owned by this User")
     * @var DisplayGroup[]
     */
    public $displayGroups = [];

    /**
     * @SWG\Property(description="An array of Dayparts owned by this User")
     * @var DayPart[]
     */
    public $dayParts = [];

    /**
     * @SWG\Property(description="The name of home page")
     * @var string
     */
    public $homePage;

    /**
     * @SWG\Property(description="Does this Group receive system notifications.")
     * @var int
     */
    public $isSystemNotification = 0;

    /**
     * @SWG\Property(description="Does this Group receive system notifications.")
     * @var int
     */
    public $isDisplayNotification = 0;

    /**
     * @SWG\Property(description="The two factor type id")
     * @var int
     */
    public $twoFactorTypeId;

    /**
     * @SWG\Property(description="Two Factor authorisation shared secret for this user")
     * @var string
     */
    public $twoFactorSecret;

    /**
     * @SWG\Property(description="Two Factor authorisation recovery codes", @SWG\Items(type="string"))
     * @var array
     */
    public $twoFactorRecoveryCodes = [];

    /**
     * @SWG\Property(description="Should we show content added by standard users in relevant grids (1) or content added by the DOOH user? (2). Super admins have an option to change this in their User profile. ")
     * @var int
     */
    public $showContentFrom = 1;

    /**
     * @var UserOption[]
     */
    private $userOptions = [];

    /**
     * User options that have been removed
     * @var \Xibo\Entity\UserOption[]
     */
    private $userOptionsRemoved = [];

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

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @var PlayerVersionFactory */
    private $playerVersionFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var DataSetFactory */
    private $dataSetFactory;

    /** @var DayPartFactory */
    private $dayPartFactory;

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
        $this->excludeProperty('twoFactorSecret');
        $this->excludeProperty('twoFactorRecoveryCodes');
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
     * Set Child Object Dependencies
     *  must be set before calling Load with all objects
     * @param CampaignFactory $campaignFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayFactory $displayFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param WidgetFactory $widgetFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param PlaylistFactory $playlistFactory
     * @param DataSetFactory $dataSetFactory
     * @param DayPartFactory $dayPartFactory
     * @return $this
     */
    public function setChildObjectDependencies($campaignFactory, $layoutFactory, $mediaFactory, $scheduleFactory, $displayFactory, $displayGroupFactory, $widgetFactory, $playerVersionFactory, $playlistFactory, $dataSetFactory, $dayPartFactory)
    {
        $this->campaignFactory = $campaignFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->widgetFactory = $widgetFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->dayPartFactory = $dayPartFactory;
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
     * Remove the provided option
     * @param \Xibo\Entity\UserOption $option
     * @return $this
     */
    private function removeOption($option)
    {
        $this->getLog()->debug('Removing: ' . $option);

        $this->userOptionsRemoved[] = $option;
        $this->userOptions = array_diff($this->userOptions, [$option]);
        return $this;
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
        } catch (NotFoundException $e) {
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
            $option = $this->getOption($option);

            if ($value === null) {
                $this->removeOption($option);
            } else {
                $option->value = $value;
            }
        } catch (NotFoundException $e) {
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
        // Validate the old password if one is provided
        if ($oldPassword != null) {
            $this->checkPassword($oldPassword);
        }

        // Test against a policy if one exists
        $this->testPasswordAgainstPolicy($password);

        // Set the hash
        $this->setNewPasswordHash($password);
    }

    /**
     * Set a new password and hash
     * @param string $password
     */
    private function setNewPasswordHash($password)
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->CSPRNG = 2;
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

        if ($this->CSPRNG == 0) {
            // Password is tested using a plain MD5 check
            if ($this->password != md5($password))
                throw new AccessDeniedException();
        }
        else if ($this->CSPRNG == 1) {
            // Test with Pbkdf2
            try {
                if (!Pbkdf2Hash::verifyPassword($password, $this->password)) {
                    $this->getLog()->debug('Password failed Pbkdf2Hash Check.');
                    throw new AccessDeniedException();
                }
            } catch (\InvalidArgumentException $e) {
                $this->getLog()->warning('Invalid password hash stored for userId ' . $this->userId);
                $this->getLog()->debug('Hash error: ' . $e->getMessage());
            }
        }
        else {
            if (!password_verify($password, $this->password)) {
                $this->getLog()->debug('Password failed Hash Check.');
                throw new AccessDeniedException();
            }
        }

        $this->getLog()->debug('Password checked out OK');

        // Do we need to convert?
        $this->updateHashIfRequired($password);
    }

    /**
     * Update hash if required
     * @param string $password
     */
    private function updateHashIfRequired($password)
    {
        if (($this->CSPRNG == 0 || $this->CSPRNG == 1) || ($this->CSPRNG == 2 && password_needs_rehash($this->password, PASSWORD_DEFAULT))) {
            $this->getLog()->debug('Converting password to use latest hash');

            // Set the hash
            $this->setNewPasswordHash($password);

            // Save
            $this->save(['validate' => false, 'passwordUpdate' => true]);
        }
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
     * @throws NotFoundException
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
            if ($this->campaignFactory == null || $this->layoutFactory == null || $this->mediaFactory == null || $this->scheduleFactory == null || $this->playlistFactory == null || $this->displayGroupFactory == null || $this->dayPartFactory == null)
                throw new \RuntimeException('Cannot load user with all objects without first calling setChildObjectDependencies');

            $this->campaigns = $this->campaignFactory->getByOwnerId($this->userId);
            $this->layouts = $this->layoutFactory->getByOwnerId($this->userId);
            $this->media = $this->mediaFactory->getByOwnerId($this->userId);
            $this->events = $this->scheduleFactory->getByOwnerId($this->userId);
            $this->playlists = $this->playlistFactory->getByOwnerId($this->userId);
            $this->displayGroups = $this->displayGroupFactory->getByOwnerId($this->userId, -1);
            $this->dayParts = $this->dayPartFactory->getByOwnerId($this->userId);
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

        $count = count($this->campaigns) + count($this->layouts) + count($this->media) + count($this->events) + count($this->playlists) + count($this->displayGroups) + count($this->dayParts);
        $this->getLog()->debug('Counted Children on %d, there are %d', $this->userId, $count);

        return $count;
    }

    /**
     * Reassign all
     * @param User $user
     * @throws XiboException
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
            $event->load();
            $event->setOwner($user->getOwnerId());
            $event->setDisplayFactory($this->displayFactory);
            $event->load();
            $event->save(['generate' => false]);
        }
        foreach ($this->layouts as $layout) {
            /* @var Layout $layout */
            $layout->setOwner($user->getOwnerId(), true);
            $layout->save(['saveTags' => false]);
        }
        foreach ($this->campaigns as $campaign) {
            /* @var Campaign $campaign */
            $campaign->setOwner($user->getOwnerId());
            $campaign->save(['saveTags' => false]);
        }
        foreach ($this->playlists as $playlist) {
            $playlist->setOwner($user->getOwnerId());
            $playlist->save(['saveTags' => false]);
        }
        foreach($this->displayGroups as $displayGroup) {
            $displayGroup->setOwner($user->getOwnerId());
            $displayGroup->save(['saveTags' => false, 'manageDynamicDisplayLinks' => false]);
        }
        foreach($this->dataSetFactory->getByOwnerId($this->userId) as $dataSet) {
            $dataSet->setOwner($user->getOwnerId());
            $dataSet->save();
        }

        // Reassign resolutions
        $this->getStore()->update('UPDATE `resolution` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $user->userId,
            'oldUserId' => $this->userId
        ]);

        // Reassign Dayparts
        $this->getStore()->update('UPDATE `daypart` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $user->userId,
            'oldUserId' => $this->userId
        ]);

        // Load again
        $this->loaded = false;
        $this->load(true);

        $this->getLog()->debug('Reassign and reload complete, there are %d children', $this->countChildren());
    }

    /**
     * Validate
     * @throws XiboException
     */
    public function validate()
    {
        if (!v::alnum('_.-')->length(1, 50)->validate($this->userName) && !v::email()->validate($this->userName))
            throw new InvalidArgumentException(__('User name must be between 1 and 50 characters.'), 'userName');

        if (!v::stringType()->notEmpty()->validate($this->password))
            throw new InvalidArgumentException(__('Please enter a Password.'), 'password');

        if (!v::intType()->validate($this->libraryQuota))
            throw new InvalidArgumentException(__('Library Quota must be a whole number.'), 'libraryQuota');

        if (!empty($this->email) && !v::email()->validate($this->email))
            throw new InvalidArgumentException(__('Please enter a valid email address or leave it empty.'), 'email');

        try {
            $user = $this->userFactory->getByName($this->userName);

            if ($this->userId == null || $this->userId != $user->userId)
                throw new DuplicateEntityException(__('There is already a user with this name. Please choose another.'));
        }
        catch (NotFoundException $e) {

        }

        try {
            $this->pageFactory->getById($this->homePageId);
        }
        catch (NotFoundException $e) {
            throw new InvalidArgumentException(__('Selected home page does not exist'), 'homePageId');
        }

        // Library quota
        if (!empty($this->libraryQuota) && $this->libraryQuota < 0) {
            throw new InvalidArgumentException(__('Library Quota must be a positive number.'), 'libraryQuota');
        }
    }

    /**
     * Save User
     * @param array $options
     * @throws \Xibo\Exception\XiboException
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
            // Remove any that have been cleared
            foreach ($this->userOptionsRemoved as $userOption) {
                $userOption->delete();
            }

            // Save all Options
            foreach ($this->userOptions as $userOption) {
                /* @var UserOption $userOption */
                $userOption->userId = $this->userId;
                $userOption->save();
            }
        }
    }

    /**
     * Delete User
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws XiboException
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
            $campaign->setChildObjectDependencies($this->layoutFactory);
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
            $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
            $media->delete();
        }

        // Delete Playlists owned by this user
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */
            $playlist->delete();
        }

        // Display Groups owned by this user
        foreach($this->displayGroupFactory->getByOwnerId($this->userId) as $displayGroup) {
            $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
            $displayGroup->delete();
        }

        foreach($this->dataSetFactory->getByOwnerId($this->userId) as $dataSet) {
            $dataSet->delete();
        }

        // Delete user specific entities
        $this->getStore()->update('DELETE FROM `resolution` WHERE userId = :userId', ['userId' => $this->userId]);
        $this->getStore()->update('DELETE FROM `daypart` WHERE userId = :userId', ['userId' => $this->userId]);
        $this->getStore()->update('DELETE FROM `session` WHERE userId = :userId', ['userId' => $this->userId]);
        $this->getStore()->update('DELETE FROM `user` WHERE userId = :userId', ['userId' => $this->userId]);
    }

    /**
     * Add user
     */
    private function add()
    {
        $sql = 'INSERT INTO `user` (UserName, UserPassword, isPasswordChangeRequired, usertypeid, newUserWizard, email, homePageId, CSPRNG, firstName, lastName, phone, ref1, ref2, ref3, ref4, ref5)
                     VALUES (:userName, :password, :isPasswordChangeRequired, :userTypeId, :newUserWizard, :email, :homePageId, :CSPRNG, :firstName, :lastName, :phone, :ref1, :ref2, :ref3, :ref4, :ref5)';

        // Get the ID of the record we just inserted
        $this->userId = $this->getStore()->insert($sql, [
            'userName' => $this->userName,
            'password' => $this->password,
            'isPasswordChangeRequired' => $this->isPasswordChangeRequired,
            'userTypeId' => $this->userTypeId,
            'newUserWizard' => $this->newUserWizard,
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
        $group->isDisplayNotification = $this->isDisplayNotification;
        $group->save();
    }

    /**
     * Update user
     */
    private function update()
    {
        $this->getLog()->debug('Update userId %d.', $this->userId);

        $sql = 'UPDATE `user` SET UserName = :userName,
                  homePageId = :homePageId,
                  Email = :email,
                  Retired = :retired,
                  userTypeId = :userTypeId,
                  newUserWizard = :newUserWizard,
                  CSPRNG = :CSPRNG,
                  `UserPassword` = :password,
                  `isPasswordChangeRequired` = :isPasswordChangeRequired,
                  `twoFactorTypeId` = :twoFactorTypeId,
                  `twoFactorSecret` = :twoFactorSecret,
                  `twoFactorRecoveryCodes` = :twoFactorRecoveryCodes,
                  `showContentFrom` = :showContentFrom,
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
            'newUserWizard' => $this->newUserWizard,
            'CSPRNG' => $this->CSPRNG,
            'password' => $this->password,
            'isPasswordChangeRequired' => $this->isPasswordChangeRequired,
            'twoFactorTypeId' => $this->twoFactorTypeId,
            'twoFactorSecret' => $this->twoFactorSecret,
            'twoFactorRecoveryCodes' => ($this->twoFactorRecoveryCodes == '') ? null : json_encode($this->twoFactorRecoveryCodes),
            'showContentFrom' => $this->showContentFrom,
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
        $group->isDisplayNotification = $this->isDisplayNotification;
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
     * @param bool $forcePasswordChange
     */
    public function touch($forcePasswordChange = false)
    {
        $sql = 'UPDATE `user` SET lastAccessed = :time ';

        if ($forcePasswordChange) {
            $sql .= ' , isPasswordChangeRequired = 1 ';
        }

        $sql .= ' WHERE userId = :userId';

        // This needs to happen on a separate connection
        $this->getStore()->update($sql, [
            'userId' => $this->userId,
            'time' => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * Authenticates the route given against the user credentials held
     * @param $route string
     * @param $method string
     * @param $scopes array[ScopeEntity]
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\NotFoundException
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

        // Super-admins and DOOH users get all routes
        if ($this->isSuperAdmin())
            return true;

        // All users have access to the logout page
        if ($route === '/logout')
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
     * Given an array of routes, count the ones that are viewable
     * @param $routes
     * @return int
     * @throws \Xibo\Exception\ConfigurationException
     */
    public function countViewable($routes)
    {
        // Shortcut for super admins.
        if ($this->isSuperAdmin()) {
            return count($routes);
        }

        // Test each route
        $count = 0;

        foreach ($routes as $route) {
            if ($this->routeViewable($route)) {
                $count++;
            }
        }

        return $count;
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
                    $new->edit = max($permission->edit, $old->edit);
                    $new->delete = max($permission->delete, $old->delete);

                    $this->permissionCache[$entity][$permission->objectId] = $new;
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
        if (!method_exists($object, 'getId') || !method_exists($object, 'getOwnerId') || !method_exists($object, 'permissionsClass'))
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
        if ($this->isSuperAdmin() || $this->userId == $object->getOwnerId()) {
            return $this->permissionFactory->getFullPermissions();
        }

        // Group Admins
        if ($this->userTypeId == 2 && count(array_intersect($this->groups, $this->userGroupFactory->getByUserId($object->getOwnerId()))))
            // Group Admin and in the same group as the owner.
            return $this->permissionFactory->getFullPermissions();

        // Get the permissions for that entity
        $permissions = $this->loadPermissions($object->permissionsClass());

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
     * @throws \Xibo\Exception\NotFoundException
     */
    public function checkViewable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        if ($this->isSuperAdmin() || $this->userId == $object->getOwnerId())
            return true;

        // Group Admins
        if ($this->userTypeId == 2 && count(array_intersect($this->groups, $this->userGroupFactory->getByUserId($object->getOwnerId()))))
            // Group Admin and in the same group as the owner.
            return true;

        // Get the permissions for that entity
        $permissions = $this->loadPermissions($object->permissionsClass());

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
     * @throws \Xibo\Exception\NotFoundException
     */
    public function checkEditable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        if ($this->isSuperAdmin() || $this->userId == $object->getOwnerId())
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
     * @throws \Xibo\Exception\NotFoundException
     */
    public function checkDeleteable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        // Note here that the DOOH user isn't allowed to outright delete other users things
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
            return ($permissions[$object->getId()]->delete == 1);
        else
            return false;
    }

    /**
     * Check the given objects permissions are modify-able
     * @param object $object
     * @return bool
     * @throws \Xibo\Exception\NotFoundException
     */
    public function checkPermissionsModifyable($object)
    {
        // Check that this object has the necessary methods
        $this->checkObjectCompatibility($object);

        // Admin users
        // Note here that the DOOH user isn't allowed to outright change permissions on  other users things
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
        return ($this->userTypeId == 1 || $this->userTypeId == 4);
    }

    /**
     * Is Group Admin
     * @return bool
     */
    public function isGroupAdmin()
    {
       return ($this->userTypeId == 2);
    }

    /**
     * Is this users library quota full
     * @param boolean $reconnect
     * @throws LibraryFullException when the library is full or cannot be determined
     */
    public function isQuotaFullByUser($reconnect = false)
    {
        $groupId = 0;
        $userQuota = 0;

        // Get the maximum quota of this users groups and their own quota
        $rows = $this->getStore()->select('
            SELECT group.groupId, IFNULL(group.libraryQuota, 0) AS libraryQuota
              FROM `group`
                INNER JOIN `lkusergroup`
                ON group.groupId = lkusergroup.groupId
             WHERE lkusergroup.userId = :userId
            ORDER BY `group`.isUserSpecific DESC, IFNULL(group.libraryQuota, 0) DESC
        ', ['userId' => $this->userId], null, $reconnect);

        if (count($rows) <= 0) {
            throw new LibraryFullException('Problem calculating this users library quota.');
        }

        foreach ($rows as $row) {

            if ($row['libraryQuota'] > 0) {
                $groupId = $row['groupId'];
                $userQuota = intval($row['libraryQuota']);
                break;
            }
        }

        if ($userQuota > 0) {
            // If there is a quota, then test it against the current library position for this user.
            //   use the groupId that generated the quota in order to calculate the usage
            $rows = $this->getStore()->select('
              SELECT IFNULL(SUM(FileSize), 0) AS SumSize
                FROM `media`
                  INNER JOIN `lkusergroup`
                  ON lkusergroup.userId = media.userId
               WHERE lkusergroup.groupId = :groupId
            ', ['groupId' => $groupId], null, true);

            if (count($rows) <= 0) {
                throw new LibraryFullException("Error Processing Request", 1);
            }

            $fileSize = intval($rows[0]['SumSize']);

            if (($fileSize / 1024) >= $userQuota) {
                $this->getLog()->debug('User has exceeded library quota. FileSize: ' . $fileSize . ' bytes, quota is ' . $userQuota * 1024);
                throw new LibraryFullException(__('You have exceeded your library quota'));
            }
        }
    }

    /**
     * Tests the supplied password against the password policy
     * @param string $password
     */
    public function testPasswordAgainstPolicy($password)
    {
        // Check password complexity
        $policy = $this->configService->getSetting('USER_PASSWORD_POLICY');

        if ($policy != '')
        {
            $policyError = $this->configService->getSetting('USER_PASSWORD_ERROR');
            $policyError = ($policyError == '') ? __('Your password does not meet the required complexity') : $policyError;

            if(!preg_match($policy, $password, $matches))
                throw new \InvalidArgumentException($policyError);
        }
    }

    /**
     * @return UserOption[]
     */
    public function getUserOptions()
    {
        // Don't return anything with Grid in it (these have to be specifically requested).
        return array_filter($this->userOptions, function($element) {
            return !(stripos($element->option, 'Grid'));
        });
    }

    /**
     * Clear the two factor stored secret and recovery codes
     */
    public function clearTwoFactor()
    {
        $this->twoFactorTypeId = 0;
        $this->twoFactorSecret = NULL;
        $this->twoFactorRecoveryCodes = NULL;

        $sql = 'UPDATE `user` SET twoFactorSecret = :twoFactorSecret,
                  twoFactorTypeId = :twoFactorTypeId,
                  twoFactorRecoveryCodes =:twoFactorRecoveryCodes
               WHERE userId = :userId';

        $params = [
            'userId' => $this->userId,
            'twoFactorSecret' => $this->twoFactorSecret,
            'twoFactorTypeId' => $this->twoFactorTypeId,
            'twoFactorRecoveryCodes' => $this->twoFactorRecoveryCodes
        ];

        $this->getStore()->update($sql, $params);
    }

    public function updateRecoveryCodes($recoveryCodes)
    {
        $sql = 'UPDATE `user` SET twoFactorRecoveryCodes = :twoFactorRecoveryCodes WHERE userId = :userId';

        $params = [
            'userId' => $this->userId,
            'twoFactorRecoveryCodes' => $recoveryCodes
        ];

        $this->getStore()->update($sql, $params);
    }
}
