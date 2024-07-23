<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\ScheduleCriteriaFactory;
use Xibo\Factory\ScheduleExclusionFactory;
use Xibo\Factory\ScheduleReminderFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Schedule
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Schedule implements \JsonSerializable
{
    use EntityTrait;

    public static $LAYOUT_EVENT = 1;
    public static $COMMAND_EVENT = 2;
    public static $OVERLAY_EVENT = 3;
    public static $INTERRUPT_EVENT = 4;
    public static $CAMPAIGN_EVENT = 5;
    public static $ACTION_EVENT = 6;

    public static $MEDIA_EVENT = 7;
    public static $PLAYLIST_EVENT = 8;
    public static $SYNC_EVENT = 9;
    public static $DATA_CONNECTOR_EVENT = 10;
    public static $DATE_MIN = 0;
    public static $DATE_MAX = 2147483647;

    /**
     * @SWG\Property(
     *  description="The ID of this Event"
     * )
     * @var int
     */
    public $eventId;

    /**
     * @SWG\Property(
     *  description="The Event Type ID"
     * )
     * @var int
     */
    public $eventTypeId;

    /**
     * @SWG\Property(
     *  description="The CampaignID this event is for"
     * )
     * @var int
     */
    public $campaignId;

    /**
     * @SWG\Property(
     *  description="The CommandId this event is for"
     * )
     * @var int
     */
    public $commandId;

    /**
     * @SWG\Property(
     *  description="Display Groups assigned to this Scheduled Event.",
     *  type="array",
     *  @SWG\Items(ref="#/definitions/DisplayGroup")
     * )
     * @var DisplayGroup[]
     */
    public $displayGroups = [];

    /**
     * @SWG\Property(
     *  description="Schedule Reminders assigned to this Scheduled Event.",
     *  type="array",
     *  @SWG\Items(ref="#/definitions/ScheduleReminder")
     * )
     * @var ScheduleReminder[]
     */
    public $scheduleReminders = [];

    /**
     * @SWG\Property(
     *  description="Schedule Criteria assigned to this Scheduled Event.",
     *  type="array",
     *  @SWG\Items(ref="#/definitions/ScheduleCriteria")
     * )
     * @var ScheduleCriteria[]
     */
    public $criteria = [];

    /**
     * @SWG\Property(
     *  description="The userId that owns this event."
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the from date of this event in CMS time."
     * )
     * @var int
     */
    public $fromDt;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the to date of this event in CMS time."
     * )
     * @var int
     */
    public $toDt;

    /**
     * @SWG\Property(
     *  description="Integer indicating the event priority."
     * )
     * @var int
     */
    public $isPriority;

    /**
     * @SWG\Property(
     *  description="The display order for this event."
     * )
     * @var int
     */
    public $displayOrder;

    /**
     * @SWG\Property(
     *  description="If this event recurs when what is the recurrence period.",
     *  enum={"None", "Minute", "Hour", "Day", "Week", "Month", "Year"}
     * )
     * @var string
     */
    public $recurrenceType;

    /**
     * @SWG\Property(
     *  description="If this event recurs when what is the recurrence frequency.",
     * )
     * @var int
     */
    public $recurrenceDetail;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp indicating the end time of the recurring events."
     * )
     * @var int
     */
    public $recurrenceRange;

    /**
     * @SWG\Property(description="Recurrence repeats on days - 0 to 7 where 0 is a monday")
     * @var string
     */
    public $recurrenceRepeatsOn;

    /**
     * @SWG\Property(description="Recurrence monthly repeats on - 0 is day of month, 1 is weekday of week")
     * @var int
     */
    public $recurrenceMonthlyRepeatsOn;

    /**
     * @SWG\Property(
     *  description="The Campaign/Layout Name",
     *  readOnly=true
     * )
     * @var string
     */
    public $campaign;

    /**
     * @SWG\Property(
     *  description="The Command Name",
     *  readOnly=true
     * )
     * @var string
     */
    public $command;

    /**
     * @SWG\Property(
     *  description="The Day Part Id"
     * )
     * @var int
     */
    public $dayPartId;

    /**
     * @SWG\Property(description="Is this event an always on event?")
     * @var int
     */
    public $isAlways;

    /**
     * @SWG\Property(description="Does this event have custom from/to date times?")
     * @var int
     */
    public $isCustom;

    /**
     * Last Recurrence Watermark
     * @var int
     */
    public $lastRecurrenceWatermark;

    /**
     * @SWG\Property(description="Flag indicating whether the event should be synchronised across displays")
     * @var int
     */
    public $syncEvent = 0;

    /**
     * @SWG\Property(description="Flag indicating whether the event will sync to the Display timezone")
     * @var int
     */
    public $syncTimezone;

    /**
     * @SWG\Property(description="Seconds (0-3600) of each full hour that is scheduled that this Layout should occupy")
     * @var int
     */
    public $shareOfVoice;

    /**
     * @SWG\Property(description="The maximum number of plays per hour per display for this event")
     * @var int
     */
    public $maxPlaysPerHour;

    /**
     * @SWG\Property(description="Flag (0-1), whether this event is using Geo Location")
     * @var int
     */
    public $isGeoAware;

    /**
     * @SWG\Property(description="Geo JSON representing the area of this event")
     * @var string
     */
    public $geoLocation;

    /**
     * @SWG\Property(description="For Action event type, Action trigger code")
     * @var string
     */
    public $actionTriggerCode;

    /**
     * @SWG\Property(description="For Action event type, the type of the Action (navigate to Layout or Command)")
     * @var string
     */
    public $actionType;

    /**
     * @SWG\Property(description="For Action event type and navigate to Layout Action type, the Layout code")
     * @var string
     */
    public $actionLayoutCode;

    /**
     * @SWG\Property(description="If the schedule should be considered part of a larger campaign")
     * @var int
     */
    public $parentCampaignId;

    /**
     * @SWG\Property(description="For sync events, the id the the sync group")
     * @var int
     */
    public $syncGroupId;

    /**
     * @SWG\Property(description="For data connector events, the dataSetId")
     * @var int
     */
    public $dataSetId;

    /**
     * @SWG\Property(description="For data connector events, the data set parameters")
     * @var int
     */
    public $dataSetParams;

    /**
     * @SWG\Property(description="The userId of the user that last modified this Schedule")
     * @var int
     */
    public $modifiedBy;
    public $modifiedByName;

    /**
     * @SWG\Property(description="The Date this Schedule was created on")
     * @var string
     */
    public $createdOn;

    /**
     * @SWG\Property(description="The Date this Schedule was las updated on")
     * @var string
     */
    public $updatedOn;

    /**
     * @SWG\Property(description="The name of this Scheduled Event")
     * @var string
     */
    public $name;

    /**
     * @var ScheduleEvent[]
     */
    private $scheduleEvents = [];

    private $datesToFormat = ['toDt', 'fromDt'];

    private $dayPart = null;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var  PoolInterface */
    private $pool;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /** @var  CampaignFactory */
    private $campaignFactory;

    /** @var  ScheduleReminderFactory */
    private $scheduleReminderFactory;

    /** @var  ScheduleExclusionFactory */
    private $scheduleExclusionFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DayPartFactory $dayPartFactory
     * @param UserFactory $userFactory
     * @param ScheduleReminderFactory $scheduleReminderFactory
     * @param ScheduleExclusionFactory $scheduleExclusionFactory
     */
    public function __construct(
        $store,
        $log,
        $dispatcher,
        $config,
        $pool,
        $displayGroupFactory,
        $dayPartFactory,
        $userFactory,
        $scheduleReminderFactory,
        $scheduleExclusionFactory,
        private readonly ScheduleCriteriaFactory $scheduleCriteriaFactory
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->config = $config;
        $this->pool = $pool;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->userFactory = $userFactory;
        $this->scheduleReminderFactory = $scheduleReminderFactory;
        $this->scheduleExclusionFactory = $scheduleExclusionFactory;

        $this->excludeProperty('lastRecurrenceWatermark');
    }

    /**
     * @param CampaignFactory $campaignFactory
     * @return $this
     */
    public function setCampaignFactory($campaignFactory)
    {
        $this->campaignFactory = $campaignFactory;
        return $this;
    }

    public function __clone()
    {
        $this->eventId = null;
    }

    /**
     * @param DisplayNotifyServiceInterface $displayNotifyService
     * @return $this
     */
    public function setDisplayNotifyService($displayNotifyService)
    {
        $this->displayNotifyService = $displayNotifyService;
        return $this;
    }

    /**
     * Get the Display Notify Service
     * @return DisplayNotifyServiceInterface
     */
    public function getDisplayNotifyService(): DisplayNotifyServiceInterface
    {
        return $this->displayNotifyService->init();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->eventId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->userId = $ownerId;
    }

    /**
     * @param ScheduleCriteria $criteria
     * @param int|null $id
     * @return $this
     */
    public function addOrUpdateCriteria(ScheduleCriteria $criteria, ?int $id = null): Schedule
    {
        // set empty array as the default value if original value is empty/null
        $originalValue = $this->getOriginalValue('criteria') ?? [];

        // Does this already exist?
        foreach ($originalValue as $existing) {
            if ($id !== null && $existing->id === $id) {
                $this->criteria[] = $criteria;
                return $this;
            }
        }

        // We didn't find it.
        $this->criteria[] = $criteria;
        return $this;
    }

    /**
     * Are the provided dates within the schedule look ahead
     * @return bool
     * @throws GeneralException
     */
    private function inScheduleLookAhead()
    {
        if ($this->isAlwaysDayPart()) {
            return true;
        }

        // From Date and To Date are in UNIX format
        $currentDate = Carbon::now();
        $rfLookAhead = clone $currentDate;
        $rfLookAhead->addSeconds(intval($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD')));

        // Dial current date back to the start of the day
        $currentDate->startOfDay();

        // Test dates
        if ($this->recurrenceType != '') {
            // A recurring event
            $this->getLog()->debug('Checking look ahead based on recurrence');
            // we should check whether the event from date is before the lookahead (i.e. the event has recurred once)
            // we should also check whether the recurrence range is still valid
            // (i.e. we've not stopped recurring and we don't recur forever)
            return (
                $this->fromDt <= $rfLookAhead->format('U')
                && ($this->recurrenceRange == 0 || $this->recurrenceRange > $currentDate->format('U'))
            );
        } else if (!$this->isCustomDayPart() || $this->eventTypeId == self::$COMMAND_EVENT) {
            // Day parting event (non recurring) or command event
            // only test the from date.
            $this->getLog()->debug('Checking look ahead based from date ' . $currentDate->toRssString());
            return ($this->fromDt >= $currentDate->format('U') && $this->fromDt <= $rfLookAhead->format('U'));
        } else {
            // Compare the event dates
            $this->getLog()->debug(
                'Checking look ahead based event dates '
                . $currentDate->toRssString() . ' / ' . $rfLookAhead->toRssString()
            );
            return ($this->fromDt <= $rfLookAhead->format('U') && $this->toDt >= $currentDate->format('U'));
        }
    }

    /**
     * Load
     * @param array $options
     * @throws NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadDisplayGroups' => true,
            'loadScheduleReminders' => false,
            'loadScheduleCriteria' => true,
        ], $options);

        // If we are already loaded, then don't do it again
        if ($this->loaded || $this->eventId == null || $this->eventId == 0) {
            return;
        }

        // Load display groups
        if ($options['loadDisplayGroups']) {
            $this->displayGroups = $this->displayGroupFactory->getByEventId($this->eventId);
        }

        // Load schedule reminders
        if ($options['loadScheduleReminders']) {
            $this->scheduleReminders = $this->scheduleReminderFactory->query(null, ['eventId'=> $this->eventId]);
        }

        // Load schedule criteria
        if ($options['loadScheduleCriteria']) {
            $this->criteria = $this->scheduleCriteriaFactory->getByEventId($this->eventId);
        }

        // Set the original values now that we're loaded.
        $this->setOriginals();

        // We are fully loaded
        $this->loaded = true;
    }

    /**
     * Assign DisplayGroup
     * @param DisplayGroup $displayGroup
     * @throws NotFoundException
     */
    public function assignDisplayGroup($displayGroup)
    {
        $this->load();

        if (!in_array($displayGroup, $this->displayGroups)) {
            $this->displayGroups[] = $displayGroup;
        }
    }

    /**
     * Unassign DisplayGroup
     * @param DisplayGroup $displayGroup
     * @throws NotFoundException
     */
    public function unassignDisplayGroup($displayGroup)
    {
        $this->load();

        $this->displayGroups = array_udiff($this->displayGroups, [$displayGroup], function ($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Validate
     * @throws GeneralException
     */
    public function validate()
    {
        if (count($this->displayGroups) <= 0 && $this->eventTypeId !== Schedule::$SYNC_EVENT) {
            throw new InvalidArgumentException(__('No display groups selected'), 'displayGroups');
        }

        $this->getLog()->debug('EventTypeId: ' . $this->eventTypeId
            . '. DayPartId: ' . $this->dayPartId
            . ', CampaignId: ' . $this->campaignId
            . ', CommandId: ' . $this->commandId);

        // If we are a custom day part, make sure we don't have a fromDt which is way in the past
        if ($this->isCustomDayPart() && $this->fromDt < Carbon::now()->subYears(10)->format('U')) {
            throw new InvalidArgumentException(__('The from date is too far in the past.'), 'fromDt');
        }

        if (!empty($this->name) && strlen($this->name) > 50) {
            throw new InvalidArgumentException(
                __('Name cannot be longer than 50 characters.'),
                'name'
            );
        }

        if ($this->eventTypeId == Schedule::$LAYOUT_EVENT ||
            $this->eventTypeId == Schedule::$CAMPAIGN_EVENT ||
            $this->eventTypeId == Schedule::$OVERLAY_EVENT ||
            $this->eventTypeId == Schedule::$INTERRUPT_EVENT ||
            $this->eventTypeId == Schedule::$MEDIA_EVENT ||
            $this->eventTypeId == Schedule::$PLAYLIST_EVENT
        ) {
            // Validate layout
            if (!v::intType()->notEmpty()->validate($this->campaignId)) {
                throw new InvalidArgumentException(__('Please select a Campaign/Layout for this event.'), 'campaignId');
            }

            if ($this->isCustomDayPart()) {
                // validate the dates
                if ($this->toDt <= $this->fromDt) {
                    throw new InvalidArgumentException(
                        __('Can not have an end time earlier than your start time'),
                        'start/end'
                    );
                }
            }

            $this->commandId = null;

            // additional validation for Interrupt Layout event type
            if ($this->eventTypeId == Schedule::$INTERRUPT_EVENT) {
                if (!v::intType()->notEmpty()->min(0)->max(3600)->validate($this->shareOfVoice)) {
                    throw new InvalidArgumentException(
                        __('Share of Voice must be a whole number between 0 and 3600'),
                        'shareOfVoice'
                    );
                }
            }

        } else if ($this->eventTypeId == Schedule::$COMMAND_EVENT) {
            // Validate command
            if (!v::intType()->notEmpty()->validate($this->commandId)) {
                throw new InvalidArgumentException(__('Please select a Command for this event.'), 'command');
            }
            $this->campaignId = null;
            $this->toDt = null;
        } elseif ($this->eventTypeId == Schedule::$ACTION_EVENT) {
            if (!v::stringType()->notEmpty()->validate($this->actionType)) {
                throw new InvalidArgumentException(__('Please select a Action Type for this event.'), 'actionType');
            }

            if (!v::stringType()->notEmpty()->validate($this->actionTriggerCode)) {
                throw new InvalidArgumentException(
                    __('Please select a Action trigger code for this event.'),
                    'actionTriggerCode'
                );
            }

            if ($this->actionType === 'command') {
                if (!v::intType()->notEmpty()->validate($this->commandId)) {
                    throw new InvalidArgumentException(__('Please select a Command for this event.'), 'commandId');
                }
            } elseif ($this->actionType === 'navLayout') {
                if (!v::stringType()->notEmpty()->validate($this->actionLayoutCode)) {
                    throw new InvalidArgumentException(
                        __('Please select a Layout code for this event.'),
                        'actionLayoutCode'
                    );
                }
                $this->commandId = null;
            }
            $this->campaignId = null;
        } else if ($this->eventTypeId === Schedule::$SYNC_EVENT) {
            if (!v::intType()->notEmpty()->validate($this->syncGroupId)) {
                throw new InvalidArgumentException(__('Please select a Sync Group for this event.'), 'syncGroupId');
            }

            if ($this->isCustomDayPart()) {
                // validate the dates
                if ($this->toDt <= $this->fromDt) {
                    throw new InvalidArgumentException(
                        __('Can not have an end time earlier than your start time'),
                        'start/end'
                    );
                }
            }
        } else if ($this->eventTypeId === Schedule::$DATA_CONNECTOR_EVENT) {
            if (!v::intType()->notEmpty()->validate($this->dataSetId)) {
                throw new InvalidArgumentException(__('Please select a DataSet for this event.'), 'dataSetId');
            }
            $this->campaignId = null;
        } else {
            // No event type selected
            throw new InvalidArgumentException(__('Please select the Event Type'), 'eventTypeId');
        }

        // Make sure we have a sensible recurrence setting
        if (!$this->isCustomDayPart() && ($this->recurrenceType == 'Minute' || $this->recurrenceType == 'Hour')) {
            throw new InvalidArgumentException(
                __('Repeats selection is invalid for Always or Daypart events'),
                'recurrencyType'
            );
        }
        // Check display order is positive
        if ($this->displayOrder < 0) {
            throw new InvalidArgumentException(__('Display Order must be 0 or a positive number'), 'displayOrder');
        }
        // Check priority is positive
        if ($this->isPriority < 0) {
            throw new InvalidArgumentException(__('Priority must be 0 or a positive number'), 'isPriority');
        }

        // Run some additional validation if we have a recurrence type set.
        if (!empty($this->recurrenceType)) {
            // Check recurrenceDetail every is positive
            if ($this->recurrenceDetail === null || $this->recurrenceDetail <= 0) {
                throw new InvalidArgumentException(__('Repeat every must be a positive number'), 'recurrenceDetail');
            }

            // Make sure that we don't repeat more frequently than the duration of the event as this is a common
            // misconfiguration which results in overlapping repeats
            if ($this->eventTypeId !== Schedule::$COMMAND_EVENT) {
                $eventDuration = $this->toDt - $this->fromDt;

                // Determine the number of seconds our repeat type/interval represents
                switch ($this->recurrenceType) {
                    case 'Minute':
                        $repeatDuration = $this->recurrenceDetail * 60;
                        break;

                    case 'Hour':
                        $repeatDuration = $this->recurrenceDetail * 3600;
                        break;

                    case 'Day':
                        $repeatDuration = $this->recurrenceDetail * 86400;
                        break;

                    case 'Week':
                        $repeatDuration = $this->recurrenceDetail * 86400 * 7;
                        break;

                    case 'Month':
                        $repeatDuration = $this->recurrenceDetail * 86400 * 30;
                        break;

                    case 'Year':
                        $repeatDuration = $this->recurrenceDetail * 86400 * 365;
                        break;

                    default:
                        throw new InvalidArgumentException(__('Unknown repeat type'), 'recurrenceType');
                }

                if ($repeatDuration < $eventDuration) {
                    throw new InvalidArgumentException(
                        __('An event cannot repeat more often than the interval between its start and end date'),
                        'recurrenceDetail',
                        $eventDuration . ' seconds'
                    );
                }
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
            'audit' => true,
            'deleteOrphaned' => false,
            'notify' => true
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        // Handle "always" day parts
        if ($this->isAlwaysDayPart()) {
            $this->fromDt = self::$DATE_MIN;
            $this->toDt = self::$DATE_MAX;
        }

        if ($this->eventId == null || $this->eventId == 0) {
            $this->add();
            $auditMessage = 'Added';
            $this->loaded = true;
            $isEdit = false;
        } else {
            // If this save action means there aren't any display groups assigned
            // and if we're set to deleteOrphaned, then delete
            if ($options['deleteOrphaned'] && count($this->displayGroups) <= 0) {
                $this->delete();
                return;
            } else {
                $this->edit();
                $auditMessage = 'Saved';
            }
            $isEdit = true;
        }

        // Manage display assignments
        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments($isEdit && $options['notify']);
        }

        // Update schedule criteria
        $criteriaIds = [];
        foreach ($this->criteria as $criteria) {
            $criteria->eventId = $this->eventId;
            $criteria->save();

            $criteriaIds[] = $criteria->id;
        }

        // Remove records that no longer exist.
        if (count($criteriaIds) > 0) {
            // There are still criteria left
            $this->getStore()->update('
                DELETE FROM `schedule_criteria` 
                 WHERE `id` NOT IN (' . implode(',', $criteriaIds) . ')
                    AND `eventId` = :eventId
            ', [
                'eventId' => $this->eventId,
            ]);
        } else {
            // No criteria left at all (or never was any)
            $this->getStore()->update('DELETE FROM `schedule_criteria`  WHERE `eventId` = :eventId', [
                'eventId' => $this->eventId,
            ]);
        }

        // Notify
        if ($options['notify']) {
            // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
            if ($this->inScheduleLookAhead()) {
                $this->getLog()->debug(
                    'Schedule changing is within the schedule look ahead, will notify '
                    . count($this->displayGroups) . ' display groups'
                );
                foreach ($this->displayGroups as $displayGroup) {
                    /* @var DisplayGroup $displayGroup */
                    $this
                        ->getDisplayNotifyService()
                        ->collectNow()
                        ->notifyByDisplayGroupId($displayGroup->displayGroupId);
                }
            } else {
                $this->getLog()->debug('Schedule changing is not within the schedule look ahead');
            }
        }

        if ($options['audit']) {
            $this->audit($this->getId(), $auditMessage, null, true);
        }

        // Drop the cache for this event
        $this->dropEventCache();
    }

    /**
     * Delete this Schedule Event
     */
    public function delete($options = [])
    {
        $this->load();

        $options = array_merge([
            'notify' => true
        ], $options);

        // Notify display groups
        $notify = $this->displayGroups;

        // Audit
        $this->audit($this->getId(), 'Deleted', $this->toArray(true));

        // Delete display group assignments
        $this->displayGroups = [];
        $this->unlinkDisplayGroups();

        // Delete schedule exclusions
        $scheduleExclusions = $this->scheduleExclusionFactory->query(null, ['eventId' => $this->eventId]);
        foreach ($scheduleExclusions as $exclusion) {
            $exclusion->delete();
        }

        // Delete schedule reminders
        if ($this->scheduleReminderFactory !== null) {
            $scheduleReminders = $this->scheduleReminderFactory->query(null, ['eventId' => $this->eventId]);

            foreach ($scheduleReminders as $reminder) {
                $reminder->delete();
            }
        }

        // Delete schedule criteria
        $this->getStore()->update('DELETE FROM `schedule_criteria` WHERE `eventId` = :eventId', [
            'eventId' => $this->eventId,
        ]);

        if ($this->eventTypeId === self::$SYNC_EVENT) {
            $this->getStore()->update('DELETE FROM `schedule_sync` WHERE eventId = :eventId', [
                'eventId' => $this->eventId
            ]);
        }

        // Delete the event itself
        $this->getStore()->update('DELETE FROM `schedule` WHERE eventId = :eventId', ['eventId' => $this->eventId]);

        // Notify
        if ($options['notify']) {
            // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
            if ($this->inScheduleLookAhead() && $this->displayNotifyService !== null) {
                $this->getLog()->debug(
                    'Schedule changing is within the schedule look ahead, will notify '
                    . count($notify) . ' display groups'
                );
                foreach ($notify as $displayGroup) {
                    /* @var DisplayGroup $displayGroup */
                    $this
                        ->getDisplayNotifyService()
                        ->collectNow()
                        ->notifyByDisplayGroupId($displayGroup->displayGroupId);
                }
            } else if ($this->displayNotifyService === null) {
                $this->getLog()->info('Notify disabled, dependencies not set');
            }
        } else {
            $this->getLog()->debug('Event delete: Notify disabled, option set to false');
        }

        // Drop the cache for this event
        $this->dropEventCache();
    }

    /**
     * Add
     */
    private function add()
    {
        $this->eventId = $this->getStore()->insert('
            INSERT INTO `schedule` (
                eventTypeId,
                CampaignId,
                commandId,
                userID,
                is_priority,
                FromDT,
                ToDT,
                DisplayOrder,
                recurrence_type,
                recurrence_detail,
                recurrence_range,
                `recurrenceRepeatsOn`,
                `recurrenceMonthlyRepeatsOn`,
                `dayPartId`,
                `syncTimezone`,
                `syncEvent`,
                `shareOfVoice`,
                `isGeoAware`,
                `geoLocation`,
                `actionType`,
                `actionTriggerCode`,
                `actionLayoutCode`,
                `maxPlaysPerHour`,
                `parentCampaignId`,
                `syncGroupId`,
                `modifiedBy`,
                `createdOn`,
                `updatedOn`,
                `name`,
                `dataSetId`,
                `dataSetParams`
            )
            VALUES (
                :eventTypeId,
                :campaignId,
                :commandId,
                :userId,
                :isPriority,
                :fromDt,
                :toDt,
                :displayOrder,
                :recurrenceType,
                :recurrenceDetail,
                :recurrenceRange,
                :recurrenceRepeatsOn,
                :recurrenceMonthlyRepeatsOn,
                :dayPartId,
                :syncTimezone,
                :syncEvent,
                :shareOfVoice,
                :isGeoAware,
                :geoLocation,
                :actionType,
                :actionTriggerCode,
                :actionLayoutCode,
                :maxPlaysPerHour,
                :parentCampaignId,
                :syncGroupId,
                :modifiedBy,
                :createdOn,
                :updatedOn,
                :name,
                :dataSetId,
                :dataSetParams
            )
        ', [
            'eventTypeId' => $this->eventTypeId,
            'campaignId' => $this->campaignId,
            'commandId' => $this->commandId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => empty($this->recurrenceType) ? null : $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange,
            'recurrenceRepeatsOn' => $this->recurrenceRepeatsOn,
            'recurrenceMonthlyRepeatsOn' => ($this->recurrenceMonthlyRepeatsOn == null)
                ? 0 :
                $this->recurrenceMonthlyRepeatsOn,
            'dayPartId' => $this->dayPartId,
            'syncTimezone' => $this->syncTimezone,
            'syncEvent' => $this->syncEvent,
            'shareOfVoice' => $this->shareOfVoice,
            'isGeoAware' => $this->isGeoAware,
            'geoLocation' => $this->geoLocation,
            'actionType' => $this->actionType,
            'actionTriggerCode' => $this->actionTriggerCode,
            'actionLayoutCode' => $this->actionLayoutCode,
            'maxPlaysPerHour' => $this->maxPlaysPerHour,
            'parentCampaignId' => $this->parentCampaignId == 0 ? null : $this->parentCampaignId,
            'syncGroupId' => $this->syncGroupId == 0 ? null : $this->syncGroupId,
            'modifiedBy' => null,
            'createdOn' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'updatedOn' => null,
            'name' => !empty($this->name) ? $this->name : null,
            'dataSetId' => !empty($this->dataSetId) ? $this->dataSetId : null,
            'dataSetParams' => !empty($this->dataSetParams) ? $this->dataSetParams : null,
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $this->getStore()->update('
          UPDATE `schedule` SET
            eventTypeId = :eventTypeId,
            campaignId = :campaignId,
            commandId = :commandId,
            is_priority = :isPriority,
            userId = :userId,
            fromDt = :fromDt,
            toDt = :toDt,
            displayOrder = :displayOrder,
            recurrence_type = :recurrenceType,
            recurrence_detail = :recurrenceDetail,
            recurrence_range = :recurrenceRange,
            `recurrenceRepeatsOn` = :recurrenceRepeatsOn,
            `recurrenceMonthlyRepeatsOn` = :recurrenceMonthlyRepeatsOn,
            `dayPartId` = :dayPartId,
            `syncTimezone` = :syncTimezone,
            `syncEvent` = :syncEvent,
            `shareOfVoice` = :shareOfVoice,
            `isGeoAware` = :isGeoAware,
            `geoLocation` = :geoLocation,
            `actionType` = :actionType,
            `actionTriggerCode` = :actionTriggerCode,
            `actionLayoutCode` = :actionLayoutCode,
            `maxPlaysPerHour` = :maxPlaysPerHour,
            `parentCampaignId` = :parentCampaignId,
            `syncGroupId` = :syncGroupId,
            `modifiedBy` = :modifiedBy,
            `updatedOn` = :updatedOn,
            `name` = :name,
            `dataSetId` = :dataSetId,
            `dataSetParams` = :dataSetParams
          WHERE eventId = :eventId
        ', [
            'eventTypeId' => $this->eventTypeId,
            'campaignId' => ($this->campaignId !== 0) ? $this->campaignId : null,
            'commandId' => $this->commandId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => empty($this->recurrenceType) ? null : $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange,
            'recurrenceRepeatsOn' => $this->recurrenceRepeatsOn,
            'recurrenceMonthlyRepeatsOn' => $this->recurrenceMonthlyRepeatsOn,
            'dayPartId' => $this->dayPartId,
            'syncTimezone' => $this->syncTimezone,
            'syncEvent' => $this->syncEvent,
            'shareOfVoice' => $this->shareOfVoice,
            'isGeoAware' => $this->isGeoAware,
            'geoLocation' => $this->geoLocation,
            'actionType' => $this->actionType,
            'actionTriggerCode' => $this->actionTriggerCode,
            'actionLayoutCode' => $this->actionLayoutCode,
            'maxPlaysPerHour' => $this->maxPlaysPerHour,
            'parentCampaignId' => $this->parentCampaignId == 0 ? null : $this->parentCampaignId,
            'syncGroupId' => $this->syncGroupId == 0 ? null : $this->syncGroupId,
            'modifiedBy' => $this->modifiedBy,
            'updatedOn' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'name' => $this->name,
            'dataSetId' => !empty($this->dataSetId) ? $this->dataSetId : null,
            'dataSetParams' => !empty($this->dataSetParams) ? $this->dataSetParams : null,
            'eventId' => $this->eventId,
        ]);
    }

    /**
     * Get events between the provided dates.
     * @param Carbon $fromDt
     * @param Carbon $toDt
     * @return ScheduleEvent[]
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getEvents($fromDt, $toDt)
    {
        // Events scheduled "always" will return one event
        if ($this->isAlwaysDayPart()) {
            // Create events with min/max dates
            $this->addDetail(Schedule::$DATE_MIN, Schedule::$DATE_MAX);
            return $this->scheduleEvents;
        }

        // Copy the dates as we are going to be operating on them.
        $fromDt = $fromDt->copy();
        $toDt = $toDt->copy();

        if ($this->pool == null) {
            throw new ConfigurationException(__('Cache pool not available'));
        }
        if ($this->eventId == null) {
            throw new InvalidArgumentException(__('Unable to generate schedule, unknown event'), 'eventId');
        }
        // What if we are requesting a single point in time?
        if ($fromDt == $toDt) {
            $this->log->debug(
                'Requesting event for a single point in time: '
                . $fromDt->format(DateFormatHelper::getSystemFormat())
            );
        }

        $events = [];
        $fromTimeStamp = $fromDt->format('U');
        $toTimeStamp = $toDt->format('U');

        // Rewind the from date to the start of the month
        $fromDt->startOfMonth();

        if ($fromDt == $toDt) {
            $this->log->debug(
                'From and To Dates are the same after rewinding 1 month,
                 the date is the 1st of the month, adding a month to toDate.'
            );
            $toDt->addMonth();
        }

        // Load the dates into a date object for parsing
        $eventStart = Carbon::createFromTimestamp($this->fromDt);
        $eventEnd = ($this->toDt == null) ? $eventStart->copy() :  Carbon::createFromTimestamp($this->toDt);

        // Does the original event go over the month boundary?
        if ($eventStart->month !== $eventEnd->month) {
            // We expect some residual events to spill out into the month we are generating
            // wind back the generate from date
            $fromDt->subMonth();

            $this->getLog()->debug(
                'Expecting events from the prior month to spill over into this one,
                 pulled back the generate from dt to ' . $fromDt->toRssString()
            );
        } else {
            $this->getLog()->debug('The main event has a start and end date within the month, no need to pull it in from the prior month. [eventId:' . $this->eventId . ']');
        }

        // Keep a cache of schedule exclusions, so we look them up by eventId only one time per event
        $scheduleExclusions = $this->scheduleExclusionFactory->query(null, ['eventId' => $this->eventId]);

        // Request month cache
        while ($fromDt < $toDt) {
            // Empty scheduleEvents as we are looping through each month
            // we dont want to save previous month events
            $this->scheduleEvents = [];

            // Events for the month.
            $this->generateMonth($fromDt, $eventStart, $eventEnd);

            $this->getLog()->debug(
                'Filtering Events: ' . json_encode($this->scheduleEvents, JSON_PRETTY_PRINT)
                . '. fromTimeStamp: ' . $fromTimeStamp . ', toTimeStamp: ' . $toTimeStamp
            );

            foreach ($this->scheduleEvents as $scheduleEvent) {
                // Find the excluded recurring events
                $exclude = false;
                foreach ($scheduleExclusions as $exclusion) {
                    if ($scheduleEvent->fromDt == $exclusion->fromDt &&
                        $scheduleEvent->toDt == $exclusion->toDt) {
                        $exclude = true;
                        continue;
                    }
                }

                if ($exclude) {
                    continue;
                }

                if (in_array($scheduleEvent, $events)) {
                    continue;
                }

                if ($scheduleEvent->toDt == null) {
                    if ($scheduleEvent->fromDt >= $fromTimeStamp && $scheduleEvent->toDt < $toTimeStamp) {
                        $events[] = $scheduleEvent;
                    }
                } else {
                    if ($scheduleEvent->fromDt <= $toTimeStamp && $scheduleEvent->toDt > $fromTimeStamp) {
                        $events[] = $scheduleEvent;
                    }
                }
            }

            // Move the month forwards
            $fromDt->addMonth();
        }

        // Clear our cache of schedule exclusions
        $scheduleExclusions = null;

        $this->getLog()->debug(
            'Filtered ' . count($this->scheduleEvents) . ' to ' . count($events)
            . ', events: ' . json_encode($events, JSON_PRETTY_PRINT)
        );

        return $events;
    }

    /**
     * Generate Instances
     * @param Carbon $generateFromDt
     * @param Carbon $start
     * @param Carbon $end
     * @throws GeneralException
     */
    private function generateMonth($generateFromDt, $start, $end)
    {
        // Operate on copies of the dates passed.
        $start = $start->copy();
        $end = $end->copy();
        $generateFromDt->copy()->startOfMonth();
        $generateToDt = $generateFromDt->copy()->addMonth();

        $this->getLog()->debug(
            'Request for schedule events on eventId ' . $this->eventId
            . ' from: ' . Carbon::createFromTimestamp($generateFromDt->format(DateFormatHelper::getSystemFormat()))
            . ' to: ' . Carbon::createFromTimestamp($generateToDt->format(DateFormatHelper::getSystemFormat()))
            . ' [eventId:' . $this->eventId . ']'
        );

        // If we are a daypart event, look up the start/end times for the event
        $this->calculateDayPartTimes($start, $end);

        // Does the original event fall into this window?
        if ($start <= $generateToDt && $end > $generateFromDt) {
            // Add the detail for the main event (this is the event that originally triggered the generation)
            $this->getLog()->debug('Adding original event: ' . $start->toAtomString() . ' - ' . $end->toAtomString());
            $this->addDetail($start->format('U'), $end->format('U'));
        }

        // If we don't have any recurrence, we are done
        if (empty($this->recurrenceType) || empty($this->recurrenceDetail)) {
            return;
        }

        // Detect invalid recurrences and quit early
        if (!$this->isCustomDayPart() && ($this->recurrenceType == 'Minute' || $this->recurrenceType == 'Hour')) {
            return;
        }

        // Check the cache
        $item = $this->pool->getItem('schedule/' . $this->eventId . '/' . $generateFromDt->format('Y-m'));

        if ($item->isHit()) {
            $this->scheduleEvents = $item->get();
            $this->getLog()->debug('Returning from cache! [eventId:' . $this->eventId . ']');
            return;
        }

        $this->getLog()->debug('Cache miss! [eventId:' . $this->eventId . ']');

        // vv anything below here means that the event window requested is not in the cache vv
        // WE ARE NOT IN THE CACHE
        // this means we need to always walk the tree from the last watermark
        // if the last watermark is after the from window, then we need to walk from the beginning

        // Handle recurrence
        $originalStart = $start->copy();
        $lastWatermark = ($this->lastRecurrenceWatermark != 0)
            ? Carbon::createFromTimestamp($this->lastRecurrenceWatermark)
            : Carbon::createFromTimestamp(self::$DATE_MIN);

        $this->getLog()->debug(
            'Recurrence calculation required - last water mark is set to: ' . $lastWatermark->toRssString()
            . '. Event dates: ' . $start->toRssString() . ' - '
            . $end->toRssString() . ' [eventId:' . $this->eventId . ']'
        );

        // Set the temp starts
        // the start date should be the latest of the event start date and the last recurrence date
        if ($lastWatermark > $start && $lastWatermark < $generateFromDt) {
            $this->getLog()->debug(
                'The last watermark is later than the event start date and the generate from dt,
                 using the watermark for forward population [eventId:' . $this->eventId . ']'
            );

            // Need to set the toDt based on the original event duration and the watermark start date
            $eventDuration = $start->diffInSeconds($end, true);

            /** @var Carbon $start */
            $start = $lastWatermark->copy();
            $end = $start->copy()->addSeconds($eventDuration);

            if ($start <= $generateToDt && $end >= $generateFromDt) {
                $this->getLog()->debug('The event start/end is inside the month');
                // If we're a weekly repeat, check that the start date is on a selected day
                if ($this->recurrenceType !== 'Week'
                    || (!empty($this->recurrenceRepeatsOn)
                        && in_array($start->dayOfWeekIso, explode(',', $this->recurrenceRepeatsOn)))
                ) {
                    $this->addDetail($start->format('U'), $end->format('U'));
                }
            }
        }

        // range should be the smallest of the recurrence range and the generate window todt
        // the start/end date should be the the first recurrence in the current window
        if ($this->recurrenceRange != 0) {
            $range = Carbon::createFromTimestamp($this->recurrenceRange);

            // Override the range to be within the period we are looking
            $range = ($range < $generateToDt) ? $range : $generateToDt->copy();
        } else {
            $range = $generateToDt->copy();
        }

        $this->getLog()->debug(
            '[' . $generateFromDt->toRssString() . ' - ' . $generateToDt->toRssString()
            . '] Looping from ' . $start->toRssString()
            . ' to ' . $range->toRssString() . ' [eventId:' . $this->eventId . ']'
        );

        // loop until we have added the recurring events for the schedule
        while ($start < $range) {
            $this->getLog()->debug(
                'Loop: ' . $start->toRssString() . ' to ' . $range->toRssString()
                . ' [eventId:' . $this->eventId . ', end: ' . $end->toRssString() . ']'
            );

            // add the appropriate time to the start and end
            switch ($this->recurrenceType) {
                case 'Minute':
                    $start->minute($start->minute + $this->recurrenceDetail);
                    $end->minute($end->minute + $this->recurrenceDetail);
                    break;

                case 'Hour':
                    $start->hour($start->hour + $this->recurrenceDetail);
                    $end->hour($end->hour + $this->recurrenceDetail);
                    break;

                case 'Day':
                    $start->day($start->day + $this->recurrenceDetail);
                    $end->day($end->day + $this->recurrenceDetail);
                    break;

                case 'Week':
                    // recurrenceRepeatsOn will contain an array we can use to determine which days it should repeat
                    // on. Roll forward 7 days, adding each day we hit
                    // if we go over the start of the week, then jump forward by the recurrence range
                    if (!empty($this->recurrenceRepeatsOn)) {
                        // Parse days selected and add the necessary events
                        $daysSelected = explode(',', $this->recurrenceRepeatsOn);

                        // Are we on the start day of this week already?
                        $onStartOfWeek = ($start->copy()->setTimeFromTimeString('00:00:00') == $start->copy()->locale(Translate::GetLocale())->startOfWeek()->setTimeFromTimeString('00:00:00'));

                        // What is the end of this week
                        $endOfWeek = $start->copy()->locale(Translate::GetLocale())->endOfWeek();

                        $this->getLog()->debug(
                            'Days selected: ' . $this->recurrenceRepeatsOn . '. End of week = ' . $endOfWeek
                            . ' start date ' . $start . ' [eventId:' . $this->eventId . ']'
                        );

                        for ($i = 1; $i <= 7; $i++) {
                            // Add a day to the start dates
                            // after the first pass, we will already be on the first day of the week
                            if ($i > 1 || !$onStartOfWeek) {
                                $start->day($start->day + 1);
                                $end->day($end->day + 1);
                            }

                            $this->getLog()->debug('Assessing start date ' . $start->toAtomString()
                                . ', isoDayOfWeek is ' . $start->dayOfWeekIso . ' [eventId:' . $this->eventId . ']');

                            // If we go over the recurrence range, stop
                            // if we go over the start of the week, stop
                            if ($start > $range || $start > $endOfWeek) {
                                break;
                            }

                            // Is this day set?
                            if (!in_array($start->dayOfWeekIso, $daysSelected)) {
                                continue;
                            }

                            if ($end > $generateFromDt && $start < $generateToDt) {
                                $this->getLog()->debug(
                                    'Adding detail for ' . $start->toAtomString() . ' to ' . $end->toAtomString()
                                );

                                if ($this->eventTypeId == self::$COMMAND_EVENT) {
                                    $this->addDetail($start->format('U'), null);
                                } else {
                                    // If we are a daypart event, look up the start/end times for the event
                                    $this->calculateDayPartTimes($start, $end);

                                    $this->addDetail($start->format('U'), $end->format('U'));
                                }
                            } else {
                                $this->getLog()->debug('Event is outside range');
                            }
                        }

                        $this->getLog()->debug(
                            'Finished 7 day roll forward, start date is ' . $start . ' [eventId:' . $this->eventId . ']'
                        );

                        // If we haven't passed the end of the week, roll forward
                        if ($start < $endOfWeek) {
                            $start->day($start->day + 1);
                            $end->day($end->day + 1);
                        }

                        // Wind back a week and then add our recurrence detail
                        $start->day($start->day - 7);
                        $end->day($end->day - 7);

                        $this->getLog()->debug(
                            'Resetting start date to ' . $start . ' [eventId:' . $this->eventId . ']'
                        );
                    }

                    // Jump forward a week from the original start date (when we entered this loop)
                    $start->day($start->day + ($this->recurrenceDetail * 7));
                    $end->day($end->day + ($this->recurrenceDetail * 7));

                    break;

                case 'Month':
                    // We use the difference to set the end date
                    $difference = $end->diffInSeconds($start);

                    // Are we repeating on the day of the month, or the day of the week
                    if ($this->recurrenceMonthlyRepeatsOn == 1) {
                        // Week day repeat
                        // Work out the position in the month of this day and the ordinal
                        $ordinals = ['first', 'second', 'third', 'fourth', 'last'];
                        $ordinal = $ordinals[ceil($originalStart->day / 7) - 1];

                        // Move forwards to the start of the appropriate month
                        for ($i = 0; $i < $this->recurrenceDetail; $i++) {
                            $start->endOfMonth()->addSecond();
                        }

                        // Set to the right day
                        $start->modify($ordinal . ' ' . $originalStart->format('l') . ' of ' . $start->format('F Y'));
                        $start->setTimeFrom($originalStart);

                        $this->getLog()->debug('Monthly repeats every ' . $this->recurrenceDetail . ' months on '
                            . $ordinal . ' ' . $start->format('l') . ' of ' . $start->format('F Y'));
                    } else {
                        // Day repeat
                        $startTest = $start->copy()->addDays(28 * $this->recurrenceDetail);
                        if ($originalStart->day > intval($startTest->format('t'))) {
                            // The next month has fewer days than the current month
                            $start = $startTest->endOfMonth()->setTimeFrom($originalStart);
                        } else {
                            $start->addMonth()->day($originalStart->day);
                        }

                        $this->getLog()->debug('Monthly repeats every ' . $this->recurrenceDetail . ' months '
                            . ' on a specific day ' . $originalStart->day . ' days this month ' . $start->format('t')
                            . ' set to ' . $start->format('Y-m-d'));
                    }

                    // Base the end on the start + difference
                    $end = $start->copy()->addSeconds($difference);
                    break;

                case 'Year':
                    $start->year($start->year + $this->recurrenceDetail);
                    $end->year($end->year + $this->recurrenceDetail);
                    break;

                default:
                    throw new InvalidArgumentException(__('Invalid recurrence type'), 'recurrenceType');
            }

            // after we have added the appropriate amount, are we still valid
            if ($start > $range) {
                $this->getLog()->debug(
                    'Breaking mid loop because we\'ve exceeded the range. Start: ' . $start->toRssString()
                    . ', range: ' . $range->toRssString() . ' [eventId:' . $this->eventId . ']'
                );
                break;
            }

            // Push the watermark
            $lastWatermark = $start->copy();

            // Don't add if we are weekly recurrency (handles it's own adding)
            if ($this->recurrenceType == 'Week' && !empty($this->recurrenceRepeatsOn)) {
                continue;
            }

            if ($start <= $generateToDt && $end >= $generateFromDt) {
                if ($this->eventTypeId == self::$COMMAND_EVENT) {
                    $this->addDetail($start->format('U'), null);
                } else {
                    // If we are a daypart event, look up the start/end times for the event
                    $this->calculateDayPartTimes($start, $end);

                    $this->addDetail($start->format('U'), $end->format('U'));
                }
            }
        }

        $this->getLog()->debug(
            'Our last recurrence watermark is: ' . $lastWatermark->toRssString()
            . '[eventId:' . $this->eventId . ']'
        );

        // Update our schedule with the new last watermark
        $lastWatermarkTimeStamp = $lastWatermark->format('U');

        if ($lastWatermarkTimeStamp != $this->lastRecurrenceWatermark) {
            $this->lastRecurrenceWatermark = $lastWatermarkTimeStamp;
            $this->getStore()->update('UPDATE `schedule` SET lastRecurrenceWatermark = :lastRecurrenceWatermark WHERE eventId = :eventId', [
                'eventId' => $this->eventId,
                'lastRecurrenceWatermark' => $this->lastRecurrenceWatermark
            ]);
        }

        // Update the cache
        $item->set($this->scheduleEvents);
        $item->expiresAt(Carbon::now()->addMonths(2));

        $this->pool->saveDeferred($item);

        return;
    }

    /**
     * Drop the event cache
     * @param $key
     */
    private function dropEventCache($key = null)
    {
        $compKey = 'schedule/' . $this->eventId;

        if ($key !== null) {
            $compKey .= '/' . $key;
        }

        $this->pool->deleteItem($compKey);
    }

    /**
     * Calculate the DayPart times
     * @param Carbon $start
     * @param Carbon $end
     * @throws GeneralException
     */
    private function calculateDayPartTimes($start, $end)
    {
        $dayOfWeekLookup = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        if (!$this->isAlwaysDayPart() && !$this->isCustomDayPart()) {
            // TODO: replace with $dayPart->adjustForDate()?
            // End is always based on Start
            $end->setTimestamp($start->format('U'));

            // Get the day part
            $dayPart = $this->getDayPart();

            $this->getLog()->debug(
                'Start and end time for dayPart is ' . $dayPart->startTime . ' - ' . $dayPart->endTime
            );

            // What day of the week does this start date represent?
            // dayOfWeek is 0 for Sunday to 6 for Saturday
            $found = false;
            foreach ($dayPart->exceptions as $exception) {
                // Is there an exception for this day of the week?
                if ($exception['day'] == $dayOfWeekLookup[$start->dayOfWeek]) {
                    $start->setTimeFromTimeString($exception['start']);
                    $end->setTimeFromTimeString($exception['end']);

                    if ($start >= $end) {
                        $end->addDay();
                    }

                    $this->getLog()->debug(
                        'Found exception Start and end time for dayPart exception is '
                        . $exception['start'] . ' - ' . $exception['end']
                    );
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                // Set the time section of our dates based on the daypart date
                $start->setTimeFromTimeString($dayPart->startTime);
                $end->setTimeFromTimeString($dayPart->endTime);

                if ($start >= $end) {
                    $this->getLog()->debug('Start is ahead of end - adding a day to the end date');
                    $end->addDay();
                }
            }
        }
    }

    /**
     * Add Detail
     * @param int $fromDt
     * @param int $toDt
     */
    private function addDetail($fromDt, $toDt)
    {
        $this->scheduleEvents[] = new ScheduleEvent($fromDt, $toDt);
    }

    /**
     * Manage the assignments
     * @param bool $notify should we notify or not?
     * @throws \Xibo\Exception\XiboException
     */
    private function manageAssignments($notify)
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();

        $this->getLog()->debug('manageAssignments: Assessing whether we need to notify');
        $originalDisplayGroups = $this->getOriginalValue('displayGroups');

        // Get the difference between the original display groups assigned and the new display groups assigned
        if ($notify && $originalDisplayGroups !== null && $this->inScheduleLookAhead()) {
            $diff = [];
            foreach ($originalDisplayGroups as $element) {
                /** @var \Xibo\Entity\DisplayGroup $element */
                $diff[$element->getId()] = $element;
            }

            if (count($diff) > 0) {
                $this->getLog()->debug(
                    'manageAssignments: There are ' . count($diff) . ' existing DisplayGroups on this Event'
                );
                $ids = array_map(function ($element) {
                    return $element->getId();
                }, $this->displayGroups);

                $except = array_diff(array_keys($diff), $ids);

                if (count($except) > 0) {
                    foreach ($except as $item) {
                        $this->getLog()->debug(
                            'manageAssignments: calling notify on displayGroupId '
                            . $diff[$item]->getId()
                        );
                        $this->getDisplayNotifyService()->collectNow()->notifyByDisplayGroupId($diff[$item]->getId());
                    }
                } else {
                    $this->getLog()->debug('manageAssignments: No need to notify');
                }
            } else {
                $this->getLog()->debug('manageAssignments: No change to DisplayGroup assignments');
            }
        } else {
            $this->getLog()->debug('manageAssignments: Not in look-ahead');
        }
    }

    /**
     * Link Layout
     */
    private function linkDisplayGroups()
    {
        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkscheduledisplaygroup` (eventId, displayGroupId) VALUES (:eventId, :displayGroupId) ON DUPLICATE KEY UPDATE displayGroupId = displayGroupId';

        $i = 0;
        foreach ($this->displayGroups as $displayGroup) {
            $i++;

            $this->getStore()->insert($sql, array(
                'eventId' => $this->eventId,
                'displayGroupId' => $displayGroup->displayGroupId
            ));
        }
    }

    /**
     * Unlink Layout
     */
    private function unlinkDisplayGroups()
    {
        // Unlink any layouts that are NOT in the collection
        $params = ['eventId' => $this->eventId];

        $sql = 'DELETE FROM `lkscheduledisplaygroup` WHERE eventId = :eventId AND displayGroupId NOT IN (0';

        $i = 0;
        foreach ($this->displayGroups as $displayGroup) {
            $i++;
            $sql .= ',:displayGroupId' . $i;
            $params['displayGroupId' . $i] = $displayGroup->displayGroupId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    /**
     * @return \Xibo\Entity\DayPart
     * @throws \Xibo\Exception\NotFoundException
     */
    private function getDayPart()
    {
        if ($this->dayPart === null) {
            $this->dayPart = $this->dayPartFactory->getById($this->dayPartId);
        }

        return $this->dayPart;
    }

    /**
     * Is this event an always daypart event
     * @return bool
     * @throws NotFoundException
     */
    public function isAlwaysDayPart()
    {
        return $this->getDayPart()->isAlways === 1;
    }

    /**
     * Is this event a custom daypart event
     * @return bool
     * @throws NotFoundException
     */
    public function isCustomDayPart()
    {
        return $this->getDayPart()->isCustom === 1;
    }

    /**
     * Get next reminder date
     * @param Carbon $now
     * @param ScheduleReminder $reminder
     * @param int $remindSeconds
     * @return int|null
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getNextReminderDate($now, $reminder, $remindSeconds)
    {
        // Determine toDt so that we don't getEvents which never ends
        // adding the recurrencedetail at the end (minute/hour/week) to make sure we get at least 2 next events
        $toDt = $now->copy();

        // For a future event we need to forward now to event fromDt
        $fromDt = Carbon::createFromTimestamp($this->fromDt);
        if ($fromDt > $toDt) {
            $toDt = $fromDt;
        }

        switch ($this->recurrenceType) {
            case 'Minute':
                $toDt->minute(($toDt->minute + $this->recurrenceDetail) + $this->recurrenceDetail);
                break;

            case 'Hour':
                $toDt->hour(($toDt->hour + $this->recurrenceDetail) + $this->recurrenceDetail);
                break;

            case 'Day':
                $toDt->day(($toDt->day + $this->recurrenceDetail) + $this->recurrenceDetail);
                break;

            case 'Week':
                $toDt->day(($toDt->day + $this->recurrenceDetail * 7 ) + $this->recurrenceDetail);
                break;

            case 'Month':
                $toDt->month(($toDt->month + $this->recurrenceDetail ) + $this->recurrenceDetail);
                break;

            case 'Year':
                $toDt->year(($toDt->year + $this->recurrenceDetail ) + $this->recurrenceDetail);
                break;

            default:
                throw new InvalidArgumentException(__('Invalid recurrence type'), 'recurrenceType');
        }

        // toDt is set so that we get two next events from now
        $scheduleEvents = $this->getEvents($now, $toDt);

        foreach ($scheduleEvents as $event) {
            if ($reminder->option == ScheduleReminder::$OPTION_BEFORE_START) {
                $reminderDt = $event->fromDt - $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            } elseif ($reminder->option == ScheduleReminder::$OPTION_AFTER_START) {
                $reminderDt = $event->fromDt + $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            } elseif ($reminder->option == ScheduleReminder::$OPTION_BEFORE_END) {
                $reminderDt = $event->toDt - $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            } elseif ($reminder->option == ScheduleReminder::$OPTION_AFTER_END) {
                $reminderDt = $event->toDt + $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            }
        }

        // No next event exist
        throw new NotFoundException(__('reminderDt not found as next event does not exist'));
    }

    /**
     * Get event title
     * @return string
     * @throws GeneralException
     */
    public function getEventTitle()
    {
        // Setting for whether we show Layouts with out permissions
        $showLayoutName = ($this->config->getSetting('SCHEDULE_SHOW_LAYOUT_NAME') == 1);

        // Load the display groups
        $this->load();

        $displayGroupList = '';

        if (count($this->displayGroups) >= 0) {
            $array = array_map(function ($object) {
                return $object->displayGroup;
            }, $this->displayGroups);
            $displayGroupList = implode(', ', $array);
        }

        $user = $this->userFactory->getById($this->userId);

        // Event Title
        if ($this->campaignId == 0) {
            // Command
            $title = __('%s scheduled on %s', $this->command, $displayGroupList);
        } else {
            // Should we show the Layout name, or not (depending on permission)
            // Make sure we only run the below code if we have to, its quite expensive
            if (!$showLayoutName && !$user->isSuperAdmin()) {
                // Campaign
                $campaign = $this->campaignFactory->getById($this->campaignId);

                if (!$user->checkViewable($campaign)) {
                    $this->campaign = __('Private Item');
                }
            }
            $title = __('%s scheduled on %s', $this->campaign, $displayGroupList);
        }

        return $title;
    }

    private function toArray($jsonEncodeArrays = false)
    {
        $objectAsJson = $this->jsonSerialize();

        foreach ($objectAsJson as $key => $value) {
            $displayGroups = [];
            if (is_array($value) && $jsonEncodeArrays) {
                if ($key === 'displayGroups') {
                    foreach ($value as $index => $displayGroup) {
                        /** @var DisplayGroup $displayGroup */
                        $displayGroups[$index] = $displayGroup->jsonForAudit();
                    }

                    $objectAsJson[$key] = json_encode($displayGroups);
                } else {
                    $objectAsJson[$key] = json_encode($value);
                }
            }

            if (in_array($key, $this->datesToFormat)) {
                $objectAsJson[$key] = !empty($value)
                    ? Carbon::createFromTimestamp($value)->format(DateFormatHelper::getSystemFormat())
                    : $value;
            }

            if ($key === 'campaignId' && isset($this->campaignFactory)) {
                $campaign = $this->campaignFactory->getById($value);
                $objectAsJson['campaign'] = $campaign->campaign;
            }
        }

        return $objectAsJson;
    }

    /**
     * Get all changed properties for this entity
     * @param bool $jsonEncodeArrays
     * @return array
     * @throws NotFoundException
     */
    public function getChangedProperties($jsonEncodeArrays = false)
    {
        $changedProperties = [];

        foreach ($this->jsonSerialize() as $key => $value) {
            if (!is_array($value)
                && !is_object($value)
                && $this->propertyOriginallyExisted($key)
                && $this->hasPropertyChanged($key)
            ) {
                if (in_array($key, $this->datesToFormat)) {
                    $original = empty($this->getOriginalValue($key))
                        ? $this->getOriginalValue($key)
                        : Carbon::createFromTimestamp($this->getOriginalValue($key))
                            ->format(DateFormatHelper::getSystemFormat());
                    $new = empty($value)
                        ? $value
                        : Carbon::createFromTimestamp($value)
                            ->format(DateFormatHelper::getSystemFormat());
                    $changedProperties[$key] = $original . ' > ' . $new;
                } else {
                    $changedProperties[$key] = $this->getOriginalValue($key) . ' > ' . $value;

                    if ($key === 'campaignId' && isset($this->campaignFactory)) {
                        $campaign = $this->campaignFactory->getById($value);
                        $changedProperties['campaign'] =
                            $this->getOriginalValue('campaign') . ' > ' . $campaign->campaign;
                    }
                }
            }

            if (is_array($value)
                && $jsonEncodeArrays
                && $this->propertyOriginallyExisted($key)
                && $this->hasPropertyChanged($key)
            ) {
                if ($key === 'displayGroups') {
                    $displayGroups = [];
                    $originalDisplayGroups = [];

                    foreach ($this->getOriginalValue($key) as $index => $displayGroup) {
                        /** @var DisplayGroup $displayGroup */
                        $originalDisplayGroups[$index] = $displayGroup->jsonForAudit();
                    }

                    foreach ($value as $index => $displayGroup) {
                        $displayGroups[$index] = $displayGroup->jsonForAudit();
                    }

                    $changedProperties[$key] =
                        json_encode($originalDisplayGroups) . ' > ' . json_encode($displayGroups);
                } else {
                    $changedProperties[$key] =
                        json_encode($this->getOriginalValue($key)) . ' > ' . json_encode($value);
                }
            }
        }

        return $changedProperties;
    }

    /**
     * Get an array of event types for the add/edit form
     * @return array
     */
    public static function getEventTypesForm(): array
    {
        return [
            ['eventTypeId' => self::$LAYOUT_EVENT, 'eventTypeName' => __('Layout')],
            ['eventTypeId' => self::$COMMAND_EVENT, 'eventTypeName' => __('Command')],
            ['eventTypeId' => self::$OVERLAY_EVENT, 'eventTypeName' => __('Overlay Layout')],
            ['eventTypeId' => self::$INTERRUPT_EVENT, 'eventTypeName' => __('Interrupt Layout')],
            ['eventTypeId' => self::$CAMPAIGN_EVENT, 'eventTypeName' => __('Campaign')],
            ['eventTypeId' => self::$ACTION_EVENT, 'eventTypeName' => __('Action')],
            ['eventTypeId' => self::$MEDIA_EVENT, 'eventTypeName' => __('Video/Image')],
            ['eventTypeId' => self::$PLAYLIST_EVENT, 'eventTypeName' => __('Playlist')],
            ['eventTypeId' => self::$DATA_CONNECTOR_EVENT, 'eventTypeName' => __('Data Connector')],
        ];
    }

    /**
     * Get an array of event types for the grid
     * @return array
     */
    public static function getEventTypesGrid(): array
    {
        $events = self::getEventTypesForm();
        $events[] = ['eventTypeId' => self::$SYNC_EVENT, 'eventTypeName' => __('Synchronised Event')];

        return $events;
    }

    /**
     * @return string
     */
    public function getSyncTypeForEvent(): string
    {
        $layouts = $this->getStore()->select(
            'SELECT `schedule_sync`.layoutId FROM `schedule_sync` WHERE `schedule_sync`.eventId = :eventId',
            ['eventId' => $this->eventId]
        );

        return (count(array_unique($layouts, SORT_REGULAR)) === 1)
            ? __('Synchronised Mirrored Content')
            : __('Synchronised Content');
    }

    /**
     * @param SyncGroup $syncGroup
     * @param SanitizerInterface $sanitizer
     * @return void
     * @throws NotFoundException
     */
    public function updateSyncLinks(SyncGroup $syncGroup, SanitizerInterface $sanitizer): void
    {
        foreach ($syncGroup->getSyncGroupMembers() as $display) {
            $this->getStore()->insert('INSERT INTO `schedule_sync` (`eventId`, `displayId`, `layoutId`)
            VALUES(:eventId, :displayId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = :layoutId', [
                'eventId' => $this->eventId,
                'displayId' => $display->displayId,
                'layoutId' => $sanitizer->getInt('layoutId_' . $display->displayId)
            ]);
        }
    }
}
