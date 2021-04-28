/**
 * Copyright (C) 2021 Xibo Signage Ltd
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
jQuery.fn.extend({
    xiboCalendarRender: function (options, events) {
        var $self = $(this);

        // Default options
        var defaults = {
            duration: '30',
            previewWidth: 0,
            previewHeight: 0,
            scaleOverride: 0,
        };

        options = $.extend({}, defaults, options);

        var dbgStart = moment();

        if (options.calendarType == 0) {
            // Custom calendar, do nothing
            return false;
        } else if (options.calendarType == 1) {
            console.log('Agenda View');
        } else if (options.calendarType == 2) {
            console.log('Daily View');
        } else if (options.calendarType == 3) {
            console.log('Weekly View');
        } else if (options.calendarType == 4) {
            console.log('Monthly View');

            var WEEKDAYS = moment.weekdays(true);
            var TODAY = moment();

            var INITIAL_YEAR = moment().year();

            // NOTE: month format for momentjs is 1-12 and month value is zero indexed
            var INITIAL_MONTH = moment().month();

            var maxEventPerDay;
            var maxEventPerDayWithExtra;
            var singleEventHeight;
            var extraEventsHeight;

            var currentMonthDays;
            var previousMonthDays;
            var nextMonthDays;

            var $daysOfWeekElement = $('#daysOfWeek');

            WEEKDAYS.forEach((weekday) => {
                var $weekDayElement = $('<li>');
                $daysOfWeekElement.append($weekDayElement);
                $weekDayElement.html(weekday);
            });

            console.log('P1:' + moment().diff(dbgStart, 'ms'));

            applyStyleOptions(options);

            console.log('P2:' + moment().diff(dbgStart, 'ms'));

            createCalendar();

            console.log('P3:' + moment().diff(dbgStart, 'ms'));

            addEventsToCalendar(events);

            console.log('P4:' + moment().diff(dbgStart, 'ms'));

            function applyStyleOptions(options) {
                $('#content').toggleClass('hide-header', !options.showHeader);

                options.textScale &&
                    $(':root').css('font-size', 16 * options.textScale);

                options.gridColor &&
                    $(':root').css('--grid-color', options.gridColor);

                options.dayBgColor &&
                    $(':root').css('--day-bg-color', options.dayBgColor);
                options.dayTextColor &&
                    $(':root').css('--day-text-color', options.dayTextColor);

                options.dayOtherMonthBgColor &&
                    $(':root').css('--day-other-month-bg-color', options.dayOtherMonthBgColor);
                options.dayOtherMonthTextColor &&
                    $(':root').css('--day-other-month-text-color', options.dayOtherMonthTextColor);

                options.headerBgColor &&
                    $(':root').css('--header-bg-color', options.headerBgColor);
                options.headerTextColor &&
                    $(':root').css('--header-text-color', options.headerTextColor);

                options.weekDaysHeaderBgColor &&
                    $(':root').css('--weekdays-bg-color', options.weekDaysHeaderBgColor);
                options.weekDaysHeaderTextColor &&
                    $(':root').css('--weekdays-text-color', options.weekDaysHeaderTextColor);

                options.eventBgColor &&
                    $(':root').css('--event-bg-color', options.eventBgColor);
                options.eventTextColor &&
                    $(':root').css('--event-text-color', options.eventTextColor);

                options.dailyEventBgColor &&
                    $(':root').css('--daily-event-bg-color', options.dailyEventBgColor);
                options.dailyEventTextColor &&
                    $(':root').css('--daily-event-text-color', options.dailyEventTextColor);

                options.multiDayEventBgColor &&
                    $(':root').css('--multi-day-event-bg-color', options.multiDayEventBgColor);
                options.multiDayEventTextColor &&
                    $(':root').css('--multi-day-event-text-color', options.multiDayEventTextColor);

                options.aditionalEventsBgColor &&
                    $(':root').css('--aditional-events-bg-color', options.aditionalEventsBgColor);
                options.aditionalEventsTextColor &&
                    $(':root').css(
                        '--aditional-events-text-color',
                        options.aditionalEventsTextColor
                    );
            }

            function createCalendar(year = INITIAL_YEAR, month = INITIAL_MONTH) {
                var $calendarDaysElement = $('#calendarDays');
                year = year || INITIAL_YEAR;
                month = month || INITIAL_MONTH;

                $('#selectedMonth').html(
                    moment({
                        year: year,
                        month: month,
                    }).format('MMMM YYYY')
                );

                // Remove all days
                $calendarDaysElement.find('.calendar-day').remove();

                currentMonthDays = createDaysForCurrentMonth(
                    year,
                    month,
                    moment({
                        year: year,
                        month: month,
                    }).daysInMonth()
                );

                previousMonthDays = createDaysForPreviousMonth(year, month - 1);
                nextMonthDays = createDaysForNextMonth(year, month + 1);

                var days = [...previousMonthDays, ...currentMonthDays, ...nextMonthDays];

                days.forEach((day) => {
                    appendDay(day, $calendarDaysElement);
                });
            }

            function appendDay(day, $calendarDaysElement) {
                var $dayElement = $('<li id="day_' + day.month + '_' + day.dayOfMonth + '">');
                $dayElement.addClass('calendar-day');

                // Append events container
                var $eventsContainer = $('<div class="calendar-events-container">');
                $dayElement.append($eventsContainer);

                // Append day
                var $dayOfMonthElement = $('<span class="date">');
                $dayOfMonthElement.html(day.dayOfMonth);
                $dayElement.append($dayOfMonthElement);

                $calendarDaysElement.append($dayElement);

                if (!day.isCurrentMonth) {
                    $dayElement.addClass('calendar-day--not-current');
                }

                if (day.date === moment(TODAY).format('YYYY-MM-DD')) {
                    $dayElement.addClass('calendar-day--today');
                }
            }

            function getNumberOfDaysInMonth(year, month) {
                return moment({
                    year: year,
                    month: month,
                }).daysInMonth();
            }

            function createDaysForCurrentMonth(year, month) {
                return [...Array(getNumberOfDaysInMonth(year, month))].map((day, index) => {
                    return {
                        date: moment({
                            year: year,
                            month: month,
                            day: index + 1,
                        }).format('YYYY-MM-DD'),
                        dayOfMonth: index + 1,
                        month: month,
                        isCurrentMonth: true,
                    };
                });
            }

            function createDaysForPreviousMonth(year, month) {
                var firstDayOfTheMonthWeekday = getWeekday(currentMonthDays[0].date);

                var previousMonth = moment({
                    year: year,
                    month: month - 1,
                });

                // Cover first day of the month being sunday (firstDayOfTheMonthWeekday === 0)
                var visibleNumberOfDaysFromPreviousMonth = firstDayOfTheMonthWeekday
                    ? firstDayOfTheMonthWeekday - 1
                    : 6;

                var previousMonthLastMondayDayOfMonth = moment(currentMonthDays[0].date)
                    .subtract(visibleNumberOfDaysFromPreviousMonth, 'day')
                    .date();

                return [...Array(visibleNumberOfDaysFromPreviousMonth)].map((day, index) => {
                    return {
                        date: moment({
                            year: previousMonth.year(),
                            month: previousMonth.month(),
                            day: previousMonthLastMondayDayOfMonth + index,
                        }).format('YYYY-MM-DD'),
                        month: previousMonth.month(),
                        dayOfMonth: previousMonthLastMondayDayOfMonth + index,
                        isCurrentMonth: false,
                    };
                });
            }

            function createDaysForNextMonth(year, month) {
                var lastDayOfTheMonthWeekday = getWeekday({
                    year: year,
                    month: month - 1,
                    date: currentMonthDays.length,
                });

                var nextMonth = moment({
                    year: year,
                    month: month,
                });

                var visibleNumberOfDaysFromNextMonth = lastDayOfTheMonthWeekday
                    ? 7 - lastDayOfTheMonthWeekday
                    : lastDayOfTheMonthWeekday;

                return [...Array(visibleNumberOfDaysFromNextMonth)].map((day, index) => {
                    return {
                        date: moment({
                            year: nextMonth.year(),
                            month: nextMonth.month(),
                            day: index + 1,
                        }).format('YYYY-MM-DD'),
                        month: nextMonth.month(),
                        dayOfMonth: index + 1,
                        isCurrentMonth: false,
                    };
                });
            }

            function addEventsToCalendar(events) {
                events.forEach((event) => {
                    var startDate = moment(event.startDate).startOf('date');

                    // Check if event is an all day (startDate 00:00 day 1, endDate 00:00 day after last day)
                    var allDayEvent =
                        moment(event.startDate).isSame(startDate) &&
                        moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
                    event.allDay = allDayEvent;

                    var endDate = allDayEvent
                        ? moment(event.endDate).startOf('date').subtract(1, 'd')
                        : moment(event.endDate).startOf('date');

                    var eventTotalDays = endDate.diff(startDate, 'days') + 1;
                    var currentDayOfEvent = 1;

                    // Days loop
                    var momentAux = moment(startDate);
                    while (momentAux <= endDate) {
                        addEventToDay(momentAux, event, eventTotalDays, currentDayOfEvent);
                        currentDayOfEvent++;
                        momentAux.add(1, 'd');
                    }
                });
            }

            function addEventToDay(date, event, eventTotalDays, currentDayOfEvent) {
                var getEventContainer = function (date) {
                    return $('#day_' + date.month() + '_' + date.date()).find(
                        '.calendar-events-container'
                    );
                };

                var updateContainerExtraEvents = function ($container, slots) {
                    // Calculate max events per container (if not defined)
                    if (!maxEventPerDay) {
                        var bodyFontSize = parseInt($('body').css('font-size').split('px')[0]);
                        singleEventHeight =
                            bodyFontSize * 1.25 + bodyFontSize * 0.5 + bodyFontSize * 0.125;
                        extraEventsHeight = bodyFontSize * 1.25 + bodyFontSize * 0.125;
                        maxEventPerDay = Math.floor($container.height() / singleEventHeight);

                        maxEventPerDayWithExtra = Math.floor(
                            ($container.height() - extraEventsHeight) / singleEventHeight
                        );
                    }

                    // If we show extra. get number of events that fit inside that space ( smaller )
                    var maxEventsForThisDay = maxEventPerDay;
                    if (slots.length > maxEventPerDay) {
                        maxEventsForThisDay = maxEventPerDayWithExtra;
                    }

                    // Get number of dummy events that were generated by extended events
                    var numberOfExtendedEvents = slots.filter((ev) => ev == 2).length;

                    // Remove extra elements
                    if (maxEventsForThisDay > 0 && maxEventsForThisDay > numberOfExtendedEvents) {
                        $container
                            .find(
                                '.calendar-event:gt(' +
                                    (maxEventsForThisDay - numberOfExtendedEvents - 1) +
                                    ')'
                            )
                            .remove();
                    } else {
                        $container.find('.calendar-event').remove();
                    }

                    // Calculate number of events that were hidden
                    var numEventsToHide = 0;
                    for (let index = maxEventsForThisDay; index < slots.length; index++) {
                        if (slots[index] != undefined) {
                            numEventsToHide++;
                        }
                    }

                    // Update extra events label
                    if (numEventsToHide > 0) {
                        if ($container.find('.extra-events').length > 0) {
                            $container.find('.extra-events span').html('+ ' + numEventsToHide);
                        } else {
                            $container.append(
                                '<div class="extra-events"><span>+ ' +
                                    numEventsToHide +
                                    '</span></div>'
                            );
                        }
                    }
                };

                var $newEvent = $('<div class="calendar-event">');
                var timeFormat = options.timeFormat || 'HH:mm';
                var eventDuration = 1;
                var $eventsContainer = getEventContainer(date);
                var weekDay = getWeekday(date);

                // Mark event as an all day
                if (event.allDay) {
                    $newEvent.addClass('all-day');
                }

                if (eventTotalDays > 1) {
                    // Multiple day event
                    var htmlToAdd = '<span class="event-summary">' + event.summary + '</span>';

                    // Mark as multi event
                    $newEvent.addClass('multi-day');

                    // Draw only on the first day of the event or at the beggining of the weeks when it breaks
                    if (currentDayOfEvent == 1 || weekDay == 1) {
                        if (currentDayOfEvent == 1 && !event.allDay) {
                            htmlToAdd =
                                '<span class="event-time">' +
                                moment(event.startDate).format(timeFormat) +
                                '</span>' +
                                htmlToAdd;
                        }

                        // Show event content in multiple days
                        $newEvent.html(htmlToAdd);

                        // Update element duration based on event duration
                        eventDuration = eventTotalDays - (currentDayOfEvent - 1);
                        var remainingDays = 8 - weekDay;
                        if (eventDuration > remainingDays) {
                            eventDuration = remainingDays;
                            $newEvent.addClass('cropped-event-end');
                        }

                        if (currentDayOfEvent > 1) {
                            $newEvent.addClass('cropped-event-start');
                        }
                        $newEvent.css(
                            'width',
                            'calc(' + eventDuration * 100 + '% + ' + eventDuration * 2 + 'px)'
                        );
                    } else {
                        // Multiple event that was extended, no need to be rendered
                        return;
                    }
                } else {
                    // Single day event
                    var htmlToAdd = '<span class="event-summary">' + event.summary + '</span>';

                    // Mark event as an all day
                    if (event.allDay) {
                        $newEvent.addClass('all-day');
                    } else {
                        htmlToAdd =
                            '<span class="event-time">' +
                            moment(event.startDate).format(timeFormat) +
                            '</span>' +
                            htmlToAdd;
                    }

                    // Add inner html
                    $newEvent.html(htmlToAdd);
                }

                // Calculate event slot
                var slots = $eventsContainer.data('slots');

                var daySlot;
                if (slots != undefined) {
                    for (var index = 0; index < slots.length; index++) {
                        var slot = slots[index];
                        if (slot === undefined) {
                            daySlot = index;
                            slots[index] = 1;
                            break;
                        }
                    }

                    if (daySlot === undefined) {
                        daySlot = slots.length;
                        slots.push(1);
                    }
                } else {
                    daySlot = 0;
                    slots = [1];
                }

                $eventsContainer.data('slots', slots);

                // Extend event to the remaining days
                if (eventDuration > 1) {
                    for (var dayAfter = 1; dayAfter < eventDuration; dayAfter++) {
                        var $newContainer = getEventContainer(moment(date).add(dayAfter, 'd'));
                        var dataSlots = $newContainer.data('slots');

                        if (dataSlots === undefined) {
                            dataSlots = [];
                        }

                        dataSlots[daySlot] = 2;
                        $newContainer.data('slots', dataSlots);
                    }
                }

                $newEvent.css('top', 2 + 1.875 * daySlot + 'rem');

                // Append event to container
                $newEvent.appendTo($eventsContainer);

                // Check container height and slots to show number of extra events
                updateContainerExtraEvents($eventsContainer, slots);
            }

            function getWeekday(date) {
                return moment(date).weekday() + 1;
            }
        }
        return $self;
    },
});
