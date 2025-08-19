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

namespace Xibo\Widget;

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use ICal\ICal;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\DataType\Event;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderNumItemsTrait;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Download and parse an ISC feed
 */
class IcsProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;
    use DurationProviderNumItemsTrait;

    /**
     * Fetch the ISC feed and load its data.
     * @inheritDoc
     */
    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        // Do we have a feed configured?
        $uri = $dataProvider->getProperty('uri');
        if (empty($uri)) {
            throw new InvalidArgumentException(__('Please enter the URI to a valid ICS feed.'), 'uri');
        }

        // Create an ICal helper and pass it the contents of the file.
        $iCalConfig = [
            'replaceWindowsTimeZoneIds' => ($dataProvider->getProperty('replaceWindowsTimeZoneIds', 0) == 1),
            'defaultSpan' => 1,
        ];

        // What event range are we interested in?
        // Decide on the Range we're interested in
        // $iCal->eventsFromInterval only works for future events
        $excludeAllDay = $dataProvider->getProperty('excludeAllDay', 0) == 1;

        $excludePastEvents = $dataProvider->getProperty('excludePast', 0) == 1;

        $startOfDay = match ($dataProvider->getProperty('startIntervalFrom')) {
            'month' => Carbon::now()->startOfMonth(),
            'week' => Carbon::now()->startOfWeek(),
            default => Carbon::now()->startOfDay(),
        };

        // Force timezone of each event?
        $useEventTimezone = $dataProvider->getProperty('useEventTimezone', 1);

        // do we use interval or provided date range?
        if ($dataProvider->getProperty('useDateRange')) {
            $rangeStart = $dataProvider->getProperty('rangeStart');
            $rangeStart = empty($rangeStart)
                ? Carbon::now()->startOfMonth()
                : Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $rangeStart);

            $rangeEnd = $dataProvider->getProperty('rangeEnd');
            $rangeEnd = empty($rangeEnd)
                ? Carbon::now()->endOfMonth()
                : Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $rangeEnd);
        } else {
            $interval = $dataProvider->getProperty('customInterval');
            $rangeStart = $startOfDay->copy();
            $rangeEnd = $rangeStart->copy()->add(
                \DateInterval::createFromDateString(empty($interval) ? '1 week' : $interval)
            );
        }

        $this->getLog()->debug('fetchData: final range, start=' . $rangeStart->toAtomString()
            . ', end=' . $rangeEnd->toAtomString());

        // Set up fuzzy filtering supported by the ICal library. This is included for performance.
        // https://github.com/u01jmg3/ics-parser?tab=readme-ov-file#variables
        $iCalConfig['filterDaysBefore'] = $rangeStart->diffInDays(Carbon::now(), false) + 2;
        $iCalConfig['filterDaysAfter'] = $rangeEnd->diffInDays(Carbon::now()) + 2;

        $this->getLog()->debug('Range start: ' . $rangeStart->toDateTimeString()
            . ', range end: ' . $rangeEnd->toDateTimeString()
            . ', config: ' . var_export($iCalConfig, true));

        try {
            $iCal = new ICal(false, $iCalConfig);
            $iCal->initString($this->downloadIcs($uri, $dataProvider));

            $this->getLog()->debug('Feed initialised');

            // Before we parse anything - should we use the calendar timezone as a base for our calculations?
            if ($dataProvider->getProperty('useCalendarTimezone') == 1) {
                $iCal->defaultTimeZone = $iCal->calendarTimeZone();
            }

            $this->getLog()->debug('Calendar timezone set to: ' . $iCal->defaultTimeZone);

            // Get an array of events
            /** @var \ICal\Event[] $events */
            $events = $iCal->eventsFromRange($rangeStart, $rangeEnd);

            // Go through each event returned
            foreach ($events as $event) {
                try {
                    // Parse the ICal Event into our own data type object.
                    $entry = new Event();
                    $entry->summary = $event->summary;
                    $entry->description = $event->description;
                    $entry->location = $event->location;

                    // Parse out the start/end dates.
                    if ($useEventTimezone === 1) {
                        // Use the timezone from the event.
                        $entry->startDate = Carbon::instance($iCal->iCalDateToDateTime($event->dtstart_array[3]));
                        $entry->endDate = Carbon::instance($iCal->iCalDateToDateTime($event->dtend_array[3]));
                    } else {
                        // Use the parser calculated timezone shift
                        $entry->startDate = Carbon::instance($iCal->iCalDateToDateTime($event->dtstart_tz));
                        $entry->endDate = Carbon::instance($iCal->iCalDateToDateTime($event->dtend_tz));
                    }

                    $this->getLog()->debug('Event: ' . $event->summary . ' with '
                        . $entry->startDate->format('c') . ' / ' . $entry->endDate->format('c'));

                    // Detect all day event
                    $isAllDay = false;

                    // If dtstart has value DATE
                    // (following RFC recommendations in https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.4 )
                    if (isset($event->dtstart_array[0])) {
                        // If it's a string
                        if (is_string($event->dtstart_array[0]) && strtoupper($event->dtstart_array[0]) === 'DATE') {
                            $isAllDay = true;
                        }

                        // If it's an array
                        if (is_array($event->dtstart_array[0]) &&
                            isset($event->dtstart_array[0]['VALUE']) &&
                            strtoupper($event->dtstart_array[0]['VALUE']) === 'DATE') {
                            $isAllDay = true;
                        }
                    }

                    // If MS extension flags it as all day
                    if (isset($event->x_microsoft_cdo_alldayevent) &&
                        is_string($event->x_microsoft_cdo_alldayevent) &&
                        strtoupper($event->x_microsoft_cdo_alldayevent) === 'TRUE') {
                        $isAllDay = true;
                    }

                    // Fallback: If both times are midnight and event is more than one day
                    if (!$isAllDay) {
                        $startAtMidnight = $entry->startDate->isStartOfDay();
                        $endsAtMidnight = $entry->endDate->isStartOfDay();
                        $diffDays = $entry->endDate->copy()->startOfDay()->diffInDays(
                            $entry->startDate->copy()->startOfDay()
                        );

                        $isAllDay = $startAtMidnight && $endsAtMidnight && $diffDays >= 1;
                    }

                    $entry->isAllDay = $isAllDay;

                    if ($excludeAllDay && $isAllDay) {
                        continue;
                    }

                    if ($excludePastEvents && $entry->endDate->isPast()) {
                        continue;
                    }

                    $dataProvider->addItem($entry);
                } catch (\Exception $exception) {
                    $this->getLog()->error('Unable to parse event. ' . var_export($event, true));
                }
            }

            $dataProvider->setCacheTtl($dataProvider->getProperty('updateInterval', 60) * 60);
            $dataProvider->setIsHandled();
        } catch (\Exception $exception) {
            $this->getLog()->error('iscProvider: fetchData: ' . $exception->getMessage());
            $this->getLog()->debug($exception->getTraceAsString());

            $dataProvider->addError(__('The iCal provided is not valid, please choose a valid feed'));
        }
        return $this;
    }

    public function getDataCacheKey(DataProviderInterface $dataProvider): ?string
    {
        // No special cache key requirements.
        return null;
    }

    /**
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function downloadIcs(string $uri, DataProviderInterface $dataProvider): string
    {
        // See if we have this ICS cached already.
        $cache = $dataProvider->getPool()->getItem('/widget/' . $dataProvider->getDataType() . '/' . md5($uri));
        $ics = $cache->get();

        if ($cache->isMiss() || $ics === null) {
            // Make a new request.
            $this->getLog()->debug('downloadIcs: cache miss');

            try {
                // Create a Guzzle Client to get the Feed XML
                $response = $dataProvider
                    ->getGuzzleClient([
                        'timeout' => 20, // wait no more than 20 seconds
                    ])
                    ->get($uri);

                $ics = $response->getBody()->getContents();

                // Save the resonse to cache
                $cache->set($ics);
                $cache->expiresAfter($dataProvider->getSetting('cachePeriod', 1440) * 60);
                $dataProvider->getPool()->saveDeferred($cache);
            } catch (RequestException $requestException) {
                // Log and return empty?
                $this->getLog()->error('downloadIcs: Unable to get feed: ' . $requestException->getMessage());
                $this->getLog()->debug($requestException->getTraceAsString());

                throw new ConfigurationException(__('Unable to download feed'));
            }
        } else {
            $this->getLog()->debug('downloadIcs: cache hit');
        }

        return $ics;
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        return null;
    }
}
