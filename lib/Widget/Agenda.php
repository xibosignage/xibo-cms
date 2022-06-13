<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
use ICal\ICal;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Agenda
 * @package Xibo\Widget
 */
class Agenda extends ModuleWidget
{
    use IcsTrait;

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
            $module->name = 'Agenda';
            $module->type = 'calendar';
            $module->class = '\Xibo\Widget\Agenda';
            $module->description = 'Display content from an Agenda';
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
     *  path="/playlist/widget/{widgetId}?agenda",
     *  operationId="widgetCalendarEdit",
     *  tags={"widget"},
     *  summary="Edit an Agenda Widget",
     *  description="Edit an Agenda Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="customInterval",
     *      in="formData",
     *      description="Using natural language enter a string representing the period for which events should be returned, for example 2 days or 1 week.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dateFormat",
     *      in="formData",
     *      description="The date format to apply to all dates returned",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="numItems",
     *      in="formData",
     *      description="he number of items you want to display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsPerPage",
     *      in="formData",
     *      description="When in single mode, how many items per page should be shown",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="effect",
     *      in="formData",
     *      description="Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="durationIsPerItem",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per page/item, otherwise the widget duration is divided between the number of pages/items",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsSideBySide",
     *      in="formData",
     *      description="A flag (0, 1), Should items be shown side by side",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useCurrentTemplate",
     *      in="formData",
     *      description="A flag (0, 1), Should current event use different template?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="excludeOnlyCurrent",
     *      in="formData",
     *      description="A flag (0, 1), Exclude no current events results?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="excludeCurrent",
     *      in="formData",
     *      description="A flag (0, 1), Exclude current event from results?",
     *      type="integer",
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
     *      name="template",
     *      in="formData",
     *      description="Template to use",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="template_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="currentEventTemplate",
     *      in="formData",
     *      description="Template to use for current Event",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="currentEventTemplate_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage",
     *      in="formData",
     *      description="Message to show when no notifications are available",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="styleSheet",
     *      in="formData",
     *      description="Optional StyleSheet",
     *      type="string",
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
     *      name="backgroundColor",
     *      in="formData",
     *      description="A HEX color to use as the background color of this widget",
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
        $this->setOption('customInterval', $sanitizedParams->getString('customInterval', ['defaultOnEmptyString' => true]));
        $this->setOption('eventLabelNow', $sanitizedParams->getString('eventLabelNow'));

        // Other options
        $this->setOption('backgroundColor', $sanitizedParams->getString('backgroundColor'));
        $this->setOption('dateFormat', $sanitizedParams->getString('dateFormat', ['defaultOnEmptyString' => true]));
        $this->setOption('numItems', $sanitizedParams->getInt('numItems'));
        $this->setOption('itemsPerPage', $sanitizedParams->getInt('itemsPerPage'));
        $this->setOption('effect', $sanitizedParams->getString('effect'));
        $this->setOption('durationIsPerItem', $sanitizedParams->getCheckbox('durationIsPerItem'));
        $this->setOption('itemsSideBySide', $sanitizedParams->getCheckbox('itemsSideBySide'));
        $this->setOption('useCurrentTemplate', $sanitizedParams->getCheckbox('useCurrentTemplate'));

        $this->setOption('excludeOnlyCurrent', $sanitizedParams->getCheckbox('excludeOnlyCurrent'));
        $this->setOption('excludeCurrent', $sanitizedParams->getCheckbox('excludeCurrent'));
        $this->setOption('excludeAllDay', $sanitizedParams->getCheckbox('excludeAllDay'));
        $this->setOption('updateInterval', $sanitizedParams->getInt('updateInterval', ['default' => 120]));

        $this->setRawNode('template', $request->getParam('template', null));
        $this->setOption('template_advanced', $sanitizedParams->getCheckbox('template_advanced'));

        $this->setRawNode('currentEventTemplate', $request->getParam('currentEventTemplate', null));
        $this->setOption('currentEventTemplate_advanced', $sanitizedParams->getCheckbox('currentEventTemplate_advanced'));

        $this->setRawNode('noDataMessage', $request->getParam('noDataMessage', $request->getParam('noDataMessage', null)));
        $this->setOption('noDataMessage_advanced', $sanitizedParams->getCheckbox('noDataMessage_advanced'));

        $this->setRawNode('styleSheet', $request->getParam('styleSheet', $request->getParam('styleSheet', null)));

