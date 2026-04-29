<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ScheduleReminder;
use Xibo\Factory\CampaignFactory;
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
use Xibo\Service\JwtServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Schedule
 * @package Xibo\Controller
 */
#[OA\Schema(
    schema: 'ScheduleCalendarData',
    properties: [
        new OA\Property(property: 'id', description: 'Event ID', type: 'integer'),
        new OA\Property(property: 'title', description: 'Event Title', type: 'string'),
        new OA\Property(property: 'sameDay', description: 'Does this event happen only on 1 day', type: 'integer'),
        new OA\Property(property: 'event', ref: '#/components/schemas/Schedule')
    ]
)]
class Schedule extends Base
{
    public function __construct(
        private readonly ScheduleFactory $scheduleFactory,
        private readonly DisplayGroupFactory $displayGroupFactory,
        private readonly CampaignFactory $campaignFactory,
        private readonly DisplayFactory $displayFactory,
        private readonly LayoutFactory $layoutFactory,
        private readonly DayPartFactory $dayPartFactory,
        private readonly ScheduleReminderFactory $scheduleReminderFactory,
        private readonly ScheduleExclusionFactory $scheduleExclusionFactory,
        private readonly SyncGroupFactory $syncGroupFactory,
        private readonly ScheduleCriteriaFactory $scheduleCriteriaFactory,
        private readonly JwtServiceInterface $jwtService
    ) {
    }

