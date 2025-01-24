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
namespace Xibo\Controller;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Entity\ScheduleReminder;
use Xibo\Event\ScheduleCriteriaRequestEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ScheduleCriteriaFactory;
use Xibo\Factory\ScheduleExclusionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\ScheduleReminderFactory;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Session;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Schedule
 * @package Xibo\Controller
 */
class Schedule extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var ScheduleReminderFactory
     */
    private $scheduleReminderFactory;

    /**
     * @var ScheduleExclusionFactory
     */
    private $scheduleExclusionFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;
    private SyncGroupFactory $syncGroupFactory;

    /**
     * Set common dependencies.
     * @param Session $session
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param CampaignFactory $campaignFactory
     * @param CommandFactory $commandFactory
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param DayPartFactory $dayPartFactory
     * @param ScheduleReminderFactory $scheduleReminderFactory
     * @param ScheduleExclusionFactory $scheduleExclusionFactory
     */

    public function __construct(
        $session,
        $scheduleFactory,
        $displayGroupFactory,
        $campaignFactory,
        $commandFactory,
        $displayFactory,
        $layoutFactory,
        $dayPartFactory,
        $scheduleReminderFactory,
        $scheduleExclusionFactory,
        SyncGroupFactory $syncGroupFactory,
        private readonly ScheduleCriteriaFactory $scheduleCriteriaFactory
    ) {
        $this->session = $session;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->campaignFactory = $campaignFactory;
        $this->commandFactory = $commandFactory;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->scheduleReminderFactory = $scheduleReminderFactory;
        $this->scheduleExclusionFactory = $scheduleExclusionFactory;
        $this->syncGroupFactory = $syncGroupFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        // get the default longitude and latitude from CMS options
        $defaultLat = (float)$this->getConfig()->getSetting('DEFAULT_LAT');
        $defaultLong = (float)$this->getConfig()->getSetting('DEFAULT_LONG');

        $data = [
            'defaultLat' => $defaultLat,
            'defaultLong' => $defaultLong,
            'eventTypes' => \Xibo\Entity\Schedule::getEventTypesGrid(),
        ];
        
        // Render the Theme and output
        $this->getState()->template = 'schedule-page';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Generates the calendar that we draw events on
     *
     * @SWG\Get(
     *  path="/schedule/data/events",
     *  operationId="scheduleCalendarData",
     *  tags={"schedule"},
     *  @SWG\Parameter(
     *      name="displayGroupIds",
     *      description="The DisplayGroupIds to return the schedule for. [-1] for All.",
     *      in="query",
     *      type="array",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="from",
     *      in="query",
     *      required=false,
     *      type="string",
     *      description="From Date in Y-m-d H:i:s format, if not provided defaults to start of the current month"
     *  ),
     *  @SWG\Parameter(
     *      name="to",
     *      in="query",
     *      required=false,
     *      type="string",
     *      description="To Date in Y-m-d H:i:s format, if not provided defaults to start of the next month"
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful response",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/ScheduleCalendarData")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function eventData(Request $request, Response $response)
    {
        $response = $response->withHeader('Content-Type', 'application/json');
        $this->setNoOutput();
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $displayGroupIds = $sanitizedParams->getIntArray('displayGroupIds', ['default' => []]);
        $displaySpecificDisplayGroupIds = $sanitizedParams->getIntArray('displaySpecificGroupIds', ['default' => []]);
        $originalDisplayGroupIds = array_merge($displayGroupIds, $displaySpecificDisplayGroupIds);
        $campaignId = $sanitizedParams->getInt('campaignId');

        $start = $sanitizedParams->getDate('from', ['default' => Carbon::now()->startOfMonth()]);
        $end = $sanitizedParams->getDate('to', ['default' => Carbon::now()->addMonth()->startOfMonth()]);

        if (count($originalDisplayGroupIds) <= 0) {
            return $response->withJson(['success' => 1, 'result' => []]);
        }

        // Setting for whether we show Layouts without permissions
        $showLayoutName = ($this->getConfig()->getSetting('SCHEDULE_SHOW_LAYOUT_NAME') == 1);

        // Permissions check the list of display groups with the user accessible list of display groups
        $resolvedDisplayGroupIds = array_diff($originalDisplayGroupIds, [-1]);

        if (!$this->getUser()->isSuperAdmin()) {
            $userDisplayGroupIds = array_map(function ($element) {
                /** @var \Xibo\Entity\DisplayGroup $element */
                return $element->displayGroupId;
            }, $this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]));

            // Reset the list to only those display groups that intersect and if 0 have been provided, only those from
            // the user list
            $resolvedDisplayGroupIds = (count($originalDisplayGroupIds) > 0) ? array_intersect($originalDisplayGroupIds, $userDisplayGroupIds) : $userDisplayGroupIds;

            $this->getLog()->debug('Resolved list of display groups ['
                . json_encode($resolvedDisplayGroupIds) . '] from provided list ['
                . json_encode($originalDisplayGroupIds) . '] and user list ['
                . json_encode($userDisplayGroupIds) . ']');

            // If we have none, then we do not return any events.
            if (count($resolvedDisplayGroupIds) <= 0) {
                return $response->withJson(['success' => 1, 'result' => []]);
            }
        }

        $events = [];
        $filter = [
            'futureSchedulesFrom' => $start->format('U'),
            'futureSchedulesTo' => $end->format('U'),
            'displayGroupIds' => $resolvedDisplayGroupIds,
            'geoAware' => $sanitizedParams->getInt('geoAware'),
            'recurring' => $sanitizedParams->getInt('recurring'),
            'eventTypeId' => $sanitizedParams->getInt('eventTypeId'),
            'name' => $sanitizedParams->getString('name'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'logicalOperatorName' => $sanitizedParams->getString('logicalOperatorName'),
        ];

        if ($campaignId != null) {
            // Is this an ad campaign?
            $campaign = $this->campaignFactory->getById($campaignId);
            if ($campaign->type === 'ad') {
                $filter['parentCampaignId'] = $campaignId;
            } else {
                $filter['campaignId'] = $campaignId;
            }
        }

        foreach ($this->scheduleFactory->query(['FromDT'], $filter) as $row) {
            /* @var \Xibo\Entity\Schedule $row */

            // Generate this event
            try {
                $scheduleEvents = $row->getEvents($start, $end);
            } catch (GeneralException $e) {
                $this->getLog()->error('Unable to getEvents for ' . $row->eventId);
                continue;
            }

            if (count($scheduleEvents) <= 0) {
                continue;
            }

            $this->getLog()->debug('EventId ' . $row->eventId . ' as events: ' . json_encode($scheduleEvents));

            // Load the display groups
            $row->load();

            $displayGroupList = '';

            if (count($row->displayGroups) >= 0) {
                $array = array_map(function ($object) {
                    return $object->displayGroup;
                }, $row->displayGroups);
                $displayGroupList = implode(', ', $array);
            }

            // Event Permissions
            $editable = $this->getUser()->featureEnabled('schedule.modify')
                && $this->isEventEditable($row);

            // Event Title
            if ($this->isSyncEvent($row->eventTypeId)) {
                $title = sprintf(
                    __('%s scheduled on sync group %s'),
                    $row->getSyncTypeForEvent(),
                    $row->getUnmatchedProperty('syncGroupName'),
                );
            } else if ($row->campaignId == 0) {
                // Command
                $title = __('%s scheduled on %s', $row->command, $displayGroupList);
            } else {
                // Should we show the Layout name, or not (depending on permission)
                // Make sure we only run the below code if we have to, it's quite expensive
                if (!$showLayoutName && !$this->getUser()->isSuperAdmin()) {
                    // Campaign
                    $campaign = $this->campaignFactory->getById($row->campaignId);

                    if (!$this->getUser()->checkViewable($campaign)) {
                        $row->campaign = __('Private Item');
                    }
                }

                $title = sprintf(
                    __('%s scheduled on %s'),
                    $row->getUnmatchedProperty('parentCampaignName', $row->campaign),
                    $displayGroupList
                );

                if ($row->eventTypeId === \Xibo\Entity\Schedule::$INTERRUPT_EVENT) {
                    $title .= __(' with Share of Voice %d seconds per hour', $row->shareOfVoice);
                }
            }

            // Day diff from start date to end date
            $diff = $end->diff($start)->days;

            // Show all Hourly repeats on the day view
            if ($row->recurrenceType == 'Minute' || ($diff > 1 && $row->recurrenceType == 'Hour')) {
                $title .= __(', Repeats every %s %s', $row->recurrenceDetail, $row->recurrenceType);
            }

            // Event URL
            $editUrlWeb = ($this->isSyncEvent($row->eventTypeId)) ? 'schedule.edit.sync.form' : 'schedule.edit.form';
            $editUrl = ($this->isApi($request)) ? 'schedule.edit' : $editUrlWeb;
            $url = ($editable) ? $this->urlFor($request, $editUrl, ['id' => $row->eventId]) : '#';

            $days = [];

            // Event scheduled events
            foreach ($scheduleEvents as $scheduleEvent) {
                $this->getLog()->debug(sprintf('Parsing event dates from %s and %s', $scheduleEvent->fromDt, $scheduleEvent->toDt));

                // Get the day of schedule start
                $fromDtDay = Carbon::createFromTimestamp($scheduleEvent->fromDt)->format('Y-m-d');

                // Handle command events which do not have a toDt
                if ($row->eventTypeId == \Xibo\Entity\Schedule::$COMMAND_EVENT) {
                    $scheduleEvent->toDt = $scheduleEvent->fromDt;
                }

                // Parse our dates into a Date object, so that we convert to local time correctly.
                $fromDt = Carbon::createFromTimestamp($scheduleEvent->fromDt);
                $toDt = Carbon::createFromTimestamp($scheduleEvent->toDt);

                // Set the row from/to date to be an ISO date for display
                $scheduleEvent->fromDt =
                    Carbon::createFromTimestamp($scheduleEvent->fromDt)
                        ->format(DateFormatHelper::getSystemFormat());
                $scheduleEvent->toDt =
                    Carbon::createFromTimestamp($scheduleEvent->toDt)
                        ->format(DateFormatHelper::getSystemFormat());

                $this->getLog()->debug(sprintf('Start date is ' . $fromDt->toRssString() . ' ' . $scheduleEvent->fromDt));
                $this->getLog()->debug(sprintf('End date is ' . $toDt->toRssString() . ' ' . $scheduleEvent->toDt));

                // For a minute/hourly repeating events show only 1 event per day
                if ($row->recurrenceType == 'Minute' || ($diff > 1 && $row->recurrenceType == 'Hour')) {
                    if (array_key_exists($fromDtDay, $days)) {
                        continue;
                    } else {
                        $days[$fromDtDay] = $scheduleEvent->fromDt;
                    }
                }

                /**
                 * @SWG\Definition(
                 *  definition="ScheduleCalendarData",
                 *  @SWG\Property(
                 *      property="id",
                 *      type="integer",
                 *      description="Event ID"
                 *  ),
                 *  @SWG\Property(
                 *      property="title",
                 *      type="string",
                 *      description="Event Title"
                 *  ),
                 *  @SWG\Property(
                 *      property="sameDay",
                 *      type="integer",
                 *      description="Does this event happen only on 1 day"
                 *  ),
                 *  @SWG\Property(
                 *      property="event",
                 *      ref="#/definitions/Schedule"
                 *  )
                 * )
                 */
                $events[] = [
                    'id' => $row->eventId,
                    'title' => $title,
                    'url' => ($editable) ? $url : null,
                    'start' => $fromDt->format('U') * 1000,
                    'end' => $toDt->format('U') * 1000,
                    'sameDay' => ($fromDt->day == $toDt->day && $fromDt->month == $toDt->month && $fromDt->year == $toDt->year),
                    'editable' => $editable,
                    'event' => $row,
                    'scheduleEvent' => $scheduleEvent,
                    'recurringEvent' => $row->recurrenceType != ''
                ];
            }
        }

        return $response->withJson(['success' => 1, 'result' => $events]);
    }

    /**
     * Event List
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @SWG\Get(
     *  path="/schedule/{displayGroupId}/events",
     *  operationId="scheduleCalendarDataDisplayGroup",
     *  tags={"schedule"},
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      description="The DisplayGroupId to return the event list for.",
     *      in="path",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="date",
     *      in="query",
     *      required=true,
     *      type="string",
     *      description="Date in Y-m-d H:i:s"
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful response"
     *  )
     * )
     */
    public function eventList(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkViewable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Setting for whether we show Layouts with out permissions
        $showLayoutName = ($this->getConfig()->getSetting('SCHEDULE_SHOW_LAYOUT_NAME') == 1);

        $date = $sanitizedParams->getDate('date');

        // Reset the seconds
        $date->second(0);

        $this->getLog()->debug(
            sprintf(
                'Generating eventList for DisplayGroupId ' . $id . ' on date '
                . $date->format(DateFormatHelper::getSystemFormat())
            )
        );

        // Get a list of scheduled events
        $events = [];
        $displayGroups = [];
        $layouts = [];
        $campaigns = [];

        // Add the displayGroupId I am filtering for to the displayGroup object
        $displayGroups[$displayGroup->displayGroupId] = $displayGroup;

        // Is this group a display specific group, or a standalone?
        $options = [];
        /** @var \Xibo\Entity\Display $display */
        $display = null;
        if ($displayGroup->isDisplaySpecific == 1) {
            // We should lookup the displayId for this group.
            $display = $this->displayFactory->getByDisplayGroupId($id)[0];
        } else {
            $options['useGroupId'] = true;
            $options['displayGroupId'] = $id;
        }

        // Get list of events
        $scheduleForXmds = $this->scheduleFactory->getForXmds(($display === null) ? null : $display->displayId, $date, $date, $options);

        $this->getLog()->debug(count($scheduleForXmds) . ' events returned for displaygroup and date');

        foreach ($scheduleForXmds as $event) {

            // Ignore command events
            if ($event['eventTypeId'] == \Xibo\Entity\Schedule::$COMMAND_EVENT)
                continue;

            // Ignore events that have a campaignId, but no layoutId (empty Campaigns)
            if ($event['layoutId'] == 0 && $event['campaignId'] != 0)
                continue;

            // Assess schedules
            $schedule = $this->scheduleFactory->createEmpty()->hydrate($event, ['intProperties' => ['isPriority', 'syncTimezone', 'displayOrder', 'fromDt', 'toDt']]);
            $schedule->load();

            $this->getLog()->debug('EventId ' . $schedule->eventId . ' exists in the schedule window, checking its instances for activity');

            // Get scheduled events based on recurrence
            try {
                $scheduleEvents = $schedule->getEvents($date, $date);
            } catch (GeneralException $e) {
                $this->getLog()->error('Unable to getEvents for ' . $schedule->eventId);
                continue;
            }

            // If this event is active, collect extra information and add to the events list
            if (count($scheduleEvents) > 0) {
                // Add the link to the schedule
                if (!$this->isApi($request)) {
                    $route = $event['eventTypeId'] == \Xibo\Entity\Schedule::$SYNC_EVENT
                        ? 'schedule.edit.sync.form'
                        : 'schedule.edit.form';
                    $schedule->setUnmatchedProperty(
                        'link',
                        $this->urlFor($request, $route, ['id' => $schedule->eventId])
                    );
                }

                // Add the Layout
                if ($event['eventTypeId'] == \Xibo\Entity\Schedule::$SYNC_EVENT) {
                    $layoutId = $event['syncLayoutId'];
                } else {
                    $layoutId = $event['layoutId'];
                }

                $this->getLog()->debug('Adding this events layoutId [' . $layoutId . '] to list');

                if ($layoutId != 0 && !array_key_exists($layoutId, $layouts)) {
                    // Look up the layout details
                    $layout = $this->layoutFactory->getById($layoutId);

                    // Add the link to the layout
                    if (!$this->isApi($request)) {
                        // do not link to Layout Designer for Full screen Media/Playlist Layout.
                        $link = (in_array($event['eventTypeId'], [7, 8]))
                            ? ''
                            : $this->urlFor($request, 'layout.designer', ['id' => $layout->layoutId]);

                        $layout->setUnmatchedProperty(
                            'link',
                            $link
                        );
                    }
                    if ($showLayoutName || $this->getUser()->checkViewable($layout)) {
                        $layouts[$layoutId] = $layout;
                    } else {
                        $layouts[$layoutId] = [
                            'layout' => __('Private Item')
                        ];
                    }

                    // Add the Campaign
                    $layout->campaigns = $this->campaignFactory->getByLayoutId($layout->layoutId);

                    if (count($layout->campaigns) > 0) {
                        // Add to the campaigns array
                        foreach ($layout->campaigns as $campaign) {
                            if (!array_key_exists($campaign->campaignId, $campaigns)) {
                                $campaigns[$campaign->campaignId] = $campaign;
                            }
                        }
                    }
                }

                $event['campaign'] = is_object($layouts[$layoutId]) ? $layouts[$layoutId]->layout : $layouts[$layoutId];

                // Display Group details
                $this->getLog()->debug('Adding this events displayGroupIds to list');
                $schedule->excludeProperty('displayGroups');

                foreach ($schedule->displayGroups as $scheduleDisplayGroup) {
                    if (!array_key_exists($scheduleDisplayGroup->displayGroupId, $displayGroups)) {
                        $displayGroups[$scheduleDisplayGroup->displayGroupId] = $scheduleDisplayGroup;
                    }
                }

                // Determine the intermediate display groups
                $this->getLog()->debug('Adding this events intermediateDisplayGroupIds to list');
                $schedule->setUnmatchedProperty(
                    'intermediateDisplayGroupIds',
                    $this->calculateIntermediates($display, $displayGroup, $event['displayGroupId'])
                );

                foreach ($schedule->getUnmatchedProperty('intermediateDisplayGroupIds') as $intermediate) {
                    if (!array_key_exists($intermediate, $displayGroups)) {
                        $displayGroups[$intermediate] = $this->displayGroupFactory->getById($intermediate);
                    }
                }

                $this->getLog()->debug(sprintf('Adding scheduled events: ' . json_encode($scheduleEvents)));

                // We will never save this and we need the eventId on the agenda view
                $eventId = $schedule->eventId;

                foreach ($scheduleEvents as $scheduleEvent) {
                    $schedule = clone $schedule;
                    $schedule->eventId = $eventId;
                    $schedule->fromDt = $scheduleEvent->fromDt;
                    $schedule->toDt = $scheduleEvent->toDt;
                    $schedule->setUnmatchedProperty('layoutId', intval($layoutId));
                    $schedule->setUnmatchedProperty('displayGroupId', intval($event['displayGroupId']));

                    $events[] = $schedule;
                }
            } else {
                $this->getLog()->debug('No activity inside window');
            }
        }

        $this->getState()->hydrate([
             'data' => [
                 'events' => $events,
                 'displayGroups' => $displayGroups,
                 'layouts' => $layouts,
                 'campaigns' => $campaigns
             ]
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param \Xibo\Entity\Display $display
     * @param \Xibo\Entity\DisplayGroup $displayGroup
     * @param int $eventDisplayGroupId
     * @return array
     * @throws NotFoundException
     */
    private function calculateIntermediates($display, $displayGroup, $eventDisplayGroupId)
    {
        $this->getLog()->debug('Calculating intermediates for events displayGroupId ' . $eventDisplayGroupId . ' viewing displayGroupId ' . $displayGroup->displayGroupId);

        $intermediates = [];
        $eventDisplayGroup = $this->displayGroupFactory->getById($eventDisplayGroupId);

        // Is the event scheduled directly on the displayGroup in question?
        if ($displayGroup->displayGroupId == $eventDisplayGroupId)
            return $intermediates;

        // Is the event scheduled directly on the display in question?
        if ($eventDisplayGroup->isDisplaySpecific == 1)
            return $intermediates;

        $this->getLog()->debug('Event isnt directly scheduled to a display or to the current displaygroup ');

        // There are nested groups involved, so we need to trace the relationship tree.
        if ($display === null) {
            $this->getLog()->debug('We are looking at a DisplayGroup');
            // We are on a group.

            // Get the relationship tree for this display group
            $tree = $this->displayGroupFactory->getRelationShipTree($displayGroup->displayGroupId);

            foreach ($tree as $branch) {
                $this->getLog()->debug(
                    'Branch found: ' . $branch->displayGroup .
                    ' [' . $branch->displayGroupId . '], ' .
                    $branch->getUnmatchedProperty('depth') . '-' .
                    $branch->getUnmatchedProperty('level')
                );
                
                if ($branch->getUnmatchedProperty('depth') < 0 &&
                    $branch->displayGroupId != $eventDisplayGroup->displayGroupId
                ) {
                    $intermediates[] = $branch->displayGroupId;
                }
            }
        } else {
            // We are on a display.
            $this->getLog()->debug('We are looking at a Display');

            // We will need to get all of this displays groups and then add only those ones that give us an eventual
            // match on the events display group (complicated or what!)
            $display->load();

            foreach ($display->displayGroups as $displayDisplayGroup) {

                // Ignore the display specific group
                if ($displayDisplayGroup->isDisplaySpecific == 1)
                    continue;

                // Get the relationship tree for this display group
                $tree = $this->displayGroupFactory->getRelationShipTree($displayDisplayGroup->displayGroupId);

                $found = false;
                $possibleIntermediates = [];

                foreach ($tree as $branch) {
                    $this->getLog()->debug(
                        'Branch found: ' . $branch->displayGroup .
                        ' [' . $branch->displayGroupId . '], ' .
                        $branch->getUnmatchedProperty('depth') . '-' .
                        $branch->getUnmatchedProperty('level')
                    );

                    if ($branch->displayGroupId != $eventDisplayGroup->displayGroupId) {
                        $possibleIntermediates[] = $branch->displayGroupId;
                    }

                    if ($branch->displayGroupId != $eventDisplayGroup->displayGroupId && count($possibleIntermediates) > 0)
                        $found = true;
                }

                if ($found) {
                    $this->getLog()->debug('We have found intermediates ' . json_encode($possibleIntermediates) . ' for display when looking at displayGroupId ' . $displayDisplayGroup->displayGroupId);
                    $intermediates = array_merge($intermediates, $possibleIntermediates);
                }
            }
        }

        $this->getLog()->debug('Returning intermediates: ' . json_encode($intermediates));

        return $intermediates;
    }

    /**
     * Shows a form to add an event
     * @param Request $request
     * @param Response $response
     * @param string|null $from
     * @param int|null $id
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function addForm(Request $request, Response $response, ?string $from, ?int $id): Response|ResponseInterface
    {
        // get the default longitude and latitude from CMS options
        $defaultLat = (float)$this->getConfig()->getSetting('DEFAULT_LAT');
        $defaultLong = (float)$this->getConfig()->getSetting('DEFAULT_LONG');

        // Dispatch an event to initialize schedule criteria
        $event = new ScheduleCriteriaRequestEvent();
        $this->getDispatcher()->dispatch($event, ScheduleCriteriaRequestEvent::$NAME);

        // Retrieve the criteria data from the event
        $criteria = $event->getCriteria();
        $criteriaDefaultCondition = $event->getCriteriaDefaultCondition();

        $addFormData = [
            'dayParts' => $this->dayPartFactory->allWithSystem(),
            'reminders' => [],
            'defaultLat' => $defaultLat,
            'defaultLong' => $defaultLong,
            'eventTypes' => \Xibo\Entity\Schedule::getEventTypesForm(),
            'isScheduleNow' => false,
            'relativeTime' => 0,
            'setDisplaysFromFilter' => true,
            'scheduleCriteria' => $criteria,
            'criteriaDefaultCondition' => $criteriaDefaultCondition
        ];
        $formNowData = [];

        if (!empty($from) && !empty($id)) {
            $formNowData = [
                'eventTypeId' => $this->getEventTypeId($from),
                'campaign' => (
                    ($from == 'Campaign' || $from == 'Layout')
                    ? $this->campaignFactory->getById($id)
                    : null
                ),
                'displayGroups' => (($from == 'DisplayGroup') ? [$this->displayGroupFactory->getById($id)] : null),
                'displayGroupIds' => (($from == 'DisplayGroup') ? [$id] : [0]),
                'mediaId' => (($from === 'Library') ? $id : null),
                'playlistId' => (($from === 'Playlist') ? $id : null),
                'readonlySelect' => !($from == 'DisplayGroup'),
                'isScheduleNow' => true,
                'relativeTime' => 1,
                'setDisplaysFromFilter' => false,
            ];
        }

        $formData = array_merge($addFormData, $formNowData);

        $this->getState()->template = 'schedule-form-add';
        $this->getState()->setData($formData);

        return $this->render($request, $response);
    }

    /**
     * Model to use for supplying key/value pairs to arrays
     * @SWG\Definition(
     *  definition="ScheduleReminderArray",
     *  @SWG\Property(
     *      property="reminder_value",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="reminder_type",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="reminder_option",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="reminder_isEmailHidden",
     *      type="integer"
     *  )
     * )
     */

    /**
     * Add Event
     * @SWG\Post(
     *  path="/schedule",
     *  operationId="scheduleAdd",
     *  tags={"schedule"},
     *  summary="Add Schedule Event",
     *  description="Add a new scheduled event for a Campaign/Layout to be shown on a Display Group/Display.",
     *  @SWG\Parameter(
     *      name="eventTypeId",
     *      in="formData",
     *      description="The Event Type Id to use for this Event.
     * 1=Layout, 2=Command, 3=Overlay, 4=Interrupt, 5=Campaign, 6=Action, 7=Media Library, 8=Playlist",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The Campaign ID to use for this Event.
     * If a Layout is needed then the Campaign specific ID for that Layout should be used.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="fullScreenCampaignId",
     *      in="formData",
     *      description="For Media or Playlist eventyType. The Layout specific Campaign ID to use for this Event.
     * This needs to be the Layout created with layout/fullscreen function",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="formData",
     *      description="The Command ID to use for this Event.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="displayOrder",
     *      in="formData",
     *      description="The display order for this event. ",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="isPriority",
     *      in="formData",
     *      description="An integer indicating the priority of this event. Normal events have a priority of 0.",
     *      type="integer",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="displayGroupIds",
     *      in="formData",
     *      description="The Display Group IDs for this event. Display specific Group IDs should be used to schedule on single displays.",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Parameter(
     *      name="dayPartId",
     *      in="formData",
     *      description="The Day Part for this event. Overrides supported are 0(custom) and 1(always). Defaulted to 0.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="syncTimezone",
     *      in="formData",
     *      description="Should this schedule be synced to the resulting Display timezone?",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The from date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The to date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceType",
     *      in="formData",
     *      description="The type of recurrence to apply to this event.",
     *      type="string",
     *      required=false,
     *      enum={"", "Minute", "Hour", "Day", "Week", "Month", "Year"}
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceDetail",
     *      in="formData",
     *      description="The interval for the recurrence.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRange",
     *      in="formData",
     *      description="The end date for this events recurrence.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRepeatsOn",
     *      in="formData",
     *      description="The days of the week that this event repeats - weekly only",
     *      type="string",
     *      format="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Parameter(
     *      name="scheduleReminders",
     *      in="formData",
     *      description="Array of Reminders for this event",
     *      type="array",
     *      required=false,
     *      @SWG\Items(
     *          ref="#/definitions/ScheduleReminderArray"
     *      )
     *   ),
     *   @SWG\Parameter(
     *      name="isGeoAware",
     *      in="formData",
     *      description="Flag (0-1), whether this event is using Geo Location",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="geoLocation",
     *      in="formData",
     *      description="Array of comma separated strings each with comma separated pair of coordinates",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *   @SWG\Parameter(
     *      name="geoLocationJson",
     *      in="formData",
     *      description="Valid GeoJSON string, use as an alternative to geoLocation parameter",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="actionType",
     *      in="formData",
     *      description="For Action eventTypeId, the type of the action - command or navLayout",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="actionTriggerCode",
     *      in="formData",
     *      description="For Action eventTypeId, the webhook trigger code for the Action",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="actionLayoutCode",
     *      in="formData",
     *      description="For Action eventTypeId and navLayout actionType, the Layout Code identifier",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="dataSetId",
     *      in="formData",
     *      description="For Data Connector eventTypeId, the DataSet ID",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="dataSetParams",
     *      in="formData",
     *      description="For Data Connector eventTypeId, the DataSet params",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Schedule"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function add(Request $request, Response $response)
    {
        $this->getLog()->debug('Add Schedule');
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $embed = ($sanitizedParams->getString('embed') != null)
            ? explode(',', $sanitizedParams->getString('embed'))
            : [];

        // Get the custom day part to use as a default day part
        $customDayPart = $this->dayPartFactory->getCustomDayPart();

        $schedule = $this->scheduleFactory->createEmpty();
        $schedule->userId = $this->getUser()->userId;
        $schedule->eventTypeId = $sanitizedParams->getInt('eventTypeId');
        $schedule->campaignId = $this->isFullScreenSchedule($schedule->eventTypeId)
                ? $sanitizedParams->getInt('fullScreenCampaignId')
                : $sanitizedParams->getInt('campaignId');
        $schedule->commandId = $sanitizedParams->getInt('commandId');
        $schedule->displayOrder = $sanitizedParams->getInt('displayOrder', ['default' => 0]);
        $schedule->isPriority = $sanitizedParams->getInt('isPriority', ['default' => 0]);
        $schedule->dayPartId = $sanitizedParams->getInt('dayPartId', ['default' => $customDayPart->dayPartId]);
        $schedule->isGeoAware = $sanitizedParams->getCheckbox('isGeoAware');
        $schedule->actionType = $sanitizedParams->getString('actionType');
        $schedule->actionTriggerCode = $sanitizedParams->getString('actionTriggerCode');
        $schedule->actionLayoutCode = $sanitizedParams->getString('actionLayoutCode');
        $schedule->maxPlaysPerHour = $sanitizedParams->getInt('maxPlaysPerHour', ['default' => 0]);
        $schedule->syncGroupId = $sanitizedParams->getInt('syncGroupId');
        $schedule->name = $sanitizedParams->getString('name');

        // Set the parentCampaignId for campaign events
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$CAMPAIGN_EVENT) {
            $schedule->parentCampaignId = $schedule->campaignId;

            // Make sure we're not directly scheduling an ad campaign
            $campaign = $this->campaignFactory->getById($schedule->campaignId);
            if ($campaign->type === 'ad') {
                throw new InvalidArgumentException(
                    __('Direct scheduling of an Ad Campaign is not allowed'),
                    'campaignId'
                );
            }
        }

        // Fields only collected for interrupt events
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$INTERRUPT_EVENT) {
            $schedule->shareOfVoice = $sanitizedParams->getInt('shareOfVoice', [
                'throw' => function () {
                    new InvalidArgumentException(
                        __('Share of Voice must be a whole number between 0 and 3600'),
                        'shareOfVoice'
                    );
                }
            ]);
        } else {
            $schedule->shareOfVoice = null;
        }

        // Fields only collected for data connector events
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$DATA_CONNECTOR_EVENT) {
            $schedule->dataSetId = $sanitizedParams->getInt('dataSetId', [
                'throw' => function () {
                    new InvalidArgumentException(
                        __('Please select a DataSet'),
                        'dataSetId'
                    );
                }
            ]);
            $schedule->dataSetParams = $sanitizedParams->getString('dataSetParams');
        }

        // API request can provide an array of coordinates or valid GeoJSON, handle both cases here.
        if ($this->isApi($request) && $schedule->isGeoAware === 1) {
            if ($sanitizedParams->getArray('geoLocation') != null) {
                // get string array from API
                $coordinates = $sanitizedParams->getArray('geoLocation');
                // generate GeoJSON and assign to Schedule
                $schedule->geoLocation = $this->createGeoJson($coordinates);
            } else {
                // we were provided with GeoJSON
                $schedule->geoLocation = $sanitizedParams->getString('geoLocationJson');
            }
        } else {
            // if we are not using API, then valid GeoJSON is created in the front end.
            $schedule->geoLocation = $sanitizedParams->getString('geoLocation');
        }

        // Workaround for cases where we're supplied 0 as the dayPartId (legacy custom dayPart)
        if ($schedule->dayPartId === 0) {
            $schedule->dayPartId = $customDayPart->dayPartId;
        }

        $schedule->syncTimezone = $sanitizedParams->getCheckbox('syncTimezone');
        $schedule->syncEvent = $this->isSyncEvent($schedule->eventTypeId);
        $schedule->recurrenceType = $sanitizedParams->getString('recurrenceType');
        $schedule->recurrenceDetail = $sanitizedParams->getInt('recurrenceDetail');
        $recurrenceRepeatsOn = $sanitizedParams->getIntArray('recurrenceRepeatsOn');
        $schedule->recurrenceRepeatsOn = (empty($recurrenceRepeatsOn)) ? null : implode(',', $recurrenceRepeatsOn);
        $schedule->recurrenceMonthlyRepeatsOn = $sanitizedParams->getInt(
            'recurrenceMonthlyRepeatsOn',
            ['default' => 0]
        );

        foreach ($sanitizedParams->getIntArray('displayGroupIds', ['default' => []]) as $displayGroupId) {
            $schedule->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));
        }

        if (!$schedule->isAlwaysDayPart()) {
            // Handle the dates
            $fromDt = $sanitizedParams->getDate('fromDt');
            $toDt = $sanitizedParams->getDate('toDt');
            $recurrenceRange = $sanitizedParams->getDate('recurrenceRange');

            if ($fromDt === null) {
                throw new InvalidArgumentException(__('Please enter a from date'), 'fromDt');
            }

            $logToDt = $toDt?->format(DateFormatHelper::getSystemFormat());
            $logRecurrenceRange = $recurrenceRange?->format(DateFormatHelper::getSystemFormat());
            $this->getLog()->debug(
                'Times received are: FromDt=' . $fromDt->format(DateFormatHelper::getSystemFormat())
                . '. ToDt=' . $logToDt . '. recurrenceRange=' . $logRecurrenceRange
            );

            if (!$schedule->isCustomDayPart() && !$schedule->isAlwaysDayPart()) {
                // Daypart selected
                // expect only a start date (no time)
                $schedule->fromDt = $fromDt->startOfDay()->format('U');
                $schedule->toDt = null;

                if ($recurrenceRange != null) {
                    $schedule->recurrenceRange = $recurrenceRange->format('U');
                }

            } else if (!($this->isApi($request) || Str::contains($this->getConfig()->getSetting('DATE_FORMAT'), 's'))) {
                // In some circumstances we want to trim the seconds from the provided dates.
                // this happens when the date format provided does not include seconds and when the add
                // event comes from the UI.
                $this->getLog()->debug('Date format does not include seconds, removing them');
                $schedule->fromDt = $fromDt->setTime($fromDt->hour, $fromDt->minute, 0)->format('U');

                if ($toDt !== null) {
                    $schedule->toDt = $toDt->setTime($toDt->hour, $toDt->minute, 0)->format('U');
                }

                if ($recurrenceRange != null) {
                    $schedule->recurrenceRange =
                        $recurrenceRange->setTime(
                            $recurrenceRange->hour,
                            $recurrenceRange->minute,
                            0
                        )->format('U');
                }
            } else {
                $schedule->fromDt = $fromDt->format('U');

                if ($toDt !== null) {
                    $schedule->toDt = $toDt->format('U');
                }

                if ($recurrenceRange != null) {
                    $schedule->recurrenceRange = $recurrenceRange->format('U');
                }
            }

            $logToDt = $toDt?->format(DateFormatHelper::getSystemFormat());
            $logRecurrenceRange = $recurrenceRange?->format(DateFormatHelper::getSystemFormat());
            $this->getLog()->debug(
                'Processed times are: FromDt=' . $fromDt->format(DateFormatHelper::getSystemFormat())
                . '. ToDt=' . $logToDt . '. recurrenceRange=' . $logRecurrenceRange
            );
        }

        // Schedule Criteria
        $criteria = $sanitizedParams->getArray('criteria');
        if (is_array($criteria) && count($criteria) > 0) {
            foreach ($criteria as $item) {
                $itemParams = $this->getSanitizer($item);
                $criterion = $this->scheduleCriteriaFactory->createEmpty();
                $criterion->metric = $itemParams->getString('metric');
                $criterion->type = $itemParams->getString('type');
                $criterion->condition = $itemParams->getString('condition');
                $criterion->value = $itemParams->getString('value');
                $schedule->addOrUpdateCriteria($criterion);
            }
        }

        // Ready to do the add
        $schedule->setDisplayNotifyService($this->displayFactory->getDisplayNotifyService());
        if ($schedule->campaignId != null) {
            $schedule->setCampaignFactory($this->campaignFactory);
        }
        $schedule->save();

        $this->getLog()->debug('Add Schedule Reminder');

        // API Request
        $rows = [];
        if ($this->isApi($request)) {
            $reminders =  $sanitizedParams->getArray('scheduleReminders', ['default' => []]);
            foreach ($reminders as $i => $reminder) {
                $rows[$i]['reminder_value'] = (int) $reminder['reminder_value'];
                $rows[$i]['reminder_type'] = (int) $reminder['reminder_type'];
                $rows[$i]['reminder_option'] = (int) $reminder['reminder_option'];
                $rows[$i]['reminder_isEmailHidden'] = (int) $reminder['reminder_isEmailHidden'];
            }
        } else {
            for ($i=0; $i < count($sanitizedParams->getIntArray('reminder_value', ['default' => []])); $i++) {
                $rows[$i]['reminder_value'] = $sanitizedParams->getIntArray('reminder_value')[$i];
                $rows[$i]['reminder_type'] = $sanitizedParams->getIntArray('reminder_type')[$i];
                $rows[$i]['reminder_option'] = $sanitizedParams->getIntArray('reminder_option')[$i];
                $rows[$i]['reminder_isEmailHidden'] = $sanitizedParams->getIntArray('reminder_isEmailHidden')[$i];
            }
        }

        // Save new reminders
        foreach ($rows as $reminder) {
            // Do not add reminder if empty value provided for number of minute/hour
            if ($reminder['reminder_value'] == 0) {
                continue;
            }

            $scheduleReminder = $this->scheduleReminderFactory->createEmpty();
            $scheduleReminder->scheduleReminderId = null;
            $scheduleReminder->eventId = $schedule->eventId;
            $scheduleReminder->value = $reminder['reminder_value'];
            $scheduleReminder->type = $reminder['reminder_type'];
            $scheduleReminder->option = $reminder['reminder_option'];
            $scheduleReminder->isEmail = $reminder['reminder_isEmailHidden'];

            $this->saveReminder($schedule, $scheduleReminder);
        }

        // We can get schedule reminders in an array
        if ($this->isApi($request)) {
            $schedule = $this->scheduleFactory->getById($schedule->eventId);
            $schedule->load([
                'loadScheduleReminders' => in_array('scheduleReminders', $embed),
            ]);
        }

        if ($this->isSyncEvent($schedule->eventTypeId)) {
            $syncGroup = $this->syncGroupFactory->getById($schedule->syncGroupId);
            $syncGroup->validateForSchedule($sanitizedParams);
            $schedule->updateSyncLinks($syncGroup, $sanitizedParams);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added Event'),
            'id' => $schedule->eventId,
            'data' => $schedule
        ]);

        return $this->render($request, $response);
    }

    /**
     * Shows a form to edit an event
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    function editForm(Request $request, Response $response, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Recurring event start/end
        $eventStart = $sanitizedParams->getInt('eventStart', ['default' => 1000]) / 1000;
        $eventEnd = $sanitizedParams->getInt('eventEnd', ['default' => 1000]) / 1000;

        $schedule = $this->scheduleFactory->getById($id);
        $schedule->load();

        // Dispatch an event to retrieve criteria for scheduling from the OpenWeatherMap connector.
        $event = new ScheduleCriteriaRequestEvent();
        $this->getDispatcher()->dispatch($event, ScheduleCriteriaRequestEvent::$NAME);

        // Retrieve the data from the event
        $criteria = $event->getCriteria();
        $criteriaDefaultCondition = $event->getCriteriaDefaultCondition();

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        // Fix the event dates for display
        if ($schedule->isAlwaysDayPart()) {
            $schedule->fromDt = '';
            $schedule->toDt = '';
        } else {
            $schedule->fromDt =
                Carbon::createFromTimestamp($schedule->fromDt)
                    ->format(DateFormatHelper::getSystemFormat());
            if ($schedule->toDt != null) {
                $schedule->toDt =
                    Carbon::createFromTimestamp($schedule->toDt)
                        ->format(DateFormatHelper::getSystemFormat());
            }
        }

        if ($schedule->recurrenceRange != null) {
            $schedule->recurrenceRange =
                Carbon::createFromTimestamp($schedule->recurrenceRange)
                    ->format(DateFormatHelper::getSystemFormat());
        }
        // Get all reminders
        $scheduleReminders = $this->scheduleReminderFactory->query(null, ['eventId' => $id]);

        // get the default longitude and latitude from CMS options
        $defaultLat = (float)$this->getConfig()->getSetting('DEFAULT_LAT');
        $defaultLong = (float)$this->getConfig()->getSetting('DEFAULT_LONG');

        if ($this->isFullScreenSchedule($schedule->eventTypeId)) {
            $schedule->setUnmatchedProperty('fullScreenCampaignId', $schedule->campaignId);
            
            if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$MEDIA_EVENT) {
                $schedule->setUnmatchedProperty(
                    'mediaId',
                    $this->layoutFactory->getLinkedFullScreenMediaId($schedule->campaignId)
                );
            } else if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$PLAYLIST_EVENT) {
                $schedule->setUnmatchedProperty(
                    'playlistId',
                    $this->layoutFactory->getLinkedFullScreenPlaylistId($schedule->campaignId)
                );
            }
        }

        $this->getState()->template = 'schedule-form-edit';
        $this->getState()->setData([
            'event' => $schedule,
            'dayParts' => $this->dayPartFactory->allWithSystem(),
            'displayGroups' => $schedule->displayGroups,
            'campaign' => !empty($schedule->campaignId) ? $this->campaignFactory->getById($schedule->campaignId) : null,
            'displayGroupIds' => array_map(function ($element) {
                return $element->displayGroupId;
            }, $schedule->displayGroups),
            'reminders' => $scheduleReminders,
            'defaultLat' => $defaultLat,
            'defaultLong' => $defaultLong,
            'recurringEvent' => $schedule->recurrenceType != '',
            'eventStart' => $eventStart,
            'eventEnd' => $eventEnd,
            'eventTypes' => \Xibo\Entity\Schedule::getEventTypesForm(),
            'scheduleCriteria' => $criteria,
            'criteriaDefaultCondition' => $criteriaDefaultCondition
        ]);

        return $this->render($request, $response);
    }

    /**
     * Shows the Delete a Recurring Event form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function deleteRecurrenceForm(Request $request, Response $response, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Recurring event start/end
        $eventStart = $sanitizedParams->getInt('eventStart', ['default' => 1000]);
        $eventEnd = $sanitizedParams->getInt('eventEnd', ['default' => 1000]);

        $schedule = $this->scheduleFactory->getById($id);
        $schedule->load();

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'schedule-recurrence-form-delete';
        $this->getState()->setData([
            'event' => $schedule,
            'eventStart' => $eventStart,
            'eventEnd' => $eventEnd,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Deletes a recurring Event from all displays
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @SWG\Delete(
     *  path="/schedulerecurrence/{eventId}",
     *  operationId="schedulerecurrenceDelete",
     *  tags={"schedule"},
     *  summary="Delete a Recurring Event",
     *  description="Delete a Recurring Event of a Scheduled Event",
     *  @SWG\Parameter(
     *      name="eventId",
     *      in="path",
     *      description="The Scheduled Event ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function deleteRecurrence(Request $request, Response $response, $id)
    {
        $schedule = $this->scheduleFactory->getById($id);
        $schedule->load();

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Recurring event start/end
        $eventStart = $sanitizedParams->getInt('eventStart', ['default' => 1000]);
        $eventEnd = $sanitizedParams->getInt('eventEnd', ['default' => 1000]);
        $scheduleExclusion = $this->scheduleExclusionFactory->create($schedule->eventId, $eventStart, $eventEnd);

        $this->getLog()->debug('Create a schedule exclusion record');
        $scheduleExclusion->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Deleted Event')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edits an event
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @SWG\Put(
     *  path="/schedule/{eventId}",
     *  operationId="scheduleEdit",
     *  tags={"schedule"},
     *  summary="Edit Schedule Event",
     *  description="Edit a scheduled event for a Campaign/Layout to be shown on a Display Group/Display.",
     *  @SWG\Parameter(
     *      name="eventId",
     *      in="path",
     *      description="The Scheduled Event ID",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="eventTypeId",
     *      in="formData",
     *      description="The Event Type Id to use for this Event.
     * 1=Layout, 2=Command, 3=Overlay, 4=Interrupt, 5=Campaign, 6=Action",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The Campaign ID to use for this Event.
     * If a Layout is needed then the Campaign specific ID for that Layout should be used.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="formData",
     *      description="The Command ID to use for this Event.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="displayOrder",
     *      in="formData",
     *      description="The display order for this event. ",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="isPriority",
     *      in="formData",
     *      description="An integer indicating the priority of this event. Normal events have a priority of 0.",
     *      type="integer",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="displayGroupIds",
     *      in="formData",
     *      description="The Display Group IDs for this event.
     * Display specific Group IDs should be used to schedule on single displays.",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Parameter(
     *      name="dayPartId",
     *      in="formData",
     *      description="The Day Part for this event. Overrides supported are 0(custom) and 1(always). Defaulted to 0.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="syncTimezone",
     *      in="formData",
     *      description="Should this schedule be synced to the resulting Display timezone?",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The from date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The to date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceType",
     *      in="formData",
     *      description="The type of recurrence to apply to this event.",
     *      type="string",
     *      required=false,
     *      enum={"", "Minute", "Hour", "Day", "Week", "Month", "Year"}
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceDetail",
     *      in="formData",
     *      description="The interval for the recurrence.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRange",
     *      in="formData",
     *      description="The end date for this events recurrence.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRepeatsOn",
     *      in="formData",
     *      description="The days of the week that this event repeats - weekly only",
     *      type="string",
     *      format="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Parameter(
     *      name="scheduleReminders",
     *      in="formData",
     *      description="Array of Reminders for this event",
     *      type="array",
     *      required=false,
     *      @SWG\Items(
     *          ref="#/definitions/ScheduleReminderArray"
     *      )
     *   ),
     *   @SWG\Parameter(
     *      name="isGeoAware",
     *      in="formData",
     *      description="Flag (0-1), whether this event is using Geo Location",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="geoLocation",
     *      in="formData",
     *      description="Array of comma separated strings each with comma separated pair of coordinates",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="string")
     *   ),
     *   @SWG\Parameter(
     *      name="geoLocationJson",
     *      in="formData",
     *      description="Valid GeoJSON string, use as an alternative to geoLocation parameter",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="actionType",
     *      in="formData",
     *      description="For Action eventTypeId, the type of the action - command or navLayout",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="actionTriggerCode",
     *      in="formData",
     *      description="For Action eventTypeId, the webhook trigger code for the Action",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="actionLayoutCode",
     *      in="formData",
     *      description="For Action eventTypeId and navLayout actionType, the Layout Code identifier",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="dataSetId",
     *      in="formData",
     *      description="For Data Connector eventTypeId, the DataSet ID",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="dataSetParams",
     *      in="formData",
     *      description="For Data Connector eventTypeId, the DataSet params",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Schedule")
     *  )
     * )
     */
    public function edit(Request $request, Response $response, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $embed = ($sanitizedParams->getString('embed') != null) ? explode(',', $sanitizedParams->getString('embed')) : [];

        $schedule = $this->scheduleFactory->getById($id);
        $schedule->load([
            'loadScheduleReminders' => in_array('scheduleReminders', $embed),
        ]);

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        $schedule->eventTypeId = $sanitizedParams->getInt('eventTypeId');
        $schedule->campaignId = $this->isFullScreenSchedule($schedule->eventTypeId)
            ? $sanitizedParams->getInt('fullScreenCampaignId')
            : $sanitizedParams->getInt('campaignId');
        $schedule->displayOrder = $sanitizedParams->getInt('displayOrder', ['default' => $schedule->displayOrder]);
        $schedule->isPriority = $sanitizedParams->getInt('isPriority', ['default' => $schedule->isPriority]);
        $schedule->dayPartId = $sanitizedParams->getInt('dayPartId', ['default' => $schedule->dayPartId]);
        $schedule->syncTimezone = $sanitizedParams->getCheckbox('syncTimezone');
        $schedule->syncEvent = $this->isSyncEvent($schedule->eventTypeId);
        $schedule->recurrenceType = $sanitizedParams->getString('recurrenceType');
        $schedule->recurrenceDetail = $sanitizedParams->getInt('recurrenceDetail');
        $recurrenceRepeatsOn = $sanitizedParams->getIntArray('recurrenceRepeatsOn');
        $schedule->recurrenceRepeatsOn = (empty($recurrenceRepeatsOn)) ? null : implode(',', $recurrenceRepeatsOn);
        $schedule->recurrenceMonthlyRepeatsOn = $sanitizedParams->getInt(
            'recurrenceMonthlyRepeatsOn',
            ['default' => 0]
        );
        $schedule->displayGroups = [];
        $schedule->isGeoAware = $sanitizedParams->getCheckbox('isGeoAware');
        $schedule->maxPlaysPerHour = $sanitizedParams->getInt('maxPlaysPerHour', ['default' => 0]);
        $schedule->name = $sanitizedParams->getString('name');
        $schedule->modifiedBy = $this->getUser()->getId();

        // collect action event relevant properties only on action event
        // null these properties otherwise
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$ACTION_EVENT) {
            $schedule->actionType = $sanitizedParams->getString('actionType');
            $schedule->actionTriggerCode = $sanitizedParams->getString('actionTriggerCode');
            $schedule->commandId = $sanitizedParams->getInt('commandId');
            $schedule->actionLayoutCode = $sanitizedParams->getString('actionLayoutCode');
            $schedule->campaignId = null;
        } else {
            $schedule->actionType = null;
            $schedule->actionTriggerCode = null;
            $schedule->commandId = null;
            $schedule->actionLayoutCode = null;
        }

        // collect commandId on Command event
        // Retain existing commandId value otherwise
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$COMMAND_EVENT) {
            $schedule->commandId = $sanitizedParams->getInt('commandId');
            $schedule->campaignId = null;
        }

        // Set the parentCampaignId for campaign events
        // null parentCampaignId on other events
        // make sure correct Layout/Campaign is selected for relevant event.
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$CAMPAIGN_EVENT) {
            $schedule->parentCampaignId = $schedule->campaignId;

            // Make sure we're not directly scheduling an ad campaign
            $campaign = $this->campaignFactory->getById($schedule->campaignId);
            if ($campaign->type === 'ad') {
                throw new InvalidArgumentException(
                    __('Direct scheduling of an Ad Campaign is not allowed'),
                    'campaignId'
                );
            }

            if ($campaign->isLayoutSpecific === 1) {
                throw new InvalidArgumentException(
                    __('Cannot schedule Layout as a Campaign, please select a Campaign instead.'),
                    'campaignId'
                );
            }
        } else {
            $schedule->parentCampaignId = null;
            if (!empty($schedule->campaignId)) {
                $campaign = $this->campaignFactory->getById($schedule->campaignId);
                if ($campaign->isLayoutSpecific === 0) {
                    throw new InvalidArgumentException(
                        __('Cannot schedule Campaign in selected event type, please select a Layout instead.'),
                        'campaignId'
                    );
                }
            }
        }

        // Fields only collected for interrupt events
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$INTERRUPT_EVENT) {
            $schedule->shareOfVoice = $sanitizedParams->getInt('shareOfVoice', [
                'throw' => function () {
                    new InvalidArgumentException(
                        __('Share of Voice must be a whole number between 0 and 3600'),
                        'shareOfVoice'
                    );
                }
            ]);
        } else {
            $schedule->shareOfVoice = null;
        }

        // Fields only collected for data connector events
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$DATA_CONNECTOR_EVENT) {
            $schedule->dataSetId = $sanitizedParams->getInt('dataSetId', [
                'throw' => function () {
                    new InvalidArgumentException(
                        __('Please select a DataSet'),
                        'dataSetId'
                    );
                }
            ]);
            $schedule->dataSetParams = $sanitizedParams->getString('dataSetParams');
        }

        // API request can provide an array of coordinates or valid GeoJSON, handle both cases here.
        if ($this->isApi($request) && $schedule->isGeoAware === 1) {
            if ($sanitizedParams->getArray('geoLocation') != null) {
                // get string array from API
                $coordinates = $sanitizedParams->getArray('geoLocation');
                // generate GeoJSON and assign to Schedule
                $schedule->geoLocation = $this->createGeoJson($coordinates);
            } else {
                // we were provided with GeoJSON
                $schedule->geoLocation = $sanitizedParams->getString('geoLocationJson');
            }
        } else {
            // if we are not using API, then valid GeoJSON is created in the front end.
            $schedule->geoLocation = $sanitizedParams->getString('geoLocation');
        }

        // if we are editing Layout/Campaign event that was set with Always daypart and change it to Command event type
        // the daypartId will remain as always, which will then cause the event to "disappear" from calendar
        // https://github.com/xibosignage/xibo/issues/1982
        if ($schedule->eventTypeId == \Xibo\Entity\Schedule::$COMMAND_EVENT) {
            $schedule->dayPartId = $this->dayPartFactory->getCustomDayPart()->dayPartId;
        }

        foreach ($sanitizedParams->getIntArray('displayGroupIds', ['default' => []]) as $displayGroupId) {
            $schedule->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));
        }

        if (!$schedule->isAlwaysDayPart()) {
            // Handle the dates
            $fromDt = $sanitizedParams->getDate('fromDt');
            $toDt = $sanitizedParams->getDate('toDt');
            $recurrenceRange = $sanitizedParams->getDate('recurrenceRange');

            if ($fromDt === null) {
                throw new InvalidArgumentException(__('Please enter a from date'). 'fromDt');
            }

            $logToDt = $toDt?->format(DateFormatHelper::getSystemFormat());
            $logRecurrenceRange = $recurrenceRange?->format(DateFormatHelper::getSystemFormat());
            $this->getLog()->debug(
                'Times received are: FromDt=' . $fromDt->format(DateFormatHelper::getSystemFormat())
                . '. ToDt=' . $logToDt . '. recurrenceRange=' . $logRecurrenceRange
            );

            if (!$schedule->isCustomDayPart() && !$schedule->isAlwaysDayPart()) {
                // Daypart selected
                // expect only a start date (no time)
                $schedule->fromDt = $fromDt->startOfDay()->format('U');
                $schedule->toDt = null;
                $schedule->recurrenceRange = ($recurrenceRange === null) ? null : $recurrenceRange->format('U');

            } else if (!($this->isApi($request) || Str::contains($this->getConfig()->getSetting('DATE_FORMAT'), 's'))) {
                // In some circumstances we want to trim the seconds from the provided dates.
                // this happens when the date format provided does not include seconds and when the add
                // event comes from the UI.
                $this->getLog()->debug('Date format does not include seconds, removing them');
                $schedule->fromDt = $fromDt->setTime($fromDt->hour, $fromDt->minute, 0)->format('U');

                // If we have a toDt
                if ($toDt !== null) {
                    $schedule->toDt = $toDt->setTime($toDt->hour, $toDt->minute, 0)->format('U');
                }

                $schedule->recurrenceRange = ($recurrenceRange === null)
                    ? null
                    : $recurrenceRange->setTime($recurrenceRange->hour, $recurrenceRange->minute, 0)->format('U');
            } else {
                $schedule->fromDt = $fromDt->format('U');

                if ($toDt !== null) {
                    $schedule->toDt = $toDt->format('U');
                }

                $schedule->recurrenceRange = ($recurrenceRange === null) ? null : $recurrenceRange->format('U');
            }

            $this->getLog()->debug('Processed start is: FromDt=' . $fromDt->toRssString());
        } else {
            // This is an always day part, which cannot be recurring, make sure we clear the recurring type if it has been set
            $schedule->recurrenceType = null;
        }

        // Schedule Criteria
        $schedule->criteria = [];
        $criteria = $sanitizedParams->getArray('criteria');
        if (is_array($criteria)) {
            foreach ($criteria as $item) {
                $itemParams = $this->getSanitizer($item);
                $criterion = $this->scheduleCriteriaFactory->createEmpty();
                $criterion->metric = $itemParams->getString('metric');
                $criterion->type = $itemParams->getString('type');
                $criterion->condition = $itemParams->getString('condition');
                $criterion->value = $itemParams->getString('value');
                $schedule->addOrUpdateCriteria($criterion, $itemParams->getInt('id'));
            }
        }

        // Ready to do the add
        $schedule->setDisplayNotifyService($this->displayFactory->getDisplayNotifyService());
        if ($schedule->campaignId != null) {
            $schedule->setCampaignFactory($this->campaignFactory);
        }
        $schedule->save();

        if ($this->isSyncEvent($schedule->eventTypeId)) {
            $syncGroup = $this->syncGroupFactory->getById($schedule->syncGroupId);
            $syncGroup->validateForSchedule($sanitizedParams);
            $schedule->updateSyncLinks($syncGroup, $sanitizedParams);
        }

        // Get form reminders
        $rows = [];
        for ($i=0; $i < count($sanitizedParams->getIntArray('reminder_value',['default' => []])); $i++) {
            $entry = [];

            if ($sanitizedParams->getIntArray('reminder_scheduleReminderId')[$i] == null) {
                continue;
            }

            $entry['reminder_scheduleReminderId'] = $sanitizedParams->getIntArray('reminder_scheduleReminderId')[$i];
            $entry['reminder_value'] = $sanitizedParams->getIntArray('reminder_value')[$i];
            $entry['reminder_type'] = $sanitizedParams->getIntArray('reminder_type')[$i];
            $entry['reminder_option'] = $sanitizedParams->getIntArray('reminder_option')[$i];
            $entry['reminder_isEmail'] = $sanitizedParams->getIntArray('reminder_isEmailHidden')[$i];

            $rows[$sanitizedParams->getIntArray('reminder_scheduleReminderId')[$i]] = $entry;
        }
        $formReminders = $rows;

        // Compare to delete
        // Get existing db reminders
        $scheduleReminders = $this->scheduleReminderFactory->query(null, ['eventId' => $id]);

        $rows = [];
        foreach ($scheduleReminders as $reminder) {

            $entry = [];
            $entry['reminder_scheduleReminderId'] = $reminder->scheduleReminderId;
            $entry['reminder_value'] = $reminder->value;
            $entry['reminder_type'] = $reminder->type;
            $entry['reminder_option'] = $reminder->option;
            $entry['reminder_isEmail'] = $reminder->isEmail;

            $rows[$reminder->scheduleReminderId] = $entry;
        }
        $dbReminders = $rows;

        $deleteReminders = $schedule->compareMultidimensionalArrays($dbReminders, $formReminders, false);
        foreach ($deleteReminders as $reminder) {
            $reminder = $this->scheduleReminderFactory->getById($reminder['reminder_scheduleReminderId']);
            $reminder->delete();
        }

        // API Request
        $rows = [];
        if ($this->isApi($request)) {

            $reminders =  $sanitizedParams->getArray('scheduleReminders', ['default' => []]);
            foreach ($reminders as $i => $reminder) {

                $rows[$i]['reminder_scheduleReminderId'] = isset($reminder['reminder_scheduleReminderId']) ? (int) $reminder['reminder_scheduleReminderId'] : null;
                $rows[$i]['reminder_value'] = (int) $reminder['reminder_value'];
                $rows[$i]['reminder_type'] = (int) $reminder['reminder_type'];
                $rows[$i]['reminder_option'] = (int) $reminder['reminder_option'];
                $rows[$i]['reminder_isEmailHidden'] = (int) $reminder['reminder_isEmailHidden'];
            }
        } else {

            for ($i=0; $i < count($sanitizedParams->getIntArray('reminder_value', ['default' => []])); $i++) {
                $rows[$i]['reminder_scheduleReminderId'] = $sanitizedParams->getIntArray('reminder_scheduleReminderId')[$i];
                $rows[$i]['reminder_value'] = $sanitizedParams->getIntArray('reminder_value')[$i];
                $rows[$i]['reminder_type'] = $sanitizedParams->getIntArray('reminder_type')[$i];
                $rows[$i]['reminder_option'] = $sanitizedParams->getIntArray('reminder_option')[$i];
                $rows[$i]['reminder_isEmailHidden'] = $sanitizedParams->getIntArray('reminder_isEmailHidden')[$i];
            }

        }

        // Save rest of the reminders
        foreach ($rows as $reminder) {

            // Do not add reminder if empty value provided for number of minute/hour
            if ($reminder['reminder_value'] == 0) {
                continue;
            }

            $scheduleReminderId = isset($reminder['reminder_scheduleReminderId']) ? $reminder['reminder_scheduleReminderId'] : null;

            try {
                $scheduleReminder = $this->scheduleReminderFactory->getById($scheduleReminderId);
                $scheduleReminder->load();
            } catch (NotFoundException $e) {
                $scheduleReminder = $this->scheduleReminderFactory->createEmpty();
                $scheduleReminder->scheduleReminderId = null;
                $scheduleReminder->eventId = $id;
            }

            $scheduleReminder->value = $reminder['reminder_value'];
            $scheduleReminder->type = $reminder['reminder_type'];
            $scheduleReminder->option = $reminder['reminder_option'];
            $scheduleReminder->isEmail = $reminder['reminder_isEmailHidden'];

            $this->saveReminder($schedule, $scheduleReminder);
        }

        // If this is a recurring event delete all schedule exclusions
        if ($schedule->recurrenceType != '') {
            // Delete schedule exclusions
            $scheduleExclusions = $this->scheduleExclusionFactory->query(null, ['eventId' => $schedule->eventId]);
            foreach ($scheduleExclusions as $exclusion) {
                $exclusion->delete();
            }
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Event'),
            'id' => $schedule->eventId,
            'data' => $schedule
        ]);

        return $this->render($request, $response);
    }

    /**
     * Shows the DeleteEvent form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        $schedule = $this->scheduleFactory->getById($id);
        $schedule->load();

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'schedule-form-delete';
        $this->getState()->setData([
            'event' => $schedule,
        ]);

        return $this->render($request,$response);
    }

    /**
     * Deletes an Event from all displays
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @SWG\Delete(
     *  path="/schedule/{eventId}",
     *  operationId="scheduleDelete",
     *  tags={"schedule"},
     *  summary="Delete Event",
     *  description="Delete a Scheduled Event",
     *  @SWG\Parameter(
     *      name="eventId",
     *      in="path",
     *      description="The Scheduled Event ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete(Request $request, Response $response, $id)
    {
        $schedule = $this->scheduleFactory->getById($id);
        $schedule->load();

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        $schedule
            ->setDisplayNotifyService($this->displayFactory->getDisplayNotifyService())
            ->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Deleted Event')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Is this event editable?
     * @param \Xibo\Entity\Schedule $event
     * @return bool
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function isEventEditable(\Xibo\Entity\Schedule $event): bool
    {
        if (!$this->getUser()->featureEnabled('schedule.modify')) {
            return false;
        }

        // Is this an event coming from an ad campaign?
        if (!empty($event->parentCampaignId) && $event->eventTypeId === \Xibo\Entity\Schedule::$INTERRUPT_EVENT) {
            return false;
        }

        $scheduleWithView = ($this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 1);

        // Work out if this event is editable or not. To do this we need to compare the permissions
        // of each display group this event is associated with
        foreach ($event->displayGroups as $displayGroup) {
            // Can schedule with view, but no view permissions
            if ($scheduleWithView && !$this->getUser()->checkViewable($displayGroup)) {
                return false;
            }

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && !$this->getUser()->checkEditable($displayGroup)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return Schedule eventTypeId based on the grid the requests is coming from
     * @param $from
     * @return int
     */
    private function getEventTypeId($from)
    {
        return match ($from) {
            'Campaign' => \Xibo\Entity\Schedule::$CAMPAIGN_EVENT,
            'Library' => \Xibo\Entity\Schedule::$MEDIA_EVENT,
            'Playlist' => \Xibo\Entity\Schedule::$PLAYLIST_EVENT,
            default => \Xibo\Entity\Schedule::$LAYOUT_EVENT
        };
    }

    /**
     * Generates the Schedule events grid
     *
     * @SWG\Get(
     *  path="/schedule",
     *  operationId="scheduleSearch",
     *  tags={"schedule"},
     *  @SWG\Parameter(
     *      name="eventTypeId",
     *      in="query",
     *      required=false,
     *      type="integer",
     *      description="Filter grid by eventTypeId.
     * 1=Layout, 2=Command, 3=Overlay, 4=Interrupt, 5=Campaign, 6=Action, 7=Media Library, 8=Playlist"
     *  ),
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="query",
     *      required=false,
     *      type="string",
     *      description="From Date in Y-m-d H:i:s format"
     *  ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="query",
     *      required=false,
     *      type="string",
     *      description="To Date in Y-m-d H:i:s format"
     *  ),
     *  @SWG\Parameter(
     *      name="geoAware",
     *      in="query",
     *      required=false,
     *      type="integer",
     *      description="Flag (0-1), whether to return events using Geo Location"
     *  ),
     *  @SWG\Parameter(
     *      name="recurring",
     *      in="query",
     *      required=false,
     *      type="integer",
     *      description="Flag (0-1), whether to return Recurring events"
     *  ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="query",
     *      required=false,
     *      type="integer",
     *      description="Filter events by specific campaignId"
     *  ),
     *  @SWG\Parameter(
     *      name="displayGroupIds",
     *      in="query",
     *      required=false,
     *      type="array",
     *      description="Filter events by an array of Display Group Ids",
     *      @SWG\Items(type="integer")
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Schedule")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $params = $this->getSanitizer($request->getParams());

        $displayGroupIds = $params->getIntArray('displayGroupIds', ['default' => []]);
        $displaySpecificDisplayGroupIds = $params->getIntArray('displaySpecificGroupIds', ['default' => []]);
        $originalDisplayGroupIds = array_merge($displayGroupIds, $displaySpecificDisplayGroupIds);

        if (!$this->getUser()->isSuperAdmin()) {
            $userDisplayGroupIds = array_map(function ($element) {
                /** @var \Xibo\Entity\DisplayGroup $element */
                return $element->displayGroupId;
            }, $this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]));

            // Reset the list to only those display groups that intersect and if 0 have been provided, only those from
            // the user list
            $resolvedDisplayGroupIds = (count($originalDisplayGroupIds) > 0)
                    ? array_intersect($originalDisplayGroupIds, $userDisplayGroupIds)
                    : $userDisplayGroupIds;

            $this->getLog()->debug('Resolved list of display groups ['
                . json_encode($displayGroupIds) . '] from provided list ['
                . json_encode($originalDisplayGroupIds) . '] and user list ['
                . json_encode($userDisplayGroupIds) . ']');

            // If we have none, then we do not return any events.
            if (count($resolvedDisplayGroupIds) <= 0) {
                $this->getState()->template = 'grid';
                $this->getState()->recordsTotal = $this->scheduleFactory->countLast();
                $this->getState()->setData([]);

                return $this->render($request, $response);
            }
        } else {
            $resolvedDisplayGroupIds = $originalDisplayGroupIds;
        }

        $events = $this->scheduleFactory->query(
            $this->gridRenderSort($params),
            $this->gridRenderFilter([
                'eventTypeId' => $params->getInt('eventTypeId'),
                'futureSchedulesFrom' =>  $params->getDate('fromDt')?->format('U'),
                'futureSchedulesTo' => $params->getDate('toDt')?->format('U'),
                'geoAware' => $params->getInt('geoAware'),
                'recurring' => $params->getInt('recurring'),
                'campaignId' => $params->getInt('campaignId'),
                'displayGroupIds' => $resolvedDisplayGroupIds,
                'name' => $params->getString('name'),
                'useRegexForName' => $params->getCheckbox('useRegexForName'),
                'logicalOperatorName' => $params->getString('logicalOperatorName'),
                'directSchedule' => $params->getCheckbox('directSchedule'),
                'sharedSchedule' => $params->getCheckbox('sharedSchedule'),
                'gridFilter' => 1,
            ], $params)
        );

        // Grab some settings which determine how events are displayed.
        $showLayoutName = ($this->getConfig()->getSetting('SCHEDULE_SHOW_LAYOUT_NAME') == 1);
        $defaultTimezone = $this->getConfig()->getSetting('defaultTimezone');

        foreach ($events as $event) {
            $event->load();

            if (count($event->displayGroups) > 0) {
                $array = array_map(function ($object) {
                    return $object->displayGroup;
                }, $event->displayGroups);
                $displayGroupList = implode(', ', $array);
            } else {
                $displayGroupList = '';
            }

            $eventTypes = \Xibo\Entity\Schedule::getEventTypesGrid();
            foreach ($eventTypes as $eventType) {
                if ($eventType['eventTypeId'] === $event->eventTypeId) {
                    $event->setUnmatchedProperty('eventTypeName', $eventType['eventTypeName']);
                }
            }

            $event->setUnmatchedProperty('displayGroupList', $displayGroupList);
            $event->setUnmatchedProperty('recurringEvent', !empty($event->recurrenceType));
            
            if ($this->isSyncEvent($event->eventTypeId)) {
                $event->setUnmatchedProperty(
                    'displayGroupList',
                    $event->getUnmatchedProperty('syncGroupName')
                );
                $event->setUnmatchedProperty(
                    'syncType',
                    $event->getSyncTypeForEvent()
                );
            }

            if (!$showLayoutName && !$this->getUser()->isSuperAdmin() && !empty($event->campaignId)) {
                // Campaign
                $campaign = $this->campaignFactory->getById($event->campaignId);

                if (!$this->getUser()->checkViewable($campaign)) {
                    $event->campaign = __('Private Item');
                }
            }

            if (!empty($event->recurrenceType)) {
                $repeatsOn = '';
                $repeatsUntil = '';

                if ($event->recurrenceType === 'Week' && !empty($event->recurrenceRepeatsOn)) {
                    $weekdays = Carbon::getDays();
                    $repeatDays = explode(',', $event->recurrenceRepeatsOn);
                    $i = 0;
                    foreach ($repeatDays as $repeatDay) {
                        // Carbon getDays starts with Sunday,
                        // return first element from that array if in our array we have 7 (Sunday)
                        $repeatDay = ($repeatDay == 7) ? 0 : $repeatDay;
                        $repeatsOn .= $weekdays[$repeatDay];
                        if ($i < count($repeatDays) - 1) {
                            $repeatsOn .= ', ';
                        }
                        $i++;
                    }
                } else if ($event->recurrenceType === 'Month') {
                    // Force the timezone for this date (schedule from/to dates are timezone agnostic, but this
                    // date still has timezone information, which could lead to use formatting as the wrong day)
                    $date = Carbon::parse($event->fromDt)->tz($defaultTimezone);
                    $this->getLog()->debug('grid: setting description for monthly event with date: '
                        . $date->toAtomString());

                    if ($event->recurrenceMonthlyRepeatsOn === 0) {
                        $repeatsOn = 'the ' . $date->format('jS') . ' day of the month';
                    } else {
                        // Which day of the month is this?
                        $firstDay = Carbon::parse('first ' . $date->format('l') . ' of ' . $date->format('F'));

                        $this->getLog()->debug('grid: the first day of the month for this date is: '
                            . $firstDay->toAtomString());

                        $nth = $firstDay->diffInDays($date) / 7 + 1;
                        $repeatWeekDayDate = $date->copy()->setDay($nth)->format('jS');
                        $repeatsOn = 'the ' . $repeatWeekDayDate . ' '
                            . $date->format('l')
                            . ' of the month';
                    }
                }

                if (!empty($event->recurrenceRange)) {
                    $repeatsUntil = Carbon::createFromTimestamp($event->recurrenceRange)
                            ->format(DateFormatHelper::getSystemFormat());
                }

                $event->setUnmatchedProperty(
                    'recurringEventDescription',
                    __(sprintf(
                        'Repeats every %d %s %s %s',
                        $event->recurrenceDetail,
                        $event->recurrenceType . ($event->recurrenceDetail > 1 ? 's' : ''),
                        !empty($repeatsOn) ? 'on ' . $repeatsOn : '',
                        !empty($repeatsUntil) ? ' until ' . $repeatsUntil : ''
                    ))
                );
            } else {
                $event->setUnmatchedProperty('recurringEventDescription', '');
            }

            if (!$event->isAlwaysDayPart() && !$event->isCustomDayPart()) {
                $dayPart = $this->dayPartFactory->getById($event->dayPartId);
                $dayPart->adjustForDate(Carbon::createFromTimestamp($event->fromDt));
                $event->fromDt = $dayPart->adjustedStart->format('U');
                $event->toDt = $dayPart->adjustedEnd->format('U');
            }

            if ($event->eventTypeId == \Xibo\Entity\Schedule::$COMMAND_EVENT) {
                $event->toDt = $event->fromDt;
            }

            // Set the row from/to date to be an ISO date for display (no timezone)
            $event->setUnmatchedProperty(
                'displayFromDt',
                Carbon::createFromTimestamp($event->fromDt)->format(DateFormatHelper::getSystemFormat())
            );
            $event->setUnmatchedProperty(
                'displayToDt',
                Carbon::createFromTimestamp($event->toDt)->format(DateFormatHelper::getSystemFormat())
            );

            if ($this->isApi($request)) {
                continue;
            }

            $event->includeProperty('buttons');
            $event->setUnmatchedProperty('isEditable', $this->isEventEditable($event));
            if ($this->isEventEditable($event)) {
                $event->buttons[] = [
                    'id' => 'schedule_button_edit',
                    'url' => $this->urlFor(
                        $request,
                        ($this->isSyncEvent($event->eventTypeId))
                            ? 'schedule.edit.sync.form'
                            : 'schedule.edit.form',
                        ['id' => $event->eventId]
                    ),
                    'dataAttributes' => [
                        ['name' => 'event-id', 'value' => $event->eventId],
                        ['name' => 'event-start', 'value' => $event->fromDt * 1000],
                        ['name' => 'event-end', 'value' => $event->toDt * 1000]
                    ],
                    'text' => __('Edit')
                ];

                $event->buttons[] = [
                    'id' => 'schedule_button_delete',
                    'url' => $this->urlFor($request, 'schedule.delete.form', ['id' => $event->eventId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor($request, 'schedule.delete', ['id' => $event->eventId])
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'schedule_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $event->eventId]
                    ]
                ];
            }
        }

        // Store the table rows
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->scheduleFactory->countLast();
        $this->getState()->setData($events);

        return $this->render($request, $response);
    }

    /**
     * @param \Xibo\Entity\Schedule $schedule
     * @param ScheduleReminder $scheduleReminder
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function saveReminder($schedule, $scheduleReminder)
    {
        // if someone changes from custom to always
        // we should keep the definitions, but make sure they don't get executed in the task
        if ($schedule->isAlwaysDayPart()) {
            $scheduleReminder->reminderDt = 0;
            $scheduleReminder->save();
            return;
        }

        switch ($scheduleReminder->type) {
            case ScheduleReminder::$TYPE_MINUTE:
                $type = ScheduleReminder::$MINUTE;
                break;
            case ScheduleReminder::$TYPE_HOUR:
                $type = ScheduleReminder::$HOUR;
                break;
            case ScheduleReminder::$TYPE_DAY:
                $type = ScheduleReminder::$DAY;
                break;
            case ScheduleReminder::$TYPE_WEEK:
                $type = ScheduleReminder::$WEEK;
                break;
            case ScheduleReminder::$TYPE_MONTH:
                $type = ScheduleReminder::$MONTH;
                break;
            default:
                throw new NotFoundException(__('Unknown type'));
        }

        // Remind seconds that we will subtract/add from schedule fromDt/toDt to get reminderDt
        $remindSeconds =  $scheduleReminder->value *  $type;

        // Set reminder date
        if ($scheduleReminder->option == ScheduleReminder::$OPTION_BEFORE_START) {
            $scheduleReminder->reminderDt = $schedule->fromDt - $remindSeconds;
        } elseif ($scheduleReminder->option == ScheduleReminder::$OPTION_AFTER_START) {
            $scheduleReminder->reminderDt = $schedule->fromDt + $remindSeconds;
        } elseif ($scheduleReminder->option == ScheduleReminder::$OPTION_BEFORE_END) {
            $scheduleReminder->reminderDt = $schedule->toDt - $remindSeconds;
        } elseif ($scheduleReminder->option == ScheduleReminder::$OPTION_AFTER_END) {
            $scheduleReminder->reminderDt = $schedule->toDt + $remindSeconds;
        }

        // Is recurring event?
        $now = Carbon::now();
        if ($schedule->recurrenceType != '') {

            // find the next event from now
            try {
                $nextReminderDate = $schedule->getNextReminderDate($now, $scheduleReminder, $remindSeconds);
            } catch (NotFoundException $error) {
                $nextReminderDate = 0;
                $this->getLog()->debug('No next occurrence of reminderDt found. ReminderDt set to 0.');
            }

            if ($nextReminderDate != 0) {

                if ($nextReminderDate < $scheduleReminder->lastReminderDt) {

                    // handle if someone edit in frontend after notifications were created
                    // we cannot have a reminderDt set to below the lastReminderDt
                    // so we make the lastReminderDt 0
                    $scheduleReminder->lastReminderDt = 0;
                    $scheduleReminder->reminderDt = $nextReminderDate;

                } else {
                    $scheduleReminder->reminderDt = $nextReminderDate;
                }

            } else {
                // next event is not found
                // we make the reminderDt and lastReminderDt as 0
                $scheduleReminder->lastReminderDt = 0;
                $scheduleReminder->reminderDt = 0;
            }

            // Save
            $scheduleReminder->save();

        } else { // one off event

            $scheduleReminder->save();

        }
    }

    private function createGeoJson($coordinates)
    {
        $properties = new \StdClass();
        $convertedCoordinates = [];


        // coordinates come as array of strings, we need convert that to array of arrays with float values for the Geo JSON
        foreach ($coordinates as $coordinate) {

            // each $coordinate is a comma separated string with 2 coordinates
            // make it into an array
            $explodedCords = explode(',', $coordinate);

            // prepare a new array, we will add float values to it, need to be cleared for each set of coordinates
            $floatCords = [];

            // iterate through the exploded array, change the type to float store in a new array
            foreach ($explodedCords as $explodedCord) {
                $explodedCord = (float)$explodedCord;
                $floatCords[] = $explodedCord;
            }

            // each set of coordinates will be added to this new array, which we will use in the geo json
            $convertedCoordinates[] = $floatCords;
        }

        $geometry = [
            'type' => 'Polygon',
            'coordinates' => [
                $convertedCoordinates
            ]
        ];

        $geoJson = [
            'type'      => 'Feature',
            'properties' => $properties,
            'geometry'  => $geometry
        ];

        return json_encode($geoJson);
    }

    /**
     * Check if this is event is using full screen schedule with Media or Playlist
     * @param $eventTypeId
     * @return bool
     */
    private function isFullScreenSchedule($eventTypeId)
    {
        return in_array($eventTypeId, [\Xibo\Entity\Schedule::$MEDIA_EVENT, \Xibo\Entity\Schedule::$PLAYLIST_EVENT]);
    }

    /**
     * @param $eventTypeId
     * @return int
     */
    private function isSyncEvent($eventTypeId): int
    {
        return ($eventTypeId === \Xibo\Entity\Schedule::$SYNC_EVENT) ? 1 : 0;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function syncForm(Request $request, Response $response): Response|ResponseInterface
    {
        // get the default longitude and latitude from CMS options
        $defaultLat = (float)$this->getConfig()->getSetting('DEFAULT_LAT');
        $defaultLong = (float)$this->getConfig()->getSetting('DEFAULT_LONG');

        $this->getState()->template = 'schedule-form-sync-add';
        $this->getState()->setData([
            'eventTypeId' => \Xibo\Entity\Schedule::$SYNC_EVENT,
            'dayParts' => $this->dayPartFactory->allWithSystem(),
            'defaultLat' => $defaultLat,
            'defaultLong' => $defaultLong,
            'reminders' => [],
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function syncEditForm(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Recurring event start/end
        $eventStart = $sanitizedParams->getInt('eventStart', ['default' => 1000]) / 1000;
        $eventEnd = $sanitizedParams->getInt('eventEnd', ['default' => 1000]) / 1000;

        $schedule = $this->scheduleFactory->getById($id);
        $schedule->load();

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        // Fix the event dates for display
        if ($schedule->isAlwaysDayPart()) {
            $schedule->fromDt = '';
            $schedule->toDt = '';
        } else {
            $schedule->fromDt =
                Carbon::createFromTimestamp($schedule->fromDt)->format(DateFormatHelper::getSystemFormat());
            $schedule->toDt =
                Carbon::createFromTimestamp($schedule->toDt)->format(DateFormatHelper::getSystemFormat());
        }

        if ($schedule->recurrenceRange != null) {
            $schedule->recurrenceRange =
                Carbon::createFromTimestamp($schedule->recurrenceRange)->format(DateFormatHelper::getSystemFormat());
        }

        // Get all reminders
        $scheduleReminders = $this->scheduleReminderFactory->query(null, ['eventId' => $id]);

        // get the default longitude and latitude from CMS options
        $defaultLat = (float)$this->getConfig()->getSetting('DEFAULT_LAT');
        $defaultLong = (float)$this->getConfig()->getSetting('DEFAULT_LONG');

        $this->getState()->template = 'schedule-form-sync-edit';
        $this->getState()->setData([
            'eventTypeId' => \Xibo\Entity\Schedule::$SYNC_EVENT,
            'event' => $schedule,
            'dayParts' => $this->dayPartFactory->allWithSystem(),
            'defaultLat' => $defaultLat,
            'defaultLong' => $defaultLong,
            'eventStart' => $eventStart,
            'eventEnd' => $eventEnd,
            'recurringEvent' => $schedule->recurrenceType != '',
            'reminders' => $scheduleReminders,
        ]);

        return $this->render($request, $response);
    }
}
