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
jQuery.fn.extend({
  xiboCalendarRender: function(options, events) {
    // Default options
    const defaults = {
      duration: '30',
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
      startAtCurrentTime: 1,
    };

    options = $.extend({}, defaults, options);

    // Global constants
    const TODAY = moment();
    const START_DATE = options.startAtCurrentTime || events.length <= 0 ?
      TODAY.clone() :
      moment(events[0].startDate);

    const START_DATE_DAY_START = START_DATE.clone().startOf('day');
    const START_DATE_DAY_END = START_DATE.clone().endOf('day');
    const START_DATE_WEEK_START = START_DATE.clone().startOf('week');
    const START_DATE_WEEK_END = START_DATE.clone().endOf('week');
    const START_DATE_MONTH_START = START_DATE.clone().startOf('month');
    const START_DATE_MONTH_END = START_DATE.clone().endOf('month');

    const INITIAL_YEAR = START_DATE.year();

    // NOTE: month format for momentjs is 1-12 and month value is zero indexed
    const INITIAL_MONTH = START_DATE.month();
    const INITIAL_DATE = START_DATE.date();

    const TIME_FORMAT = options.timeFormat || 'HH:mm';

    const DEFAULT_DAY_START_TIME =
      START_DATE.startOf('day').format(TIME_FORMAT);
    const DEFAULT_DAY_END_TIME =
      START_DATE.endOf('day').format(TIME_FORMAT);

    const GRID_STEP = options.gridStep &&
      options.gridStep > 0 ? options.gridStep : 60;

    const DEFAULT_FONT_SIZE = 16;
    const DEFAULT_FONT_SCALE = options.textScale || 1;

    // Global vars for all calendar types
    let maxEventPerDay;
    let maxEventPerDayWithExtra;

    let weekdaysNames = moment.weekdays(true);
    if (options.weekdayNameLength == 'short') {
      weekdaysNames = moment.weekdaysMin(true);
    } else if (options.weekdayNameLength == 'medium') {
      weekdaysNames = moment.weekdaysShort(true);
    }

    let monthsNames = moment.months();
    if (options.monthNameLength == 'short') {
      monthsNames = moment.monthsShort();
    }

    // Filter events by calendar type.
    // -------------------------------
    const filteredEvents = [];
    $.each(events, function(i, event) {
      // Per calendar type, check that this event fits inside the view.
      if (options.calendarType === 2) {
        // Daily
        if (moment(event.startDate) <= START_DATE_DAY_END &&
          moment(event.endDate) >= START_DATE_DAY_START
        ) {
          filteredEvents.push(event);
        }
      } else if (options.calendarType === 3) {
        // Weekly
        if (moment(event.startDate) <= START_DATE_WEEK_END &&
          moment(event.endDate) >= START_DATE_WEEK_START
        ) {
          filteredEvents.push(event);
        }
      } else if (options.calendarType === 4) {
        // Monthly
        if (moment(event.startDate) <= START_DATE_MONTH_END &&
          moment(event.endDate) >= START_DATE_MONTH_START
        ) {
          filteredEvents.push(event);
        }
      } else {
        filteredEvents.push(event);
      }
    });

    // Main functions to be overriden
    let createCalendar = () => {};
    let addEventsToCalendar = () => {};

    /**
     * Apply style based on options
     */
    function applyStyleOptions() {
      $('body').toggleClass('hide-header', options.showHeader != '1');
      $('body')
        .toggleClass('hide-weekend', options.excludeWeekendDays == '1');

      $(':root').css('font-size', DEFAULT_FONT_SIZE * DEFAULT_FONT_SCALE);

      options.mainBackgroundColor &&
        $(':root').css('--main-background-color', options.mainBackgroundColor);

      options.gridColor && $(':root').css('--grid-color', options.gridColor);
      options.gridTextColor &&
        $(':root').css('--grid-text-color', options.gridTextColor);

      options.dayBgColor &&
        $(':root').css('--day-bg-color', options.dayBgColor);
      options.dayTextColor &&
        $(':root').css('--day-text-color', options.dayTextColor);

      options.todayTextColor &&
        $(':root').css('--today-text-color', options.todayTextColor);

      options.nowMarkerColor &&
        $(':root').css('--now-marker-color', options.nowMarkerColor);

      options.dayOtherMonthBgColor &&
        $(':root').css(
          '--day-other-month-bg-color',
          options.dayOtherMonthBgColor,
        );
      options.dayOtherMonthTextColor &&
        $(':root').css(
          '--day-other-month-text-color',
          options.dayOtherMonthTextColor,
        );

      options.headerBgColor &&
        $(':root').css('--header-bg-color', options.headerBgColor);
      options.headerTextColor &&
        $(':root').css('--header-text-color', options.headerTextColor);

      options.weekDaysHeaderBgColor &&
        $(':root').css('--weekdays-bg-color', options.weekDaysHeaderBgColor);
      options.weekDaysHeaderTextColor &&
        $(':root').css(
          '--weekdays-text-color',
          options.weekDaysHeaderTextColor,
        );

      options.eventBgColor &&
        $(':root').css('--event-bg-color', options.eventBgColor);
      options.eventTextColor &&
        $(':root').css('--event-text-color', options.eventTextColor);

      options.dailyEventBgColor &&
        $(':root').css('--daily-event-bg-color', options.dailyEventBgColor);
      options.dailyEventTextColor &&
        $(':root').css('--daily-event-text-color', options.dailyEventTextColor);

      options.multiDayEventBgColor &&
        $(':root').css(
          '--multi-day-event-bg-color',
          options.multiDayEventBgColor,
        );
      options.multiDayEventTextColor &&
        $(':root').css(
          '--multi-day-event-text-color',
          options.multiDayEventTextColor,
        );

      options.aditionalEventsBgColor &&
        $(':root').css(
          '--aditional-events-bg-color',
          options.aditionalEventsBgColor,
        );
      options.aditionalEventsTextColor &&
        $(':root').css(
          '--aditional-events-text-color',
          options.aditionalEventsTextColor,
        );

      options.noEventsBgColor &&
        $(':root').css(
          '--no-events-bg-color',
          options.noEventsBgColor,
        );
      options.noEventsTextColor &&
        $(':root').css(
          '--no-events-text-color',
          options.noEventsTextColor,
        );
    }

    /**
     * Get week day number by date
     * @param {string} date date string
     * @return {number} week day number
     */
    function getWeekday(date) {
      return moment(date).weekday() + 1;
    }

    /**
     * Create a marker showing current time
     * @param {object} $container target container
     * @param {object} timeData data with start and end dates for the view
     */
    function createNowMarker($container, timeData) {
      const dayViewDuration = timeData.end - timeData.start;
      const $nowMarker = $('<div class="now-marker">');

      const nowTimeInMinutes = moment
        .duration(
          moment(TODAY).diff(
            moment(TODAY).startOf('day'),
          ),
        )
        .as('minutes');

      // Skip if it's not included in the selected delta time view
      if (
        nowTimeInMinutes >= timeData.end ||
        nowTimeInMinutes <= timeData.start
      ) {
        return;
      }

      // Calculate position
      const eventPositionPerc = (
        nowTimeInMinutes / dayViewDuration -
        timeData.start / dayViewDuration
      ) * 100;

      $nowMarker.css(
        'top',
        eventPositionPerc + '%',
      );

      // Append marker to container
      $nowMarker.appendTo($container);
    }

    /**
     * Add events to calendar
     */
    function addEventsToCalendarBase() {
      filteredEvents.forEach((event) => {
        const startDate = moment(event.startDate).startOf('date');

        // Check if event is an all day
        // (startDate 00:00 day 1, endDate 00:00 day after last day)
        const allDayEvent =
          moment(event.startDate).isSame(startDate) &&
          moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
        event.allDay = allDayEvent;

        const endDate = allDayEvent ?
          moment(event.endDate).startOf('date').subtract(1, 'd') :
          moment(event.endDate).startOf('date');

        const eventTotalDays = endDate.diff(startDate, 'days') + 1;
        let currentDayOfEvent = 1;

        // Days loop
        const momentAux = moment(startDate);
        while (momentAux <= endDate) {
          addEventToDay(momentAux, event, eventTotalDays, currentDayOfEvent);
          currentDayOfEvent++;
          momentAux.add(1, 'd');
        }
      });
    }

    /**
       * Add event to specific day
       * @param {object} date  momentjs date
       * @param {object} event
       * @param {number} eventTotalDays
       * @param {number} currentDayOfEvent
       */
    function addEventToDay(date, event, eventTotalDays, currentDayOfEvent) {
      /**
       * Get container by date
       * @param {object} date
       * @return {object} Jquery container
       */
      function getEventContainer(date) {
        return (options.calendarType == 2) ?
          $('.calendar-day .calendar-events-container') :
          $('#day_' + date.date()).find('.calendar-events-container');
      }

      /**
       * Get all days container by date
       * @param {object} date
       * @return {object} Jquery container
       */
      function getAllDayEventsContainer(date) {
        return (options.calendarType == 2) ?
          $('.calendar-day .calendar-all-day-events-container') :
          $('#day_' + date.date()).find('.calendar-all-day-events-container');
      }

      const $newEvent = $('<div class="calendar-event">');
      const weekDay = getWeekday(date);
      let eventDuration = 1;

      // Mark event as an all day
      if (event.allDay) {
        $newEvent.addClass('all-day');
      }

      if (eventTotalDays > 1) {
        // Multiple day event
        let htmlToAdd =
          '<span class="event-summary">' + event.summary + '</span>';

        // Mark as multi event
        $newEvent.addClass('multi-day');

        // Draw only on the first day of the event
        // or at the beggining of the weeks when it breaks
        if (currentDayOfEvent == 1 || weekDay == 1) {
          if (currentDayOfEvent == 1 && !event.allDay) {
            htmlToAdd =
              '<div class="event-time">' +
              moment(event.startDate).format(TIME_FORMAT) +
              '</div>' +
              htmlToAdd;
          }

          // Show event content in multiple days
          $newEvent.html(htmlToAdd);

          // Update element duration based on event duration
          eventDuration = eventTotalDays - (currentDayOfEvent - 1);

          const remainingDays = 8 - weekDay;
          if (eventDuration > remainingDays) {
            eventDuration = remainingDays;
            $newEvent.addClass('cropped-event-end');
          }

          if (currentDayOfEvent > 1) {
            $newEvent.addClass('cropped-event-start');
          }
          $newEvent.css(
            'width',
            'calc(' +
            eventDuration * 100 +
            '% + ' +
            eventDuration * 2 +
            'px)',
          );
        } else {
          // Multiple event that was extended, no need to be rendered
          return;
        }
      } else {
        // Single day event
        let htmlToAdd =
          '<div class="event-summary">' + event.summary + '</div>';

        // Mark event as an all day
        if (event.allDay) {
          $newEvent.addClass('all-day');
        } else {
          htmlToAdd =
            htmlToAdd +
            '<div class="event-time">' +
            moment(event.startDate).format(TIME_FORMAT) +
            ' - ' +
            moment(event.endDate).format(TIME_FORMAT) +
            '</div>';
        }

        // Add inner html
        $newEvent.html(htmlToAdd);
      }

      // All day or multi day events
      if (eventTotalDays > 1 || event.allDay) {
        // If there's at least one daily event
        // enable the container in the calendar view
        $('.calendar-container').addClass('show-all-day-events');

        const $dailyEventsContainer = getAllDayEventsContainer(date);

        // Calculate event slot
        let slots = $dailyEventsContainer.data('slots');
        let daySlot;
        if (slots != undefined) {
          for (let index = 0; index < slots.length; index++) {
            const slot = slots[index];
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

        $dailyEventsContainer.data('slots', slots);

        // Extend event to the remaining days
        if (eventDuration > 1 && options.calendarType != 2) {
          for (let dayAfter = 1; dayAfter < eventDuration; dayAfter++) {
            const $newContainer = getAllDayEventsContainer(
              moment(date).add(dayAfter, 'd'),
            );
            let dataSlots = $newContainer.data('slots');

            if (dataSlots === undefined) {
              dataSlots = [];
            }

            dataSlots[daySlot] = 2;
            $newContainer.data('slots', dataSlots);
          }
        }

        $newEvent.css('top', 1.875 * daySlot + 'rem');

        // Append event to container
        $newEvent.appendTo($dailyEventsContainer);

        // Check container height and slots to show number of extra events
        updateContainerExtraEvents($dailyEventsContainer, slots);
      } else {
        // Daily timed event
        const $eventsContainer = getEventContainer(date);
        const containerData = $('.hour-grid').data();

        const dayViewDuration = containerData.end - containerData.start;
        const eventData = {
          start: moment
            .duration(
              moment(event.startDate).diff(
                moment(event.startDate).startOf('day'),
              ),
            )
            .as('minutes'),
          duration: moment
            .duration(moment(event.endDate).diff(moment(event.startDate)))
            .as('minutes'),
        };

        // Skip event if it's not included in the selected delta time view
        if (
          eventData.start >= containerData.end ||
          eventData.start + eventData.duration <= containerData.start
        ) {
          return;
        }

        // Calculate position
        let eventPositionPerc = (
          eventData.start / dayViewDuration -
          containerData.start / dayViewDuration
        ) * 100;
        let eventHeightAdj = 0;

        // Check if event starts before view time
        if (eventPositionPerc < 0) {
          $newEvent.addClass('before-view');
          eventHeightAdj = eventPositionPerc;
          eventPositionPerc = 0;
        }

        $newEvent.css(
          'top',
          eventPositionPerc + '%',
        );

        // Calculate event slot
        let slots = $eventsContainer.data('slots');
        let eventLevel = 0;
        if (slots != undefined) {
          let newLevel = 0;
          for (let index = 0; index < slots.length; index++) {
            const slot = slots[index];
            if (
              !(
                eventData.start >= slot.end ||
                eventData.start + eventData.duration <= slot.start
              )
            ) {
              newLevel = slot.level + 1;
            }
          }

          slots.push({
            level: newLevel,
            start: eventData.start,
            end: eventData.start + eventData.duration,
          });

          eventLevel = newLevel;
        } else {
          slots = [
            {
              level: 0,
              start: eventData.start,
              end: eventData.start + eventData.duration,
            },
          ];

          eventLevel = 0;
        }

        // Update container slots
        $eventsContainer.data('slots', slots);

        // Assign level
        $newEvent.addClass('level-' + eventLevel);
        $newEvent.toggleClass('concurrent', eventLevel > 0);

        // Calculate height
        const eventHeight = ((eventData.duration / dayViewDuration) * 100) +
          eventHeightAdj;

        $newEvent.height(eventHeight + '%');

        // Append event to container
        $newEvent.appendTo($eventsContainer);

        // Mark shorter events to be styled
        if ($newEvent.height() < parseFloat($newEvent.css('font-size')) * 2) {
          $newEvent.addClass('shorter-event');
          $newEvent.find('.event-time').prependTo($newEvent);
        }
      }
    }

    /**
     * Update container and hide the number of extra events
     * @param {object} $container jquery container
     * @param {object} slots occupied slots
     */
    function updateContainerExtraEvents($container, slots) {
      // Calculate max events per container (if not defined)
      if (!maxEventPerDay) {
        const bodyFontSize = parseInt(
          $('body').css('font-size').split('px')[0],
        );
        const singleEventHeight =
          bodyFontSize * 1.25 + bodyFontSize * 0.5 + bodyFontSize * 0.125;
        const extraEventsHeight = bodyFontSize * 1.25 + bodyFontSize * 0.125;
        maxEventPerDay = Math.floor($container.height() / singleEventHeight);

        maxEventPerDayWithExtra = Math.floor(
          ($container.height() - extraEventsHeight) / singleEventHeight,
        );
      }

      // If we show extra. get number of events
      // that fit inside that space ( smaller )
      let maxEventsForThisDay = maxEventPerDay;
      if (slots.length > maxEventPerDay) {
        maxEventsForThisDay = maxEventPerDayWithExtra;
      }

      // Get number of dummy events that were generated
      // by extended events in the visible elements
      const numberOfExtendedEvents = slots.filter((ev, idx) => {
        return ev == 2 && idx <= maxEventsForThisDay;
      }).length;

      // Remove extra elements
      if (
        maxEventsForThisDay > 0 &&
        maxEventsForThisDay > numberOfExtendedEvents
      ) {
        $container
          .find(
            '.calendar-event:gt(' +
            (maxEventsForThisDay - numberOfExtendedEvents - 1) +
            ')',
          )
          .remove();
      } else {
        $container.find('.calendar-event').remove();
      }

      // Calculate number of events that were hidden
      let numEventsToHide = 0;
      for (let index = maxEventsForThisDay; index < slots.length; index++) {
        if (slots[index] != undefined) {
          numEventsToHide++;
        }
      }

      // Fix for extended events
      let numExtendedEventsFix = 0;
      if (maxEventsForThisDay > 0 &&
        maxEventsForThisDay < numberOfExtendedEvents) {
        numExtendedEventsFix = numberOfExtendedEvents - maxEventsForThisDay;
      }
      numEventsToHide -= numExtendedEventsFix;

      // Update extra events label
      if (numEventsToHide > 0) {
        if ($container.find('.extra-events').length > 0) {
          $container.find('.extra-events span').html('+ ' + numEventsToHide);
        } else {
          $container.append(
            '<div class="extra-events"><span>+ ' +
            numEventsToHide +
            '</span></div>',
          );
        }
      }
    }

    /**
     * Create hour grid
     * @param {object} $container jquery container
     * @param {string} start
     * @param {string} end
     */
    function createCalendarHourGrid($container, start, end) {
      start =
        start == '' ?
          moment(DEFAULT_DAY_START_TIME, TIME_FORMAT) :
          moment(start, TIME_FORMAT);
      end =
        end == '' ?
          moment(DEFAULT_DAY_END_TIME, TIME_FORMAT) :
          moment(end, TIME_FORMAT);

      // Hour loop
      const momentAux = moment(start);
      while (momentAux <= end) {
        $container.append(
          '<li class="hour-time">' + momentAux.format(TIME_FORMAT) + '</li>',
        );
        momentAux.add(GRID_STEP, 'm');
      }

      // Save properties to the container for later use
      const containerData = {
        start: moment
          .duration(moment(start).diff(moment(start).startOf('day')))
          .as('minutes'),
        end: moment
          .duration(moment(end).diff(moment(end).startOf('day')))
          .as('minutes'),
      };
      $container.data(containerData);

      // Calculate hour grid spacing
      const gridSpacing =
        (1 / ((containerData.end - containerData.start) / GRID_STEP)) * 100;
      $container.find('.hour-time').height(gridSpacing + '%');
    }

    // Functions by calendar type
    if (options.calendarType == 1) {
      // Schedule View
      const $calendarContainer = $('.calendar-container');

      // Clear container
      $calendarContainer.empty();

      let addedEvents = 0;

      /**
       * Add event to specific day (override)
       * @param {object} date  momentjs date
       * @param {object} event
       * @param {number} eventTotalDays
       * @param {number} currentDayOfEvent
       */
      function addEventToDay(date, event, eventTotalDays, currentDayOfEvent) {
        /**
         * Get container by date
         * @param {object} date
         * @return {object} Jquery container
         */
        function getDayContainer(date) {
          let $dayContainerTemp;
          let $dayContainerTitle;

          if ($('#day_' + date.month() + '_' + date.date()).length > 0) {
            $dayContainerTemp = $('#day_' + date.month() + '_' + date.date());
          } else {
            $dayContainerTemp = $('<div>').attr('id', 'day_' +
              date.month() + '_' + date.date()).addClass('day-container');

            $dayContainerTitle = $('<div class="title-container">').appendTo(
              $dayContainerTemp);

            if (moment(date).startOf('d').isSame(moment(TODAY).startOf('d'))) {
              $dayContainerTemp.addClass('today');
            }

            $('<div class="day-title-date">' +
              date.date() +
              '</div>').appendTo($dayContainerTitle);

            $('<div class="day-title-month">' +
              monthsNames[date.month()] +
                '</div>').appendTo($dayContainerTitle);

            $('<div class="day-title-weekday">' +
              weekdaysNames[date.weekday()] +
              '</div>').appendTo($dayContainerTitle);

            $('<div class="day-events">').appendTo($dayContainerTemp);

            $dayContainerTemp.appendTo($calendarContainer);
          }

          // $calendarContainer
          return $dayContainerTemp;
        }

        const $newEvent = $('<div class="calendar-event">');
        const $dayContainer = getDayContainer(date);
        const $dayContainerEvents = $dayContainer.find('.day-events');
        let htmlToAdd = '';

        // Add time
        if (!event.allDay) {
          if (currentDayOfEvent == 1) {
            htmlToAdd +=
              '<span class="event-start-time">' +
                moment(event.startDate).format(TIME_FORMAT) +
              '</span>';

            // Save start date to object data
            $newEvent.data('start', event.startDate);

            if (eventTotalDays == 1) {
              htmlToAdd +=
                ' - <span class="event-end-time">' +
                  moment(event.endDate).format(TIME_FORMAT) +
                '</span>';
            }
          }
        } else {
          // Mark event as an all day
          $newEvent.addClass('all-day');
        }

        // Mark event as a multi-day
        if (eventTotalDays > 1) {
          $newEvent.addClass('multi');
        }

        // Add summary
        htmlToAdd += '<span class="event-summary">' + event.summary + '</span>';

        if (options.showDescription == 1 && event.description) {
          htmlToAdd += '<div class="event-description">' +
            event.description + '</div>';
        }

        // Add inner html
        $newEvent.html(htmlToAdd);

        // Append event to container
        if (event.allDay) {
          $newEvent.prependTo($dayContainerEvents);
        } else {
          $newEvent.appendTo($dayContainerEvents);
        }

        addedEvents++;
      }

      // Override addEventsToCalendarBase
      addEventsToCalendar = function(events) {
        events.forEach((event) => {
          const startDate = moment(event.startDate).startOf('date');

          // Check if event is an all day
          // (startDate 00:00 day 1, endDate 00:00 day after last day)
          const allDayEvent =
            moment(event.startDate).isSame(startDate) &&
            moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
          event.allDay = allDayEvent;

          const endDate = allDayEvent ?
            moment(event.endDate).startOf('date').subtract(1, 'd') :
            moment(event.endDate).startOf('date');

          const eventTotalDays = endDate.diff(startDate, 'days') + 1;
          let currentDayOfEvent = 1;

          // Days loop
          const momentAux = moment(startDate);

          while (momentAux <= endDate) {
            let startDate = null;
            let endDate = null;

            // If we're using a date range
            // show only events from the start date onwards
            // and before the end date
            if (options.useDateRange == 1) {
              (options.rangeStart) && (startDate = moment(options.rangeStart));
              (options.rangeEnd) && (endDate = moment(options.rangeEnd));
            } else {
              // Get start of day of today
              // but clone it to avoid modifying the original
              startDate = moment(START_DATE).startOf('d');
            }

            // Add event
            if ((!startDate || moment(event.startDate) >= startDate) &&
              (!endDate || moment(event.endDate) <= endDate)) {
              addEventToDay(
                momentAux,
                event,
                eventTotalDays,
                currentDayOfEvent);
            }

            currentDayOfEvent++;
            momentAux.add(1, 'd');
          }
        });

        // Create now marker
        if (options.showNowMarker == 1) {
          const $nowMarker = $('<div class="now-marker">');
          const $dayEventsContainer = $calendarContainer.find('#day_' +
            moment(TODAY).month() + '_' + moment(TODAY).date() +
            ' .day-events');
          let $targetElement;

          if ($dayEventsContainer.length > 0) {
            // Calculate position
            $dayEventsContainer.find('.calendar-event').each((idx, event) => {
              const start = $(event).data('start');

              if (start && TODAY < moment(start)) {
                $targetElement = $(event);
                $nowMarker.insertBefore($targetElement);
                return false;
              }
            });

            // Append marker to container
            if (!$targetElement) {
              $nowMarker.appendTo($dayEventsContainer);
            }
          }
        }

        // Show no events message
        if (options.noEventsMessage != '' && addedEvents == 0) {
          $calendarContainer.append('<div class="no-events-message">' +
            options.noEventsMessage + '</div>');
        }
        // noEventsMessage
      };
    } else if (options.calendarType == 2) {
      // Daily View
      const $hourGrid = $('.hour-grid');
      const $dayTitle = $('.day-title');
      const $dayContainer = $('.day-container');

      // Clear containers
      $hourGrid.empty();
      $dayTitle.empty();
      $dayContainer.empty();

      createCalendar = function() {
        // Add day label
        const $weekDay = $('<li>');
        $dayTitle.append($weekDay);
        $weekDay.html('<div class="week-day">' +
          weekdaysNames[START_DATE.weekday()] +
          '</div>');

        const today = moment(START_DATE);
        const day = {
          date: today.format('YYYY-MM-DD'),
          dayOfMonth: today.date(),
        };

        const $dayOfMonthElement = $('<div class="week-day-date">');
        $dayOfMonthElement.html(day.dayOfMonth);
        $weekDay.append($dayOfMonthElement);

        // Add hour grid
        createCalendarHourGrid($hourGrid, options.startTime, options.endTime);

        // Add day element
        const $dayElement = $('<li id="day_' + day.dayOfMonth + '">');
        $dayElement.addClass('calendar-day');

        // Append all day events container
        $dayElement.append(
          $('<div class="calendar-all-day-events-container">'),
        );

        // Append normal events container
        $dayElement.append($('<div class="calendar-events-container">'));

        // Append day to container
        $dayContainer.append($dayElement);

        if (options.showNowMarker == 1) {
          createNowMarker(
            $dayElement.find('.calendar-events-container'),
            $('.hour-grid').data(),
          );
        }
      };

      addEventsToCalendar = addEventsToCalendarBase;
    } else if (options.calendarType == 3) {
      // Weekly View
      const $daysOfWeek = $('.day-of-week');
      const $calendarDays = $('.days-row');
      const $hourGrid = $('.hour-grid');

      // Clear containers
      $daysOfWeek.empty();
      $calendarDays.empty();
      $hourGrid.empty();

      createCalendar = function() {
        const year = INITIAL_YEAR;
        const month = INITIAL_MONTH;
        const date = INITIAL_DATE;

        weekdaysNames.forEach((weekday, idx) => {
          const $weekDay = $('<li id="' + idx + '">');
          $daysOfWeek.append($weekDay);
          $weekDay.html('<div class="week-day">' + weekday + '</div>');
        });

        // Add hour grid
        createCalendarHourGrid($hourGrid, options.startTime, options.endTime);

        // Remove all days
        $calendarDays.find('.calendar-day').remove();

        currentWeekDays = createDaysForCurrentWeek(year, month, date);

        currentWeekDays.forEach((day) => {
          appendDay(day, $calendarDays);
        });
      };

      /**
       * Create days for current week
       * @param {string} year
       * @param {string} month
       * @param {string} date
       * @return {array} Days of the week
       */
      function createDaysForCurrentWeek(year, month, date) {
        const startOfWeek = moment({
          year: year,
          month: month,
          date: date,
        }).startOf('week');

        return [...Array(7)].map((day, index) => {
          const weekDay = moment(startOfWeek).add(index, 'd');
          return {
            date: weekDay.format('YYYY-MM-DD'),
            dayOfMonth: weekDay.date(),
            dayOfWeek: index,
          };
        });
      }

      /**
       * Append new day
       * @param {object} day
       * @param {object} $calendarDays
       */
      function appendDay(day, $calendarDays) {
        const $daysOfWeek = $('.day-of-week');
        const $dayElement = $('<li id="day_' + day.dayOfMonth + '">');
        $dayElement.addClass('calendar-day');

        // Append all day events container
        $dayElement.append(
          $('<div class="calendar-all-day-events-container">'),
        );

        // Append normal events container
        $dayElement.append($('<div class="calendar-events-container">'));

        // Append week day name element
        const $dayOfMonthElement = $('<div class="week-day-date">');
        $dayOfMonthElement.html(day.dayOfMonth);
        $daysOfWeek.find('#' + day.dayOfWeek).append($dayOfMonthElement);

        $calendarDays.append($dayElement);

        if (day.date === moment(TODAY).format('YYYY-MM-DD')) {
          $daysOfWeek.find('#' + day.dayOfWeek).addClass('day-of-week--today');

          if (options.showNowMarker == 1) {
            createNowMarker(
              $dayElement.find('.calendar-events-container'),
              $('.hour-grid').data(),
            );
          }
        }
      }

      // Override addEventsToCalendarBase
      addEventsToCalendar = function(events) {
        events.forEach((event) => {
          const startDate = moment(event.startDate).startOf('date');

          // Check if event is an all day
          // (startDate 00:00 day 1, endDate 00:00 day after last day)
          const allDayEvent =
            moment(event.startDate).isSame(startDate) &&
            moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
          event.allDay = allDayEvent;

          const endDate = allDayEvent ?
            moment(event.endDate).startOf('date').subtract(1, 'd') :
            moment(event.endDate).startOf('date');

          const eventTotalDays = endDate.diff(startDate, 'days') + 1;
          let currentDayOfEvent = 1;

          // Days loop
          const momentAux = moment(startDate);
          while (momentAux <= endDate) {
            addEventToDay(momentAux, event, eventTotalDays, currentDayOfEvent);
            currentDayOfEvent++;
            momentAux.add(1, 'd');
          }
        });
      };
    } else if (options.calendarType == 4) {
      // Monthly View
      let currentMonthDays;
      let previousMonthDays;
      let nextMonthDays;

      createCalendar = function() {
        const $calendarDays = $('#calendarDays');
        const year = INITIAL_YEAR;
        const month = INITIAL_MONTH;
        const $daysOfWeek = $('#daysOfWeek');

        // Clear week days container
        $daysOfWeek.empty();

        weekdaysNames.forEach((weekday) => {
          const $weekDay = $('<li>');
          $daysOfWeek.append($weekDay);
          $weekDay.html(weekday);
        });

        $('#selectedMonth').html(
          moment({
            year: year,
            month: month,
          }).format('MMMM YYYY'),
        );

        // Remove all days
        $calendarDays.find('.calendar-day').remove();

        currentMonthDays = createDaysForCurrentMonth(year, month);

        previousMonthDays = createDaysForPreviousMonth(year, month - 1);
        nextMonthDays = createDaysForNextMonth(year, month + 1);

        const days = [
          ...previousMonthDays,
          ...currentMonthDays,
          ...nextMonthDays,
        ];

        days.forEach((day) => {
          appendDay(day, $calendarDays);
        });
      };

      /**
       * Append new day
       * @param {object} day
       * @param {object} $calendarDays
       */
      function appendDay(day, $calendarDays) {
        const $dayElement = $(
          '<li id="day_' + day.month + '_' + day.dayOfMonth + '">',
        );
        $dayElement.addClass('calendar-day');

        // Append events container
        const $eventsContainer = $('<div class="calendar-events-container">');
        $dayElement.append($eventsContainer);

        // Append day
        const $dayOfMonthElement = $('<span class="date">');
        $dayOfMonthElement.html(day.dayOfMonth);
        $dayElement.append($dayOfMonthElement);

        $calendarDays.append($dayElement);

        if (!day.isCurrentMonth) {
          $dayElement.addClass('calendar-day--not-current');
        }

        if (day.date === moment(TODAY).format('YYYY-MM-DD')) {
          $dayElement.addClass('calendar-day--today');
        }
      }

      /**
       * Get the number of days in a given month
       * @param {number} year
       * @param {number} month
       * @return {array} day objects
       */
      function getNumberOfDaysInMonth(year, month) {
        return moment({
          year: year,
          month: month,
        }).daysInMonth();
      }

      /**
       * Create days on current month
       * @param {number} year
       * @param {number} month
       * @return {array} day objects
       */
      function createDaysForCurrentMonth(year, month) {
        return [...Array(getNumberOfDaysInMonth(year, month))].map(
          (day, index) => {
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
          },
        );
      }

      /**
       * Create days in previous month
       * @param {number} year
       * @param {number} month
       * @return {array} day objects
       */
      function createDaysForPreviousMonth(year, month) {
        const firstDayOfTheMonthWeekday = getWeekday(currentMonthDays[0].date);

        const previousMonth = moment({
          year: year,
          month: month - 1,
        });

        // Cover first day of the month
        //  being sunday (firstDayOfTheMonthWeekday === 0)
        const visibleNumberOfDaysFromPreviousMonth = firstDayOfTheMonthWeekday ?
          firstDayOfTheMonthWeekday - 1 :
          6;

        const previousMonthLastMondayDayOfMonth = moment(
          currentMonthDays[0].date,
        )
          .subtract(visibleNumberOfDaysFromPreviousMonth, 'day')
          .date();

        return [...Array(visibleNumberOfDaysFromPreviousMonth)].map(
          (day, index) => {
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
          },
        );
      }

      /**
       * Create days in next month
       * @param {number} year
       * @param {number} month
       * @return {array} day objects
       */
      function createDaysForNextMonth(year, month) {
        const lastDayOfTheMonthWeekday = getWeekday({
          year: year,
          month: month - 1,
          date: currentMonthDays.length,
        });

        const nextMonth = moment({
          year: year,
          month: month,
        });

        const visibleNumberOfDaysFromNextMonth = lastDayOfTheMonthWeekday ?
          7 - lastDayOfTheMonthWeekday :
          lastDayOfTheMonthWeekday;

        return [...Array(visibleNumberOfDaysFromNextMonth)].map(
          (day, index) => {
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
          },
        );
      }

      addEventsToCalendar = function(events) {
        events.forEach((event) => {
          const startDate = moment(event.startDate).startOf('date');

          // Check if event is an all day
          // (startDate 00:00 day 1, endDate 00:00 day after last day)
          const allDayEvent =
            moment(event.startDate).isSame(startDate) &&
            moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
          event.allDay = allDayEvent;

          const endDate = allDayEvent ?
            moment(event.endDate).startOf('date').subtract(1, 'd') :
            moment(event.endDate).startOf('date');

          const eventTotalDays = endDate.diff(startDate, 'days') + 1;

          // Days loop
          let currentDayOfEvent = 1;
          const momentAux = moment(startDate);
          while (momentAux <= endDate) {
            addEventToDay(momentAux, event, eventTotalDays, currentDayOfEvent);
            currentDayOfEvent++;
            momentAux.add(1, 'd');
          }
        });
      };

      /**
       * Add event to specific day (override)
       * @param {object} date  momentjs date
       * @param {object} event
       * @param {number} eventTotalDays
       * @param {number} currentDayOfEvent
       */
      function addEventToDay(date, event, eventTotalDays, currentDayOfEvent) {
        /**
         * Get container by date
         * @param {object} date
         * @return {object} Jquery container
         */
        function getEventContainer(date) {
          return $('#day_' + date.month() + '_' + date.date()).find(
            '.calendar-events-container',
          );
        }

        const $newEvent = $('<div class="calendar-event">');
        const $eventsContainer = getEventContainer(date);
        const weekDay = getWeekday(date);
        let eventDuration = 1;

        // Mark event as an all day
        if (event.allDay) {
          $newEvent.addClass('all-day');
        }

        if (eventTotalDays > 1) {
          // Multiple day event
          let htmlToAdd =
            '<span class="event-summary">' + event.summary + '</span>';

          // Mark as multi event
          $newEvent.addClass('multi-day');

          // Draw only on the first day of the event
          // or at the beggining of the weeks when it breaks
          if (currentDayOfEvent == 1 || weekDay == 1) {
            if (currentDayOfEvent == 1 && !event.allDay) {
              htmlToAdd =
                '<span class="event-time">' +
                moment(event.startDate).format(TIME_FORMAT) +
                '</span>' +
                htmlToAdd;
            }

            // Show event content in multiple days
            $newEvent.html(htmlToAdd);

            // Update element duration based on event duration
            eventDuration = eventTotalDays - (currentDayOfEvent - 1);

            const remainingDays = 8 - weekDay;
            if (eventDuration > remainingDays) {
              eventDuration = remainingDays;
              $newEvent.addClass('cropped-event-end');
            }

            if (currentDayOfEvent > 1) {
              $newEvent.addClass('cropped-event-start');
            }
            $newEvent.css(
              'width',
              'calc(' +
              eventDuration * 100 +
              '% + ' +
              eventDuration * 2 +
              'px)',
            );
          } else {
            // Multiple event that was extended, no need to be rendered
            return;
          }
        } else {
          // Single day event
          let htmlToAdd =
            '<span class="event-summary">' + event.summary + '</span>';

          // Mark event as an all day
          if (event.allDay) {
            $newEvent.addClass('all-day');
          } else {
            htmlToAdd =
              '<span class="event-time">' +
              moment(event.startDate).format(TIME_FORMAT) +
              '</span>' +
              htmlToAdd;
          }

          // Add inner html
          $newEvent.html(htmlToAdd);
        }

        // Calculate event slot
        let slots = $eventsContainer.data('slots');
        let daySlot;
        if (slots != undefined) {
          for (let index = 0; index < slots.length; index++) {
            const slot = slots[index];
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
          for (let dayAfter = 1; dayAfter < eventDuration; dayAfter++) {
            const $newContainer = getEventContainer(
              moment(date).add(dayAfter, 'd'),
            );
            let dataSlots = $newContainer.data('slots');

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
    }

    // Create calendar
    applyStyleOptions(options);
    createCalendar();
    addEventsToCalendar(filteredEvents);

    return true;
  },
});
