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
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Campaign
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Campaign implements \JsonSerializable
{
    use EntityTrait;
    use TagLinkTrait;

    public static $availableTypes = ['ad', 'list', 'media', 'playlist'];

    /**
     * @SWG\Property(description="The Campaign Id")
     * @var int
     */
    public $campaignId;

    /**
     * @SWG\Property(description="The userId of the User that owns this Campaign")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The type of campaign, either list, ad, playlist or media")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The name of the Campaign")
     * @var string
     */
    public $campaign;

    /**
     * @SWG\Property(description="A 0|1 flag to indicate whether this is a Layout specific Campaign or not.")
     * @var int
     */
    public $isLayoutSpecific = 0;

    /**
     * @SWG\Property(description="The number of Layouts associated with this Campaign")
     * @var int
     */
    public $numberLayouts;

    /**
     * @SWG\Property(description="The total duration of the campaign (sum of layout's durations)")
     * @var int
     */
    public $totalDuration;

    /**
     * @SWG\Property(description="Tags associated with this Campaign, array of TagLink objects")
     * @var TagLink[]
     */
    public $tags = [];

    /**
     * @SWG\Property(description="The id of the Folder this Campaign belongs to")
     * @var int
     */
    public $folderId;

    /**
     * @SWG\Property(description="The id of the Folder responsible for providing permissions for this Campaign")
     * @var int
     */
    public $permissionsFolderId;

    /**
     * @SWG\Property(description="Flag indicating whether this Campaign has cycle based playback enabled")
     * @var int
     */
    public $cyclePlaybackEnabled;

    /**
     * @SWG\Property(description="In cycle based playback, how many plays should each Layout have before moving on?")
     * @var int
     */
    public $playCount;

    /**
     * @SWG\Property(description="In list campaign types, how should the layouts play out?")
     * @var string
     */
    public $listPlayOrder;

    /**
     * @SWG\Property(description="For an ad campaign, what's the target type, plays|budget|imp")
     * @var string
     */
    public $targetType;

    /**
     * @SWG\Property(description="For an ad campaign, what's the target (expressed in targetType)")
     * @var int
     */
    public $target;

    /**
     * @SWG\Property(description="For an ad campaign, what's the start date")
     * @var int
     */
    public $startDt;

    /**
     * @SWG\Property(description="For an ad campaign, what's the end date")
     * @var int
     */
    public $endDt;

    /**
     * @SWG\Property(description="The number of plays achived by this campaign")
     * @var int
     */
    public $plays;

    /**
     * @SWG\Property(description="The amount of spend in cents/pence/etc")
     * @var double
     */
    public $spend;

    /**
     * @SWG\Property(description="The number of impressions achived by this campaign")
     * @var double
     */
    public $impressions;

    /**
     * @SWG\Property(description="The latest proof of play ID aggregated into the stats")
     * @var int
     */
    public $lastPopId;

    /**
     * @SWG\Property(description="Reference field 1")
     * @var string
     */
    public $ref1;

    /**
     * @SWG\Property(description="Reference field 1")
     * @var string
     */
    public $ref2;

    /**
     * @SWG\Property(description="Reference field 1")
     * @var string
     */
    public $ref3;

    /**
     * @SWG\Property(description="Reference field 1")
     * @var string
     */
    public $ref4;

    /**
     * @SWG\Property(description="Reference field 1")
     * @var string
     */
    public $ref5;

    public $createdAt;
    public $modifiedAt;
    public $modifiedBy;
    public $modifiedByName;

    /** @var \Xibo\Entity\LayoutOnCampaign[] */
    public $layouts = [];

    /** @var int[] */
    public $displayGroupIds = [];

    /**
     * @var Permission[]
     */
    private $permissions = [];

    /**
     * @var Schedule[]
     */
    private $events = [];

    // Private
    /** @var TagLink[] */
    private $unlinkTags = [];
    /** @var TagLink[] */
    private $linkTags = [];

    /** @var bool Have the Layout assignments been loaded? */
    private $layoutAssignmentsLoaded = false;

    /** @var bool Have the Layout assignments changed? */
    private $layoutAssignmentsChanged = false;

    private $displayGroupAssignmentsChanged = false;

    // Internal tracking variables for when we're incrementing plays/spend and impressions.
    private $additionalPlays = 0;
    private $additionalSpend = 0.0;
    private $additionalImpressions = 0.0;

    /** @var \Xibo\Factory\CampaignFactory */
    private $campaignFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param PermissionFactory $permissionFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     */
    public function __construct(
        $store,
        $log,
        $dispatcher,
        CampaignFactory $campaignFactory,
        $permissionFactory,
        $scheduleFactory,
        $displayNotifyService
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->campaignFactory = $campaignFactory;
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    public function __clone()
    {
        $this->campaignId = null;
        $this->tags = [];
        $this->linkTags = [];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf(
            'CampaignId %d, Campaign %s, LayoutSpecific %d',
            $this->campaignId,
            $this->campaign,
            $this->isLayoutSpecific
        );
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->campaignId;
    }

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * @return \Carbon\Carbon|false|null
     */
    public function getStartDt()
    {
        return $this->startDt == 0 ? null : Carbon::createFromTimestamp($this->startDt);
    }

    /**
     * @return \Carbon\Carbon|false|null
     */
    public function getEndDt()
    {
        return $this->endDt == 0 ? null : Carbon::createFromTimestamp($this->endDt);
    }

    /**
     * @param \Carbon\Carbon|null $testDate
     * @return \Xibo\Entity\CampaignProgress
     */
    public function getProgress(?Carbon $testDate = null): CampaignProgress
    {
        $progress = new CampaignProgress();

        if ($this->type !== 'ad' || $this->startDt == null || $this->endDt == null) {
            $progress->progressTime = 0;
            $progress->progressTarget = 0;
            return $progress;
        }

        if ($testDate === null) {
            $testDate = Carbon::now();
        }
        $startDt = $this->getStartDt();
        $endDt = $this->getEndDt();

        // if start and end date are the same
        // set the daysTotal to 1, to avoid potential division by 0 later on.
        $progress->daysTotal = ($this->startDt === $this->endDt) ? 1 : $endDt->diffInDays($startDt);

        $progress->targetPerDay = $this->target / $progress->daysTotal;

        if ($startDt->isAfter($testDate)) {
            $progress->progressTime = 0;
            $progress->progressTarget = 0;
        } else {
            if ($testDate->isAfter($endDt)) {
                // We've finished.
                $progress->daysIn = $progress->daysTotal;
                $progress->progressTime = 100;
            } else {
                $progress->daysIn = $testDate->diffInDays($startDt);

                // Use hours to calculate more accurate progress
                $hoursTotal = $progress->daysTotal * 24;
                $hoursIn = $testDate->diffInHours($startDt);
                $progress->progressTime = $hoursIn / $hoursTotal * 100;
            }

            if ($this->targetType === 'budget') {
                $progress->progressTarget = ($this->spend / $this->target) * 100;
            } else if ($this->targetType === 'imp') {
                $progress->progressTarget = ($this->impressions / $this->target) * 100;
            } else {
                $progress->progressTarget = ($this->plays / $this->target) * 100;
            }
        }
        return $progress;
    }

    /**
     * @param array $options
     * @throws NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadPermissions' => true,
            'loadEvents' => true,
            'loadDisplayGroupIds' => true,
        ], $options);

        // If we are already loaded, then don't do it again
        if ($this->campaignId == null || $this->loaded) {
            return;
        }

        // Permissions
        if ($options['loadPermissions']) {
            $this->permissions = $this->permissionFactory->getByObjectId('Campaign', $this->campaignId);
        }

        // Events
        if ($options['loadEvents']) {
            $this->events = $this->scheduleFactory->getByCampaignId($this->campaignId);
        }

        if ($options['loadDisplayGroupIds']) {
            $this->displayGroupIds = $this->loadDisplayGroupIds();
        }

        $this->loaded = true;
    }

    /**
     * @return \Xibo\Entity\LayoutOnCampaign[]
     */
    public function loadLayouts(): array
    {
        if (!$this->layoutAssignmentsLoaded && $this->campaignId !== null) {
            $this->layouts = $this->campaignFactory->getLinkedLayouts($this->campaignId);
            $this->layoutAssignmentsLoaded = true;
        }
        return $this->layouts;
    }

    /**
     * @param int $displayOrder
     * @return \Xibo\Entity\LayoutOnCampaign
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getLayoutAt(int $displayOrder): LayoutOnCampaign
    {
        foreach ($this->layouts as $layout) {
            if ($layout->displayOrder === $displayOrder) {
                return $layout;
            }
        }
        throw new NotFoundException();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!in_array($this->type, self::$availableTypes)) {
            throw new InvalidArgumentException(__('Invalid type'), 'type');
        }

        if (!v::stringType()->notEmpty()->validate($this->campaign)) {
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');
        }

        if ($this->cyclePlaybackEnabled === 1 && empty($this->playCount)) {
            throw new InvalidArgumentException(__('Please enter play count'), 'playCount');
        }

        if ($this->type === 'ad') {
            if (!in_array($this->targetType, ['plays', 'budget', 'imp'])) {
                throw new InvalidArgumentException(__('Invalid target type'), 'targetType');
            }

            if ($this->target <= 0) {
                throw new InvalidArgumentException(__('Please enter a target'), 'target');
            }

            if ($this->campaignId !== null && count($this->displayGroupIds) <= 0) {
                throw new InvalidArgumentException(__('Please select one or more displays'), 'displayGroupId[]');
            }

            if ($this->startDt !== null && $this->endDt !== null && $this->startDt > $this->endDt) {
                throw new InvalidArgumentException(
                    __('Cannot set end date to be earlier than the start date.'),
                    'endDt'
                );
            }
        } else {
            if ($this->listPlayOrder !== 'round' && $this->listPlayOrder !== 'block') {
                throw new InvalidArgumentException(
                    __('Please choose either round-robin or block play order for this list'),
                    'listPlayOrder'
                );
            }
        }
    }

    /**
     * Save this Campaign
     * @param array $options
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'notify' => true,
            'collectNow' => true,
            'saveTags' => true,
            'isTagEdit' => false
        ], $options);

        $this->getLog()->debug('Saving ' . $this);

        // Manually load display group IDs when editing only campaign tags.
        if ($options['isTagEdit']) {
            $this->displayGroupIds = $this->loadDisplayGroupIds();
        }

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->campaignId == null || $this->campaignId == 0) {
            $this->add();
            $this->loaded = true;
        } else {
            $this->update();
        }

        if ($options['saveTags']) {
            // Remove unwanted ones
            if (is_array($this->unlinkTags)) {
                foreach ($this->unlinkTags as $tag) {
                    $this->unlinkTagFromEntity('lktagcampaign', 'campaignId', $this->campaignId, $tag->tagId);
                }
            }

            // Save the tags
            if (is_array($this->linkTags)) {
                foreach ($this->linkTags as $tag) {
                    $this->linkTagToEntity('lktagcampaign', 'campaignId', $this->campaignId, $tag->tagId, $tag->value);
                }
            }
        }

        // Manage assignments
        $this->manageAssignments();

        // Notify anyone interested of the changes
        $this->notify($options);
    }

    /**
     * Delete Campaign
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function delete()
    {
        $this->load();

        // Unassign display groups
        $this->getStore()->update('DELETE FROM `lkcampaigndisplaygroup` WHERE campaignId = :campaignId', [
            'campaignId' => $this->campaignId,
        ]);

        // Unassign all Layouts
        $this->layouts = [];
        $this->unlinkLayouts();

        // Delete all permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        // Unassign all Tags
        $this->unlinkAllTagsFromEntity('lktagcampaign', 'campaignId', $this->campaignId);

        // Notify anyone interested of the changes
        // we do this before we delete from the DB (otherwise notify won't find anything)
        $this->notify();

        // Delete all events
        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->setDisplayNotifyService($this->displayNotifyService);
            $event->delete();
        }

        if ($this->type === 'ad') {
            foreach ($this->scheduleFactory->getByParentCampaignId($this->campaignId) as $adEvent) {
                $adEvent->delete();
            }
        }

        // Delete the Actual Campaign
        $this->getStore()->update('DELETE FROM `campaign` WHERE CampaignID = :campaignId', ['campaignId' => $this->campaignId]);
    }

    /**
     * Assign Layout
     * @param int $layoutId
     * @param int|null $displayOrder
     * @param int|null $dayPartId
     * @param string|null $daysOfWeek
     * @param string|null $geoFence
     * @return \Xibo\Entity\LayoutOnCampaign
     */
    public function assignLayout(
        int $layoutId,
        ?int $displayOrder = null,
        ?int $dayPartId = null,
        ?string $daysOfWeek = null,
        ?string $geoFence = null
    ): LayoutOnCampaign {
        $this->getLog()->debug('assignLayout: starting with layoutId: ' . $layoutId);

        // Load the layouts we do have already
        $this->loadLayouts();

        // Make a new assignment
        $assignment = $this->campaignFactory->createEmptyLayoutAssignment();
        $assignment->layoutId = $layoutId;

        // Props
        $assignment->displayOrder = empty($displayOrder) ? count($this->layouts) + 1 : $displayOrder;
        $assignment->dayPartId = $dayPartId;
        $assignment->daysOfWeek = $daysOfWeek;
        $assignment->geoFence = $geoFence;

        // We've changed assignments.
        $this->layoutAssignmentsChanged = true;
        $this->layouts[] = $assignment;
        $this->numberLayouts++;

        return $assignment;
    }

    /**
     * Unassign Layout
     * @param int $layoutId
     * @param int|null $displayOrder
     * @return \Xibo\Entity\Campaign
     */
    public function unassignLayout(
        int $layoutId,
        ?int $displayOrder = null
    ): Campaign {
        // Load the layouts we do have already
        $this->loadLayouts();

        $countBefore = count($this->layouts);
        $this->getLog()->debug('unassignLayout: Count before assign = ' . $countBefore);

        // Keep track of keys to remove
        $existingKeys = [];

        foreach ($this->layouts as $key => $existing) {
            $this->getLog()->debug('unassignLayout: Comparing existing ['
                . $existing->layoutId . ', ' . $existing->displayOrder
                . '] with unassign [' . $layoutId . ', ' . $displayOrder . '].');

            // Does this layoutId match?
            if ($layoutId === $existing->layoutId) {
                // Are we looking to remove a specific one?
                if ($displayOrder === null || $displayOrder === $existing->displayOrder) {
                    $existingKeys[] = $key;
                    $this->layoutAssignmentsChanged = true;
                }
            }
        }

        // Remove the keys necessary
        foreach ($existingKeys as $existingKey) {
            $this->getLog()->debug('Removing item at key ' . $existingKey);
            unset($this->layouts[$existingKey]);
        }

        return $this;
    }

    private function orderLayoutAssignments(): void
    {
        // Sort the layouts by their display order
        usort($this->layouts, function ($a, $b) {
            if ($a->displayOrder === null) {
                return 1;
            }

            if ($a->displayOrder === $b->displayOrder) {
                return 0;
            }

            return ($a->displayOrder < $b->displayOrder) ? -1 : 1;
        });
    }

    /**
     * Unassign all layouts
     * @return $this
     */
    public function unassignAllLayouts(): Campaign
    {
        $this->layoutAssignmentsChanged = true;
        $this->numberLayouts = 0;
        $this->layouts = [];
        return $this;
    }

    /**
     * Load displayGroupIds
     * @return int[]
     */
    public function loadDisplayGroupIds(): array
    {
        $displayGroupIds = [];
        foreach ($this->getStore()->select('SELECT * FROM lkcampaigndisplaygroup WHERE campaignId = :campaignId', [
            'campaignId' => $this->campaignId,
        ]) as $link) {
            $displayGroupIds[] = intval($link['displayGroupId']);
        }
        return $displayGroupIds;
    }

    /**
     * @param $displayGroupIds
     * @return $this
     */
    public function replaceDisplayGroupIds($displayGroupIds): Campaign
    {
        $this->displayGroupAssignmentsChanged = true;
        $this->displayGroupIds = $displayGroupIds;
        return $this;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->campaignId = $this->getStore()->insert('
            INSERT INTO `campaign` (
                campaign,
                type,
                isLayoutSpecific,
                userId,
                cyclePlaybackEnabled,
                playCount,
                listPlayOrder,
                targetType,
                target,
                folderId,
                permissionsFolderId
            ) 
            VALUES (
                :campaign,
                :type,
                :isLayoutSpecific,
                :userId,
                :cyclePlaybackEnabled,
                :playCount,
                :listPlayOrder,
                :targetType,
                :target,    
                :folderId,
                :permissionsFolderId
            )
        ', [
            'campaign' => $this->campaign,
            'type' => $this->type,
            'isLayoutSpecific' => $this->isLayoutSpecific,
            'userId' => $this->ownerId,
            'cyclePlaybackEnabled' => ($this->cyclePlaybackEnabled == null) ? 0 : $this->cyclePlaybackEnabled,
            'listPlayOrder' => $this->listPlayOrder,
            'playCount' => $this->playCount,
            'targetType' => empty($this->targetType) ? null : $this->targetType,
            'target' => empty($this->target) ? null : $this->target,
            'folderId' => ($this->folderId == null) ? 1 : $this->folderId,
            'permissionsFolderId' => ($this->permissionsFolderId == null) ? 1 : $this->permissionsFolderId
        ]);
    }

    /**
     * Update
     */
    private function update()
    {
        $this->getStore()->update('
            UPDATE `campaign`
                SET campaign = :campaign,
                    userId = :userId,
                    cyclePlaybackEnabled = :cyclePlaybackEnabled,
                    playCount = :playCount,
                    listPlayOrder = :listPlayOrder,
                    ref1 = :ref1,
                    ref2 = :ref2,
                    ref3 = :ref3,
                    ref4 = :ref4,
                    ref5 = :ref5,
                    targetType = :targetType,
                    target = :target,
                    startDt = :startDt,
                    endDt = :endDt,
                    folderId = :folderId,
                    permissionsFolderId = :permissionsFolderId,
                    modifiedBy = :modifiedBy
             WHERE campaignID = :campaignId
        ', [
            'campaignId' => $this->campaignId,
            'campaign' => $this->campaign,
            'userId' => $this->ownerId,
            'cyclePlaybackEnabled' => ($this->cyclePlaybackEnabled == null) ? 0 : $this->cyclePlaybackEnabled,
            'playCount' => $this->playCount,
            'listPlayOrder' => $this->listPlayOrder,
            'targetType' => empty($this->targetType) ? null : $this->targetType,
            'target' => empty($this->target) ? null : $this->target,
            'startDt' => empty($this->startDt) ? null : $this->startDt,
            'endDt' => empty($this->endDt) ? null : $this->endDt,
            'ref1' => empty($this->ref1) ? null : $this->ref1,
            'ref2' => empty($this->ref2) ? null : $this->ref2,
            'ref3' => empty($this->ref3) ? null : $this->ref3,
            'ref4' => empty($this->ref4) ? null : $this->ref4,
            'ref5' => empty($this->ref5) ? null : $this->ref5,
            'folderId' => $this->folderId,
            'permissionsFolderId' => $this->permissionsFolderId,
            'modifiedBy' => $this->modifiedBy,
        ]);
    }

    /**
     * Manage the assignments
     */
    private function manageAssignments()
    {
        if ($this->layoutAssignmentsChanged) {
            $this->getLog()->debug('Managing Assignments on ' . $this);
            $this->unlinkLayouts();
            $this->linkLayouts();
        } else {
            $this->getLog()->debug('Assignments have not changed on ' . $this);
        }

        if ($this->displayGroupAssignmentsChanged) {
            $this->getStore()->update('DELETE FROM `lkcampaigndisplaygroup` WHERE campaignId = :campaignId', [
                'campaignId' => $this->campaignId,
            ]);

            foreach ($this->displayGroupIds as $displayGroupId) {
                $this->getStore()->update('
                INSERT INTO `lkcampaigndisplaygroup` (campaignId, displayGroupId) 
                    VALUES (:campaignId, :displayGroupId)
                ON DUPLICATE KEY UPDATE campaignId = :campaignId
            ', [
                    'campaignId' => $this->campaignId,
                    'displayGroupId' => $displayGroupId,
                ]);
            }
        }
    }

    /**
     * Link Layout
     */
    private function linkLayouts()
    {
        // Don't do anything if we don't have any layouts
        if (count($this->layouts) <= 0) {
            return;
        }

        $this->orderLayoutAssignments();

        // Update the layouts, in order to have display order 1 to n
        $i = 0;
        $sql = '
            INSERT INTO `lkcampaignlayout` (campaignID, layoutID, displayOrder, dayPartId, daysOfWeek, geoFence)
             VALUES 
        ';
        $params = ['campaignId' => $this->campaignId];

        foreach ($this->layouts as $layout) {
            $i++;
            $layout->displayOrder = $i;

            $sql .= '(
                :campaignId,
                :layoutId_' . $i . ',
                :displayOrder_' . $i . ',
                :dayPartId_' . $i . ',
                :daysOfWeek_' . $i . ',
                :geoFence_' . $i . '
            ),';

            $params['layoutId_' . $i] = $layout->layoutId;
            $params['displayOrder_' . $i] = $layout->displayOrder;
            $params['dayPartId_' . $i] = $layout->dayPartId == null ? null : $layout->dayPartId;
            $params['daysOfWeek_' . $i] = $layout->daysOfWeek == null ? null : $layout->daysOfWeek;
            $params['geoFence_' . $i] = $layout->geoFence == null ? null : json_encode($layout->geoFence);
        }

        $sql = rtrim($sql, ',');

        $this->getStore()->update($sql, $params);
    }

    /**
     * Unlink Layout
     */
    private function unlinkLayouts()
    {
        // Delete all the links
        $this->getStore()->update('DELETE FROM `lkcampaignlayout` WHERE campaignId = :campaignId', [
            'campaignId' => $this->campaignId
        ]);
    }

    /**
     * Notify displays of this campaign change
     * @param array $options
     */
    private function notify($options = [])
    {
        $options = array_merge([
            'notify' => true,
            'collectNow' => true,
        ], $options);

        // Do we notify?
        if ($options['notify']) {
            $this->getLog()->debug('CampaignId ' . $this->campaignId . ' wants to notify.');

            $notify = $this->displayNotifyService->init();

            // Should we collect immediately
            if ($options['collectNow']) {
                $notify->collectNow();
            }

            // Notify
            $notify->notifyByCampaignId($this->campaignId);

            if (!empty($options['layoutCode'])) {
                $this->getLog()->debug('CampaignId ' . $this->campaignId . ' wants to notify with Layout Code ' . $options['layoutCode']);
                $notify->notifyByLayoutCode($options['layoutCode']);
            }
        }
    }

    /**
     * Add to the number of plays
     * @param int $plays
     * @param double $spend
     * @param double $impressions
     * @return $this
     */
    public function incrementPlays(int $plays, $spend, $impressions): Campaign
    {
        $this->plays += $plays;
        $this->additionalPlays += $plays;
        $this->spend += $spend;
        $this->additionalSpend += $spend;
        $this->impressions += $impressions;
        $this->additionalImpressions += $impressions;
        return $this;
    }

    /**
     * Save increments to the number of plays
     * @return $this
     */
    public function saveIncrementPlays(): Campaign
    {
        $this->getStore()->update('
            UPDATE `campaign`
                SET `plays` = `plays` + :plays,
                    `spend` = `spend` + :spend,
                    `impressions` = `impressions` + :impressions
             WHERE campaignId = :campaignId
        ', [
            'plays' => $this->additionalPlays,
            'spend' => $this->additionalSpend,
            'impressions' => $this->additionalImpressions,
            'campaignId' => $this->campaignId,
        ]);
        return $this;
    }

    /**
     * Overwrite the number of plays/spend and impressions
     * @return $this
     */
    public function overwritePlays(): Campaign
    {

        $this->getStore()->update('
            UPDATE `campaign`
                SET `plays` = :plays,
                    `spend` = :spend,
                    `impressions` = :impressions
             WHERE campaignId = :campaignId
        ', [
            'plays' => $this->plays,
            'spend' => $this->spend,
            'impressions' => $this->impressions,
            'campaignId' => $this->campaignId,
        ]);
        return $this;
    }
}