        $this->setOption('useEventTimezone', $sanitizedParams->getCheckbox('useEventTimezone'));
        $this->setOption('useCalendarTimezone', $sanitizedParams->getCheckbox('useCalendarTimezone'));
        $this->setOption('windowsFormatCalendar', $sanitizedParams->getCheckbox('windowsFormatCalendar'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));

        $this->setOption('useDateRange', $sanitizedParams->getCheckbox('useDateRange'));
        $this->setOption('rangeStart', $sanitizedParams->getDate('rangeStart'));
        $this->setOption('rangeEnd', $sanitizedParams->getDate('rangeEnd'));

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
        // Have we configured our feed yet?
        if (empty($this->getOption('uri'))) {
            throw new ConfigurationException(__('Configure your feed'));
        }

        // Construct the response HTML
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width);

        // Get the template and start making the body
        $template = $this->getRawNode('template', '');
        $currentEventTemplate = ($this->getOption('useCurrentTemplate') == 1) ? $this->getRawNode('currentEventTemplate', '') : null;
        $styleSheet = $this->getRawNode('styleSheet', '');

        // Parse library references first as its more efficient
        $template = $this->parseLibraryReferences($this->isPreview(), $template);
        $currentEventTemplate = $this->parseLibraryReferences($this->isPreview(), $currentEventTemplate);
        $styleSheet = $this->parseLibraryReferences($this->isPreview(), $styleSheet);

        // Get the feed URL contents from cache or source
        $items = $this->parseFeed($this->getFeed(), $template, $currentEventTemplate);

        // Do we have a no-data message to display?
        $noDataMessage = $this->getRawNode('noDataMessage');

        // Return no data message as the last element ( removed after JS event filtering )
        if ($noDataMessage != '') {
            $items[] = [
                'startDate' => 0,
                'endDate' => Carbon::now()->addYear()->format('c'),
                'item' => $noDataMessage,
                'currentEventItem' => $noDataMessage,
                'noDataMessage' => 1
            ];
        } else {
            $this->getLog()->error('Request failed for Widget=' . $this->getWidgetId() . '. Due to No Records Found');
            return '';
        }

        // Information from the Module
        $itemsSideBySide = $this->getOption('itemsSideBySide', 0);
        $duration = $this->getCalculatedDurationForGetResource();
        $durationIsPerItem = $this->getOption('durationIsPerItem', 1);
        $numItems = $this->getOption('numItems', 0);
        $takeItemsFrom = $this->getOption('takeItemsFrom', 'start');
        $itemsPerPage = $this->getOption('itemsPerPage', 0);
        $effect = $this->getOption('effect', 'none');

        // Work out how many pages we will be showing.
        $pages = $numItems;
        if ($numItems > count($items) || $numItems == 0) {
            $pages = count($items);
        }

        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);

        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';
        // Replace the head content
        $headContent = '';

        if ($itemsSideBySide == 1) {
            $headContent .= '<style type="text/css">';
            $headContent .= ' .item, .page { float: left; }';
            $headContent .= '</style>';
        }

        if ($this->getOption('backgroundColor') != '') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' body { background-color: ' . $this->getOption('backgroundColor') . '; }';
            $headContent .= '</style>';
        }

        // Include some vendor items and javascript
        $this
            ->appendJavaScriptFile('vendor/jquery.min.js')
            ->appendJavaScriptFile('vendor/moment.js')
            ->appendJavaScriptFile('xibo-layout-scaler.js')
            ->appendJavaScriptFile('xibo-image-render.js')
            ->appendJavaScriptFile('xibo-text-render.js')
            ->appendJavaScript('var xiboICTargetId = ' . $this->getWidgetId() . ';')
            ->appendJavaScriptFile('xibo-interactive-control.min.js')
            ->appendJavaScript('xiboIC.lockAllInteractions();')
            ->appendFontCss()
            ->appendCss($headContent)
            ->appendCss($styleSheet)
            ->appendCss(file_get_contents($this->getConfig()->uri('css/client.css', true)))
            ->appendOptions([
                'originalWidth' => $this->region->width,
                'originalHeight' => $this->region->height,
                'fx' => $effect,
                'duration' => $duration,
                'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true),
                'numItems' => $numItems,
                'takeItemsFrom' => $takeItemsFrom,
                'itemsPerPage' => $itemsPerPage
            ])
            ->appendJavaScript('
                $(document).ready(function() {
                    var showOnlyCurrent = ' . ($this->getOption('excludeOnlyCurrent', 0) == 0 ? 'false' : 'true') . ';
                    var excludeCurrent = ' . ($this->getOption('excludeCurrent', 0) == 0 ? 'false' : 'true') . ';
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
                        
                        // If its the no data message element and the item array already have some elements
                        // -> Skip that last element
                        if(parsedItems.length > 0 && element.noDataMessage === 1) {
                            return true;
                        }

                        // if element is the first one and its data message, add it to be shown and skip the rest
                        if(index === 0 && element.noDataMessage === 1) {
                            parsedItems.push(element.item);
                            return true;
                        }

                        // Check if there is an event ongoing ( set it to true only, if false )
                        if (!ongoingEvent) {   
                            ongoingEvent = (startDate.isBefore(now) && endDate.isAfter(now) && element.noDataMessage != 1);
                        }
                        
                        if (endDate.isAfter(now)) {
                            if (moment(element.startDate).isBefore(now)) {
                                // This is a currently active event - do we want to add or exclude these?
                                if (!excludeCurrent || showOnlyCurrent) {
                                    parsedItems.push(element.currentEventItem);
                                }
                            } else {
                                if (!showOnlyCurrent) {
                                    parsedItems.push(element.item);
                                }
                            }
                        }
                    });
                
                    $("body").find("img").xiboImageRender(options);
                    $("body").xiboLayoutScaler(options);
                    
                    const runOnVisible = function() { $("#content").xiboTextRender(options, parsedItems); };
                    (xiboIC.checkVisible()) ? runOnVisible() : xiboIC.addToQueue(runOnVisible);

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

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false) {
            $this->appendJavaScriptFile('vendor/jquery.marquee.min.js');
        }

        // Need the cycle plugin?
        if ($effect != 'none') {
            $this->appendJavaScriptFile('vendor/jquery-cycle-2.1.6.min.js');
        }

        return $this->finaliseGetResource();
    }

    /**
     * Parse the feed into an array of templated items
     * @param $feed
     * @param $template
     * @param $currentEventTemplate
     * @return array
     * @throws ConfigurationException
     */
    private function parseFeed($feed, $template, $currentEventTemplate)
    {
        $items = [];

        // Create an ICal helper and pass it the contents of the file.
        $iCalConfig = [
            'replaceWindowsTimeZoneIds' => ($this->getOption('replaceWindowsTimeZoneIds', 0) == 1),
            'defaultSpan' => 1,
            'filterDaysBefore' => 5
        ];

        // We've got something at least, so prepare the template
        $matches = [];
        preg_match_all('/\[.*?\]/', $template, $matches);

        $currentEventMatches = [];
        if ($currentEventTemplate != '') {
            preg_match_all('/\[.*?\]/', $currentEventTemplate, $currentEventMatches);
        }

        // Get a date format
        $dateFormat = $this->getOption('dateFormat', $this->getConfig()->getSetting('DATE_FORMAT'));

        // Decide on the Range we're interested in
        // $iCal->eventsFromInterval only works for future events
        $excludeAllDay = $this->getOption('excludeAllDay', 0) == 1;

        $startOfDay = Carbon::now()->startOfDay();
        $endOfDay = $startOfDay->copy()->addDay()->startOfDay();

        $this->getLog()->debug('Start of day is ' . $startOfDay->toDateTimeString());
        $this->getLog()->debug('End of day is ' . $endOfDay->toDateTimeString());

        // Force timezone of each event?
        $useEventTimezone = $this->getOption('useEventTimezone', 1);

        // do we use interval or provided date range?
        if ($this->getOption('useDateRange')) {
            $rangeStart = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $this->getOption('rangeStart'));
            $rangeEnd = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $this->getOption('rangeEnd'));
        } else {
            $rangeStart = $startOfDay->copy();
            $rangeEnd = $rangeStart->copy()->add(
                \DateInterval::createFromDateString($this->getOption('customInterval', '1 week'))
            );
        }

        // Get the difference between now and the end range.
        $iCalConfig['filterDaysAfter'] = $startOfDay->diffInDays($rangeEnd) + 2;

        $this->getLog()->debug('Range start: ' . $rangeStart->toDateTimeString()
            . ', range end: ' . $rangeEnd->toDateTimeString()
            . ', config: ' . var_export($iCalConfig, true));

        try {
            $iCal = new ICal(false, $iCalConfig);
            $iCal->initString($feed);

            $this->getLog()->debug('Feed initialised');

            // Before we parse anything - should we use the calendar timezone as a base for our calculations?
            if ($this->getOption('useCalendarTimezone') == 1) {
                $iCal->defaultTimeZone = $iCal->calendarTimeZone();
            }

            $this->getLog()->debug('Calendar timezone set to: ' . $iCal->defaultTimeZone);

            // Get an array of events
            $events = $iCal->eventsFromRange($rangeStart, $rangeEnd);

            // Go through each event returned
            foreach ($events as $event) {
                try {
                    /** @var \ICal\Event $event */
                    if ($useEventTimezone === 1) {
                        // Use the timezone from the event.
                        $startDt = Carbon::instance($iCal->iCalDateToDateTime($event->dtstart_array[3]));
                        $endDt = Carbon::instance($iCal->iCalDateToDateTime($event->dtend_array[3]));
                    } else {
                        // Use the parser calculated timezone shift
                        $startDt = Carbon::instance($iCal->iCalDateToDateTime($event->dtstart_tz));
                        $endDt = Carbon::instance($iCal->iCalDateToDateTime($event->dtend_tz));
                    }

                    $this->getLog()->debug('Event: ' . $event->summary . ' with '
                        . $startDt->format('c') . ' / ' . $endDt->format('c'));

                    if ($excludeAllDay && ($endDt->diff($startDt)->days >= 1)) {
                        continue;
                    }

                    // Substitute for all matches in the template
                    $rowString = $this->substituteForEvent($matches, $template, $startDt, $endDt, $dateFormat, $event);

                    if ($currentEventTemplate != '') {
                        $currentEventRow = $this->substituteForEvent(
                            $currentEventMatches,
                            $currentEventTemplate,
                            $startDt,
                            $endDt,
                            $dateFormat,
                            $event
                        );
                    } else {
                        $currentEventRow = $rowString;
                    }

                    $items[] = [
                        'startDate' => $startDt->format('c'),
                        'endDate' => $endDt->format('c'),
                        'item' => $rowString,
                        'currentEventItem' => $currentEventRow
                    ];
                } catch (\Exception $exception) {
                    $this->getLog()->error('Unable to parse event. ' . var_export($event, true));
                }
            }
        } catch (\Exception $exception) {
            $this->getLog()->error($exception->getMessage());
            $this->getLog()->debug($exception->getTraceAsString());

            throw new ConfigurationException(__('The iCal provided is not valid, please choose a valid feed'));
        }

        return $items;
    }

    /**
     * @param $matches
     * @param $string
     * @param Carbon $startDt
     * @param Carbon $endDt
     * @param $dateFormat
     * @param \ICal\Event $event
     * @return mixed
     */
    private function substituteForEvent($matches, $string, $startDt, $endDt, $dateFormat, $event)
    {
        // Run through all [] substitutes in $matches
        foreach ($matches[0] as $sub) {
            $replace = '';

            // Use the pool of standard tags
            switch ($sub) {
                case '[Name]':
                    $replace = $this->getOption('name');
                    break;

                case '[Summary]':
                    $replace = $event->summary;
                    break;

                case '[Description]':
                    $replace = $event->description;
                    break;

                case '[Location]':
                    $replace = $event->location;
                    break;

                case '[StartDate]':
                    $replace = $startDt->translatedFormat($dateFormat);
                    break;

                case '[EndDate]':
                    $replace = $endDt->translatedFormat($dateFormat);
                    break;
            }

            // custom date formats
            if (strpos($sub, '[StartDate|') !== false) {
                $format = str_replace('[', '', str_replace(']', '', str_replace('[StartDate|', '[', $sub)));
                $replace = $startDt->translatedFormat($format);
            }

            if (strpos($sub, '[EndDate|') !== false) {
                $format = str_replace('[', '', str_replace(']', '', str_replace('[EndDate|', '[', $sub)));
                $replace = $endDt->translatedFormat($format);
            }

            // Substitute the replacement we have found (it might be '')
            $string = str_replace($sub, $replace, $string);
        }

        return $string;
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

    /** @inheritDoc */
    public function hasHtmlEditor()
    {
        return true;
    }

    /** @inheritDoc */
    public function getHtmlWidgetOptions()
    {
        return ['template', 'currentEventTemplate', 'noDataMessage'];
    }
}