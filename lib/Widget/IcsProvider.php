<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
use GuzzleHttp\Exception\RequestException;
use ICal\ICal;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\DataType\Event;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetProviderTrait;

/**
 * Download and parse an ISC feed
 */
class IcsProvider implements WidgetProviderInterface
{
    use WidgetProviderTrait;

    /**
     * Fetch the ISC feed and load its data.
     * @inheritDoc
     */
    public function fetchData(DataProviderInterface $dataProvider): WidgetProviderInterface
    {
        // Create an ICal helper and pass it the contents of the file.
        $iCalConfig = [
            'replaceWindowsTimeZoneIds' => ($dataProvider->getProperty('replaceWindowsTimeZoneIds', 0) == 1),
            'defaultSpan' => 1,
            'filterDaysBefore' => 5
        ];

        // What event range are we interested in?
        // Decide on the Range we're interested in
        // $iCal->eventsFromInterval only works for future events
        $excludeAllDay = $dataProvider->getProperty('excludeAllDay', 0) == 1;

        $startOfDay = Carbon::now()->startOfDay();
        $endOfDay = $startOfDay->copy()->addDay()->startOfDay();

        $this->getLog()->debug('Start of day is ' . $startOfDay->toDateTimeString());
        $this->getLog()->debug('End of day is ' . $endOfDay->toDateTimeString());

        // Force timezone of each event?
        $useEventTimezone = $dataProvider->getProperty('useEventTimezone', 1);

        // do we use interval or provided date range?
        if ($dataProvider->getProperty('useDateRange')) {
            $rangeStart = Carbon::createFromFormat(
                DateFormatHelper::getSystemFormat(),
                $dataProvider->getProperty('rangeStart')
            );
            $rangeEnd = Carbon::createFromFormat(
                DateFormatHelper::getSystemFormat(),
                $dataProvider->getProperty('rangeEnd')
            );
        } else {
            $rangeStart = $startOfDay->copy();
            $rangeEnd = $rangeStart->copy()->add(
                \DateInterval::createFromDateString($dataProvider->getProperty('customInterval', '1 week'))
            );
        }

        // Get the difference between now and the end range.
        $iCalConfig['filterDaysAfter'] = $startOfDay->diffInDays($rangeEnd) + 2;

        $this->getLog()->debug('Range start: ' . $rangeStart->toDateTimeString()
            . ', range end: ' . $rangeEnd->toDateTimeString()
            . ', config: ' . var_export($iCalConfig, true));

        try {
            $iCal = new ICal(false, $iCalConfig);
            $iCal->initString($this->downloadIsc($dataProvider));

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

                    if ($excludeAllDay && ($entry->endDate->diff($entry->startDate)->days >= 1)) {
                        continue;
                    }

                    $dataProvider->addItem($entry);
                } catch (\Exception $exception) {
                    $this->getLog()->error('Unable to parse event. ' . var_export($event, true));
                }
            }
        } catch (\Exception $exception) {
            $this->getLog()->error($exception->getMessage());
            $this->getLog()->debug($exception->getTraceAsString());

            throw new ConfigurationException(__('The iCal provided is not valid, please choose a valid feed'));
        }

        return $this;
    }

    public function fetchDuration(DurationProviderInterface $durationProvider): WidgetProviderInterface
    {
        // No special duration requirements
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
    private function downloadIsc(DataProviderInterface $dataProvider): string
    {
        $uri = $dataProvider->getProperty('uri');
        if (empty($uri)) {
            throw new InvalidArgumentException('Please enter a the URI to a valid ICS feed.', 'uri');
        }

        try {
            // Create a Guzzle Client to get the Feed XML
            $response = $dataProvider
                ->getGuzzleClient([
                    'timeout' => 20, // wait no more than 20 seconds
                ])
                ->get($uri);

            return $response->getBody()->getContents();
        } catch (RequestException $requestException) {
            // Log and return empty?
            $this->getLog()->error('Unable to get feed: ' . $requestException->getMessage());
            $this->getLog()->debug($requestException->getTraceAsString());

            throw new ConfigurationException(__('Unable to download feed'));
        }
    }

    public function getDataModifiedDt(DataProviderInterface $dataProvider): ?Carbon
    {
        return null;
    }
}
