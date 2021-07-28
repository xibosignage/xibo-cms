/******/ (function() { // webpackBootstrap
var __webpack_exports__ = {};
/*!*********************************************!*\
  !*** ./modules/src/xibo-calendar-render.js ***!
  \*********************************************/
function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && Symbol.iterator in Object(iter)) return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

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
  xiboCalendarRender: function xiboCalendarRender(options, events) {
    // Default options
    var defaults = {
      duration: '30',
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0
    };
    options = $.extend({}, defaults, options); // Global constants

    var TODAY = moment();
    var INITIAL_YEAR = moment().year(); // NOTE: month format for momentjs is 1-12 and month value is zero indexed

    var INITIAL_MONTH = moment().month();
    var INITIAL_DATE = moment().date();
    var TIME_FORMAT = options.timeFormat || 'HH:mm';
    var DEFAULT_DAY_START_TIME = moment().startOf('day').format(TIME_FORMAT);
    var DEFAULT_DAY_END_TIME = moment().endOf('day').format(TIME_FORMAT);
    var GRID_STEP = options.gridStep && options.gridStep > 0 ? options.gridStep : 60;
    var DEFAULT_FONT_SIZE = 16;
    var DEFAULT_FONT_SCALE = options.textScale || 1; // Global vars for all calendar types

    var maxEventPerDay;
    var maxEventPerDayWithExtra;
    var weekdaysNames = moment.weekdays(true);

    if (options.weekdayNameLength == 'short') {
      weekdaysNames = moment.weekdaysMin(true);
    } else if (options.weekdayNameLength == 'medium') {
      weekdaysNames = moment.weekdaysShort(true);
    }

    var monthsNames = moment.months();

    if (options.monthNameLength == 'short') {
      monthsNames = moment.monthsShort();
    } // Main functions to be overriden


    var createCalendar = function createCalendar() {};

    var addEventsToCalendar = function addEventsToCalendar() {};
    /**
     * Apply style based on options
     */


    function applyStyleOptions() {
      $('#content').toggleClass('hide-header', options.showHeader != '1');
      $(':root').css('font-size', DEFAULT_FONT_SIZE * DEFAULT_FONT_SCALE);
      options.mainBackgroundColor && $(':root').css('--main-background-color', options.mainBackgroundColor);
      options.gridColor && $(':root').css('--grid-color', options.gridColor);
      options.gridTextColor && $(':root').css('--grid-text-color', options.gridTextColor);
      options.dayBgColor && $(':root').css('--day-bg-color', options.dayBgColor);
      options.dayTextColor && $(':root').css('--day-text-color', options.dayTextColor);
      options.todayTextColor && $(':root').css('--today-text-color', options.todayTextColor);
      options.nowMarkerColor && $(':root').css('--now-marker-color', options.nowMarkerColor);
      options.dayOtherMonthBgColor && $(':root').css('--day-other-month-bg-color', options.dayOtherMonthBgColor);
      options.dayOtherMonthTextColor && $(':root').css('--day-other-month-text-color', options.dayOtherMonthTextColor);
      options.headerBgColor && $(':root').css('--header-bg-color', options.headerBgColor);
      options.headerTextColor && $(':root').css('--header-text-color', options.headerTextColor);
      options.weekDaysHeaderBgColor && $(':root').css('--weekdays-bg-color', options.weekDaysHeaderBgColor);
      options.weekDaysHeaderTextColor && $(':root').css('--weekdays-text-color', options.weekDaysHeaderTextColor);
      options.eventBgColor && $(':root').css('--event-bg-color', options.eventBgColor);
      options.eventTextColor && $(':root').css('--event-text-color', options.eventTextColor);
      options.dailyEventBgColor && $(':root').css('--daily-event-bg-color', options.dailyEventBgColor);
      options.dailyEventTextColor && $(':root').css('--daily-event-text-color', options.dailyEventTextColor);
      options.multiDayEventBgColor && $(':root').css('--multi-day-event-bg-color', options.multiDayEventBgColor);
      options.multiDayEventTextColor && $(':root').css('--multi-day-event-text-color', options.multiDayEventTextColor);
      options.aditionalEventsBgColor && $(':root').css('--aditional-events-bg-color', options.aditionalEventsBgColor);
      options.aditionalEventsTextColor && $(':root').css('--aditional-events-text-color', options.aditionalEventsTextColor);
      options.noEventsBgColor && $(':root').css('--no-events-bg-color', options.noEventsBgColor);
      options.noEventsTextColor && $(':root').css('--no-events-text-color', options.noEventsTextColor);
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
      var dayViewDuration = timeData.end - timeData.start;
      var $nowMarker = $('<div class="now-marker">');
      var nowTimeInMinutes = moment.duration(moment(TODAY).diff(moment(TODAY).startOf('day'))).as('minutes'); // Skip if it's not included in the selected delta time view

      if (nowTimeInMinutes >= timeData.end || nowTimeInMinutes <= timeData.start) {
        return;
      } // Calculate position


      var eventPositionPerc = (nowTimeInMinutes / dayViewDuration - timeData.start / dayViewDuration) * 100;
      $nowMarker.css('top', eventPositionPerc + '%'); // Append marker to container

      $nowMarker.appendTo($container);
    }
    /**
     * Add events to calendar
     */


    function addEventsToCalendarBase() {
      events.forEach(function (event) {
        var startDate = moment(event.startDate).startOf('date'); // Check if event is an all day
        // (startDate 00:00 day 1, endDate 00:00 day after last day)

        var allDayEvent = moment(event.startDate).isSame(startDate) && moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
        event.allDay = allDayEvent;
        var endDate = allDayEvent ? moment(event.endDate).startOf('date').subtract(1, 'd') : moment(event.endDate).startOf('date');
        var eventTotalDays = endDate.diff(startDate, 'days') + 1;
        var currentDayOfEvent = 1; // Days loop

        var momentAux = moment(startDate);

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
        return options.calendarType == 2 ? $('.calendar-day .calendar-events-container') : $('#day_' + date.date()).find('.calendar-events-container');
      }
      /**
       * Get all days container by date
       * @param {object} date
       * @return {object} Jquery container
       */


      function getAllDayEventsContainer(date) {
        return options.calendarType == 2 ? $('.calendar-day .calendar-all-day-events-container') : $('#day_' + date.date()).find('.calendar-all-day-events-container');
      }

      var $newEvent = $('<div class="calendar-event">');
      var weekDay = getWeekday(date);
      var eventDuration = 1; // Mark event as an all day

      if (event.allDay) {
        $newEvent.addClass('all-day');
      }

      if (eventTotalDays > 1) {
        // Multiple day event
        var htmlToAdd = '<span class="event-summary">' + event.summary + '</span>'; // Mark as multi event

        $newEvent.addClass('multi-day'); // Draw only on the first day of the event
        // or at the beggining of the weeks when it breaks

        if (currentDayOfEvent == 1 || weekDay == 1) {
          if (currentDayOfEvent == 1 && !event.allDay) {
            htmlToAdd = '<div class="event-time">' + moment(event.startDate).format(TIME_FORMAT) + '</div>' + htmlToAdd;
          } // Show event content in multiple days


          $newEvent.html(htmlToAdd); // Update element duration based on event duration

          eventDuration = eventTotalDays - (currentDayOfEvent - 1);
          var remainingDays = 8 - weekDay;

          if (eventDuration > remainingDays) {
            eventDuration = remainingDays;
            $newEvent.addClass('cropped-event-end');
          }

          if (currentDayOfEvent > 1) {
            $newEvent.addClass('cropped-event-start');
          }

          $newEvent.css('width', 'calc(' + eventDuration * 100 + '% + ' + eventDuration * 2 + 'px)');
        } else {
          // Multiple event that was extended, no need to be rendered
          return;
        }
      } else {
        // Single day event
        var _htmlToAdd = '<div class="event-summary">' + event.summary + '</div>'; // Mark event as an all day


        if (event.allDay) {
          $newEvent.addClass('all-day');
        } else {
          _htmlToAdd = _htmlToAdd + '<div class="event-time">' + moment(event.startDate).format(TIME_FORMAT) + ' - ' + moment(event.endDate).format(TIME_FORMAT) + '</div>';
        } // Add inner html


        $newEvent.html(_htmlToAdd);
      } // All day or multi day events


      if (eventTotalDays > 1 || event.allDay) {
        // If there's at least one daily event
        // enable the container in the calendar view
        $('.calendar-container').addClass('show-all-day-events');
        var $dailyEventsContainer = getAllDayEventsContainer(date); // Calculate event slot

        var slots = $dailyEventsContainer.data('slots');
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

        $dailyEventsContainer.data('slots', slots); // Extend event to the remaining days

        if (eventDuration > 1 && options.calendarType != 2) {
          for (var dayAfter = 1; dayAfter < eventDuration; dayAfter++) {
            var $newContainer = getAllDayEventsContainer(moment(date).add(dayAfter, 'd'));
            var dataSlots = $newContainer.data('slots');

            if (dataSlots === undefined) {
              dataSlots = [];
            }

            dataSlots[daySlot] = 2;
            $newContainer.data('slots', dataSlots);
          }
        }

        $newEvent.css('top', 1.875 * daySlot + 'rem'); // Append event to container

        $newEvent.appendTo($dailyEventsContainer); // Check container height and slots to show number of extra events

        updateContainerExtraEvents($dailyEventsContainer, slots);
      } else {
        // Daily timed event
        var $eventsContainer = getEventContainer(date);
        var containerData = $('.hour-grid').data();
        var dayViewDuration = containerData.end - containerData.start;
        var eventData = {
          start: moment.duration(moment(event.startDate).diff(moment(event.startDate).startOf('day'))).as('minutes'),
          duration: moment.duration(moment(event.endDate).diff(moment(event.startDate))).as('minutes')
        }; // Skip event if it's not included in the selected delta time view

        if (eventData.start >= containerData.end || eventData.start + eventData.duration <= containerData.start) {
          return;
        } // Calculate position


        var eventPositionPerc = (eventData.start / dayViewDuration - containerData.start / dayViewDuration) * 100;
        var eventHeightAdj = 0; // Check if event starts before view time

        if (eventPositionPerc < 0) {
          $newEvent.addClass('before-view');
          eventHeightAdj = eventPositionPerc;
          eventPositionPerc = 0;
        }

        $newEvent.css('top', eventPositionPerc + '%'); // Calculate event slot

        var _slots = $eventsContainer.data('slots');

        var eventLevel = 0;

        if (_slots != undefined) {
          var newLevel = 0;

          for (var _index = 0; _index < _slots.length; _index++) {
            var _slot = _slots[_index];

            if (!(eventData.start >= _slot.end || eventData.start + eventData.duration <= _slot.start)) {
              newLevel = _slot.level + 1;
            }
          }

          _slots.push({
            level: newLevel,
            start: eventData.start,
            end: eventData.start + eventData.duration
          });

          eventLevel = newLevel;
        } else {
          _slots = [{
            level: 0,
            start: eventData.start,
            end: eventData.start + eventData.duration
          }];
          eventLevel = 0;
        } // Update container slots


        $eventsContainer.data('slots', _slots); // Assign level

        $newEvent.addClass('level-' + eventLevel);
        $newEvent.toggleClass('concurrent', eventLevel > 0); // Calculate height

        var eventHeight = eventData.duration / dayViewDuration * 100 + eventHeightAdj;
        $newEvent.height(eventHeight + '%'); // Append event to container

        $newEvent.appendTo($eventsContainer); // Mark shorter events to be styled

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
        var bodyFontSize = parseInt($('body').css('font-size').split('px')[0]);
        var singleEventHeight = bodyFontSize * 1.25 + bodyFontSize * 0.5 + bodyFontSize * 0.125;
        var extraEventsHeight = bodyFontSize * 1.25 + bodyFontSize * 0.125;
        maxEventPerDay = Math.floor($container.height() / singleEventHeight);
        maxEventPerDayWithExtra = Math.floor(($container.height() - extraEventsHeight) / singleEventHeight);
      } // If we show extra. get number of events
      // that fit inside that space ( smaller )


      var maxEventsForThisDay = maxEventPerDay;

      if (slots.length > maxEventPerDay) {
        maxEventsForThisDay = maxEventPerDayWithExtra;
      } // Get number of dummy events that were generated
      // by extended events in the visible elements


      var numberOfExtendedEvents = slots.filter(function (ev, idx) {
        return ev == 2 && idx <= maxEventsForThisDay;
      }).length; // Remove extra elements

      if (maxEventsForThisDay > 0 && maxEventsForThisDay > numberOfExtendedEvents) {
        $container.find('.calendar-event:gt(' + (maxEventsForThisDay - numberOfExtendedEvents - 1) + ')').remove();
      } else {
        $container.find('.calendar-event').remove();
      } // Calculate number of events that were hidden


      var numEventsToHide = 0;

      for (var index = maxEventsForThisDay; index < slots.length; index++) {
        if (slots[index] != undefined) {
          numEventsToHide++;
        }
      } // Fix for extended events


      var numExtendedEventsFix = 0;

      if (maxEventsForThisDay > 0 && maxEventsForThisDay < numberOfExtendedEvents) {
        numExtendedEventsFix = numberOfExtendedEvents - maxEventsForThisDay;
      }

      numEventsToHide -= numExtendedEventsFix; // Update extra events label

      if (numEventsToHide > 0) {
        if ($container.find('.extra-events').length > 0) {
          $container.find('.extra-events span').html('+ ' + numEventsToHide);
        } else {
          $container.append('<div class="extra-events"><span>+ ' + numEventsToHide + '</span></div>');
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
      start = start == '' ? moment(DEFAULT_DAY_START_TIME, TIME_FORMAT) : moment(start, TIME_FORMAT);
      end = end == '' ? moment(DEFAULT_DAY_END_TIME, TIME_FORMAT) : moment(end, TIME_FORMAT); // Hour loop

      var momentAux = moment(start);

      while (momentAux <= end) {
        $container.append('<li class="hour-time">' + momentAux.format(TIME_FORMAT) + '</li>');
        momentAux.add(GRID_STEP, 'm');
      } // Save properties to the container for later use


      var containerData = {
        start: moment.duration(moment(start).diff(moment(start).startOf('day'))).as('minutes'),
        end: moment.duration(moment(end).diff(moment(end).startOf('day'))).as('minutes')
      };
      $container.data(containerData); // Calculate hour grid spacing

      var gridSpacing = 1 / ((containerData.end - containerData.start) / GRID_STEP) * 100;
      $container.find('.hour-time').height(gridSpacing + '%');
    } // Functions by calendar type


    if (options.calendarType == 1) {
      // Schedule View
      var $calendarContainer = $('.calendar-container');
      var addedEvents = 0;
      /**
       * Add event to specific day (override)
       * @param {object} date  momentjs date
       * @param {object} event
       * @param {number} eventTotalDays
       * @param {number} currentDayOfEvent
       */

      function _addEventToDay(date, event, eventTotalDays, currentDayOfEvent) {
        /**
         * Get container by date
         * @param {object} date
         * @return {object} Jquery container
         */
        function getDayContainer(date) {
          var $dayContainerTemp;
          var $dayContainerTitle;

          if ($('#day_' + date.month() + '_' + date.date()).length > 0) {
            $dayContainerTemp = $('#day_' + date.month() + '_' + date.date());
          } else {
            $dayContainerTemp = $('<div>').attr('id', 'day_' + date.month() + '_' + date.date()).addClass('day-container');
            $dayContainerTitle = $('<div class="title-container">').appendTo($dayContainerTemp);

            if (moment(date).startOf('d').isSame(moment(TODAY).startOf('d'))) {
              $dayContainerTemp.addClass('today');
            }

            $('<div class="day-title-date">' + date.date() + '</div>').appendTo($dayContainerTitle);
            $('<div class="day-title-month">' + monthsNames[date.month()] + '</div>').appendTo($dayContainerTitle);
            $('<div class="day-title-weekday">' + weekdaysNames[date.weekday()] + '</div>').appendTo($dayContainerTitle);
            $('<div class="day-events">').appendTo($dayContainerTemp);
            $dayContainerTemp.appendTo($calendarContainer);
          } // $calendarContainer


          return $dayContainerTemp;
        }

        var $newEvent = $('<div class="calendar-event">');
        var $dayContainer = getDayContainer(date);
        var $dayContainerEvents = $dayContainer.find('.day-events');
        var htmlToAdd = ''; // Add time

        if (!event.allDay) {
          if (currentDayOfEvent == 1) {
            htmlToAdd += '<span class="event-start-time">' + moment(event.startDate).format(TIME_FORMAT) + '</span>'; // Save start date to object data

            $newEvent.data('start', event.startDate);

            if (eventTotalDays == 1) {
              htmlToAdd += ' - <span class="event-end-time">' + moment(event.endDate).format(TIME_FORMAT) + '</span>';
            }
          }
        } else {
          // Mark event as an all day
          $newEvent.addClass('all-day');
        } // Mark event as a multi-day


        if (eventTotalDays > 1) {
          $newEvent.addClass('multi');
        } // Add summary


        htmlToAdd += '<span class="event-summary">' + event.summary + '</span>';

        if (options.showDescription == 1 && event.description) {
          htmlToAdd += '<div class="event-description">' + event.description + '</div>';
        } // Add inner html


        $newEvent.html(htmlToAdd); // Append event to container

        if (event.allDay) {
          $newEvent.prependTo($dayContainerEvents);
        } else {
          $newEvent.appendTo($dayContainerEvents);
        }

        addedEvents++;
      } // Override addEventsToCalendarBase


      addEventsToCalendar = function addEventsToCalendar(events) {
        events.forEach(function (event) {
          var startDate = moment(event.startDate).startOf('date'); // Check if event is an all day
          // (startDate 00:00 day 1, endDate 00:00 day after last day)

          var allDayEvent = moment(event.startDate).isSame(startDate) && moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
          event.allDay = allDayEvent;
          var endDate = allDayEvent ? moment(event.endDate).startOf('date').subtract(1, 'd') : moment(event.endDate).startOf('date');
          var eventTotalDays = endDate.diff(startDate, 'days') + 1;
          var currentDayOfEvent = 1; // Days loop

          var momentAux = moment(startDate);

          while (momentAux <= endDate) {
            var _startDate = null;
            var _endDate = null; // If we're using a date range
            // show only events from the start date onwards
            // and before the end date

            if (options.useDateRange == 1) {
              options.rangeStart && (_startDate = moment(options.rangeStart));
              options.rangeEnd && (_endDate = moment(options.rangeEnd));
            } else {
              _startDate = TODAY.startOf('d');
            } // Add event


            if ((!_startDate || momentAux >= _startDate) && (!_endDate || momentAux <= _endDate)) {
              _addEventToDay(momentAux, event, eventTotalDays, currentDayOfEvent);
            }

            currentDayOfEvent++;
            momentAux.add(1, 'd');
          }
        }); // Create now marker

        if (options.showNowMarker == 1) {
          var $nowMarker = $('<div class="now-marker">');
          var $dayEventsContainer = $calendarContainer.find('#day_' + moment(TODAY).month() + '_' + moment(TODAY).date() + ' .day-events');
          var $targetElement;

          if ($dayEventsContainer.length > 0) {
            // Calculate position
            $dayEventsContainer.find('.calendar-event').each(function (idx, event) {
              var start = $(event).data('start');

              if (start && TODAY < moment(start)) {
                $targetElement = $(event);
                $nowMarker.insertBefore($targetElement);
                return false;
              }
            }); // Append marker to container

            if (!$targetElement) {
              $nowMarker.appendTo($dayEventsContainer);
            }
          }
        } // Show no events message


        if (options.noEventsMessage != '' && addedEvents == 0) {
          $calendarContainer.append('<div class="no-events-message">' + options.noEventsMessage + '</div>');
        } // noEventsMessage

      };
    } else if (options.calendarType == 2) {
      // Daily View
      var $hourGrid = $('.hour-grid');
      var $dayTitle = $('.day-title');
      var $dayContainer = $('.day-container');

      createCalendar = function createCalendar() {
        // Add day label
        var $weekDay = $('<li>');
        $dayTitle.append($weekDay);
        $weekDay.html('<div class="week-day">' + weekdaysNames[TODAY.weekday()] + '</div>');
        var today = moment(TODAY);
        var day = {
          date: today.format('YYYY-MM-DD'),
          dayOfMonth: today.date()
        };
        var $dayOfMonthElement = $('<div class="week-day-date">');
        $dayOfMonthElement.html(day.dayOfMonth);
        $weekDay.append($dayOfMonthElement); // Add hour grid

        createCalendarHourGrid($hourGrid, options.startTime, options.endTime); // Add day element

        var $dayElement = $('<li id="day_' + day.dayOfMonth + '">');
        $dayElement.addClass('calendar-day'); // Append all day events container

        $dayElement.append($('<div class="calendar-all-day-events-container">')); // Append normal events container

        $dayElement.append($('<div class="calendar-events-container">')); // Append day to container

        $dayContainer.append($dayElement);

        if (options.showNowMarker == 1) {
          createNowMarker($dayElement.find('.calendar-events-container'), $('.hour-grid').data());
        }
      };

      addEventsToCalendar = addEventsToCalendarBase;
    } else if (options.calendarType == 3) {
      // Weekly View
      var $daysOfWeek = $('.day-of-week');
      var $calendarDays = $('.days-row');

      var _$hourGrid = $('.hour-grid');

      createCalendar = function createCalendar() {
        var year = INITIAL_YEAR;
        var month = INITIAL_MONTH;
        var date = INITIAL_DATE;
        weekdaysNames.forEach(function (weekday, idx) {
          var $weekDay = $('<li id="' + idx + '">');
          $daysOfWeek.append($weekDay);
          $weekDay.html('<div class="week-day">' + weekday + '</div>');
        }); // Add hour grid

        createCalendarHourGrid(_$hourGrid, options.startTime, options.endTime); // Remove all days

        $calendarDays.find('.calendar-day').remove();
        currentWeekDays = createDaysForCurrentWeek(year, month, date);
        currentWeekDays.forEach(function (day) {
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
        var startOfWeek = moment({
          year: year,
          month: month,
          date: date
        }).startOf('week');
        return _toConsumableArray(Array(7)).map(function (day, index) {
          var weekDay = moment(startOfWeek).add(index, 'd');
          return {
            date: weekDay.format('YYYY-MM-DD'),
            dayOfMonth: weekDay.date(),
            dayOfWeek: index
          };
        });
      }
      /**
       * Append new day
       * @param {object} day
       * @param {object} $calendarDays
       */


      function appendDay(day, $calendarDays) {
        var $daysOfWeek = $('.day-of-week');
        var $dayElement = $('<li id="day_' + day.dayOfMonth + '">');
        $dayElement.addClass('calendar-day'); // Append all day events container

        $dayElement.append($('<div class="calendar-all-day-events-container">')); // Append normal events container

        $dayElement.append($('<div class="calendar-events-container">')); // Append week day name element

        var $dayOfMonthElement = $('<div class="week-day-date">');
        $dayOfMonthElement.html(day.dayOfMonth);
        $daysOfWeek.find('#' + day.dayOfWeek).append($dayOfMonthElement);
        $calendarDays.append($dayElement);

        if (day.date === moment(TODAY).format('YYYY-MM-DD')) {
          $daysOfWeek.find('#' + day.dayOfWeek).addClass('day-of-week--today');

          if (options.showNowMarker == 1) {
            createNowMarker($dayElement.find('.calendar-events-container'), $('.hour-grid').data());
          }
        }
      } // Override addEventsToCalendarBase


      addEventsToCalendar = function addEventsToCalendar(events) {
        events.forEach(function (event) {
          var startDate = moment(event.startDate).startOf('date'); // Check if event is an all day
          // (startDate 00:00 day 1, endDate 00:00 day after last day)

          var allDayEvent = moment(event.startDate).isSame(startDate) && moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
          event.allDay = allDayEvent;
          var endDate = allDayEvent ? moment(event.endDate).startOf('date').subtract(1, 'd') : moment(event.endDate).startOf('date');
          var eventTotalDays = endDate.diff(startDate, 'days') + 1;
          var currentDayOfEvent = 1; // Days loop

          var momentAux = moment(startDate);

          while (momentAux <= endDate) {
            addEventToDay(momentAux, event, eventTotalDays, currentDayOfEvent);
            currentDayOfEvent++;
            momentAux.add(1, 'd');
          }
        });
      };
    } else if (options.calendarType == 4) {
      // Monthly View
      var currentMonthDays;
      var previousMonthDays;
      var nextMonthDays;

      createCalendar = function createCalendar() {
        var $calendarDays = $('#calendarDays');
        var year = INITIAL_YEAR;
        var month = INITIAL_MONTH;
        var $daysOfWeek = $('#daysOfWeek');
        weekdaysNames.forEach(function (weekday) {
          var $weekDay = $('<li>');
          $daysOfWeek.append($weekDay);
          $weekDay.html(weekday);
        });
        $('#selectedMonth').html(moment({
          year: year,
          month: month
        }).format('MMMM YYYY')); // Remove all days

        $calendarDays.find('.calendar-day').remove();
        currentMonthDays = createDaysForCurrentMonth(year, month);
        previousMonthDays = createDaysForPreviousMonth(year, month - 1);
        nextMonthDays = createDaysForNextMonth(year, month + 1);
        var days = [].concat(_toConsumableArray(previousMonthDays), _toConsumableArray(currentMonthDays), _toConsumableArray(nextMonthDays));
        days.forEach(function (day) {
          appendDay(day, $calendarDays);
        });
      };
      /**
       * Append new day
       * @param {object} day
       * @param {object} $calendarDays
       */


      function appendDay(day, $calendarDays) {
        var $dayElement = $('<li id="day_' + day.month + '_' + day.dayOfMonth + '">');
        $dayElement.addClass('calendar-day'); // Append events container

        var $eventsContainer = $('<div class="calendar-events-container">');
        $dayElement.append($eventsContainer); // Append day

        var $dayOfMonthElement = $('<span class="date">');
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
          month: month
        }).daysInMonth();
      }
      /**
       * Create days on current month
       * @param {number} year
       * @param {number} month
       * @return {array} day objects
       */


      function createDaysForCurrentMonth(year, month) {
        return _toConsumableArray(Array(getNumberOfDaysInMonth(year, month))).map(function (day, index) {
          return {
            date: moment({
              year: year,
              month: month,
              day: index + 1
            }).format('YYYY-MM-DD'),
            dayOfMonth: index + 1,
            month: month,
            isCurrentMonth: true
          };
        });
      }
      /**
       * Create days in previous month
       * @param {number} year
       * @param {number} month
       * @return {array} day objects
       */


      function createDaysForPreviousMonth(year, month) {
        var firstDayOfTheMonthWeekday = getWeekday(currentMonthDays[0].date);
        var previousMonth = moment({
          year: year,
          month: month - 1
        }); // Cover first day of the month
        //  being sunday (firstDayOfTheMonthWeekday === 0)

        var visibleNumberOfDaysFromPreviousMonth = firstDayOfTheMonthWeekday ? firstDayOfTheMonthWeekday - 1 : 6;
        var previousMonthLastMondayDayOfMonth = moment(currentMonthDays[0].date).subtract(visibleNumberOfDaysFromPreviousMonth, 'day').date();
        return _toConsumableArray(Array(visibleNumberOfDaysFromPreviousMonth)).map(function (day, index) {
          return {
            date: moment({
              year: previousMonth.year(),
              month: previousMonth.month(),
              day: previousMonthLastMondayDayOfMonth + index
            }).format('YYYY-MM-DD'),
            month: previousMonth.month(),
            dayOfMonth: previousMonthLastMondayDayOfMonth + index,
            isCurrentMonth: false
          };
        });
      }
      /**
       * Create days in next month
       * @param {number} year
       * @param {number} month
       * @return {array} day objects
       */


      function createDaysForNextMonth(year, month) {
        var lastDayOfTheMonthWeekday = getWeekday({
          year: year,
          month: month - 1,
          date: currentMonthDays.length
        });
        var nextMonth = moment({
          year: year,
          month: month
        });
        var visibleNumberOfDaysFromNextMonth = lastDayOfTheMonthWeekday ? 7 - lastDayOfTheMonthWeekday : lastDayOfTheMonthWeekday;
        return _toConsumableArray(Array(visibleNumberOfDaysFromNextMonth)).map(function (day, index) {
          return {
            date: moment({
              year: nextMonth.year(),
              month: nextMonth.month(),
              day: index + 1
            }).format('YYYY-MM-DD'),
            month: nextMonth.month(),
            dayOfMonth: index + 1,
            isCurrentMonth: false
          };
        });
      }

      addEventsToCalendar = function addEventsToCalendar(events) {
        events.forEach(function (event) {
          var startDate = moment(event.startDate).startOf('date'); // Check if event is an all day
          // (startDate 00:00 day 1, endDate 00:00 day after last day)

          var allDayEvent = moment(event.startDate).isSame(startDate) && moment(event.endDate).isSame(moment(event.endDate).startOf('date'));
          event.allDay = allDayEvent;
          var endDate = allDayEvent ? moment(event.endDate).startOf('date').subtract(1, 'd') : moment(event.endDate).startOf('date');
          var eventTotalDays = endDate.diff(startDate, 'days') + 1; // Days loop

          var currentDayOfEvent = 1;
          var momentAux = moment(startDate);

          while (momentAux <= endDate) {
            _addEventToDay2(momentAux, event, eventTotalDays, currentDayOfEvent);

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


      function _addEventToDay2(date, event, eventTotalDays, currentDayOfEvent) {
        /**
         * Get container by date
         * @param {object} date
         * @return {object} Jquery container
         */
        function getEventContainer(date) {
          return $('#day_' + date.month() + '_' + date.date()).find('.calendar-events-container');
        }

        var $newEvent = $('<div class="calendar-event">');
        var $eventsContainer = getEventContainer(date);
        var weekDay = getWeekday(date);
        var eventDuration = 1; // Mark event as an all day

        if (event.allDay) {
          $newEvent.addClass('all-day');
        }

        if (eventTotalDays > 1) {
          // Multiple day event
          var htmlToAdd = '<span class="event-summary">' + event.summary + '</span>'; // Mark as multi event

          $newEvent.addClass('multi-day'); // Draw only on the first day of the event
          // or at the beggining of the weeks when it breaks

          if (currentDayOfEvent == 1 || weekDay == 1) {
            if (currentDayOfEvent == 1 && !event.allDay) {
              htmlToAdd = '<span class="event-time">' + moment(event.startDate).format(TIME_FORMAT) + '</span>' + htmlToAdd;
            } // Show event content in multiple days


            $newEvent.html(htmlToAdd); // Update element duration based on event duration

            eventDuration = eventTotalDays - (currentDayOfEvent - 1);
            var remainingDays = 8 - weekDay;

            if (eventDuration > remainingDays) {
              eventDuration = remainingDays;
              $newEvent.addClass('cropped-event-end');
            }

            if (currentDayOfEvent > 1) {
              $newEvent.addClass('cropped-event-start');
            }

            $newEvent.css('width', 'calc(' + eventDuration * 100 + '% + ' + eventDuration * 2 + 'px)');
          } else {
            // Multiple event that was extended, no need to be rendered
            return;
          }
        } else {
          // Single day event
          var _htmlToAdd2 = '<span class="event-summary">' + event.summary + '</span>'; // Mark event as an all day


          if (event.allDay) {
            $newEvent.addClass('all-day');
          } else {
            _htmlToAdd2 = '<span class="event-time">' + moment(event.startDate).format(TIME_FORMAT) + '</span>' + _htmlToAdd2;
          } // Add inner html


          $newEvent.html(_htmlToAdd2);
        } // Calculate event slot


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

        $eventsContainer.data('slots', slots); // Extend event to the remaining days

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

        $newEvent.css('top', 2 + 1.875 * daySlot + 'rem'); // Append event to container

        $newEvent.appendTo($eventsContainer); // Check container height and slots to show number of extra events

        updateContainerExtraEvents($eventsContainer, slots);
      }
    } // Create calendar


    applyStyleOptions(options);
    createCalendar();
    addEventsToCalendar(events);
    return true;
  }
});
/******/ })()
;
//# sourceMappingURL=xibo-calendar-render.js.map