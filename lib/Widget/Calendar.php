<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Xibo Signage Ltd
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
 *
 * (Calendar.php)
 */
namespace Xibo\Widget;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use ICal\ICal;
use Jenssegers\Date\Date;
use Respect\Validation\Validator as v;
use Stash\Invalidation;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;

/**
 * Class Calendar
 * @package Xibo\Widget
 */
class Calendar extends ModuleWidget
{
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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
    }

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        return 'calendar-designer-javascript';
    }

    /** @inheritdoc */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('customInterval', $this->getSanitizer()->getString('customInterval'));
        $this->setOption('eventLabelNow', $this->getSanitizer()->getString('eventLabelNow'));

        // Other options
        $this->setOption('dateFormat', $this->getSanitizer()->getString('dateFormat'));
        $this->setOption('numItems', $this->getSanitizer()->getInt('numItems'));
        $this->setOption('itemsPerPage', $this->getSanitizer()->getInt('itemsPerPage'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('durationIsPerItem', $this->getSanitizer()->getCheckbox('durationIsPerItem'));
        $this->setOption('itemsSideBySide', $this->getSanitizer()->getCheckbox('itemsSideBySide'));
        $this->setOption('useCurrentTemplate', $this->getSanitizer()->getCheckbox('useCurrentTemplate'));

        $this->setOption('excludeCurrent', $this->getSanitizer()->getCheckbox('excludeCurrent'));
        $this->setOption('excludeAllDay', $this->getSanitizer()->getCheckbox('excludeAllDay'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 120));

        $this->setRawNode('template', $this->getSanitizer()->getParam('template', null));
        $this->setOption('template_advanced', $this->getSanitizer()->getCheckbox('template_advanced'));

        $this->setRawNode('currentEventTemplate', $this->getSanitizer()->getParam('currentEventTemplate', null));
        $this->setOption('currentEventTemplate_advanced', $this->getSanitizer()->getCheckbox('currentEventTemplate_advanced'));

        $this->setRawNode('noDataMessage', $this->getSanitizer()->getParam('noDataMessage', $this->getSanitizer()->getParam('noDataMessage', null)));
        $this->setOption('noDataMessage_advanced', $this->getSanitizer()->getCheckbox('noDataMessage_advanced'));

        $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', $this->getSanitizer()->getParam('styleSheet', null)));

        $this->setOption('useEventTimezone', $this->getSanitizer()->getCheckbox('useEventTimezone'));
        $this->setOption('useCalendarTimezone', $this->getSanitizer()->getCheckbox('useCalendarTimezone'));
        $this->setOption('windowsFormatCalendar', $this->getSanitizer()->getCheckbox('windowsFormatCalendar'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));

        $this->isValid();
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function getResource($displayId)
    {
        // Construct the response HTML
        $this->initialiseGetResource()->appendViewPortWidth($this->region->width);

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

        // Return empty string if there are no items to show.
        if (count($items) === 0) {
            $this->getLog()->debug('No items returned after parsing the feed, including the noDataMessage');

            // Do we have a no-data message to display?
            $noDataMessage = $this->getRawNode('noDataMessage');

            if ($noDataMessage != '') {
                $items[] = [
                    'startDate' => 0,
                    'endDate' => Date::now()->addYear()->format('c'),
                    'item' => $noDataMessage,
                    'currentEventItem' => $noDataMessage
                ];
            } else {
                $this->getLog()->error('Request failed for Widget=' . $this->getWidgetId() . '. Due to No Records Found');
                return '';
            }
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
        if ($numItems > count($items) || $numItems == 0)
            $pages = count($items);

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
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScriptFile('vendor/moment.js')
            ->appendJavaScriptFile('xibo-layout-scaler.js')
            ->appendJavaScriptFile('xibo-image-render.js')
            ->appendJavaScriptFile('xibo-text-render.js')
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
                    var excludeCurrent = ' . ($this->getOption('excludeCurrent', 0) == 0 ? 'false' : 'true') . ';
                    var parsedItems = [];
                    var now = moment();
                
                    // Prepare the items array, sorting it and removing any items that have expired.
                    $.each(items, function(index, element) {
                        // Parse the item and add it to the array if it has not finished yet
                        var endDate = moment(element.endDate);
                        
                        if (endDate.isAfter(now)) {
                            if (moment(element.startDate).isBefore(now)) {
                                // This is a currently active event - do we want to add or exclude these?
                                if (!excludeCurrent) {
                                    parsedItems.push(element.currentEventItem);
                                }
                            } else {
                                parsedItems.push(element.item);
                            }
                        }
                    });
                
                    $("body").find("img").xiboImageRender(options);
                    $("body").xiboLayoutScaler(options);
                    $("#content").xiboTextRender(options, parsedItems);
                });
            ')
            ->appendItems($items);

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $this->appendJavaScriptFile('vendor/jquery.marquee.min.js');

        // Need the cycle plugin?
        if ($effect != 'none')
            $this->appendJavaScriptFile('vendor/jquery-cycle-2.1.6.min.js');

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
     * @param $template
     * @param $currentEventTemplate
     * @return array
     * @throws ConfigurationException
     */
    private function parseFeed($feed, $template, $currentEventTemplate)
    {
        $items = [];

        // Create an ICal helper and pass it the contents of the file.
        $iCal = new ICal(false, [
            'replaceWindowsTimeZoneIds' => ($this->getSetting('replaceWindowsTimeZoneIds', 0) == 1)
        ]);

        try {
            $iCal->initString($feed);
        } catch (\Exception $exception) {
            $this->getLog()->debug($exception->getMessage() . $exception->getTraceAsString());

            throw new ConfigurationException(__('The iCal provided is not valid, please choose a valid feed'));
        }

        // Before we parse anything - should we use the calendar timezone as a base for our calculations?
        if ($this->getSetting('useCalendarTimezone') == 1) {
            $iCal->defaultTimeZone = $iCal->calendarTimeZone();
        }

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

        $startOfDay = Date::now()->startOfDay();
        $endOfDay = $startOfDay->copy()->addDay()->startOfDay();

        $this->getLog()->debug('Start of day is ' . $startOfDay->toDateTimeString());
        $this->getLog()->debug('End of day is ' . $endOfDay->toDateTimeString());

        // Force timezone of each event?
        $useEventTimezone = $this->getSetting('useEventTimezone', 1);

        // Go through each event returned
        foreach ($iCal->eventsFromInterval($this->getOption('customInterval', '1 week')) as $event) {
            try {
                /** @var \ICal\Event $event */
                $startDt = Date::instance($iCal->iCalDateToDateTime($event->dtstart, $useEventTimezone));
                $endDt = Date::instance($iCal->iCalDateToDateTime($event->dtend, $useEventTimezone));

                $this->getLog()->debug('Event with ' . $startDt->format('c') . ' / ' . $endDt->format('c') . '. diff in days = ' . $endDt->diff($startDt)->days);

                if ($excludeAllDay && ($endDt->diff($startDt)->days >= 1)) {
                    continue;
                }

                // Substitute for all matches in the template
                $rowString = $this->substituteForEvent($matches, $template, $startDt, $endDt, $dateFormat, $event);

                if ($currentEventTemplate != '') {
                    $currentEventRow = $this->substituteForEvent($currentEventMatches, $currentEventTemplate, $startDt,
                        $endDt, $dateFormat, $event);
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

        return $items;
    }

    /**
     * @param $matches
     * @param $string
     * @param Date $startDt
     * @param Date $endDt
     * @param $dateFormat
     * @param $event
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
                    $replace = $startDt->format($dateFormat);
                    break;

                case '[EndDate]':
                    $replace = $endDt->format($dateFormat);
                    break;
            }

            // custom date formats
            if (strpos($sub, '[StartDate|') !== false) {
                $format = str_replace('[', '', str_replace(']', '', str_replace('[StartDate|', '[', $sub)));
                $replace = $startDt->format($format);
            }

            if (strpos($sub, '[EndDate|') !== false) {
                $format = str_replace('[', '', str_replace(']', '', str_replace('[EndDate|', '[', $sub)));
                $replace = $endDt->format($format);
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
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        // Validate the URL
        if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri'))))
            throw new InvalidArgumentException(__('Please enter a feed URI containing the events you want to display'), 'uri');

        if ($this->getWidgetId() != '') {
            $customInterval = $this->getOption('customInterval');

            if ($customInterval != '') {
                // Try to create a date interval from it
                $dateInterval = \DateInterval::createFromDateString($customInterval);

                // Use now and add the date interval to it
                $now = Date::now();
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