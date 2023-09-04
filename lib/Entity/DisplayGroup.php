<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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


use Carbon\Carbon;
use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DisplayGroup
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DisplayGroup implements \JsonSerializable
{
    use EntityTrait;
    use TagLinkTrait;

    /**
     * @SWG\Property(
     *  description="The displayGroup Id"
     * )
     * @var int
     */
    public $displayGroupId;

    /**
     * @SWG\Property(
     *  description="The displayGroup Name"
     * )
     * @var string
     */
    public $displayGroup;

    /**
     * @SWG\Property(
     *  description="The displayGroup Description"
     * )
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(
     *  description="A flag indicating whether this displayGroup is a single display displayGroup",
     * )
     * @var int
     */
    public $isDisplaySpecific = 0;

    /**
     * @SWG\Property(
     *  description="A flag indicating whether this displayGroup is dynamic",
     * )
     * @var int
     */
    public $isDynamic = 0;

    /**
     * @SWG\Property(
     *  description="Criteria for this dynamic group. A comma separated set of regular expressions to apply",
     * )
     * @var string
     */
    public $dynamicCriteria;

    /**
     * @SWG\Property(description="Which logical operator should be used when filtering by multiple dynamic criteria? OR|AND")
     * @var string
     */
    public $dynamicCriteriaLogicalOperator;

    /**
     * @SWG\Property(
     *  description="Criteria for this dynamic group. A comma separated set of tags to apply",
     * )
     * @var string
     */
    public $dynamicCriteriaTags;

    /**
     * @SWG\Property(description="Flag indicating whether to filter by exact Tag match")
     * @var int
     */
    public $dynamicCriteriaExactTags;

    /**
     * @SWG\Property(description="Which logical operator should be used when filtering by multiple Tags? OR|AND")
     * @var string
     */
    public $dynamicCriteriaTagsLogicalOperator;

    /**
     * @SWG\Property(
     *  description="The UserId who owns this display group",
     * )
     * @var int
     */
    public $userId = 0;

    /**
     * @SWG\Property(description="Tags associated with this Display Group, array of TagLink objects")
     * @var TagLink[]
     */
    public $tags = [];

    /**
     * @SWG\Property(description="The display bandwidth limit")
     * @var int
     */
    public $bandwidthLimit;

    /**
     * @SWG\Property(description="A comma separated list of groups/users with permissions to this DisplayGroup")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * @SWG\Property(description="The datetime this entity was created")
     * @var string
     */
    public $createdDt;

    /**
     * @SWG\Property(description="The datetime this entity was last modified")
     * @var string
     */
    public $modifiedDt;

    /**
     * @SWG\Property(description="The id of the Folder this Display Group belongs to")
     * @var int
     */
    public $folderId;

    /**
     * @SWG\Property(description="The id of the Folder responsible for providing permissions for this Display Group")
     * @var int
     */
    public $permissionsFolderId;

    /**
     * @SWG\Property(description="Optional Reference 1")
     * @var string
     */
    public $ref1;

    /**
     * @SWG\Property(description="Optional Reference 2")
     * @var string
     */
    public $ref2;

    /**
     * @SWG\Property(description="Optional Reference 3")
     * @var string
     */
    public $ref3;

    /**
     * @SWG\Property(description="Optional Reference 4")
     * @var string
     */
    public $ref4;

    /**
     * @SWG\Property(description="Optional Reference 5")
     * @var string
     */
    public $ref5;

    // Child Items the Display Group is linked to
    public $displays = [];
    public $media = [];
    public $layouts = [];
    public $events = [];
    private $displayGroups = [];
    private $permissions = [];
    /** @var TagLink[] */
    private $unlinkTags = [];
    /** @var TagLink[] */
    private $linkTags = [];
    private $jsonInclude = ['displayGroupId', 'displayGroup'];

    // Track original assignments
    private $originalDisplayGroups = [];

    /**
     * Is notify required during save?
     * @var bool
     */
    private $notifyRequired = false;

    /**
     * Is collect required?
     * @var bool
     */
    private $collectRequired = true;

    /**
     * @var bool Are we allowed to notify?
     */
    private $allowNotify = true;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param DisplayGroupFactory $displayGroupFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $dispatcher, $displayGroupFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->displayGroupFactory = $displayGroupFactory;
        $this->permissionFactory = $permissionFactory;
    }

    public function setDisplayFactory(DisplayFactory $displayFactory)
    {
        $this->displayFactory = $displayFactory;
    }

    public function __clone()
    {
        $this->displayGroupId = null;
        $this->originalDisplayGroups = [];
        $this->loaded = false;

        if ($this->isDynamic) {
            $this->clearDisplays()->clearDisplayGroups();
        }
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->displayGroupId;
    }

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Set the owner of this group
     * @param $userId
     */
    public function setOwner($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return bool
     */
    public function canChangeOwner()
    {
        return $this->isDisplaySpecific == 0;
    }

    /**
     * Set Collection Required
     *  If true will send a player action to collect immediately
     * @param bool|true $collectRequired
     */
    public function setCollectRequired($collectRequired = true)
    {
        $this->collectRequired = $collectRequired;
    }

    /**
     * Set the Owner of this Group
     * @param Display $display
     * @throws NotFoundException
     */
    public function setDisplaySpecificDisplay($display)
    {
        $this->load();

        $this->isDisplaySpecific = 1;
        $this->assignDisplay($display);
    }

    public function clearDisplays(): DisplayGroup
    {
        $this->displays = [];
        return $this;
    }

    public function clearDisplayGroups(): DisplayGroup
    {
        $this->displayGroups = [];
        return $this;
    }

    public function clearTags(): DisplayGroup
    {
        $this->tags = [];
        return $this;
    }

    public function clearLayouts(): DisplayGroup
    {
        $this->layouts = [];
        return $this;
    }

    public function clearMedia(): DisplayGroup
    {
        $this->media = [];
        return $this;
    }

    /**
     * Set the Media Status to Incomplete
     * @param int[] $displayIds
     */
    public function notify($displayIds = [])
    {
        if ($this->allowNotify) {

            $notify = $this->displayFactory->getDisplayNotifyService();

            if ($this->collectRequired)
                $notify->collectNow();

            if (count($displayIds) > 0) {
                foreach ($displayIds as $displayId) {
                    $notify->notifyByDisplayId($displayId);
                }
            } else {
                $notify->notifyByDisplayGroupId($this->displayGroupId);
            }
        }
    }

    /**
     * Assign Display
     * @param Display $display
     * @throws NotFoundException
     */
    public function assignDisplay($display)
    {
        $found = false;
        foreach ($this->displays as $existingDisplay) {
            if ($existingDisplay->getId() === $display->getId()) {
                $found = true;
                break;
            }
        }

        if (!$found)
            $this->displays[] = $display;
    }

    /**
     * Unassign Display
     * @param Display $display
     * @throws NotFoundException
     */
    public function unassignDisplay($display)
    {
        // Changes made?
        $countBefore = count($this->displays);

        $this->displays = array_udiff($this->displays, [$display], function($a, $b) {
            /**
             * @var Display $a
             * @var Display $b
             */
            return $a->getId() - $b->getId();
        });

        // Notify if necessary
        if ($countBefore !== count($this->displays))
            $this->notifyRequired = true;
    }

    /**
     * Assign DisplayGroup
     * @param DisplayGroup $displayGroup
     * @throws NotFoundException
     */
    public function assignDisplayGroup($displayGroup)
    {
        if (!in_array($displayGroup, $this->displayGroups))
            $this->displayGroups[] = $displayGroup;
    }

    /**
     * Unassign DisplayGroup
     * @param DisplayGroup $displayGroup
     * @throws NotFoundException
     */
    public function unassignDisplayGroup($displayGroup)
    {
        // Changes made?
        $countBefore = count($this->displayGroups);

        $this->displayGroups = array_udiff($this->displayGroups, [$displayGroup], function($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });

        // Notify if necessary
        if ($countBefore !== count($this->displayGroups))
            $this->notifyRequired = true;
    }

    /**
     * Assign Media
     * @param Media $media
     * @throws NotFoundException
     */
    public function assignMedia($media)
    {
        if (!in_array($media, $this->media)) {
            $this->media[] = $media;

            // We should notify
            $this->notifyRequired = true;
        }
    }

    /**
     * Unassign Media
     * @param Media $media
     * @throws NotFoundException
     */
    public function unassignMedia($media)
    {
        // Changes made?
        $countBefore = count($this->media);

        $this->media = array_udiff($this->media, [$media], function($a, $b) {
            /**
             * @var Media $a
             * @var Media $b
             */
            return $a->getId() - $b->getId();
        });

        // Notify if necessary
        if ($countBefore !== count($this->media))
            $this->notifyRequired = true;
    }

    /**
     * Assign Layout
     * @param Layout $layout
     * @throws NotFoundException
     */
    public function assignLayout($layout)
    {
        if (!in_array($layout, $this->layouts)) {
            $this->layouts[] = $layout;

            // We should notify
            $this->notifyRequired = true;
        }
    }

    /**
     * Unassign Layout
     * @param Layout $layout
     * @throws NotFoundException
     */
    public function unassignLayout($layout)
    {
        // Changes made?
        $countBefore = count($this->layouts);

        $this->layouts = array_udiff($this->layouts, [$layout], function($a, $b) {
            /**
             * @var Layout $a
             * @var Layout $b
             */
            return $a->getId() - $b->getId();
        });

        // Notify if necessary
        if ($countBefore !== count($this->layouts))
            $this->notifyRequired = true;
    }

    /**
     * Load the contents for this display group
     * @param array $options
     * @throws NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadTags' => true
        ], $options);

        if ($this->loaded || $this->displayGroupId == null || $this->displayGroupId == 0) {
            return;
        }

        $this->permissions = $this->permissionFactory->getByObjectId(get_class($this), $this->displayGroupId);

        $this->displayGroups = $this->displayGroupFactory->getByParentId($this->displayGroupId);

        // Set the originals
        $this->originalDisplayGroups = $this->displayGroups;

        // We are loaded
        $this->loaded = true;
    }

    /**
     * Validate this display
     * @throws DuplicateEntityException
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->displayGroup)) {
            throw new InvalidArgumentException(__('Please enter a display group name'), 'displayGroup');
        }

        if (!empty($this->description) && !v::stringType()->length(null, 254)->validate($this->description)) {
            throw new InvalidArgumentException(__('Description can not be longer than 254 characters'), 'description');
        }

        if ($this->isDisplaySpecific == 0) {
            // Check the name
            $result = $this->getStore()->select('SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = :displayGroup AND IsDisplaySpecific = 0 AND displayGroupId <> :displayGroupId', [
                'displayGroup' => $this->displayGroup,
                'displayGroupId' => (($this->displayGroupId == null) ? 0 : $this->displayGroupId)
            ]);

            if (count($result) > 0) {
                throw new DuplicateEntityException(sprintf(__('You already own a display group called "%s". Please choose another name.'), $this->displayGroup));
            }
            // If we are dynamic, then make sure we have some criteria
            if ($this->isDynamic == 1 && ($this->dynamicCriteria == '' && $this->dynamicCriteriaTags == '')) {
                throw new InvalidArgumentException(__('Dynamic Display Groups must have at least one Criteria specified.'), 'dynamicCriteria');
            }
        }
    }

    /**
     * Save
     * @param array $options
     * @throws GeneralException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'saveGroup' => true,
            'manageLinks' => true,
            'manageDisplayLinks' => true,
            'manageDynamicDisplayLinks' => true,
            'allowNotify' => true,
            'saveTags' => true,
            'setModifiedDt' => true,
        ], $options);

        // Should we allow notification or not?
        $this->allowNotify = $options['allowNotify'];

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->displayGroupId == null || $this->displayGroupId == 0) {
            $this->add();
            $this->loaded = true;
        } else if ($options['saveGroup']) {
            $this->edit($options);
        }

        if ($options['saveTags']) {
            // Remove unwanted ones
            if (is_array($this->unlinkTags)) {
                foreach ($this->unlinkTags as $tag) {
                    $this->unlinkTagFromEntity('lktagdisplaygroup', 'displayGroupId', $this->displayGroupId, $tag->tagId);
                }
            }

            // Save the tags
            if (is_array($this->linkTags)) {
                foreach ($this->linkTags as $tag) {
                    $this->linkTagToEntity('lktagdisplaygroup', 'displayGroupId', $this->displayGroupId, $tag->tagId, $tag->value);
                }
            }
        }

        if ($this->loaded) {
            $this->getLog()->debug('Manage links');

            if ($options['manageLinks']) {
                // Handle any changes in the media linked
                $this->linkMedia();
                $this->unlinkMedia();

                // Handle any changes in the layouts linked
                $this->linkLayouts();
                $this->unlinkLayouts();
            }

            if ($options['manageDisplayLinks']) {
                // Handle any changes in the displays linked
                $this->manageDisplayLinks($options['manageDynamicDisplayLinks']);

                // Handle any group links
                $this->manageDisplayGroupLinks();
            }

        } else if ($this->isDynamic == 1 && $options['manageDynamicDisplayLinks']) {
            $this->manageDisplayLinks();
        }

        // Set media incomplete if necessary
        if ($this->notifyRequired) {
            $this->notify();
        }
    }

    /**
     * Delete
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function delete()
    {
        // Load everything for the delete
        $this->load();

        // Delete things this group can own
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->unassignDisplayGroup($this);
            $event->save([
                'audit' => false,
                'validate' => false,
                'deleteOrphaned' => true,
                'notify' => false
            ]);
        }

        $this->unlinkAllTagsFromEntity('lktagdisplaygroup', 'displayGroupId', $this->displayGroupId);

        // Delete assignments
        $this->removeAssignments();

        // delete link to ad campaign.
        $this->getStore()->update('DELETE FROM `lkcampaigndisplaygroup` WHERE displayGroupId = :displayGroupId', [
            'displayGroupId' => $this->displayGroupId
        ]);

        // Delete the Group itself
        $this->getStore()->update('DELETE FROM `displaygroup` WHERE DisplayGroupID = :displayGroupId', ['displayGroupId' => $this->displayGroupId]);
    }

    /**
     * Remove any assignments
     */
    public function removeAssignments()
    {
        $this->displays = [];
        $this->displayGroups = [];
        $this->layouts = [];
        $this->media = [];

        $this->unlinkDisplays();
        $this->unlinkAllDisplayGroups();
        $this->unlinkLayouts();
        $this->unlinkMedia();

        // Delete Notifications
        // NB: notifications aren't modelled as child objects because there could be many thousands of notifications on each
        // displaygroup. We consider the notification to be the parent here and it manages the assignments.
        // This does mean that we might end up with an empty notification (not assigned to anything)
        $this->getStore()->update('DELETE FROM `lknotificationdg` WHERE `displayGroupId` = :displayGroupId', ['displayGroupId' => $this->displayGroupId]);
    }

    private function add()
    {
        $time = Carbon::now()->format(DateFormatHelper::getSystemFormat());

        $this->displayGroupId = $this->getStore()->insert('
          INSERT INTO displaygroup (DisplayGroup, IsDisplaySpecific, Description, `isDynamic`, `dynamicCriteria`, `dynamicCriteriaLogicalOperator`, `dynamicCriteriaTags`, `dynamicCriteriaExactTags`, `dynamicCriteriaTagsLogicalOperator`, `userId`, `createdDt`, `modifiedDt`, `folderId`, `permissionsFolderId`, `ref1`, `ref2`, `ref3`, `ref4`, `ref5`)
            VALUES (:displayGroup, :isDisplaySpecific, :description, :isDynamic, :dynamicCriteria, :dynamicCriteriaLogicalOperator, :dynamicCriteriaTags, :dynamicCriteriaExactTags, :dynamicCriteriaTagsLogicalOperator, :userId, :createdDt, :modifiedDt, :folderId, :permissionsFolderId, :ref1, :ref2, :ref3, :ref4, :ref5)
        ', [
            'displayGroup' => $this->displayGroup,
            'isDisplaySpecific' => $this->isDisplaySpecific,
            'description' => $this->description,
            'isDynamic' => $this->isDynamic,
            'dynamicCriteria' => $this->dynamicCriteria,
            'dynamicCriteriaLogicalOperator' => $this->dynamicCriteriaLogicalOperator ?? 'OR',
            'dynamicCriteriaTags' => $this->dynamicCriteriaTags,
            'dynamicCriteriaExactTags' => $this->dynamicCriteriaExactTags ?? 0,
            'dynamicCriteriaTagsLogicalOperator' => $this->dynamicCriteriaTagsLogicalOperator ?? 'OR',
            'userId' => $this->userId,
            'createdDt' => $time,
            'modifiedDt' => $time,
            'folderId' => ($this->folderId === null) ? 1 : $this->folderId,
            'permissionsFolderId' => ($this->permissionsFolderId == null) ? 1 : $this-> permissionsFolderId,
            'ref1' => $this->ref1,
            'ref2' => $this->ref2,
            'ref3' => $this->ref3,
            'ref4' => $this->ref4,
            'ref5' => $this->ref5
        ]);

        // Insert my self link
        $this->getStore()->insert('INSERT INTO `lkdgdg` (`parentId`, `childId`, `depth`) VALUES (:parentId, :childId, 0)', [
            'parentId' => $this->displayGroupId,
            'childId' => $this->displayGroupId
        ]);
    }

    private function edit($options = [])
    {
        $this->getLog()->debug(sprintf('Updating Display Group. %s, %d', $this->displayGroup, $this->displayGroupId));

        $this->getStore()->update('
          UPDATE displaygroup
            SET DisplayGroup = :displayGroup,
              Description = :description,
              `isDynamic` = :isDynamic,
              `dynamicCriteria` = :dynamicCriteria,
              `dynamicCriteriaLogicalOperator` = :dynamicCriteriaLogicalOperator,
              `dynamicCriteriaTags` = :dynamicCriteriaTags,
              `dynamicCriteriaExactTags` = :dynamicCriteriaExactTags,
              `dynamicCriteriaTagsLogicalOperator` = :dynamicCriteriaTagsLogicalOperator,
              `bandwidthLimit` = :bandwidthLimit,
              `userId` = :userId,
              `modifiedDt` = :modifiedDt,
              `folderId` = :folderId,
              `permissionsFolderId` = :permissionsFolderId,
              `ref1` = :ref1,
              `ref2` = :ref2,
              `ref3` = :ref3,
              `ref4` = :ref4,
              `ref5` = :ref5
           WHERE DisplayGroupID = :displayGroupId
          ', [
            'displayGroup' => $this->displayGroup,
            'description' => $this->description,
            'displayGroupId' => $this->displayGroupId,
            'isDynamic' => $this->isDynamic,
            'dynamicCriteria' => $this->dynamicCriteria,
            'dynamicCriteriaLogicalOperator' => $this->dynamicCriteriaLogicalOperator ?? 'OR',
            'dynamicCriteriaTags' => $this->dynamicCriteriaTags,
            'dynamicCriteriaExactTags' => $this->dynamicCriteriaExactTags ?? 0,
            'dynamicCriteriaTagsLogicalOperator' => $this->dynamicCriteriaTagsLogicalOperator ?? 'OR',
            'bandwidthLimit' => $this->bandwidthLimit,
            'userId' => $this->userId,
            'modifiedDt' => $options['setModifiedDt']
                ? Carbon::now()->format(DateFormatHelper::getSystemFormat())
                : $this->modifiedDt,
            'folderId' => $this->folderId,
            'permissionsFolderId' => $this->permissionsFolderId,
            'ref1' => $this->ref1,
            'ref2' => $this->ref2,
            'ref3' => $this->ref3,
            'ref4' => $this->ref4,
            'ref5' => $this->ref5
        ]);
    }

    /**
     * Manage the links to this display, dynamic or otherwise
     * @var bool $manageDynamic
     * @throws NotFoundException
     */
    private function manageDisplayLinks($manageDynamic = true)
    {
        $this->getLog()->debug('Manage display links. Manage Dynamic = ' . $manageDynamic . ', Dynamic = ' . $this->isDynamic);
        $difference = [];

        if ($this->isDynamic == 1 && $manageDynamic) {

            $this->getLog()->info('Managing Display Links for Dynamic Display Group ' . $this->displayGroup);

            $originalDisplays = ($this->loaded) ? $this->displays : $this->displayFactory->getByDisplayGroupId($this->displayGroupId);

            // Update the linked displays based on the filter criteria
            // these displays must be permission checked based on the owner of the group NOT the logged in user
            $this->displays = $this->displayFactory->query(null, [
                'display' => $this->dynamicCriteria,
                'logicalOperatorName' => $this->dynamicCriteriaLogicalOperator,
                'tags' => $this->dynamicCriteriaTags,
                'exactTags' => $this->dynamicCriteriaExactTags,
                'logicalOperator' => $this->dynamicCriteriaTagsLogicalOperator,
                'userCheckUserId' => $this->getOwnerId(),
                'useRegexForName' => true
            ]);

            $this->getLog()->debug(sprintf('There are %d original displays and %d displays that match the filter criteria now.', count($originalDisplays), count($this->displays)));

            // Map our arrays to simple displayId lists
            $displayIds = array_map(function ($element) { return $element->displayId; }, $this->displays);
            $originalDisplayIds = array_map(function ($element) { return $element->displayId; }, $originalDisplays);

            $difference = array_merge(array_diff($displayIds, $originalDisplayIds), array_diff($originalDisplayIds, $displayIds));

            // This is a dynamic display group
            // only manage the links that have changed
            if (count($difference) > 0) {
                $this->getLog()->debug(count($difference) . ' changes in dynamic Displays, will notify individually');

                $this->notifyRequired = true;
            } else {
                $this->getLog()->debug('No changes in dynamic Displays, wont notify');

                $this->notifyRequired = false;
            }
        }

        // Manage the links we've made either way
        // Link
        $this->linkDisplays();

        // Check if we should notify
        if ($this->notifyRequired) {
            // We must notify before we unlink
            $this->notify($difference);
        }

        // Unlink
        //  we never unlink from a display specific display group, unless we're deleting which does not call
        //  manage display links.
        if ($this->isDisplaySpecific == 0) {
            $this->unlinkDisplays();
        }

        // Don't do it again
        $this->notifyRequired = false;
    }

    /**
     * Manage display group links
     * @throws InvalidArgumentException
     */
    private function manageDisplayGroupLinks()
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();

        // Check for circular references
        // this is a lazy last minute check as we can't really tell if there is a circular reference unless
        // we've inserted the records already.
        if ($this->getStore()->exists('SELECT depth FROM `lkdgdg` WHERE parentId = :parentId AND childId = parentId AND depth > 0', ['parentId' => $this->displayGroupId]))
            throw new InvalidArgumentException(__('This assignment creates a circular reference'));
    }

    private function linkDisplays()
    {
        foreach ($this->displays as $display) {
            /* @var Display $display */
            $this->getStore()->update('INSERT INTO lkdisplaydg (DisplayGroupID, DisplayID) VALUES (:displayGroupId, :displayId) ON DUPLICATE KEY UPDATE DisplayID = DisplayID', [
                'displayGroupId' => $this->displayGroupId,
                'displayId' => $display->displayId
            ]);
        }
    }

    private function unlinkDisplays()
    {
        // Unlink any displays that are NOT in the collection
        $params = ['displayGroupId' => $this->displayGroupId];

        $sql = 'DELETE FROM lkdisplaydg WHERE DisplayGroupID = :displayGroupId AND DisplayID NOT IN (0';

        $i = 0;
        foreach ($this->displays as $display) {
            /* @var Display $display */
            $i++;
            $sql .= ',:displayId' . $i;
            $params['displayId' . $i] = $display->displayId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    /**
     * Links the display groups that have been added to the OM
     * adding them to the closure table `lkdgdg`
     */
    private function linkDisplayGroups()
    {
        $links = array_udiff($this->displayGroups, $this->originalDisplayGroups, function($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });

        $this->getLog()->debug('Linking %d display groups to Display Group %s', count($links), $this->displayGroup);

        foreach ($links as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            $this->getStore()->insert('
                INSERT INTO lkdgdg (parentId, childId, depth)
                SELECT p.parentId, c.childId, p.depth + c.depth + 1
                  FROM lkdgdg p, lkdgdg c
                 WHERE p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $this->displayGroupId,
                'childId' => $displayGroup->displayGroupId
            ]);
        }
    }

    /**
     * Unlinks the display groups that have been removed from the OM
     * removing them from the closure table `lkdgdg`
     */
    private function unlinkDisplayGroups()
    {
        $links = array_udiff($this->originalDisplayGroups, $this->displayGroups, function($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });

        $this->getLog()->debug('Unlinking ' . count($links) . ' display groups to Display Group ' . $this->displayGroup);

        foreach ($links as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            // Only ever delete 1 because if there are more than 1, we can assume that it is linked at that level from
            // somewhere else
            // https://github.com/xibosignage/xibo/issues/1417
            $linksToDelete = $this->getStore()->select('
                SELECT DISTINCT link.parentId, link.childId, link.depth
                  FROM `lkdgdg` p
                    INNER JOIN `lkdgdg` link
                    ON p.parentId = link.parentId
                    INNER JOIN `lkdgdg` c
                    ON c.childId = link.childId
                 WHERE p.childId = :parentId 
                    AND c.parentId = :childId
                ', [
                'parentId' => $this->displayGroupId,
                'childId' => $displayGroup->displayGroupId
            ]);

            foreach ($linksToDelete as $linkToDelete) {
                $this->getStore()->update('
                  DELETE FROM `lkdgdg` 
                   WHERE parentId = :parentId 
                    AND childId = :childId 
                    AND depth = :depth 
                  LIMIT 1
                ', [
                    'parentId' => $linkToDelete['parentId'],
                    'childId' => $linkToDelete['childId'],
                    'depth' => $linkToDelete['depth']
                ]);
            }
        }
    }

    /**
     * Unlinks all display groups
     * usually in preparation for a delete
     */
    private function unlinkAllDisplayGroups()
    {
        $this->getStore()->update('
            DELETE link
              FROM `lkdgdg` p, `lkdgdg` link, `lkdgdg` c, `lkdgdg` to_delete
             WHERE p.parentId = link.parentId AND c.childId = link.childId
               AND p.childId  = to_delete.parentId AND c.parentId = to_delete.childId
               AND (to_delete.parentId = :parentId OR to_delete.childId = :childId)
               AND to_delete.depth < 2
        ', [
            'parentId' => $this->displayGroupId,
            'childId' => $this->displayGroupId
        ]);
    }

    private function linkMedia()
    {
        foreach ($this->media as $media) {
            /* @var Media $media */
            $this->getStore()->update('INSERT INTO `lkmediadisplaygroup` (mediaid, displaygroupid) VALUES (:mediaId, :displayGroupId) ON DUPLICATE KEY UPDATE mediaid = mediaid', [
                'displayGroupId' => $this->displayGroupId,
                'mediaId' => $media->mediaId
            ]);
        }
    }

    private function unlinkMedia()
    {
        // Unlink any media that is NOT in the collection
        $params = ['displayGroupId' => $this->displayGroupId];

        $sql = 'DELETE FROM `lkmediadisplaygroup` WHERE DisplayGroupID = :displayGroupId AND mediaId NOT IN (0';

        $i = 0;
        foreach ($this->media as $media) {
            /* @var Media $media */
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $media->mediaId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    private function linkLayouts()
    {
        foreach ($this->layouts as $layout) {
            /* @var Layout $media */
            $this->getStore()->update('INSERT INTO `lklayoutdisplaygroup` (layoutid, displaygroupid) VALUES (:layoutId, :displayGroupId) ON DUPLICATE KEY UPDATE layoutid = layoutid', [
                'displayGroupId' => $this->displayGroupId,
                'layoutId' => $layout->layoutId
            ]);
        }
    }

    private function unlinkLayouts()
    {
        // Unlink any layout that is NOT in the collection
        $params = ['displayGroupId' => $this->displayGroupId];

        $sql = 'DELETE FROM `lklayoutdisplaygroup` WHERE DisplayGroupID = :displayGroupId AND layoutId NOT IN (0';

        $i = 0;
        foreach ($this->layouts as $layout) {
            /* @var Layout $layout */
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layout->layoutId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }
}
