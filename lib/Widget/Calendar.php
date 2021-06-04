<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

namespace Xibo\Widget;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use ICal\ICal;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Invalidation;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Calendar
 * @package Xibo\Widget
 */
class Calendar extends ModuleWidget
{
    const CALENDAR_TYPES = array(
        'custom',
        'schedule',
        'daily',
        'weekly',
        'monthly'
    );

    /** @inheritdoc */
    public function init()
    {
        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }

    /** @inheritdoc */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Calendar';
            $module->type = 'calendar';
            $module->class = '\Xibo\Widget\Calendar';
            $module->description = 'Display content from a Calendar';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = 1;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->installName = 'calendar';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /** @inheritdoc */
    public function installFiles()
    {
        // Extends parent's method
        parent::installFiles();

        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-calendar-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
    }

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        return 'calendar-designer-javascript';
    }

    /**
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?calendar",
     *  operationId="widgetCalendarEdit",
     *  tags={"widget"},
     *  summary="Edit a Calendar Widget",
     *  description="Edit a Calendar Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Widget Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="uri",
     *      in="formData",
     *      description="The Link for the iCal Feed",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="noEventsMessage",
     *      in="formData",
     *      description="No upcoming events message",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="customInterval",
     *      in="formData",
     *      description="Using natural language enter a string representing the period for which events should be returned, for example 2 days or 1 week.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="excludeAllDay",
     *      in="formData",
     *      description="A flag (0, 1), Exclude all day events from results?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="updateInterval",
     *      in="formData",
     *      description="Update interval in minutes",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useEventTimezone",
     *      in="formData",
     *      description="A flag (0,1), Should we use Event Timezone?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useCalendarTimezone",
     *      in="formData",
     *      description="A flag (0,1), Should we use Calendar Timezone?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="windowsFormatCalendar",
     *      in="formData",
     *      description="Does the calendar feed come from Windows - if unsure leave unselected.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useDateRange",
     *      in="formData",
     *      description="Should we look for events with provided date range?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="rangeStart",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="rangeEnd",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noEventTrigger",
     *      in="formData",
     *      description="Trigger code for a no event action",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="currentEventTrigger",
     *      in="formData",
     *      description="Trigger code for a current event action",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="calendarType",
     *      in="formData",
     *      description="Calendar Type (0:custom ; 1:schedule ; 2:daily ; 3:weekly ; 4:monthly )",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="showHeader",
     *      in="formData",
     *      description="A flag (0, 1), Should the calendar template have a header?",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="showNowMarker",
     *      in="formData",
     *      description="A flag (0, 1), Should the calendar show a marker for the current time?",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="showDescription",
     *      in="formData",
     *      description="A flag (0, 1), Should the event description if exists?",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="timeFormat",
     *      in="formData",
     *      description="Moment time format",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="startTime",
     *      in="formData",
     *      description="The start time for the calendar view",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="endTime",
     *      in="formData",
     *      description="The end time for the calendar view",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="textScale",
     *      in="formData",
     *      description="Scale for the text elements on calendar templates, defaults to 1.",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="weekdayNameLength",
     *      in="formData",
     *      description="Week day name length",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="gridStep",
     *      in="formData",
     *      description="Weekly/Daily grid step in minutes.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="templateTheme",
     *      in="formData",
     *      description="Template color theme dark/light
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="overrideColorTemplate",
     *      in="formData",
     *      description="A flag (0, 1), Should the template colors be overriden?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="gridColor",
     *      in="formData",
     *      description="Colour for the grid between days/hours",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="mainBackgroundColor",
     *      in="formData",
     *      description="Colour for the main template",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="gridTextColor",
     *      in="formData",
     *      description="Colour for the grid text",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dayBgColor",
     *      in="formData",
     *      description="Background colour for day elements on monthly/weekly calendars",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dayTextColor",
     *      in="formData",
     *      description="Text colour for day elements on monthly/weekly calendars",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="todayTextColor",
     *      in="formData",
     *      description="Text colour for current day element on monthly/weekly calendars",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="nowMarkerColor",
     *      in="formData",
     *      description="Background colour for the current time marker",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dayOtherMonthBgColor",
     *      in="formData",
     *      description="Background colour for unfocused month day elements on monthly calendar",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dayOtherMonthTextColor",
     *      in="formData",
     *      description="Text colour for unfocused month day elements on monthly calendar",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="headerBgColor",
     *      in="formData",
     *      description="Background colour for main header",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="headerTextColor",
     *      in="formData",
     *      description="Text colour for main header",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="weekDaysHeaderBgColor",
     *      in="formData",
     *      description="Background colour for week days label on monthly/weekly calendars",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="weekDaysHeaderTextColor",
     *      in="formData",
     *      description="Text colour for week days label on monthly/weekly calendars",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="eventBgColor",
     *      in="formData",
     *      description="Background colour for a single day timed event",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="eventTextColor",
     *      in="formData",
     *      description="Text colour for a single day timed event",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dailyEventBgColor",
     *      in="formData",
     *      description="Background colour for a all day event",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dailyEventTextColor",
     *      in="formData",
     *      description="Text colour for a all day event",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="multiDayEventBgColor",
     *      in="formData",
     *      description="Background colour for a multiple day event",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="multiDayEventTextColor",
     *      in="formData",
     *      description="Text colour for a multiple day event",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="aditionalEventsBgColor",
     *      in="formData",
     *      description="Background colour for the extra element counter label",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="aditionalEventsTextColor",
     *      in="formData",
     *      description="Text colour for the extra element counter label",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noEventsBgColor",
     *      in="formData",
     *      description="Background colour for the no events message",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noEventsTextColor",
     *      in="formData",
     *      description="Text colour for the no events message",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritdoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('uri', urlencode($sanitizedParams->getString('uri')));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('eventLabelNow', $sanitizedParams->getString('eventLabelNow'));
        $this->setOption('calendarType', $sanitizedParams->getInt('calendarType', ['default' => 1]));

        // Template/Configure options
        if ($this->getOption('calendarType') == 1) {
            // Schedule templates
            $this->setOption('customInterval', $sanitizedParams->getString('customInterval', ['defaultOnEmptyString' => true]));
            $this->setOption('noEventsMessage', $sanitizedParams->getString('noEventsMessage', ['defaultOnEmptyString' => true]));
            $this->setOption('noEventsBgColor', $sanitizedParams->getString('noEventsBgColor'));
            $this->setOption('noEventsTextColor', $sanitizedParams->getString('noEventsTextColor'));
            $this->setOption('useDateRange', $sanitizedParams->getCheckbox('useDateRange'));
            $this->setOption('rangeStart', $sanitizedParams->getDate('rangeStart'));
            $this->setOption('rangeEnd', $sanitizedParams->getDate('rangeEnd'));
        }

        // Properties common to schedule, daily, weekly and monthly view
        $this->setOption('showHeader', $sanitizedParams->getCheckbox('showHeader'));
        $this->setOption('showNowMarker', $sanitizedParams->getCheckbox('showNowMarker'));
        $this->setOption('showDescription', $sanitizedParams->getCheckbox('showDescription'));
        $this->setOption('timeFormat', $sanitizedParams->getString('timeFormat', ['defaultOnEmptyString' => true]));
        $this->setOption('startTime', $sanitizedParams->getString('startTime'));
        $this->setOption('endTime', $sanitizedParams->getString('endTime'));
        $this->setOption('textScale', $sanitizedParams->getDouble('textScale'));
        $this->setOption('weekdayNameLength', $sanitizedParams->getString('weekdayNameLength'));
        $this->setOption('gridStep', $sanitizedParams->getInt('gridStep'));
        $this->setOption('templateTheme', $sanitizedParams->getString('templateTheme'));
        $this->setOption('overrideColorTemplate', $sanitizedParams->getCheckbox('overrideColorTemplate'));
        $this->setOption('gridColor', $sanitizedParams->getString('gridColor'));
        $this->setOption('mainBackgroundColor', $sanitizedParams->getString('mainBackgroundColor'));
        $this->setOption('gridTextColor', $sanitizedParams->getString('gridTextColor'));
        $this->setOption('dayBgColor', $sanitizedParams->getString('dayBgColor'));
        $this->setOption('dayTextColor', $sanitizedParams->getString('dayTextColor'));
        $this->setOption('todayTextColor', $sanitizedParams->getString('todayTextColor'));
        $this->setOption('nowMarkerColor', $sanitizedParams->getString('nowMarkerColor'));
        $this->setOption('dayOtherMonthBgColor', $sanitizedParams->getString('dayOtherMonthBgColor'));
        $this->setOption('dayOtherMonthTextColor', $sanitizedParams->getString('dayOtherMonthTextColor'));
        $this->setOption('headerBgColor', $sanitizedParams->getString('headerBgColor'));
        $this->setOption('headerTextColor', $sanitizedParams->getString('headerTextColor'));
        $this->setOption('weekDaysHeaderBgColor', $sanitizedParams->getString('weekDaysHeaderBgColor'));
        $this->setOption('weekDaysHeaderTextColor', $sanitizedParams->getString('weekDaysHeaderTextColor'));
        $this->setOption('eventBgColor', $sanitizedParams->getString('eventBgColor'));
        $this->setOption('eventTextColor', $sanitizedParams->getString('eventTextColor'));
        $this->setOption('dailyEventBgColor', $sanitizedParams->getString('dailyEventBgColor'));
        $this->setOption('dailyEventTextColor', $sanitizedParams->getString('dailyEventTextColor'));
        $this->setOption('multiDayEventBgColor', $sanitizedParams->getString('multiDayEventBgColor'));
        $this->setOption('multiDayEventTextColor', $sanitizedParams->getString('multiDayEventTextColor'));
        $this->setOption('aditionalEventsBgColor', $sanitizedParams->getString('aditionalEventsBgColor'));
        $this->setOption('aditionalEventsTextColor', $sanitizedParams->getString('aditionalEventsTextColor'));
    
        $this->setOption('excludeAllDay', $sanitizedParams->getCheckbox('excludeAllDay'));
        $this->setOption('updateInterval', $sanitizedParams->getInt('updateInterval', ['default' => 120]));

        $this->setOption('useEventTimezone', $sanitizedParams->getCheckbox('useEventTimezone'));
        $this->setOption('useCalendarTimezone', $sanitizedParams->getCheckbox('useCalendarTimezone'));
        $this->setOption('windowsFormatCalendar', $sanitizedParams->getCheckbox('windowsFormatCalendar'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));

        $this->setOption('noEventTrigger', $sanitizedParams->getString('noEventTrigger'));
        $this->setOption('currentEventTrigger', $sanitizedParams->getString('currentEventTrigger'));

        $this->isValid();
        $this->saveWidget();

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function getResource($displayId = 0)
    {
        // Construct the response HTML
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width);

        $calendarType = intval($this->getOption('calendarType', 1));

        $styleSheet = '';
        $mainTemplate = '';

        $calendarTypeName = self::CALENDAR_TYPES[$calendarType];
        $templateFromJSON = $this->getTemplateById($calendarTypeName);

        if (isset($templateFromJSON)) {
            $mainTemplate = $templateFromJSON['template'];
            $styleSheet = $templateFromJSON['css'];
        }

        // Get the feed URL contents from cache or source
        $items = $this->parseFeed($this->getFeed(), $calendarType);

        // Information from the Module
        $duration = $this->getCalculatedDurationForGetResource();
        $takeItemsFrom = $this->getOption('takeItemsFrom', 'start');

        $totalDuration = $duration;

        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- DURATION=' . $totalDuration . ' -->';
        // Replace the head content
        $headContent = '';

        if ($this->getOption('backgroundColor') != '') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' body { background-color: ' . $this->getOption('backgroundColor') . '; }';
            $headContent .= '</style>';
        }

        // Build calendar options
        $calendarOptions = [
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'duration' => $duration,
            'takeItemsFrom' => $takeItemsFrom,
            'calendarType' => $calendarType,
            'showHeader' => $this->getOption('showHeader'),
            'showNowMarker' => $this->getOption('showNowMarker'),
            'showDescription' => $this->getOption('showDescription'),
            'timeFormat' => $this->getOption('timeFormat'),
            'useDateRange' => $this->getOption('useDateRange'),
            'rangeStart' => $this->getOption('rangeStart'),
            'rangeEnd' => $this->getOption('rangeEnd'),
            'startTime' => $this->getOption('startTime'),
            'endTime' => $this->getOption('endTime'),
            'endTime' => $this->getOption('endTime'),
            'noEventsMessage' => $this->getOption('noEventsMessage'),
            'textScale' => $this->getOption('textScale'),
            'weekdayNameLength' => $this->getOption('weekdayNameLength'),
            'gridStep' => $this->getOption('gridStep', 60),
            'gridColor' => $this->getOption('gridColor'),
            'mainBackgroundColor' => $this->getOption('mainBackgroundColor'),
            'gridTextColor' => $this->getOption('gridTextColor'),
            'dayBgColor' => $this->getOption('dayBgColor'),
            'dayTextColor' => $this->getOption('dayTextColor'),
            'todayTextColor' => $this->getOption('todayTextColor'),
            'nowMarkerColor' => $this->getOption('nowMarkerColor'),
            'dayOtherMonthBgColor' => $this->getOption('dayOtherMonthBgColor'),
            'dayOtherMonthTextColor' => $this->getOption('dayOtherMonthTextColor'),
            'headerBgColor' => $this->getOption('headerBgColor'),
            'headerTextColor' => $this->getOption('headerTextColor'),
            'weekDaysHeaderBgColor' => $this->getOption('weekDaysHeaderBgColor'),
            'weekDaysHeaderTextColor' => $this->getOption('weekDaysHeaderTextColor'),
            'eventBgColor' => $this->getOption('eventBgColor'),
            'eventTextColor' => $this->getOption('eventTextColor'),
            'dailyEventBgColor' => $this->getOption('dailyEventBgColor'),
            'dailyEventTextColor' => $this->getOption('dailyEventTextColor'),
            'multiDayEventBgColor' => $this->getOption('multiDayEventBgColor'),
            'multiDayEventTextColor' => $this->getOption('multiDayEventTextColor'),
            'aditionalEventsBgColor' => $this->getOption('aditionalEventsBgColor'),
            'aditionalEventsTextColor' => $this->getOption('aditionalEventsTextColor'),
            'noEventsBgColor' => $this->getOption('noEventsBgColor'),
            'noEventsTextColor' => $this->getOption('noEventsTextColor')
        ];

        // Include some vendor items and javascript
        $this
            ->appendJavaScriptFile('vendor/jquery.min.js')
            ->appendJavaScriptFile('vendor/moment.js')
            ->appendJavaScriptFile('xibo-layout-scaler.js')
            ->appendJavaScriptFile('xibo-calendar-render.js')
            ->appendJavaScriptFile('xibo-image-render.js')
            ->appendJavaScriptFile('xibo-text-render.js')
            ->appendJavaScript('var xiboICTargetId = ' . $this->getWidgetId() . ';')
            ->appendJavaScriptFile('xibo-interactive-control.min.js')
            ->appendJavaScript('xiboIC.lockAllInteractions();')
            ->appendJavaScript('moment.locale("' . Translate::GetJsLocale() . '");')
            ->appendFontCss()
            ->appendCss($headContent)
            ->appendCss($styleSheet)
            ->appendCss(file_get_contents($this->getConfig()->uri('css/client.css', true)))
            ->appendOptions($calendarOptions)
            ->appendJavaScript('
                $(document).ready(function() {
                    var parsedItems = [];
                    var now = moment();
                    var ongoingEvent = false;
                    var noEventTrigger = ' . ($this->getOption('noEventTrigger', '') == '' ? 'false' : ('"' . $this->getOption('noEventTrigger') . '"')) . ';
                    var currentEventTrigger = ' . ($this->getOption('currentEventTrigger', '') == '' ? 'false' : ('"' . $this->getOption('currentEventTrigger'). '"')) . ';

                    // Prepare the items array, sorting it and removing any items that have expired.
                    $.each(items, function(index, element) {
                        // Parse the item and add it to the array if it has not finished yet
                        var startDate = moment(element.startDate);
                        var endDate = moment(element.endDate);
                        
                        // Check if there is an event ongoing
                        ongoingEvent = (startDate.isBefore(now) && endDate.isAfter(now));
                        
                        if (endDate.isAfter(now)) {
                            if (moment(element.startDate).isBefore(now)) {
                                element.currentEvent = true;
                            } else {
                                element.currentEvent = false;
                            }
                        }

                        // Return all elements
                        parsedItems.push(element);
                    });
                
                    $("body").find("img").xiboImageRender(options);
                    $("body").xiboLayoutScaler(options);

                    // Run calendar render
                    $("body").xiboCalendarRender(options, parsedItems);
                    
                    if(ongoingEvent && currentEventTrigger) {
                        // If there is an event now, send the Current Event trigger ( if exists )
                        xiboIC.trigger(currentEventTrigger);
                    } else if(noEventTrigger) {
                        // If there is no event now, send the No Event trigger
                        xiboIC.trigger(noEventTrigger);
                    }
                });
            ')
            ->appendItems($items);

        // Append calendar structure
        $this->appendBody($mainTemplate);

        return $this->finaliseGetResource();
    }

    /**
     * Get the feed from Cache or Source
     * @return bool|string
     * @throws ConfigurationException
     */
    private function getFeed()
    {
        // Create a key to use as a caching key for this item.
        // the rendered feed will be cached, so it is important the key covers all options.
        $feedUrl = urldecode($this->getOption('uri'));

        /** @var \Stash\Item $cache */
        $cache = $this->getPool()->getItem($this->makeCacheKey(md5($feedUrl)));
        $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        $this->getLog()->debug('Calendar ' . $feedUrl . '. Cache key: ' . $cache->getKey());

        // Get the document out of the cache
        $document = $cache->get();

        // Check our cache to see if the key exists
        if ($cache->isMiss()) {
            // Invalid local cache
            $this->getLog()->debug('Cache Miss, getting RSS items');

            // Lock this cache item (120 seconds)
            $cache->lock(120);

            try {
                // Create a Guzzle Client to get the Feed XML
                $client = new Client();
                $response = $client->get($feedUrl, $this->getConfig()->getGuzzleProxy([
                    'timeout' => 20 // wait no more than 20 seconds: https://github.com/xibosignage/xibo/issues/1401
                ]));

                $document = $response->getBody()->getContents();

            } catch (RequestException $requestException) {
                // Log and return empty?
                $this->getLog()->error('Unable to get feed: ' . $requestException->getMessage());
                $this->getLog()->debug($requestException->getTraceAsString());

                throw new ConfigurationException(__('Unable to download feed'));
            }

            // Add this to the cache.
            $cache->set($document);
            $cache->expiresAfter($this->getOption('updateInterval', 360) * 60);

            // Save
            $this->getPool()->saveDeferred($cache);
        } else {
            $this->getLog()->debug('Feed returned from Cache');
        }

        return $document;
    }

    /**
     * Parse the feed into an array of templated items
     * @param $feed
     * @param $calendarType
     * @return array
     * @throws ConfigurationException
     */
    private function parseFeed($feed, $calendarType)
    {
        $items = [];

        // Create an ICal helper and pass it the contents of the file.
        $iCal = new ICal(false, [
            'replaceWindowsTimeZoneIds' => ($this->getOption('replaceWindowsTimeZoneIds', 0) == 1)
        ]);

        try {
            $iCal->initString($feed);
        } catch (\Exception $exception) {
            $this->getLog()->debug($exception->getMessage() . $exception->getTraceAsString());

            throw new ConfigurationException(__('The iCal provided is not valid, please choose a valid feed'));
        }

        // Before we parse anything - should we use the calendar timezone as a base for our calculations?
        if ($this->getOption('useCalendarTimezone') == 1) {
            $iCal->defaultTimeZone = $iCal->calendarTimeZone();
        }

        // Decide on the Range we're interested in
        // $iCal->eventsFromInterval only works for future events
        $excludeAllDay = $this->getOption('excludeAllDay', 0) == 1;

        $startOfDay = Carbon::now()->startOfDay();
        $endOfDay = $startOfDay->copy()->addDay()->startOfDay();

        $this->getLog()->debug('Start of day is ' . $startOfDay->toDateTimeString());
        $this->getLog()->debug('End of day is ' . $endOfDay->toDateTimeString());

        // Force timezone of each event?
        $useEventTimezone = $this->getOption('useEventTimezone', 1);

        // do we use interval or provided date range? ( use also for daily, weekly and monthly types )
        if ($this->getOption('useDateRange') || $calendarType > 1) {
            if ($calendarType == 2) {
                // Daily
                $rangeStart = $startOfDay;
                $rangeEnd = $endOfDay;
            } else if ($calendarType == 3) {
                // Weekly
                $rangeStart = Carbon::now()->startOfWeek();
                $rangeEnd = Carbon::now()->endOfWeek();
            } else if ($calendarType == 4) {
                // Monthly
                $rangeStart = Carbon::now()->startOfMonth();
                $rangeEnd = Carbon::now()->endOfMonth();
            } else {
                $rangeStart = $this->getOption('rangeStart');
                $rangeEnd = $this->getOption('rangeEnd');
            }

            $events = $iCal->eventsFromRange($rangeStart, $rangeEnd);
        } else {
            $events = $iCal->eventsFromInterval($this->getOption('customInterval', '1 week'));
        }

        // Go through each event returned
        foreach ($events as $event) {
            try {
                /** @var \ICal\Event $event */
                $startDt = Carbon::instance($iCal->iCalDateToDateTime($event->dtstart));
                $endDt = Carbon::instance($iCal->iCalDateToDateTime($event->dtend));

                if ($useEventTimezone === 1) {
                    $startDt->setTimezone($iCal->defaultTimeZone);
                    $endDt->setTimezone($iCal->defaultTimeZone);
                }

                $this->getLog()->debug('Event with ' . $startDt->format('c') . ' / ' . $endDt->format('c') . '. diff in days = ' . $endDt->diff($startDt)->days);

                if ($excludeAllDay && ($endDt->diff($startDt)->days >= 1)) {
                    continue;
                }

                // Create basic event element
                $itemToAdd = [
                    'startDate' => $startDt->format('c'),
                    'endDate' => $endDt->format('c'),
                    'item' => $event
                ];

                // Get event properties and add them to the resulting object
                $itemToAdd['summary'] = $event->summary;
                $itemToAdd['description'] = $event->description;
                $itemToAdd['location'] = $event->location;

                // Add item to array
                $items[] = $itemToAdd;
            } catch (\Exception $exception) {
                $this->getLog()->error('Unable to parse event. ' . var_export($event, true));
            }
        }

        return $items;
    }

    /** @inheritdoc */
    public function isValid()
    {
        // Must have a duration
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0) {
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');
        }

        // Validate the URL
        if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri')))) {
            throw new InvalidArgumentException(__('Please enter a feed URI containing the events you want to display'), 'uri');
        }

        if ($this->getWidgetId() != null) {
            $customInterval = $this->getOption('customInterval');

            if ($customInterval != '') {
                // Try to create a date interval from it
                $dateInterval = \DateInterval::createFromDateString($customInterval);

                // Use now and add the date interval to it
                $now = Carbon::now();
                $check = $now->copy()->add($dateInterval);

                if ($now->equalTo($check))
                    throw new InvalidArgumentException(__('That is not a valid date interval, please use natural language such as 1 week'), 'customInterval');

            }
        }

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        return $this->getOption('updateInterval', 120) * 60;
    }

    /** @inheritdoc */
    public function getLockKey()
    {
        // Make sure we lock for the entire iCal URI to prevent any clashes
        return md5(urldecode($this->getOption('uri')));
    }
}