    #[OA\Get(
        path: '/schedule/{displayGroupId}/events',
        operationId: 'scheduleCalendarDataDisplayGroup',
        summary: 'Event List',
        tags: ['schedule']
    )]
    #[OA\Parameter(
        name: 'displayGroupId',
        description: 'The DisplayGroupId to return the event list for.',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(name: 'singlePointInTime', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(
        name: 'date',
        description: 'Date in Y-m-d H:i:s',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'startDate',
        description: 'Date in Y-m-d H:i:s',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'endDate',
        description: 'Date in Y-m-d H:i:s',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(response: 200, description: 'successful response')]
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
     */
    public function eventList(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkViewable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Setting for whether we show Layouts with out permissions
        $showLayoutName = ($this->getConfig()->getSetting('SCHEDULE_SHOW_LAYOUT_NAME') == 1);

        $singlePointInTime = $sanitizedParams->getInt('singlePointInTime');
        if ($singlePointInTime == 1) {
            $startDate = $sanitizedParams->getDate('date');
            $endDate = $sanitizedParams->getDate('date');
        } else {
            $startDate = $sanitizedParams->getDate('startDate');
            $endDate = $sanitizedParams->getDate('endDate');
        }

        // Reset the seconds
        $startDate->second(0);
        $endDate->second(0);

        $this->getLog()->debug(
            'Generating eventList for DisplayGroupId ' . $id . ' from date '
            . $startDate->format(DateFormatHelper::getSystemFormat()) . ' to '
            . $endDate->format(DateFormatHelper::getSystemFormat())
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
        $scheduleForXmds = $this->scheduleFactory->getForXmds(
            ($display === null) ? null : $display->displayId,
            $startDate,
            $endDate,
            $options
        );

        $this->getLog()->debug(count($scheduleForXmds) . ' events returned for displaygroup and date');

        foreach ($scheduleForXmds as $event) {
            // Ignore command events
            if ($event['eventTypeId'] == \Xibo\Entity\Schedule::$COMMAND_EVENT) {
                continue;
            }

            // Ignore events that have a campaignId, but no layoutId (empty Campaigns)
            if ($event['layoutId'] == 0 && $event['campaignId'] != 0) {
                continue;
            }

            // Assess schedules
            $schedule = $this->scheduleFactory->createEmpty()->hydrate($event, [
                'intProperties' => [
                    'isPriority',
                    'syncTimezone',
                    'displayOrder',
                    'fromDt',
                    'toDt'
                ]
            ]);
            $schedule->load();

            $this->getLog()->debug(
                'EventId ' . $schedule->eventId .
                ' exists in the schedule window, checking its instances for activity'
            );

            // Get scheduled events based on recurrence
            try {
                $scheduleEvents = $schedule->getEvents($startDate, $endDate);
            } catch (GeneralException $e) {
                $this->getLog()->error('Unable to getEvents for ' . $schedule->eventId);
                continue;
            }

            // If this event is active, collect extra information and add to the events list
            if (count($scheduleEvents) > 0) {
                // Add the link to the schedule
                if (!$this->isApi($request)) {
                    $route = 'schedule.edit.form';
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

                    // Add preview token to layout
                    $jwt = $this->jwtService->generateJwt(
                        'Preview',
                        'layout',
                        $layout->layoutId,
                        '/preview/layout/preview/' . $layout->layoutId,
                        3600,
                    )->toString();

                    $layout->setUnmatchedProperty(
                        'previewJwt',
                        $jwt
                    );

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

        return $response
            ->withStatus(200)
            ->withJson([
                'events' => $events,
                'displayGroups' => $displayGroups,
                'layouts' => $layouts,
                'campaigns' => $campaigns
            ]);
    }

    /**
     * @param ?\Xibo\Entity\Display $display
     * @param \Xibo\Entity\DisplayGroup $displayGroup
     * @param int $eventDisplayGroupId
     * @return array
     * @throws NotFoundException
     */
    private function calculateIntermediates(
        ?\Xibo\Entity\Display $display,
        \Xibo\Entity\DisplayGroup $displayGroup,
        int $eventDisplayGroupId
    ): array {
        $this->getLog()->debug(
            'Calculating intermediates for events displayGroupId ' . $eventDisplayGroupId .
            ' viewing displayGroupId ' . $displayGroup->displayGroupId
        );

        $intermediates = [];
        $eventDisplayGroup = $this->displayGroupFactory->getById($eventDisplayGroupId);

        // Is the event scheduled directly on the displayGroup in question?
        if ($displayGroup->displayGroupId == $eventDisplayGroupId) {
            return $intermediates;
        }

        // Is the event scheduled directly on the display in question?
        if ($eventDisplayGroup->isDisplaySpecific == 1) {
            return $intermediates;
        }

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
                if ($displayDisplayGroup->isDisplaySpecific == 1) {
                    continue;
                }

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

                    if ($branch->displayGroupId != $eventDisplayGroup->displayGroupId &&
                        count($possibleIntermediates) > 0
                    ) {
                        $found = true;
                    }
                }

                if ($found) {
                    $this->getLog()->debug(
                        'We have found intermediates ' . json_encode($possibleIntermediates) .
                        ' for display when looking at displayGroupId ' . $displayDisplayGroup->displayGroupId
                    );
                    $intermediates = array_merge($intermediates, $possibleIntermediates);
                }
            }
        }

        $this->getLog()->debug('Returning intermediates: ' . json_encode($intermediates));

        return $intermediates;
    }

    #[OA\Schema(
        schema: 'ScheduleReminderArray',
        properties: [
            new OA\Property(property: 'reminder_value', type: 'integer'),
            new OA\Property(property: 'reminder_type', type: 'integer'),
            new OA\Property(property: 'reminder_option', type: 'integer'),
            new OA\Property(property: 'reminder_isEmailHidden', type: 'integer')
        ]
    )]
    #[OA\Post(
        path: '/schedule',
        operationId: 'scheduleAdd',
        description: 'Add a new scheduled event for a Campaign/Layout to be shown on a Display Group/Display.',
        summary: 'Add Schedule Event',
        tags: ['schedule']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['eventTypeId', 'displayOrder', 'isPriority', 'displayGroupIds', 'fromDt'],
                properties: array(
                    new OA\Property(
                        property: 'eventTypeId',
                        description: 'The Event Type Id to use for this Event.
     * 1=Layout, 2=Command, 3=Overlay, 4=Interrupt, 5=Campaign, 6=Action, 7=Media Library, 8=Playlist',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'campaignId',
                        description: 'The Campaign ID to use for this Event.
     * If a Layout is needed then the Campaign specific ID for that Layout should be used.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'fullScreenCampaignId',
                        description: 'For Media or Playlist event Type.
                         The Layout specific Campaign ID to use for this Event.
     * This needs to be the Layout created with layout/fullscreen function',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'commandId',
                        description: 'The Command ID to use for this Event.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'mediaId',
                        description: 'The Media ID to use for this Event.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'displayOrder',
                        description: 'The display order for this event. ',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'isPriority',
                        description: 'An integer indicating the priority of this event. Normal events have a priority of 0.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'displayGroupIds',
                        description: 'The Display Group IDs for this event. Display specific Group IDs should be used to schedule on single displays.', // phpcs:ignore
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'dayPartId',
                        description: 'The Day Part for this event. Overrides supported are 0(custom) and 1(always). Defaulted to 0.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'syncTimezone',
                        description: 'Should this schedule be synced to the resulting Display timezone?',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'fromDt',
                        description: 'The from date for this event.',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'toDt',
                        description: 'The to date for this event.',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'recurrenceType',
                        description: 'The type of recurrence to apply to this event.',
                        type: 'string',
                        enum: array('', 'Minute', 'Hour', 'Day', 'Week', 'Month', 'Year')
                    ),
                    new OA\Property(
                        property: 'recurrenceDetail',
                        description: 'The interval for the recurrence.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'recurrenceRange',
                        description: 'The end date for this events recurrence.',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'recurrenceRepeatsOn',
                        description: 'The days of the week that this event repeats - weekly only',
                        type: 'string',
                        format: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'scheduleReminders',
                        description: 'Array of Reminders for this event',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/ScheduleReminderArray')
                    ),
                    new OA\Property(
                        property: 'isGeoAware',
                        description: 'Flag (0-1), whether this event is using Geo Location',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'geoLocation',
                        description: 'Array of comma separated strings each with comma separated pair of coordinates',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(
                        property: 'geoLocationJson',
                        description: 'Valid GeoJSON string, use as an alternative to geoLocation parameter',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'actionType',
                        description: 'For Action eventTypeId, the type of the action - command or navLayout',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'actionTriggerCode',
                        description: 'For Action eventTypeId, the webhook trigger code for the Action',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'actionLayoutCode',
                        description: 'For Action eventTypeId and navLayout actionType, the Layout Code identifier',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'dataSetId',
                        description: 'For Data Connector eventTypeId, the DataSet ID',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dataSetParams',
                        description: 'For Data Connector eventTypeId, the DataSet params',
                        type: 'string'
                    )
                )
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ],
        content: new OA\JsonContent(ref: '#/components/schemas/Schedule')
    )]
    /**
     * Add Event
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function add(Request $request, Response $response): Response|ResponseInterface
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
        $schedule->campaignId = $schedule->isFullScreenSchedule()
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

        // Create fullscreen layout for media/playlist events
        if ($schedule->isFullScreenSchedule()) {
            $type = $schedule->eventTypeId === \Xibo\Entity\Schedule::$MEDIA_EVENT ? 'media' : 'playlist';
            $id = ($type === 'media') ? $sanitizedParams->getInt('mediaId') : $sanitizedParams->getInt('playlistId');

            if (!$id) {
                throw new InvalidArgumentException(
                    sprintf('%sId is required when scheduling %s events.', ucfirst($type), $type)
                );
            }

            $fsLayout = $this->layoutFactory->createFullScreenLayout(
                $type,
                $id,
                $sanitizedParams->getInt('resolutionId'),
                $sanitizedParams->getString('backgroundColor'),
                $sanitizedParams->getInt('layoutDuration'),
            );

            $schedule->campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($fsLayout->layoutId);
            $schedule->parentCampaignId = $schedule->campaignId;
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
        $schedule->syncEvent = $schedule->isSyncEvent();
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

        if ($schedule->isSyncEvent()) {
            $syncGroup = $this->syncGroupFactory->getById($schedule->syncGroupId);
            $syncGroup->validateForSchedule($sanitizedParams);
            $schedule->updateSyncLinks($syncGroup, $sanitizedParams);
        }

        return $response
            ->withStatus(201)
            ->withJson([
                'id' => $schedule->eventId,
                'data' => $schedule
            ]);
    }

    #[OA\Delete(
        path: '/schedulerecurrence/{eventId}',
        operationId: 'schedulerecurrenceDelete',
        description: 'Delete a Recurring Event of a Scheduled Event',
        summary: 'Delete a Recurring Event',
        tags: ['schedule']
    )]
    #[OA\Parameter(
        name: 'eventId',
        description: 'The Scheduled Event ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
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
     */
    public function deleteRecurrence(Request $request, Response $response, $id): Response|ResponseInterface
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
        return $response->withStatus(204);
    }

    #[OA\Put(
        path: '/schedule/{eventId}',
        operationId: 'scheduleEdit',
        description: 'Edit a scheduled event for a Campaign/Layout to be shown on a Display Group/Display.',
        summary: 'Edit Schedule Event',
        tags: ['schedule']
    )]
    #[OA\Parameter(
        name: 'eventId',
        description: 'The Scheduled Event ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['eventTypeId', 'displayOrder', 'isPriority', 'displayGroupIds', 'fromDt'],
                properties: [
                    new OA\Property(
                        property: 'eventTypeId',
                        description: 'The Event Type Id to use for this Event.
     * 1=Layout, 2=Command, 3=Overlay, 4=Interrupt, 5=Campaign, 6=Action', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'campaignId',
                        description: 'The Campaign ID to use for this Event.
     * If a Layout is needed then the Campaign specific ID for that Layout should be used.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'commandId',
                        description: 'The Command ID to use for this Event.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'mediaId',
                        description: 'The Media ID to use for this Event.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'displayOrder',
                        description: 'The display order for this event. ',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'isPriority',
                        description: 'An integer indicating the priority of this event. Normal events have a priority of 0.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'displayGroupIds',
                        description: 'The Display Group IDs for this event.
     * Display specific Group IDs should be used to schedule on single displays.', // phpcs:ignore
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'dayPartId',
                        description: 'The Day Part for this event. Overrides supported are 0(custom) and 1(always). Defaulted to 0.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'syncTimezone',
                        description: 'Should this schedule be synced to the resulting Display timezone?',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'fromDt',
                        description: 'The from date for this event.',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'toDt',
                        description: 'The to date for this event.',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'recurrenceType',
                        description: 'The type of recurrence to apply to this event.',
                        type: 'string',
                        enum: ['', 'Minute', 'Hour', 'Day', 'Week', 'Month', 'Year']
                    ),
                    new OA\Property(
                        property: 'recurrenceDetail',
                        description: 'The interval for the recurrence.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'recurrenceRange',
                        description: 'The end date for this events recurrence.',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'recurrenceRepeatsOn',
                        description: 'The days of the week that this event repeats - weekly only',
                        type: 'string',
                        format: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'scheduleReminders',
                        description: 'Array of Reminders for this event',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/ScheduleReminderArray')
                    ),
                    new OA\Property(
                        property: 'isGeoAware',
                        description: 'Flag (0-1), whether this event is using Geo Location',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'geoLocation',
                        description: 'Array of comma separated strings each with comma separated pair of coordinates',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(
                        property: 'geoLocationJson',
                        description: 'Valid GeoJSON string, use as an alternative to geoLocation parameter',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'actionType',
                        description: 'For Action eventTypeId, the type of the action - command or navLayout',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'actionTriggerCode',
                        description: 'For Action eventTypeId, the webhook trigger code for the Action',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'actionLayoutCode',
                        description: 'For Action eventTypeId and navLayout actionType, the Layout Code identifier',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'dataSetId',
                        description: 'For Data Connector eventTypeId, the DataSet ID',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dataSetParams',
                        description: 'For Data Connector eventTypeId, the DataSet params',
                        type: 'string'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Schedule')
    )]
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
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $embed = ($sanitizedParams->getString('embed') != null)
            ? explode(',', $sanitizedParams->getString('embed'))
            : [];

        $schedule = $this->scheduleFactory->getById($id);
        $oldSchedule = clone $schedule;

        $schedule->load([
            'loadScheduleReminders' => in_array('scheduleReminders', $embed),
        ]);

        if (!$this->isEventEditable($schedule)) {
            throw new AccessDeniedException();
        }

        $schedule->eventTypeId = $sanitizedParams->getInt('eventTypeId');
        $schedule->campaignId = $schedule->isFullScreenSchedule()
            ? $sanitizedParams->getInt('fullScreenCampaignId')
            : $sanitizedParams->getInt('campaignId');
        // displayOrder and isPriority: if present but empty (""): set to 0
        // if missing from form: keep existing value (fallback to 0 if unset)
        $schedule->displayOrder = $sanitizedParams->hasParam('displayOrder')
            ? $sanitizedParams->getInt('displayOrder', ['default' => 0])
            : ($schedule->displayOrder ?? 0);

        $schedule->isPriority = $sanitizedParams->hasParam('isPriority')
            ? $sanitizedParams->getInt('isPriority', ['default' => 0])
            : ($schedule->isPriority ?? 0);

        $schedule->dayPartId = $sanitizedParams->getInt('dayPartId', ['default' => $schedule->dayPartId]);
        $schedule->syncTimezone = $sanitizedParams->getCheckbox('syncTimezone');
        $schedule->syncEvent = $schedule->isSyncEvent();
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
        $schedule->syncGroupId = $sanitizedParams->getInt('syncGroupId');
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

        // Get the campaignId for media/playlist events
        if ($schedule->isFullScreenSchedule()) {
            $type = $schedule->eventTypeId === \Xibo\Entity\Schedule::$MEDIA_EVENT ? 'media' : 'playlist';
            $fsId = ($type === 'media') ? $sanitizedParams->getInt('mediaId') : $sanitizedParams->getInt('playlistId');

            if (!$fsId) {
                throw new InvalidArgumentException(
                    sprintf('%sId is required when scheduling %s events.', ucfirst($type), $type)
                );
            }

            // Create a full screen layout for this event
            $fsLayout = $this->layoutFactory->createFullScreenLayout(
                $type,
                $fsId,
                $sanitizedParams->getInt('resolutionId'),
                $sanitizedParams->getString('backgroundColor'),
                $sanitizedParams->getInt('layoutDuration'),
            );

            $schedule->campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($fsLayout->layoutId);
            $schedule->parentCampaignId = $schedule->campaignId;
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

        if ($schedule->isSyncEvent()) {
            $syncGroup = $this->syncGroupFactory->getById($schedule->syncGroupId);
            $syncGroup->validateForSchedule($sanitizedParams);
            $schedule->updateSyncLinks($syncGroup, $sanitizedParams);
        }

        // Get form reminders
        $rows = [];
        for ($i=0; $i < count($sanitizedParams->getIntArray('reminder_value', ['default' => []])); $i++) {
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
                $rows[$i]['reminder_scheduleReminderId'] = isset($reminder['reminder_scheduleReminderId'])
                    ? (int) $reminder['reminder_scheduleReminderId']
                    : null;
                $rows[$i]['reminder_value'] = (int) $reminder['reminder_value'];
                $rows[$i]['reminder_type'] = (int) $reminder['reminder_type'];
                $rows[$i]['reminder_option'] = (int) $reminder['reminder_option'];
                $rows[$i]['reminder_isEmailHidden'] = (int) $reminder['reminder_isEmailHidden'];
            }
        } else {
            for ($i=0; $i < count($sanitizedParams->getIntArray('reminder_value', ['default' => []])); $i++) {
                $rows[$i]['reminder_scheduleReminderId'] =
                    $sanitizedParams->getIntArray('reminder_scheduleReminderId')[$i];
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

            $scheduleReminderId = $reminder['reminder_scheduleReminderId'] ?? null;

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

        return $response
            ->withStatus(200)
            ->withJson([
                'id' => $schedule->eventId,
                'data' => $schedule,
            ]);
    }

    #[OA\Delete(
        path: '/schedule/{eventId}',
        operationId: 'scheduleDelete',
        description: 'Delete a Scheduled Event',
        summary: 'Delete Event',
        tags: ['schedule']
    )]
    #[OA\Parameter(
        name: 'eventId',
        description: 'The Scheduled Event ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response, int $id): Response|ResponseInterface
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
        return $response->withStatus(204);
    }

    /**
     * Copy a Schedule Event
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     */
    public function copy(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $originalSchedule = $this->scheduleFactory->getById($id);
        $originalSchedule->load();

        if (!$this->isEventEditable($originalSchedule)) {
            throw new AccessDeniedException();
        }

        $schedule = clone $originalSchedule;
        $schedule->name = $sanitizedParams->getString('name');
        $schedule->userId = $this->getUser()->userId;

        $schedule->setDisplayNotifyService($this->displayFactory->getDisplayNotifyService());

        if ($schedule->campaignId != null) {
            $schedule->setCampaignFactory($this->campaignFactory);
        }

        $schedule->save();

        return $response
            ->withStatus(201)
            ->withJson($schedule);
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

    #[OA\Get(path: '/schedule', operationId: 'scheduleSearch', tags: ['schedule'])]
    #[OA\Parameter(
        name: 'eventTypeId',
        description: 'Filter grid by eventTypeId.
     * 1=Layout, 2=Command, 3=Overlay, 4=Interrupt, 5=Campaign, 6=Action, 7=Media Library, 8=Playlist', // phpcs:ignore
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by Schedule Name or ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'fromDt',
        description: 'From Date in Y-m-d H:i:s format',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'toDt',
        description: 'To Date in Y-m-d H:i:s format',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'geoAware',
        description: 'Flag (0-1), whether to return events using Geo Location',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'recurring',
        description: 'Flag (0-1), whether to return Recurring events',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'campaignId',
        description: 'Filter events by specific campaignId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'displayGroupIds',
        description: 'Filter events by an array of Display Group Ids',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'eventId',
                'eventTypeId',
                'name',
                'fromDt',
                'toDt',
                'campaign',
                'campaignId',
                'shareOfVoice',
                'maxPlaysPerHour',
                'isGeoAware',
                'recurringEvent',
                'recurrenceType',
                'recurrenceDetail',
                'recurrenceRepeatsOn',
                'recurrenceRange',
                'isPriority',
                'criteria',
                'createdOn',
                'updatedOn',
                'modifiedByName',
            ]
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Schedule')
        )
    )]
    /**
     * Generates the Schedule events grid
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
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
                return $response
                    ->withStatus(200)
                    ->withHeader('X-Total-Count', $this->scheduleFactory->countLast())
                    ->withJson([]);
            }
        } else {
            $resolvedDisplayGroupIds = $originalDisplayGroupIds;
        }

        $events = $this->scheduleFactory->query(
            $this->gridRenderSort($params, $this->isJson($request)),
            $this->getScheduleFilters($params, $resolvedDisplayGroupIds)
        );

        foreach ($events as $event) {
            $this->decorateEventProperties($event);
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->scheduleFactory->countLast())
            ->withJson($events);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    #[OA\Get(
        path: '/schedule/{eventId}',
        operationId: 'ScheduleSearchById',
        description: 'Get the Schedule object specified by the provided eventId',
        summary: 'Schedule search by ID',
        tags: ['schedule']
    )]
    #[OA\Parameter(
        name: 'eventId',
        description: 'Numeric ID of the Scheduled Event to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Schedule')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $schedule = $this->scheduleFactory->getById($id, false);
        $this->decorateEventProperties($schedule);

        if (!$this->getUser()->isSuperAdmin()) {
            foreach ($schedule->displayGroups as $displayGroup) {
                if (!$this->getUser()->checkViewable($displayGroup)) {
                    throw new AccessDeniedException();
                }
            }
        }

        if ($schedule->isFullScreenSchedule()) {
            $schedule->setUnmatchedProperty('fullScreenCampaignId', $schedule->campaignId);

            if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$MEDIA_EVENT) {
                $schedule->setUnmatchedProperty(
                    'mediaId',
                    $this->layoutFactory->getLinkedFullScreenMediaId($schedule->campaignId)
                );
            } else {
                $schedule->setUnmatchedProperty(
                    'playlistId',
                    $this->layoutFactory->getLinkedFullScreenPlaylistId($schedule->campaignId)
                );
            }

            $fsLayout = $this->layoutFactory->getById(
                $this->campaignFactory->getLinkedLayouts($schedule->campaignId)[0]->layoutId
            );
            $schedule->backgroundColor = $fsLayout->backgroundColor;
            $schedule->layoutDuration = $fsLayout->duration;
            $schedule->resolutionId = $this->layoutFactory->getLayoutResolutionId($fsLayout)->resolutionId;
        }

        return $response
            ->withStatus(200)
            ->withJson($schedule);
    }
    /**
     * @param \Xibo\Entity\Schedule $schedule
     * @param ScheduleReminder $scheduleReminder
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function saveReminder(\Xibo\Entity\Schedule $schedule, ScheduleReminder $scheduleReminder): void
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

    /**
     * @param array $coordinates
     * @return bool|string
     */
    private function createGeoJson(array $coordinates): bool|string
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
     * Get the media filters
     * @param SanitizerInterface $params
     * @param array $resolvedDisplayGroupIds
     * @return array
     */
    private function getScheduleFilters(SanitizerInterface $params, array $resolvedDisplayGroupIds): array
    {
        return $this->gridRenderFilter([
            'eventTypeId' => $params->getInt('eventTypeId'),
            'futureSchedulesFrom' => $params->getDate('fromDt')?->format('U'),
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
        ], $params);
    }

    /**
     * @param \Xibo\Entity\Schedule $event
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private function decorateEventProperties(\Xibo\Entity\Schedule $event): void
    {
        // Grab some settings which determine how events are displayed.
        $showLayoutName = ($this->getConfig()->getSetting('SCHEDULE_SHOW_LAYOUT_NAME') == 1);
        $defaultTimezone = $this->getConfig()->getSetting('defaultTimezone');

        $event->load();

        if (count($event->displayGroups) > 0) {
            $array = array_map(function ($object) {
                return $object->displayGroup;
            }, $event->displayGroups);
            $displayGroupList = implode(', ', $array);
        } else {
            $displayGroupList = '';
        }

        $eventTypes = \Xibo\Entity\Schedule::getEventTypes();
        foreach ($eventTypes as $eventType) {
            if ($eventType['eventTypeId'] === $event->eventTypeId) {
                $event->setUnmatchedProperty('eventTypeName', $eventType['eventTypeName']);
            }
        }

        $event->setUnmatchedProperty('displayGroupList', $displayGroupList);
        $event->setUnmatchedProperty('recurringEvent', !empty($event->recurrenceType));

        if ($event->isSyncEvent()) {
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

            $event->setUnmatchedProperty(
                'scheduleExclusions',
                $this->scheduleExclusionFactory->query(null, ['eventId' => $event->eventId])
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
            $event->fromDt !== null
                ? Carbon::createFromTimestamp($event->fromDt)->format(DateFormatHelper::getSystemFormat())
                : ''
        );
        $event->setUnmatchedProperty(
            'displayToDt',
            $event->toDt !== null
                ? Carbon::createFromTimestamp($event->toDt)->format(DateFormatHelper::getSystemFormat())
                : ''
        );

        $event->setUnmatchedProperty('isEditable', $this->isEventEditable($event));
    }
}
