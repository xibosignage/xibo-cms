/*
 * Copyright (C) 2022 Xibo Signage Ltd
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
  xiboCountdownRender: function(options, body) {
    // Check if the given input is a number/offset
    // or a date, and return the object
    const getDate = function(inputDate) {
      if (!isNaN(inputDate)) {
        return moment().add(inputDate, 's');
      } else if (moment(inputDate).isValid()) {
        return moment(inputDate);
      } else {
        console.error('Invalid Date/Time!!!');
      }
    };

    // Ge remaining time
    const getTimeRemaining = function(endtime) {
      const timeNow = moment().startOf('seconds');
      const duration =
        moment.duration(endtime.startOf('seconds').diff(timeNow));

      return {
        now: timeNow,
        total: Math.floor(duration.asMilliseconds()),
        seconds: Math.floor(duration.seconds()),
        secondsAll: Math.floor(duration.asSeconds()),
        minutes: Math.floor(duration.minutes()),
        minutesAll: Math.floor(duration.asMinutes()),
        hours: Math.floor(duration.hours()),
        hoursAll: Math.floor(duration.asHours()),
        days: Math.floor(duration.asDays()),
        weeks: Math.floor(duration.asWeeks()),
        months: Math.floor(duration.asMonths()),
        years: Math.floor(duration.asYears()),
      };
    };

    // Initialize clock
    const initialiseClock = function(clock, deadlineDate, warningDate) {
      const yearsSpan = clock.find('.years');
      const monthsSpan = clock.find('.months');
      const weeksSpan = clock.find('.weeks');
      const daysSpan = clock.find('.days');
      const hoursSpan = clock.find('.hours');
      const hoursAllSpan = clock.find('.hoursAll');
      const minutesSpan = clock.find('.minutes');
      const minutesAllSpan = clock.find('.minutesAll');
      const secondsSpan = clock.find('.seconds');
      const secondsAllSpan = clock.find('.secondsAll');

      // Remove warning and finished classes on init
      $(clock).removeClass('warning finished');

      // Clear interval if exists
      if (window.timeinterval) {
        clearInterval(window.timeinterval);
      }

      /**
       * Update clock
       */
      function updateClock() {
        const t = getTimeRemaining(deadlineDate);
        yearsSpan.html(t.years);
        monthsSpan.html(t.months);
        weeksSpan.html(t.weeks);
        daysSpan.html(t.days);
        hoursSpan.html(('0' + t.hours).slice(-2));
        hoursAllSpan.html(t.hoursAll);
        minutesSpan.html(('0' + t.minutes).slice(-2));
        minutesAllSpan.html(t.minutesAll);
        secondsSpan.html(('0' + t.seconds).slice(-2));
        secondsAllSpan.html(t.secondsAll);

        if (
          warningDate && deadlineDate.diff(warningDate) != 0 &&
          warningDate.diff(t.now) <= 0
        ) {
          $(clock).addClass('warning');
        }

        if (t.total <= 0) {
          $(clock).removeClass('warning').addClass('finished');
          clearInterval(window.timeinterval);
          yearsSpan.html('0');
          monthsSpan.html('0');
          daysSpan.html('0');
          hoursSpan.html('00');
          minutesSpan.html('00');
          secondsSpan.html('00');
          hoursAllSpan.html('0');
          minutesAllSpan.html('0');
          secondsAllSpan.html('0');
        }
      }

      updateClock(); // run function once at first to avoid delay

      // Update every second
      window.timeinterval = setInterval(updateClock, 1000);
    };

    // Default options
    const defaults = {
      duration: '30',
      previewWidth: 0,
      previewHeight: 0,
      scaleOverride: 0,
    };

    options = $.extend({}, defaults, options);

    // For each matched element
    this.each(function(_idx, _el) {
      // Calculate duration (use widget or given)
      let initDuration = options.duration;
      if (options.countdownType == 2) {
        initDuration = options.countdownDuration;
      } else if (options.countdownType == 3) {
        initDuration = options.countdownDate;
      }

      // Get deadline date
      const deadlineDate = getDate(initDuration);

      // Calculate warning duration ( use widget or given)
      let warningDuration = 0;
      if (options.countdownType == 1 || options.countdownType == 2) {
        warningDuration = options.countdownWarningDuration;
      } else if (options.countdownType == 3) {
        warningDuration = options.countdownWarningDate;
      }
      // Get warning date
      const warningDate =
        (
          warningDuration == 0 ||
          warningDuration == '' ||
          warningDuration == null
        ) ? false : getDate(warningDuration);

      // Initialise clock
      initialiseClock($(_el), deadlineDate, warningDate);
    });

    return $(this);
  },
});
