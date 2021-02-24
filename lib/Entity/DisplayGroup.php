<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Xibo\Exception\DuplicateEntityException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayGroup
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DisplayGroup implements \JsonSerializable
{
    use EntityTrait;

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
     * @SWG\Property(
     *  description="Criteria for this dynamic group. A comma separated set of tags to apply",
     * )
     * @var string
     */
    public $dynamicCriteriaTags;

    /**
     * @SWG\Property(
     *  description="The UserId who owns this display group",
     * )
     * @var int
     */
    public $userId = 0;

    /**
     * @SWG\Property(description="Tags associated with this DisplayGroup")
     * @var Tag[]
     */
    public $tags = [];
    public $tagValues;

    /**
     * @SWG\Property(description="The display bandwidth limit")
     * @var int
     */
    public $bandwidthLimit;

    /**
     * Minimum save options
     * @var array
     */
    public static $saveOptionsMinimum = [
        'validate' => false,
        'saveGroup' => true,
        'manageLinks' => false,
        'manageDisplayLinks' => false
    ];

    // Child Items the Display Group is linked to
    private $displays = [];
    private $displayGroups = [];
    private $layouts = [];
    private $media = [];
    private $permissions = [];
    private $events = [];
    private $unassignTags = [];
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
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DisplayGroupFactory $displayGroupFactory
     * @param PermissionFactory $permissionFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $displayGroupFactory, $permissionFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->displayGroupFactory = $displayGroupFactory;
        $this->permissionFactory = $permissionFactory;
        $this->tagFactory = $tagFactory;
    }

    public function __clone()
    {
        $this->displayGroupId = null;
    }

    /**
     * Set child object dependencies
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @return $this
     */
    public function setChildObjectDependencies($displayFactory, $layoutFactory, $mediaFactory, $scheduleFactory)
    {
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->displayGroupId;
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
     */
    public function setDisplaySpecificDisplay($display)
    {
        $this->load();

        $this->isDisplaySpecific = 1;
        $this->assignDisplay($display);
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
     */
    public function assignDisplay($display)
    {
        $this->load();

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
     */
    public function unassignDisplay($display)
    {
        $this->load();

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
     */
    public function assignDisplayGroup($displayGroup)
    {
        $this->load();

        if (!in_array($displayGroup, $this->displayGroups))
            $this->displayGroups[] = $displayGroup;
    }

    /**
     * Unassign DisplayGroup
     * @param DisplayGroup $displayGroup
     */
    public function unassignDisplayGroup($displayGroup)
    {
        $this->load();

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
     */
    public function assignMedia($media)
    {
        $this->load();

        if (!in_array($media, $this->media)) {
            $this->media[] = $media;

            // We should notify
            $this->notifyRequired = true;
        }
    }

    /**
     * Unassign Media
     * @param Media $media
     */
    public function unassignMedia($media)
    {
        $this->load();

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
     */
    public function assignLayout($layout)
    {
        $this->load();

        if (!in_array($layout, $this->layouts)) {
            $this->layouts[] = $layout;

            // We should notify
            $this->notifyRequired = true;
        }
    }

    /**
     * Unassign Layout
     * @param Layout $layout
     */
    public function unassignLayout($layout)
    {
        $this->load();

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
     * Does the campaign have the provided tag?
     * @param $searchTag
     * @return bool
     */
    public function hasTag($searchTag)
    {
        $this->load();

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            if ($tag->tag == $searchTag)
                return true;
        }

        return false;
    }

    /**
     * Assign Tag
     * @param Tag $tag
     * @return $this
     * @throws NotFoundException
     */
    public function assignTag($tag)
    {
        $this->load();

        if ($this->tags != [$tag]) {

            if (!in_array($tag, $this->tags)) {
                $this->tags[] = $tag;
            }
        } else {
            $this->getLog()->debug('No Tags to assign');
        }

        return $this;
    }

    /**
     * Unassign tag
     * @param Tag $tag
     * @return $this
     * @throws NotFoundException
     */
    public function unassignTag($tag)
    {
        $this->load();
        $this->tags = array_udiff($this->tags, [$tag], function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->unassignTags[] = $tag;

        $this->getLog()->debug('Tags after removal %s', json_encode($this->tags));

        return $this;
    }

    /**
     * @param array[Tag] $tags
     */
    public function replaceTags($tags = [])
    {
        if (!is_array($this->tags) || count($this->tags) <= 0)
            $this->tags = $this->tagFactory->loadByDisplayGroupId($this->displayGroupId);

        if ($this->tags != $tags) {
            $this->unassignTags = array_udiff($this->tags, $tags, function ($a, $b) {
                /* @var Tag $a */
                /* @var Tag $b */
                return $a->tagId - $b->tagId;
            });

            $this->getLog()->debug('Tags to be removed: %s', json_encode($this->unassignTags));

            // Replace the arrays
            $this->tags = $tags;

            $this->getLog()->debug('Tags remaining: %s', json_encode($this->tags));
        } else {
            $this->getLog()->debug('Tags were not changed');
        }
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

        if ($this->loaded || $this->displayGroupId == null || $this->displayGroupId == 0)
            return;

        if ($this->permissionFactory == null || $this->displayFactory == null || $this->displayGroupFactory == null || $this->layoutFactory == null || $this->mediaFactory == null || $this->scheduleFactory == null)
            throw new \RuntimeException('Cannot load without first calling setChildObjectDependencies');

        $this->permissions = $this->permissionFactory->getByObjectId(get_class($this), $this->displayGroupId);

        $this->displays = $this->displayFactory->getByDisplayGroupId($this->displayGroupId);

        $this->displayGroups = $this->displayGroupFactory->getByParentId($this->displayGroupId);

        $this->layouts = $this->layoutFactory->getByDisplayGroupId($this->displayGroupId);

        $this->media = $this->mediaFactory->getByDisplayGroupId($this->displayGroupId);

        $this->events = $this->scheduleFactory->getByDisplayGroupId($this->displayGroupId);

        // Load all tags
        if ($options['loadTags'])
            $this->tags = $this->tagFactory->loadByDisplayGroupId($this->displayGroupId);

        // Set the originals
        $this->originalDisplayGroups = $this->displayGroups;

        // We are loaded
        $this->loaded = true;
    }

    /**
     * Validate this display
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->displayGroup))
            throw new InvalidArgumentException(__('Please enter a display group name'), 'displayGroup');

        if (!empty($this->description) && !v::stringType()->length(null, 254)->validate($this->description))
            throw new InvalidArgumentException(__('Description can not be longer than 254 characters'), 'description');

        if ($this->isDisplaySpecific == 0) {
            // Check the name
            $result = $this->getStore()->select('SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = :displayGroup AND IsDisplaySpecific = 0 AND displayGroupId <> :displayGroupId', [
                'displayGroup' => $this->displayGroup,
                'displayGroupId' => (($this->displayGroupId == null) ? 0 : $this->displayGroupId)
            ]);

            if (count($result) > 0)
                throw new DuplicateEntityException(sprintf(__('You already own a display group called "%s". Please choose another name.'), $this->displayGroup));

            // If we are dynamic, then make sure we have some criteria
            if ($this->isDynamic == 1 && ($this->dynamicCriteria == '' && $this->dynamicCriteriaTags == ''))
                throw new InvalidArgumentException(__('Dynamic Display Groups must have at least one Criteria specified.'), 'dynamicCriteria');
        }
    }

    /**
     * Save
     * @param array $options
     * @throws XiboException
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
            'saveTags' => true
        ], $options);

        // Should we allow notification or not?
        $this->allowNotify = $options['allowNotify'];

        if ($options['validate'])
            $this->validate();

        if ($this->displayGroupId == null || $this->displayGroupId == 0) {
            $this->add();
            $this->loaded = true;
        }
        else if ($options['saveGroup']) {
            $this->edit();
        }

        if ($options['saveTags']) {
            // Tags
            if (is_array($this->tags)) {
                foreach ($this->tags as $tag) {
                    /* @var Tag $tag */

                    $this->getLog()->debug('Assigning tag ' . $tag->tag);

                    $tag->assignDisplayGroup($this->displayGroupId);
                    $tag->save();
                }
            }

            // Remove unwanted ones
            if (is_array($this->unassignTags)) {
                foreach ($this->unassignTags as $tag) {
                    /* @var Tag $tag */
                    $this->getLog()->debug('Unassigning tag ' . $tag->tag);

                    $tag->unassignDisplayGroup($this->displayGroupId);
                    $tag->save();
                }
            }
        }

        if ($this->loaded) {

            if ($options['manageLinks']) {
                $this->getLog()->debug('Manage links to Display Group');

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

        } else if ($this->isDynamic && $options['manageDynamicDisplayLinks']) {
            $this->manageDisplayLinks(true);
        }

        // Set media incomplete if necessary
        if ($this->notifyRequired)
            $this->notify();
    }

    /**
     * Delete
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

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->unassignDisplayGroup($this->displayGroupId);
            $tag->save();
        }

        // Delete assignments
        $this->removeAssignments();

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
        $this->displayGroupId = $this->getStore()->insert('
          INSERT INTO displaygroup (DisplayGroup, IsDisplaySpecific, Description, `isDynamic`, `dynamicCriteria`, `dynamicCriteriaTags`, `userId`)
            VALUES (:displayGroup, :isDisplaySpecific, :description, :isDynamic, :dynamicCriteria, :dynamicCriteriaTags, :userId)
        ', [
            'displayGroup' => $this->displayGroup,
            'isDisplaySpecific' => $this->isDisplaySpecific,
            'description' => $this->description,
            'isDynamic' => $this->isDynamic,
            'dynamicCriteria' => $this->dynamicCriteria,
            'dynamicCriteriaTags' => $this->dynamicCriteriaTags,
            'userId' => $this->userId
        ]);

        // Insert my self link
        $this->getStore()->insert('INSERT INTO `lkdgdg` (`parentId`, `childId`, `depth`) VALUES (:parentId, :childId, 0)', [
            'parentId' => $this->displayGroupId,
            'childId' => $this->displayGroupId
        ]);
    }

    private function edit()
    {
        $this->getLog()->debug('Updating Display Group. %s, %d', $this->displayGroup, $this->displayGroupId);

        $this->getStore()->update('
          UPDATE displaygroup
            SET DisplayGroup = :displayGroup,
              Description = :description,
              `isDynamic` = :isDynamic,
              `dynamicCriteria` = :dynamicCriteria,
              `dynamicCriteriaTags` = :dynamicCriteriaTags,
              `bandwidthLimit` = :bandwidthLimit,
              `userId` = :userId
           WHERE DisplayGroupID = :displayGroupId
          ', [
            'displayGroup' => $this->displayGroup,
            'description' => $this->description,
            'displayGroupId' => $this->displayGroupId,
            'isDynamic' => $this->isDynamic,
            'dynamicCriteria' => $this->dynamicCriteria,
            'dynamicCriteriaTags' => $this->dynamicCriteriaTags,
            'bandwidthLimit' => $this->bandwidthLimit,
            'userId' => $this->userId
        ]);
    }

    /**
     * Manage the links to this display, dynamic or otherwise
     * @var bool $manageDynamic
     * @throws NotFoundException
     */
    private function manageDisplayLinks($manageDynamic = true)
    {
        $difference = [];

        if ($this->isDynamic && $manageDynamic) {

            $this->getLog()->info('Managing Display Links for Dynamic Display Group %s', $this->displayGroup);

            $originalDisplays = ($this->loaded) ? $this->displays : $this->displayFactory->getByDisplayGroupId($this->displayGroupId);

            // Update the linked displays based on the filter criteria
            // these displays must be permission checked based on the owner of the group NOT the logged in user
            $this->displays = $this->displayFactory->query(null, ['display' => $this->dynamicCriteria, 'tags' => $this->dynamicCriteriaTags, 'userCheckUserId' => $this->getOwnerId(), 'useRegexForName' => true]);

            $this->getLog()->debug('There are %d original displays and %d displays that match the filter criteria now.', count($originalDisplays), count($this->displays));

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
        $this->unlinkDisplays();

        // Don't do it again
        $this->notifyRequired = false;
    }

    /**
     * Manage display group links
     */
    private function manageDisplayGroupLinks()
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();

        // Check for circular references
        // this is a lazy last minute check as we can't really tell if there is a circular reference unless
        // we've inserted the records already.
        if ($this->getStore()->exists('SELECT depth FROM `lkdgdg` WHERE parentId = :parentId AND childId = parentId AND depth > 0', ['parentId' => $this->displayGroupId]))
            throw new \InvalidArgumentException(__('This assignment creates a circular reference'));
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