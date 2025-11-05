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

// Global calendar object
window.calendar = undefined;
window.calendarEvents = [];
window.agendaCalendar = undefined;
window.agendaEvents = [];
window.getJsonRequestControl = null;
let mymap;
let mymapmarker;

$(function() {
  // Set a listener for popover clicks
  //  http://stackoverflow.com/questions/11703093/how-to-dismiss-a-twitter-bootstrap-popover-by-clicking-outside
  $('body').on('click', function(e) {
    $('[data-toggle="popover"]').each(function(_idx, el) {
      // the 'is' for buttons that trigger popups
      // the 'has' for icons within a button that triggers a popup
      if (
        !$(el).is(e.target) &&
        $(el).has(e.target).length === 0 &&
        $('.popover').has(e.target).length === 0
      ) {
        $(el).popover('hide');
      }
    });
  });

  // Set a listener for type change event
  $('body').on(
    'change',
    '#scheduleCriteriaFields select[name="criteria_type[]"]',
    function(e) {
      // Capture the event target
      const $target = $(e.target);
      // Get the row where the type was changed
      const $row = $target.closest('.form-group');
      const selectedType = $target.val();
      const $fields = $('#scheduleCriteriaFields');
      const scheduleCriteria = $fields.data('scheduleCriteria');
      const criteriaDefaultCondition = $fields.data('criteriaDefaultCondition');

      if (scheduleCriteria) {
        if (selectedType === 'custom') {
          // Use a text input for metrics
          updateMetricsFieldAsText($row);
          // Use a text input for values
          updateValueFieldAsText($row);
          // Revert condition field to default
          updateConditionFieldToDefault($row, criteriaDefaultCondition);
        } else if (scheduleCriteria) {
          // Update metrics based on the selected type
          // and change text field to dropdown
          updateMetricsField($row, scheduleCriteria, selectedType);
        }
      }
    });

  // Function to update the metrics field based on the selected type
  function updateMetricsField($row, scheduleCriteria, type) {
    const $metricLabel = $row.find('label[for="criteria_metric[]"]');
    const $fields = $('#scheduleCriteriaFields');
    const criteriaDefaultCondition = $fields.data('criteriaDefaultCondition');
    let $metricField;
    let selectedMetric;

    // Check if scheduleCriteria has types
    if (scheduleCriteria.types) {
      const typeData = scheduleCriteria.types.find((t) => t.id === type);
      if (typeData) {
        const metrics = typeData.metrics;

        // If only one metric is available, show readonly input
        if (metrics.length === 1) {
          const metric = metrics[0];
          selectedMetric = metric.id;

          // initialize new text input fields
          $metricField = createReadonlyAndHiddenFields(
            metric.name,
            metric.id,
            'criteria_metric[]',
          );
        } else {
          // Create a dropdown for multiple metrics
          $metricField = $('<select>', {
            name: 'criteria_metric[]',
            class: 'form-control',
          });

          // populate the dropdown
          metrics.forEach(function(metric) {
            $metricField.append(new Option(metric.name, metric.id));
          });

          // Select the first metric by default
          selectedMetric = metrics[0]?.id;
          $metricField.val(selectedMetric);
        }

        // Find the selected metric's data
        const metricData = metrics.find((m) => m.id === selectedMetric);

        // Update the value field based on the selected metric
        if (metricData && metricData.values) {
          updateValueField($row, metricData.values);
        } else {
          updateValueFieldAsText($row);
        }

        // Update the condition field based on the selected metric
        if (metricData && metricData.conditions) {
          updateConditionField($row, metricData.conditions);
        } else {
          // If no conditions are defined, use the default conditions
          updateConditionFieldToDefault($row, criteriaDefaultCondition);
        }
      }
    }

    // Remove only input or select elements inside the label
    $metricLabel.find('input, select').remove();
    $metricLabel.append($metricField);
  }

  // Function to revert the metrics field to a text input
  function updateMetricsFieldAsText($row) {
    const $metricLabel = $row.find('label[for="criteria_metric[]"]');
    const $metricInput =
      $('<input class="form-control" name="criteria_metric[]"' +
        ' type="text" value="" />');

    // Remove only input or select elements inside the label
    $metricLabel.find('input, select').remove();
    $metricLabel.append($metricInput);
  }

  // Handle value field update outside of updateMetricsField
  $('body').on(
    'change',
    '#scheduleCriteriaFields select[name="criteria_metric[]"]', function(e) {
      // Capture the event target
      const $target = $(e.target);
      // Get the row where the metric was changed
      const $row = $target.closest('.form-group');
      const selectedMetric = $target.val();
      const $fields = $('#scheduleCriteriaFields');
      const scheduleCriteria = $fields.data('scheduleCriteria');
      const criteriaDefaultCondition = $fields.data('criteriaDefaultCondition');
      const selectedType = $row.find('select[name="criteria_type[]"]').val();

      if (scheduleCriteria && selectedType) {
        const typeData =
          scheduleCriteria.types.find((t) => t.id === selectedType);
        if (typeData) {
          const metrics = typeData.metrics;
          const metricData = metrics.find((m) => m.id === selectedMetric);

          // Update the value field based on the selected metric
          if (metricData && metricData.values) {
            updateValueField($row, metricData.values);
          } else {
            updateValueFieldAsText($row);
          }

          // Update the condition field based on the selected metric
          if (metricData && metricData.conditions) {
            updateConditionField($row, metricData.conditions);
          } else {
            // If no conditions are defined, use the default conditions
            updateConditionFieldToDefault($row, criteriaDefaultCondition);
          }
        }
      }
    });

  // Function to update the value field based on the selected metric's values
  function updateValueField($row, values) {
    const $valueLabel = $row.find('label[for="criteria_value[]"]');

    // Remove only input or select elements inside the label
    $valueLabel.find('input, select').remove();

    // Check the inputType in the values object
    if (values.inputType === 'dropdown') {
      // If only one metric is available, show readonly input
      if (values.values.length === 1) {
        const value = values.values[0];

        // append the text input fields
        $valueLabel.append(createReadonlyAndHiddenFields(
          value.title,
          value.id,
          'criteria_value[]',
        ));
      } else {
        // change to dropdown and populate
        const $valueSelect =
          $('<select class="form-control" name="criteria_value[]"></select>');

        values.values.forEach(function(value) {
          $valueSelect.append(new Option(value.title, value.id));
        });

        $valueLabel.append($valueSelect);
      }
    } else {
      // change to either text or number field
      let $valueInput;
      if (
        values.inputType === 'text' ||
        values.inputType === 'number' ||
        values.inputType === 'date'

      ) {
        $valueInput =
          $('<input class="form-control" name="criteria_value[]" type="' +
            values.inputType + '" value="" />');
      }
      $valueLabel.append($valueInput);
    }
  }

  // Function to revert the value field to a text input
  function updateValueFieldAsText($row) {
    const $valueLabel = $row.find('label[for="criteria_value[]"]');
    const $valueInput =
      $('<input class="form-control" name="criteria_value[]"' +
        ' type="text" value="" />');

    // Remove only input or select elements inside the label
    $valueLabel.find('input, select').remove();
    $valueLabel.append($valueInput);
  }

  // Calendar is initialised without any event_source
  // (that is changed when the selector is used)
  if (($('#Calendar').length > 0)) {
    // Get some options for the calendar
    const calendarOptions = $('#CalendarContainer').data();

    // Callback function to navigate to calendar date with the date picker
    const navigateToCalendarDate = function() {
      if (calendar != undefined) {
        const selectedDate =
          moment(moment($('#fromDt').val()).format(systemDateFormat));
        // Add event to the picker to update the calendar
        // only if the selected date is valid
        if (selectedDate.isValid()) {
          calendar.navigate('date', selectedDate);
        }
      }
    };

    const navigateToCalendarDatePicker = function() {
      calendar.navigate(
        'date',
        moment($('#dateInput input[data-input]').val()),
      );

      updateRangeFilter(
        $('#range'),
        $('#fromDt'),
        $('#toDt'),
        navigateToCalendarDate,
        {date: $('#dateInput input[data-input]').val()},
      );
    };

    $('#range').on('change', function() {
      updateRangeFilter(
        $('#range'),
        $('#fromDt'),
        $('#toDt'),
        navigateToCalendarDate,
      );
    });
    updateRangeFilter($('#range'), $('#fromDt'), $('#toDt'));

    // Select picker options
    let pickerOptions = {};

    if (calendarType == 'Jalali') {
      pickerOptions = {
        autoClose: true,
        altField: '#dateInputLink',
        altFieldFormatter: function(unixTime) {
          const newDate = moment.unix(unixTime / 1000);
          newDate.set('hour', 0);
          newDate.set('minute', 0);
          newDate.set('second', 0);
          return newDate.format(jsDateFormat);
        },
        onSelect: function() { },
        onHide: function() {
          // Trigger change after close
          $('#dateInput').trigger('change');
          $('#dateInputLink').trigger('change');
        },
      };
    } else if (calendarType == 'Gregorian') {
      pickerOptions = {
        wrap: true,
        altFormat: jsDateOnlyFormat,
      };
    }

    // Create the date input shortcut
    initDatePicker(
      $('#dateInput'),
      systemDateFormat,
      jsDateOnlyFormat,
      pickerOptions,
      navigateToCalendarDatePicker,
      false, // clear button
    );

    // Calendar options
    const options = {
      time_start: '00:00',
      time_end: '00:00',
      events_source: function() {
        return calendarEvents;
      },
      view: 'month',
      tmpl_path: function(name) {
        // Create underscore template
        // with translations and add to body
        if ($('#calendar-template-' + name).length === 0) {
          const $template = $('<div id="calendar-template-' + name + '">');

          $template.text(templates.calendar[name]({
            trans: translations.schedule.calendar,
          })).hide();
          $template.appendTo('body');
        }

        // Return name only
        // ( to work the same way in calendar and calendar-jalali)
        return 'calendar-template-' + name;
      },
      tmpl_cache: true,
      onBeforeEventsLoad: function(done) {
        if (typeof scheduleEvents === 'undefined') {
          console.log('Events not loaded, stop here!');
          return;
        }

        // Generate calendar events
        window.calendarEvents = generateCalendarEvents(
          scheduleEvents,
          this.options.position.start.getTime(),
          this.options.position.end.getTime(),
        );

        if (done != undefined) {
          done();
        }
      },
      onAfterEventsLoad: function(events) {
        if (!events) {
          return;
        }
      },
      onAfterViewLoad: function(view) {
        // Sync the date of the date picker to the current calendar date
        if (
          this.options.position.start != undefined &&
          this.options.position.start != ''
        ) {
          // Update timepicker
          updateDatePicker(
            $('#dateInput'),
            moment.unix(
              this.options.position.start.getTime() / 1000,
            ).format(systemDateFormat),
            systemDateFormat,
          );
        }

        // Manage calendar numbers and Agenda open
        $('.calendar-view .cal-month-day-number').each(function(_idx, el) {
          const $el = $(el);
          const date = $el.data('calDate');

          // If no events on that day
          // or agenda view not enabled
          if (
            $el.siblings('.events-list').length === 0 ||
            userAgendaViewEnabled != '1'
          ) {
            $el.removeAttr('title').off('click').css('cursor', 'auto');
          } else {
            $el.attr('title', translations.schedule.calendar.openAgenda)
              .css('cursor', 'pointer')
              .off('click').on('click', function(e) {
                e.stopPropagation();
                openAgendaModal(date);
              });
          }
        });

        $('.calendar-view').on('calendar.eventListReady',
          function(e, dateValue) {
            // Show Agenda button if feature is enabled
            if (userAgendaViewEnabled == '1') {
              $(e.currentTarget).find('.cal-agenda-button')
                .addClass('is-visible')
                .off('click.agenda')
                .on('click.agenda', function(e) {
                  e.stopPropagation();
                  openAgendaModal(dateValue);
                });
            }
          });

        // Hook up any pop-overs (for small events)
        $('[data-toggle="popover"]').popover({
          trigger: 'manual',
          html: true,
          placement: 'bottom',
          content: function() {
            return $(this).html();
          },
        }).on('mouseenter', function(ev) {
          const self = ev.currentTarget;

          // Hide all other popover
          $('[data-toggle="popover"]').not(self).popover('hide');

          // Show this popover
          $(self).popover('show');

          // Hide popover when mouse leaves it
          $('.popover').off('mouseleave').on('mouseleave', function() {
            $(self).popover('hide');
          });
        }).on('shown.bs.popover', function(ev) {
          const source = $(ev.currentTarget);
          const popover = source.attr('aria-describedby');

          $('#' + popover + ' a').on('click', function(e) {
            e.preventDefault();
            XiboFormRender(source);
            source.popover('hide');
          });
        });

        if (typeof this.getTitle === 'function') {
          let title = this.getTitle();

          if ($('#range').val() == 'custom') {
            const dateFormat = 'HH:mm D MMMM YYYY';
            const fromDate = ($('#fromDt').val()) ?
              moment($('#fromDt').val()).format(dateFormat) :
              translations.schedule.calendar.customFromToAlways;
            const toDate = ($('#toDt').val()) ?
              moment($('#toDt').val()).format(dateFormat) :
              translations.schedule.calendar.customFromToAlways;
            title = (!$('#fromDt').val() && !$('#toDt').val()) ?
              translations.schedule.calendar.customFromToAlways :
              translations.schedule.calendar.customFromTo
                .replace(':from', fromDate)
                .replace(':to', toDate);
          }
          $('h1.page-header').text(title);
        }

        $('.btn-group button').removeClass('active');
        $('button[data-calendar-view="' + view + '"]').addClass('active');
      },
      language: calendarLanguage,
    };

    options.type = calendarOptions.calendarType;
    calendar = $('#Calendar').calendar(options);
  }
});


// Generate all calendar events ( and reccurent events )
// based on schedule events
function generateCalendarEvents(scheduleEvents, viewStartMs, viewEndMs) {
  const allOccurrences = [];
  const deepClone = (obj) => JSON.parse(JSON.stringify(obj));

  const generateRecurrences = (sourceEv) => {
    if (!sourceEv.recurrenceType || !sourceEv.recurrenceDetail) {
      return [];
    }

    const generated = [];
    const interval = parseInt(sourceEv.recurrenceDetail, 10);
    if (interval <= 0) return [];

    const originalStart = moment(sourceEv.fromDt * 1000);
    const duration = moment.duration((sourceEv.toDt - sourceEv.fromDt) * 1000);
    const rangeEnd = sourceEv.recurrenceRange ?
      moment(sourceEv.recurrenceRange * 1000) :
      moment('9999-12-31'); // Infinity

    const unitMap =
      {Minute: 'm', Hour: 'h', Day: 'd', Month: 'M', Year: 'y'};

    // Check if events repeat more than once a day
    // so we can optimize them
    const isMinuteRepeat = sourceEv.recurrenceType === 'Minute';
    const isHourRepeat = sourceEv.recurrenceType === 'Hour';

    const isHighFrequency =
      (isMinuteRepeat && interval < 1440) ||
      (isHourRepeat && interval < 24);

    // If frequency is high (minute or hour), only generate one day
    // for the monthly view
    if (isHighFrequency && calendar.options.view === 'month') {
      let currentDayIter = originalStart.clone().startOf('day');

      // Find the first valid day
      if (currentDayIter.isBefore(moment(viewStartMs).startOf('day'))) {
        currentDayIter = moment(viewStartMs).startOf('day');
      }

      // Loop day by day
      while (
        currentDayIter.isBefore(moment(viewEndMs)) &&
        currentDayIter.isBefore(rangeEnd)
      ) {
        // Find the time of the first event on this day
        let eventMoment = currentDayIter.clone().set({
          hour: originalStart.hour(),
          minute: originalStart.minute(),
          second: originalStart.second(),
        });

        if (eventMoment.isBefore(originalStart)) {
          eventMoment = originalStart.clone();
        }

        if (
          eventMoment.isSame(currentDayIter, 'day') &&
          eventMoment.isBefore(rangeEnd)
        ) {
          const startMs = eventMoment.unix() * 1000;
          const endMs = (eventMoment.unix() + duration.asSeconds()) * 1000;

          if (startMs < viewEndMs && endMs > viewStartMs) {
            const clone = deepClone(sourceEv);
            clone.fromDt = eventMoment.unix();
            clone.toDt = eventMoment.clone().add(duration).unix();
            clone.isHighFrequency = true;
            generated.push(clone);
          }
        }

        // Advance one day
        currentDayIter.add(1, 'day');
      }
      return generated;
    } else {
      // Generate normal occurrences
      let currentMoment = originalStart.clone();

      while (
        currentMoment.isBefore(moment(viewEndMs)) &&
        currentMoment.isBefore(rangeEnd)
      ) {
        const isWeekly = sourceEv.recurrenceType === 'Week' &&
          sourceEv.recurrenceRepeatsOn;

        if (isWeekly) {
          const days = sourceEv.recurrenceRepeatsOn.split(',').map(Number);
          const weekStart = currentMoment.clone().startOf('isoWeek');

          for (let i = 0; i < 7; i++) {
            const dayInWeek = weekStart.clone().add(i, 'days');
            if (!days.includes(dayInWeek.isoWeekday())) continue;

            const occStart = dayInWeek.set({
              hour: originalStart.hour(),
              minute: originalStart.minute(),
              second: originalStart.second(),
            });

            if (
              occStart.isAfter(originalStart) &&
              occStart.isBefore(rangeEnd)
            ) {
              const clone = deepClone(sourceEv);
              clone.fromDt = occStart.unix();
              clone.toDt = occStart.clone().add(duration).unix();
              generated.push(clone);
            }
          }

          currentMoment.add(interval, 'weeks');
          continue;
        }

        let nextMoment;
        let eventMoment;
        let isValidOccurrence = true;

        if (sourceEv.recurrenceType === 'Month') {
          const nextMonthBase = currentMoment.clone().add(interval, 'M');

          if (sourceEv.recurrenceMonthlyRepeatsOn === 1) {
            // Repeat on the same weekday of the same original nth week
            const startOfMonth = originalStart.clone().startOf('month');
            const weekNumber = originalStart.diff(startOfMonth, 'weeks') + 1;
            const originalWeekday = originalStart.isoWeekday();

            const firstDayOfNextMonth = nextMonthBase.clone().startOf('month');
            const firstDayWeekday = firstDayOfNextMonth.isoWeekday();

            const offset = (originalWeekday - firstDayWeekday + 7) % 7;

            // Calculate the nth ocorrence
            eventMoment = firstDayOfNextMonth
              .clone()
              .add(offset, 'days')
              .add(weekNumber - 1, 'weeks')
              .set({ // set same time
                hour: originalStart.hour(),
                minute: originalStart.minute(),
                second: originalStart.second(),
              });

            // If the new date is after the target month, skip it
            if (eventMoment.month() !== nextMonthBase.month()) {
              isValidOccurrence = false;

              // Don't use the spilled-over date
              // nextMoment is a simple month advance
              const originalDay = originalStart.date();
              nextMoment = nextMonthBase;
              const maxDay = nextMoment.daysInMonth();
              nextMoment.date(Math.min(originalDay, maxDay));
            } else {
              // It's valid. The event date is also the next loop date.
              nextMoment = eventMoment.clone();
            }
          } else {
            // Repeat on the same day of the month
            const originalDay = originalStart.date();
            nextMoment = nextMonthBase;

            // Clamp to the last valid day of the month if needed
            const maxDay = nextMoment.daysInMonth();
            nextMoment.date(Math.min(originalDay, maxDay));
            eventMoment = nextMoment.clone();
          }
        } else {
          nextMoment = currentMoment.clone()
            .add(interval, unitMap[sourceEv.recurrenceType]);

          if (!nextMoment || nextMoment.isSameOrBefore(currentMoment)) {
            break;
          }
          eventMoment = nextMoment.clone();
        }

        currentMoment = nextMoment;

        if (eventMoment.isBefore(rangeEnd) && isValidOccurrence) {
          const clone = deepClone(sourceEv);
          clone.fromDt = eventMoment.unix();
          clone.toDt = eventMoment.clone().add(duration).unix();
          generated.push(clone);
        }
      }
      return generated;
    }
  };

  // Generate schedule events array
  scheduleEvents.forEach((sourceEv) => {
    allOccurrences.push(sourceEv);

    // If it's a recurring event, also generate recurrences
    if (sourceEv.recurringEvent) {
      allOccurrences.push(...generateRecurrences(sourceEv));
    }
  });

  const formatCalendarEvent = (rawEv) => {
    const startMs = rawEv.fromDt * 1000;
    const endMs = rawEv.toDt * 1000;

    const editButton =
      rawEv.buttons.find((b) => b.id === 'schedule_button_edit');
    const eventUrl = editButton ?
      editButton.url :
      `/schedule/form/edit/${rawEv.eventId}`;
    const titleText = ((rawEv.name) ? '"' + rawEv.name + '" - ' : '') +
      translations.schedule.calendar.eventOnDisplay
        .replace(':event', rawEv.parentCampaignName || rawEv.campaign)
        .replace(
          ':display',
          rawEv.displayGroupList || rawEv.displayGroups[0].displayGroup,
        );

    return {
      id: rawEv.eventId,
      title: titleText,
      url: eventUrl,
      start: startMs,
      end: endMs,
      sameDay: moment(startMs).isSame(moment(endMs), 'day'),
      editable: rawEv.isEditable,
      event: rawEv,
      scheduleEvent: {
        fromDt: moment(startMs).format(jsDateFormat),
        toDt: moment(endMs).format(jsDateFormat),
      },
      recurringEvent: rawEv.recurringEvent,
    };
  };

  // Filter events for the current view
  const filteredOccurrences = allOccurrences.filter((ev) => {
    const startMs = ev.fromDt * 1000;
    const endMs = ev.toDt * 1000;
    return startMs < viewEndMs && endMs > viewStartMs;
  });

  // Group events by day
  const groupedEvents = new Map();
  filteredOccurrences.forEach((ev) => {
    const dayKey = moment(ev.fromDt * 1000).startOf('day').format('YYYY-MM-DD');
    // Group by the original eventId and the day
    const groupKey = `${ev.eventId}-${dayKey}`;

    if (!groupedEvents.has(groupKey)) {
      groupedEvents.set(groupKey, []);
    }
    groupedEvents.get(groupKey).push(ev);
  });

  // Add only 1 occurrence per day of the same event group
  const finalEvents = [];
  for (const eventsOnDay of groupedEvents.values()) {
    // Get the first event of the group
    const rawEv = eventsOnDay[0];
    if (!rawEv) {
      continue;
    }

    const hasMultiple = eventsOnDay.length > 1;
    const isHighFrequency = rawEv.isHighFrequency;

    // Format just the first event
    const formattedEvent = formatCalendarEvent(rawEv);

    // If there are multiple events for this day AND it's a recurring event
    if (hasMultiple || isHighFrequency && rawEv.recurringEvent) {
      try {
        formattedEvent.title += ' - ' +
          translations.schedule.calendar.eventDescripiton
            .replace('${recurrenceDetail}', rawEv.recurrenceDetail)
            .replace('${recurrenceType}', rawEv.recurrenceType.toLowerCase())
            .replace(
              '${recurrenceTypeExtra}',
              (rawEv.recurrenceDetail > 1) ? 's' : '');
      } catch (e) {
        console.error('Error generating recurrence description:', e, rawEv);
      }
    }

    // Add only the first formatted event
    finalEvents.push(formattedEvent);
  }

  return finalEvents;
}

// Creates a readonly text input for display and a hidden input for submission.
function createReadonlyAndHiddenFields(
  readonlyValue,
  hiddenValue,
  hiddenName,
) {
  // Create readonly input for display
  const $readonlyInput = $('<input>', {
    type: 'text',
    value: readonlyValue,
    readonly: true,
    class: 'form-control',
  }).css('background-color', '#fff');

  // Create hidden input for submission
  const $hiddenInput = $('<input>', {
    type: 'hidden',
    name: hiddenName,
    value: hiddenValue,
  });

  // Return both inputs
  return $readonlyInput.add($hiddenInput);
}

// Function to update the Condition dropdown
// according to the selected metric's available condition
function updateConditionField($row, conditions, selectedCondition) {
  const $conditionLabel = $row.find('label[for="criteria_condition[]"]');
  $conditionLabel.empty();

  if (conditions.length === 1) {
    const condition = conditions[0];

    // Create and append the text fields
    $conditionLabel.append(createReadonlyAndHiddenFields(
      condition.name,
      condition.id,
      'criteria_condition[]',
    ));
  } else {
    // Initialize a new dropdown
    const $newSelect = $('<select>', {
      name: 'criteria_condition[]',
      class: 'form-control',
    });

    // Populate with provided conditions
    conditions.forEach((condition) => {
      $newSelect.append(
        $('<option>', {value: condition.id}).text(condition.name),
      );
    });

    // Pre-select the condition if provided
    // otherwise select the first condition
    $newSelect.val(selectedCondition || conditions[0]?.id || '');

    $conditionLabel.append($newSelect);
  }
}

// Function to revert the Condition dropdown to its default selection
function updateConditionFieldToDefault(
  $row,
  defaultConditions,
  selectedCondition,
) {
  const $conditionLabel = $row.find('label[for="criteria_condition[]"]');
  $conditionLabel.empty();

  // Initialize a new dropdown
  const $newSelect = $('<select>', {
    name: 'criteria_condition[]',
    class: 'form-control',
  });

  // Populate with default conditions
  defaultConditions.forEach((condition) => {
    $newSelect.append(
      $('<option>', {value: condition.id}).text(condition.name),
    );
  });

  // Pre-select the condition if provided
  // otherwise select the first condition
  $newSelect.val(selectedCondition || defaultConditions[0]?.id || '');

  $conditionLabel.append($newSelect);
}

/**
 * Callback for the schedule form
 */
window.setupScheduleForm = function(dialog) {
  const $dialog = $(dialog);
  // console.log("Setup schedule form");

  // Form steps
  const stepWizard = $dialog.find('.stepwizard');
  if (stepWizard.length > 0) {
    // Form step validation
    const validateStep = function(step) {
      let isValid = true;
      let errorMessage = '';

      // Remove previous errors
      $(dialog).find('.form-error').remove();
      $(dialog).find('.form-group').removeClass('has-error');

      if (step === 1) {
        // Content
        const eventType = Number($(dialog).find('#eventTypeId').val());

        // Validate fields map for all that require
        const validateMap = [
          {
            type: [1, 3, 4, 5],
            fieldQuery: '#campaignId',
            errorProperty: 'layout',
          },
          {
            type: [2],
            fieldQuery: '#commandId',
            errorProperty: 'command',
          },
          {
            type: [7],
            fieldQuery: '#mediaId',
            errorProperty: 'videoImage',
          },
          {
            type: [8],
            fieldQuery: '#playlistId',
            errorProperty: 'playlist',
          },
          {
            type: [10],
            fieldQuery: '#dataSetId',
            errorProperty: 'dataset',
          },
        ];

        // Validate all fields that have the same type of validation
        validateMap.forEach((val) => {
          const fieldVal = $(dialog).find(val.fieldQuery).val();
          if (
            val.type.indexOf(eventType) != -1 &&
            (fieldVal === '' || fieldVal === null)
          ) {
            errorMessage =
              translations.schedule.stepWizard.error[val.errorProperty];
            $(dialog).find(val.fieldQuery).parents('.form-group')
              .addClass('has-error');
            isValid = false;
          }
        });

        // Special fields

        // Sync- validate sync group field and layouts
        if (eventType === 9) {
          // Validate sync group
          const syncGroupId = $(dialog).find('#syncGroupId').val();
          if (syncGroupId == null || syncGroupId == '') {
            errorMessage = translations.schedule.stepWizard.error.syncGroup;
            $(dialog).find('#syncGroupId').parents('.form-group')
              .addClass('has-error');
            isValid = false;
          } else {
            // Validate layouts
            $(dialog).find('#contentSelectorTable select.syncContentSelect')
              .each((_idx, layout) => {
                const layoutVal = $(layout).val();
                if (layoutVal == null || layoutVal == '') {
                  errorMessage =
                    translations.schedule.stepWizard.error.syncGroupLayouts;
                  $(layout).parents('.form-group.pagedSelect')
                    .addClass('has-error');
                  isValid = false;
                }
              });
          }
        }

        // If event of action type, validate action field
        if (eventType === 6) {
          const $triggerCode = $(dialog).find('#actionTriggerCode');
          const $actionType = $(dialog).find('#actionType');
          const $actionLayoutCode = $(dialog).find('#actionLayoutCode');
          const $actionCommand = $(dialog).find('#commandId');

          if ($triggerCode.val() === '') {
            errorMessage =
              translations.schedule.stepWizard.error.actionTriggerCode;
            $triggerCode.parents('.form-group').addClass('has-error');
            isValid = false;
          }

          if (
            $actionType.val() === 'navLayout' &&
            $actionLayoutCode.val() === null
          ) {
            errorMessage =
              translations.schedule.stepWizard.error.actionLayoutCode;
            $actionLayoutCode.parents('.form-group').addClass('has-error');
            isValid = false;
          }

          if (
            $actionType.val() === 'command' &&
            $actionCommand.val() === null
          ) {
            errorMessage =
              translations.schedule.stepWizard.error.actionCommand;
            $actionCommand.parents('.form-group').addClass('has-error');
            isValid = false;
          }
        }
      } else if (step === 2) {
        // Displays
        const $displays = $(dialog).find('[name="displayGroupIds[]"]');
        if (!(Array.isArray($displays.val()) && $displays.val().length > 0)) {
          errorMessage = translations.schedule.stepWizard.error.displays;
          $displays.parents('.form-group').addClass('has-error');
          isValid = false;
        }
      } else if (step === 3) {
        // Time
        const isCustom = $(dialog).find('#dayPartId :selected')
          .data('isCustom') == 1;
        const isAlways = $(dialog).find('#dayPartId :selected')
          .data('isAlways') == 1;
        const relativeTimeChecked =
          $(dialog).find('#relativeTime').is(':checked');
        const $fromDt = $(dialog).find('#fromDt');
        const $toDt = $(dialog).find('#toDt');
        const $hours = $(dialog).find('#hours');
        const $minutes = $(dialog).find('#minutes');
        const eventType = $(dialog).find('#eventTypeId').val();

        if (isCustom) {
          // Custom
          if (!relativeTimeChecked) {
            if ($fromDt.val() === '') {
              errorMessage = translations.schedule.stepWizard.error.timeStart;
              $fromDt.parents('.form-group').addClass('has-error');
              isValid = false;
            }

            // Command doesn't need end date
            if ($toDt.val() === '' && eventType != 2) {
              errorMessage = translations.schedule.stepWizard.error.timeEnd;
              $toDt.parents('.form-group').addClass('has-error');
              isValid = false;
            }
          } else {
            // Relative time
            if ($hours.val() === '' && $minutes.val() === '') {
              errorMessage =
                translations.schedule.stepWizard.error.relativeTime;
              $hours.parents('.form-group').addClass('has-error');
              $minutes.parents('.form-group').addClass('has-error');
              isValid = false;
            }
          }
        } else if (!isAlways) {
          // Daypart
          if ($fromDt.val() === '') {
            errorMessage = translations.schedule.stepWizard.error.timeStart;
            $fromDt.parents('.form-group').addClass('has-error');
            isValid = false;
          }
        }
      }

      // Show form error
      if (!isValid) {
        SystemMessageInline(errorMessage, dialog);
      }

      return isValid;
    };

    const $navListItems =
      $dialog.find('div.stepwizard-row div.stepwizard-step a');
    const $allWells = $dialog.find('.stepwizard-content');
    const isAddForm = $dialog.find('form').is('#scheduleAddForm');
    const $modalFooter = $dialog.find('.modal-footer');
    const $saveButton = $dialog.find('.save-button');

    $navListItems.on('click', function(e) {
      e.preventDefault();
      const $target = $($(e.currentTarget).attr('href'));
      const $item = $(e.currentTarget);

      if (!$item.attr('disabled') && !$item.hasClass('btn-success')) {
        // Set all step links to inactive
        $navListItems
          .removeClass('btn-success')
          .addClass('btn-default');

        // Activate this specific one
        $item.addClass('btn-success');

        // Hide all the panels and show this specific one
        $allWells.hide();
        $target.show();
        $target.find('input:eq(0)').focus();

        // Set the active panel on the links
        stepWizard.data('active', $target.prop('id'));

        // Is the next action to finish?
        // Only for add form
        if (isAddForm) {
          if ($target.data('next') === 'finished') {
            $nextButton.hide();
            $saveButton.show();
          } else {
            $nextButton.show();
            $saveButton.hide();
          }
        }
      }
    });

    const $deleteButton =
      $('<a id="scheduleDeleteButton" class="btn btn-danger">')
        .html(translations.schedule.stepWizard.delete)
        .on('click', function(e) {
          e.preventDefault();
          XiboSwapDialog($(dialog).find('form').data('deleteUrl'));
        });
    $modalFooter.prepend($deleteButton);

    const $nextButton =
      $('<a id="schedule-steper-next-button" class="btn btn-primary">')
        .html(translations.schedule.stepWizard.next)
        .on('click', function(e) {
          e.preventDefault();
          const steps = $(dialog).find('.stepwizard');
          const curStep = $(dialog).find('#' + steps.data('active'));
          // If sync event and step 1, move to step 3
          const nextStep =
            (
              $(curStep).data('step') === 1 &&
              $('#eventTypeId', dialog).val() === '9'
            ) ?
              'schedule-step-3' :
              curStep.data('next');
          const nextStepWizard =
            steps.find('a[href=\'#' + nextStep + '\']');

          // Validate current step first
          if (validateStep(curStep.data('step'))) {
            nextStepWizard.removeAttr('disabled').trigger('click');
          }
        });
    $modalFooter.append($nextButton);

    if (isAddForm) {
      // Hide save and delete button for add form
      $saveButton.hide();
      $deleteButton.hide();
    } else {
      // Hide next button for edit form
      $nextButton.hide();
    }
  }

  // geo schedule
  const $geoAware = $('#isGeoAware');
  let isGeoAware = $geoAware.is(':checked');
  const $form = dialog.find('form');

  // Configure the schedule criteria fields.
  configureCriteriaFields(dialog);

  if (isGeoAware) {
    // without this additional check the map will not load correctly
    // it should be initialised when we are on the Geo Location tab
    $('.nav-tabs a').on('shown.bs.tab', function(event) {
      if ($(event.target).text() === 'Geo Location') {
        $('#geoScheduleMap').removeClass('d-none');
        generateGeoMap($form);
      }
    });
  }

  // hide/show and generate map according to the Geo Schedule checkbox value
  $geoAware.on('change', function() {
    isGeoAware = $('#isGeoAware').is(':checked');

    if (isGeoAware) {
      $('#geoScheduleMap').removeClass('d-none');
      generateGeoMap($form);
    } else {
      $('#geoScheduleMap').addClass('d-none');
    }
  });

  // Share of voice
  const shareOfVoice = $('#shareOfVoice');
  const shareOfVoicePercentage = $('#shareOfVoicePercentage');
  shareOfVoice.on('change paste keyup', function() {
    convertShareOfVoice(shareOfVoice.val());
  });

  shareOfVoicePercentage.on('change paste keyup', function() {
    const percentage = shareOfVoicePercentage.val();
    const conversion = Math.round((3600 * Number(percentage)) / 100);
    shareOfVoice.val(conversion);
  });


  const convertShareOfVoice = function(seconds) {
    const conversion = (100 * seconds) / 3600;
    shareOfVoicePercentage.val(conversion.toFixed(2));
  };

  convertShareOfVoice(shareOfVoice.val());

  setupSelectForSchedule(dialog);

  $('select[name="recurrenceRepeatsOn[]"]', dialog).select2({
    width: '100%',
  });

  // Hide/Show form elements according to the selected options
  // Initial state of the components
  processScheduleFormElements($('#recurrenceType', dialog), dialog);
  processScheduleFormElements($('#eventTypeId', dialog), dialog);
  processScheduleFormElements($('#campaignId', dialog), dialog);
  processScheduleFormElements($('#actionType', dialog), dialog);
  processScheduleFormElements($('#relativeTime', dialog), dialog);

  // Events on change
  $('#recurrenceType, ' +
    '#eventTypeId, ' +
    '#dayPartId, ' +
    '#campaignId, ' +
    '#actionType, ' +
    '#fullScreenCampaignId, ' +
    '#relativeTime, ' +
    '#syncTimezone', dialog)
    .on('change', function(ev) {
      processScheduleFormElements($(ev.currentTarget), dialog);
    });

  const evaluateDates = _.debounce(function() {
    scheduleEvaluateRelativeDateTime($form);
  }, 500);

  // Bind to the H:i:s fields
  $form.find('#hours').on('change keyup', evaluateDates);
  $form.find('#minutes').on('change keyup', evaluateDates);
  $form.find('#seconds').on('change keyup', evaluateDates);

  // Handle the repeating monthly selector
  // Run when the tab changes
  $('a[data-toggle="tab"]', dialog).on('shown.bs.tab', function(e) {
    const nth = function(n) {
      return n + (['st', 'nd', 'rd'][((n + 90) % 100 - 10) % 10 - 1] || 'th');
    };
    const $fromDt = $(dialog).find('input[name=fromDt]');
    const fromDt =
      ($fromDt.val() === null || $fromDt.val() === '') ?
        moment() : moment($fromDt.val());
    const $recurrenceMonthlyRepeatsOn =
      $(dialog).find('select[name=recurrenceMonthlyRepeatsOn]');
    const $dayOption =
      $('<option value="0">' + $recurrenceMonthlyRepeatsOn.data('transDay')
        .replace('[DAY]', fromDt.format('Do')) + '</option>');
    const $weekdayOption =
      $('<option value="1">' + $recurrenceMonthlyRepeatsOn.data('transWeekday')
        .replace('[POSITION]', nth(Math.ceil(fromDt.date() / 7)))
        .replace('[WEEKDAY]', fromDt.format('dddd')) + '</option>');

    $recurrenceMonthlyRepeatsOn.find('option').remove().end()
      .append($dayOption).append($weekdayOption)
      .val($recurrenceMonthlyRepeatsOn.data('value'));
  });

  // Bind to the dialog submit
  // this should make any changes to the form needed before we submit.
  // eslint-disable-next-line max-len
  $('#scheduleAddForm, #scheduleEditForm, #scheduleDeleteForm, #scheduleRecurrenceDeleteForm')
    .on('submit', function(e) {
      e.preventDefault();

      // eslint-disable-next-line no-invalid-this
      const $form = $(this);
      const data = $form.serializeObject();

      // Criteria fields
      processCriteriaFields($form, data);

      $.ajax({
        type: $form.attr('method'),
        url: $form.attr('action'),
        data: data,
        cache: false,
        dataType: 'json',
        success: function(xhr, textStatus, error) {
          XiboSubmitResponse(xhr, $form);

          if (xhr.success && calendar !== undefined) {
            // Clear option cache
            calendar.options['clearCache'] = true;
            // Make sure we remove mini layout preview
            destroyMiniLayoutPreview();
            // Reload the Calendar
            calendar.view();
          }
        },
      });
    });

  // Popover
  $(dialog).find('[data-toggle="popover"]').popover();

  // Post processing on the schedule-edit form.
  const $scheduleEditForm =
    $(dialog).find('#scheduleEditForm');
  if ($scheduleEditForm.length > 0) {
    // Add a button for duplicating this event
    let $button = $('<button>').addClass('btn btn-info')
      .attr('id', 'scheduleDuplateButton')
      .html(translations.duplicate)
      .on('click', function() {
        duplicateScheduledEvent($scheduleEditForm);
      });

    $(dialog).find('.modal-footer').prepend($button);

    // Update the date/times for this event in the correct format.
    $scheduleEditForm.find('#instanceStartDate').html(
      moment($scheduleEditForm.data().eventStart, 'X').format(jsDateFormat));
    $scheduleEditForm.find('#instanceEndDate').html(
      moment($scheduleEditForm.data().eventEnd, 'X').format(jsDateFormat));

    // Add a button for deleting single recurring event
    $button = $('<button>').addClass('btn btn-primary')
      .attr('id', 'scheduleRecurringDeleteButton')
      .html(translations.deleteRecurring)
      .on('click', function() {
        deleteRecurringScheduledEvent(
          $scheduleEditForm.data('eventId'),
          $scheduleEditForm.data('eventStart'),
          $scheduleEditForm.data('eventEnd'),
        );
      });

    $(dialog).find('#recurringInfo').prepend($button);
  }

  // Fullscreen schedule fields
  const updateFSFields = function() {
    const eventType = $('#eventTypeId', dialog).val();
    const mediaId = $('#mediaId', dialog).val();
    const playlistId = $('#playlistId', dialog).val();

    if (eventType == '7' && mediaId) {
      // If media type, with media
      // Show all FS controls
      $('.media-control-option', dialog).removeClass('hidden');
      $('.fs-control-option', dialog).removeClass('hidden');
    } else if (eventType == '8' && playlistId) {
      // If playlist type, with playlist
      // Show playlist controls, but hide media ones
      $('.fs-control-option', dialog).removeClass('hidden');
      $('.media-control-option', dialog).addClass('hidden');
    } else {
      $('.media-control-option', dialog).addClass('hidden');
      $('.fs-control-option', dialog).addClass('hidden');
    }
  };
  // Update when changing target, or event type
  $('#mediaId, #playlistId, #eventTypeId', dialog)
    .on('select2:select, change', updateFSFields);
  // Run on start
  updateFSFields();

  configReminderFields($(dialog));
};

const deleteRecurringScheduledEvent = function(id, eventStart, eventEnd) {
  const url = scheduleRecurrenceDeleteUrl.replace(':id', id);
  const data = {
    eventStart: eventStart,
    eventEnd: eventEnd,
  };
  XiboSwapDialog(url, data);
};

window.beforeSubmitScheduleForm = function(form) {
  const checkboxes = form.find('[name="reminder_isEmail[]"]');

  checkboxes.each(function(index, el) {
    $(el).parent().find('[type="hidden"]')
      .val($(el).is(':checked') ? '1' : '0');
  });

  $('[data-toggle="popover"]').popover();
  form.submit();
};

/**
 * Create or fetch a full screen layout
 * for selected media or playlist
 * accept callback function.
 *
 * @param {object} form
 * @param {function} callBack
 * @param {boolean} populateHiddenFields
 */
window.fullscreenBeforeSubmit = function(
  form, callBack, populateHiddenFields = true,
) {
  const eventTypeId = form.find('[name="eventTypeId"]').val();

  const data = {
    id: eventTypeId == 7 ?
      form.find('[name="mediaId"]').val() :
      form.find('[name="playlistId"]').val(),
    type: eventTypeId == 7 ? 'media' : 'playlist',
    layoutDuration: eventTypeId == 7 ?
      form.find('[name="layoutDuration"]').val() : null,
    resolutionId:
      form.find('[name="resolutionId"]').select2('data').length > 0 ?
        form.find('[name="resolutionId"]').select2('data')[0].id : null,
    backgroundColor: form.find('[name="backgroundColor"]').val(),
  };

  // create or fetch Full screen Layout linked to this media/playlist
  $.ajax({
    type: 'POST',
    url: form.data().fullScreenUrl,
    cache: false,
    dataType: 'json',
    data: data,
  })
    .then(
      (response) => {
        if (!response.success) {
          SystemMessageInline(
            (response.message === '') ? translations.failure : response.message,
            form.closest('.modal'),
          );
        }

        if (populateHiddenFields) {
          // populate hidden fields
          // trigger change on fullScreenCampaignId,
          // to show the campaign preview
          if (eventTypeId == 7) {
            const $fullScreenControl = $('#fullScreenControl_media');
            $fullScreenControl.text($fullScreenControl.data('hasLayout'));
            $('#fullScreen-media')
              .val(form.find('#mediaId').select2('data')[0].text);
            $('#fullScreen-mediaId')
              .val(form.find('#mediaId').select2('data')[0].id);
          } else if (eventTypeId == 8) {
            const $fullScreenControl = $('#fullScreenControl_playlist');
            $fullScreenControl.text($fullScreenControl.data('hasLayout'));
            $('#fullScreen-playlist')
              .val(form.find('#playlistId').select2('data')[0].text);
            $('#fullScreen-playlistId')
              .val(form.find('#playlistId').select2('data')[0].id);
          }
        }

        $('#fullScreenCampaignId')
          .val(response.data.campaignId).trigger('change');

        (typeof callBack === 'function') && callBack(form);

        // close this modal, return to main schedule modal.
        $('#full-screen-schedule-modal').modal('hide');
      }, (xhr) => {
        SystemMessage(xhr.responseText, false);
      });
};

/**
 * Configure the query builder ( order and filter )
 * @param {object} dialog - Dialog object
 */
const configReminderFields = function(dialog) {
  const reminderFields = dialog.find('#reminderFields');

  if (reminderFields.length == 0) {
    return;
  }

  // console.log(reminderFields.data().reminders.length);
  if (reminderFields.data().reminders.length == 0) {
    // Add a template row
    const context = {
      title: 0,
      buttonGlyph: 'fa-plus',
    };
    reminderFields.append(templates.schedule.reminderEvent({
      ...context,
      ...{
        trans: translations.schedule.reminder,
      },
    }));
  } else {
    // For each of the existing codes, create form components
    let i = 0;
    $.each(reminderFields.data().reminders, function(index, field) {
      i++;

      const context = {
        scheduleReminderId: field.scheduleReminderId,
        value: field.value,
        type: field.type,
        option: field.option,
        isEmail: field.isEmail,
        title: i,
        buttonGlyph: ((i == 1) ? 'fa-plus' : 'fa-minus'),
      };

      reminderFields.append(templates.schedule.reminderEvent({
        ...context,
        ...{
          trans: translations.schedule.reminder,
        },
      }));
    });
  }

  // Nabble the resulting buttons
  reminderFields.on('click', 'button', function(e) {
    e.preventDefault();

    // find the gylph
    if ($(e.currentTarget).find('i').hasClass('fa-plus')) {
      const context =
      {
        title: reminderFields.find('.form-group').length + 1,
        buttonGlyph: 'fa-minus',

      };
      reminderFields.append(templates.schedule.reminderEvent({
        ...context,
        ...{
          trans: translations.schedule.reminder,
        },
      }));
    } else {
      // Remove this row
      $(e.currentTarget).closest('.form-group').remove();
    }
  });
};

/**
 * Process schedule form elements for the purpose of showing/hiding them
 * @param el jQuery element
 */
const processScheduleFormElements = function(el, dialog) {
  const fieldVal = el.val();
  const relativeTime = $('#relativeTime').is(':checked');
  const isAddForm = $(dialog).find('form').is('#scheduleAddForm');
  let endTimeControlDisplay;
  let startTimeControlDisplay;
  let relativeTimeControlDisplay;
  let relativeTimeCheckboxDisplay;
  let $startTime;
  let $endTime;
  let $relative;

  switch (el.attr('id')) {
    case 'recurrenceType':
      // console.log('Process: recurrenceType, val = ' + fieldVal);

      const repeatControlGroupDisplay = (fieldVal == '') ? 'none' : '';
      const repeatControlGroupWeekDisplay = (fieldVal != 'Week') ? 'none' : '';
      const repeatControlGroupMonthDisplay =
        (fieldVal !== 'Month') ? 'none' : '';

      $('.repeat-control-group').css('display', repeatControlGroupDisplay);
      $('.repeat-weekly-control-group').css(
        'display',
        repeatControlGroupWeekDisplay,

      );
      $('.repeat-monthly-control-group').css(
        'display',
        repeatControlGroupMonthDisplay,

      );
      $('#recurrenceDetail').parent().find('.input-group-addon').html(el.val());

      break;

    case 'eventTypeId':
      // console.log('Process: eventTypeId, val = ' + fieldVal);

      const layoutControlDisplay =
        (
          fieldVal == 2 ||
          fieldVal == 6 ||
          fieldVal == 7 ||
          fieldVal == 8 ||
          fieldVal == 9 ||
          fieldVal == 10

        ) ? 'none' : '';
      endTimeControlDisplay = (fieldVal == 2 || relativeTime) ? 'none' : '';
      startTimeControlDisplay = (relativeTime && fieldVal != 2) ? 'none' : '';
      const dayPartControlDisplay = (fieldVal == 2) ? 'none' : '';
      const commandControlDisplay = (fieldVal == 2) ? '' : 'none';
      const interruptControlDisplay = (fieldVal == 4) ? '' : 'none';
      const actionControlDisplay = (fieldVal == 6) ? '' : 'none';
      const maxPlaysControlDisplay =
        (fieldVal == 2 || fieldVal == 6 || fieldVal == 10) ? 'none' : '';
      const mediaScheduleControlDisplay = (fieldVal == 7) ? '' : 'none';
      const playlistScheduleControlDisplay = (fieldVal == 8) ? '' : 'none';
      const playlistMediaScheduleControlDisplay =
        (fieldVal == 7 || fieldVal == 8) ? '' : 'none';
      relativeTimeControlDisplay =
        (fieldVal == 2 || !relativeTime) ? 'none' : '';
      relativeTimeCheckboxDisplay = (fieldVal == 2) ? 'none' : '';
      const dataConnectorDisplay = fieldVal == 10 ? '' : 'none';
      const syncGroupDisplay = fieldVal == 9 ? '' : 'none';
      const displayGroupDisplay = fieldVal == 9 ? 'none' : '';

      $('.layout-control', dialog).css('display', layoutControlDisplay);
      $('.endtime-control', dialog).css('display', endTimeControlDisplay);
      $('.starttime-control', dialog).css('display', startTimeControlDisplay);
      $('.day-part-control', dialog).css('display', dayPartControlDisplay);
      $('.command-control', dialog).css('display', commandControlDisplay);
      $('.interrupt-control', dialog).css('display', interruptControlDisplay);
      $('.action-control', dialog).css('display', actionControlDisplay);
      $('.max-plays-control', dialog).css('display', maxPlaysControlDisplay);
      $('.media-control', dialog).css('display', mediaScheduleControlDisplay);

      // Hide step 2 ( Displays for sync event )
      if (fieldVal == 9) {
        // Hide step 2
        $('[href="#schedule-step-2"]', dialog).parent().hide();

        // Re-number step 3 and 4
        $('[href="#schedule-step-3"]', dialog).text(2);
        $('[href="#schedule-step-4"]', dialog).text(3);

        // Clear display groups for other events to avoid clashing with
        // display groups on sync group
        $('[name="displayGroupIds[]"]', dialog).val('').trigger('change');
      } else {
        // Show step 2
        $('[href="#schedule-step-2"]', dialog).parent().show();

        // Re-number step 3 and 4
        $('[href="#schedule-step-3"]', dialog).text(3);
        $('[href="#schedule-step-4"]', dialog).text(4);

        // If event type is not sync event, clear sync group
        // and clear sync layouts
        $('#syncGroupId', dialog).val('').trigger('change');
        $('#content-selector tbody', dialog).html('');
      }

      $('.sync-group-control', dialog).css('display', syncGroupDisplay);

      if (fieldVal != 9) {
        $('.sync-group-content-selector', dialog).css('display', 'none');
      }
      $('.display-group-control', dialog).css('display', displayGroupDisplay);
      $('.playlist-control', dialog)
        .css('display', playlistScheduleControlDisplay);
      $('.media-playlist-control', dialog).css(
        'display',
        playlistMediaScheduleControlDisplay);
      $('.relative-time-control', dialog)
        .css('display', relativeTimeControlDisplay);
      $('.relative-time-checkbox', dialog)
        .css('display', relativeTimeCheckboxDisplay);
      $('.data-connector-control', dialog).css('display', dataConnectorDisplay);

      // action event type
      if (fieldVal === 6) {
        $('.displayOrder-control', dialog).css('display', 'none');
      }

      // Hide preview if not needed
      if ([1, 2, 3, 4, 5, 7, 8].indexOf(Number(fieldVal)) === -1) {
        $('#previewButton', dialog).closest('.preview-button-container').hide();
      }

      // If the fieldVal is 2 (command)
      // then we should set the dayPartId to be 0 (custom)
      if (fieldVal == 2) {
        // Determine what the custom day part is.
        const $dayPartId = $('#dayPartId', dialog);
        let customDayPartId = 0;
        $dayPartId.find('option').each(function(i, el) {
          if ($(el).data('isCustom') === 1) {
            customDayPartId = $(el).val();
          }
        });

        // console.log('Setting dayPartId to custom: ' + customDayPartId);
        $dayPartId.val(customDayPartId);

        $startTime = $('.starttime-control', dialog);
        $startTime.find('input[name=fromDt_Link2]').show();
        $startTime.find('.help-block').html(
          $startTime.closest('form').data().daypartMessage,
        );

        // Set the repeats/reminders tabs to visible.
        $('li.repeats', dialog).css('display', 'block');
        $('li.reminders', dialog).css('display', 'block');
      }

      // Call function for the daypart ID
      processScheduleFormElements($('#dayPartId', dialog), dialog);

      // Change the help text and label of the campaignId dropdown
      const $campaignSelect = el.closest('form').find('#campaignId');
      const $layoutControl = $('.layout-control', dialog);
      let searchIsLayoutSpecific = -1;

      if (fieldVal === '1' || fieldVal === '3' || fieldVal === '4') {
        // Load Layouts only
        searchIsLayoutSpecific = 1;

        // If it was campaign field, reset dropdown
        if (
          $layoutControl.children('label').text() ===
          $campaignSelect.data('transCampaign')
        ) {
          $layoutControl.find('#campaignId').val('').trigger('change');
        }

        // Change Label and Help text when Layout event type is selected
        $layoutControl.children('label')
          .text($campaignSelect.data('transLayout'));
        $layoutControl.children('div').children('small.form-text.text-muted')
          .text($campaignSelect.data('transLayoutHelpText'));
      } else {
        // Load Campaigns only
        searchIsLayoutSpecific = 0;

        // If it was a layout field, reset dropdown
        if (
          $layoutControl.children('label').text() ===
          $campaignSelect.data('transLayout')
        ) {
          $layoutControl.find('#campaignId').val('').trigger('change');
        }

        // Change Label and Help text when Campaign event type is selected
        $layoutControl.children('label')
          .text($campaignSelect.data('transCampaign'));
        $layoutControl.children('div').children('small.form-text.text-muted')
          .text($campaignSelect.data('transCampaignHelpText'));
      }

      // Set the search criteria
      $campaignSelect.data('searchIsLayoutSpecific', searchIsLayoutSpecific);

      // If add form, we reset the steps > 1 to disabled
      if (isAddForm) {
        $('.stepwizard-step', dialog)
          .find('[href="#schedule-step-2"], ' +
            '[href="#schedule-step-3"], ' +
            '[href="#schedule-step-4"]')
          .attr('disabled', 'disabled');
      }

      break;

    case 'dayPartId':
      const meta = el.find('option[value=' + fieldVal + ']').data();

      endTimeControlDisplay =
        (
          meta.isCustom === 0 ||
          relativeTime ||
          $('#eventTypeId', dialog).val() == 2
        ) ? 'none' : '';
      startTimeControlDisplay =
        (
          meta.isAlways === 1 ||
          (relativeTime && meta.isCustom === 1)
        ) ? 'none' : '';
      const repeatsControlDisplay = (meta.isAlways === 1) ? 'none' : '';
      const reminderControlDisplay = (meta.isAlways === 1) ? 'none' : '';
      relativeTimeControlDisplay =
        (meta.isCustom === 0 || !relativeTime) ? 'none' : '';
      relativeTimeCheckboxDisplay = (meta.isCustom === 0) ? 'none' : '';

      $startTime = $('.starttime-control', dialog);
      $endTime = $('.endtime-control', dialog);
      const $repeats = $('li.repeats', dialog);
      const $reminder = $('li.reminders', dialog);
      $relative = $('.relative-time-control', dialog);
      const $relativeCheckbox = $('.relative-time-checkbox', dialog);

      // Set control visibility
      $startTime.css('display', startTimeControlDisplay);
      $endTime.css('display', endTimeControlDisplay);
      $repeats.css('display', repeatsControlDisplay);
      $reminder.css('display', reminderControlDisplay);
      $relative.css('display', relativeTimeControlDisplay);
      $relativeCheckbox.css('display', relativeTimeCheckboxDisplay);

      // If repeats and reminders tab are opened, revert to General if hidden
      if (
        meta.isAlways === 1 &&
        (
          $repeats.find('.nav-link.active') ||
          $reminder.find('.nav-link.active')
        )
      ) {
        $('li .nav-link[href="#general"]', dialog).tab('show');
      }

      // Dayparts only show the start control
      if (meta.isAlways === 0 && meta.isCustom === 0) {
        // We need to update the date/time controls
        // to only accept the date element
        $startTime.find('input[name=fromDt_Link2]').hide();
        $startTime.find('small.text-muted').html(
          $startTime.closest('form').data().notDaypartMessage,
        );
      } else {
        $startTime.find('input[name=fromDt_Link2]').show();
        $startTime.find('small.text-muted').html(
          $startTime.closest('form').data().daypartMessage,
        );
      }

      // if dayparting is set to always, disable start time and end time
      if (meta.isAlways === 0) {
        $startTime.find('input[name=fromDt]').prop('disabled', false);
        $endTime.find('input[name=toDt]').prop('disabled', false);
      } else {
        $startTime.find('input[name=fromDt]').prop('disabled', true);
        $endTime.find('input[name=toDt]').prop('disabled', true);
      }

      break;

    case 'campaignId':
    case 'fullScreenCampaignId':
      // Update the preview button URL
      const $previewButton = $('#previewButton', dialog);

      if (fieldVal === null || fieldVal === '' || fieldVal === 0) {
        $previewButton.closest('.preview-button-container').hide();
      } else {
        $previewButton.closest('.preview-button-container').show();
        $previewButton.attr(
          'href',
          $previewButton.data().url.replace(':id', fieldVal),
        );
      }

      break;

    case 'actionType':
      if (!el.is(':visible')) {
        return;
      }

      const layoutCodeControl =
        (fieldVal == 'navLayout' && el.is(':visible')) ? '' : 'none';
      const commandControlDisplay2 = (fieldVal == 'command') ? '' : 'none';

      $('.layout-code-control', dialog).css('display', layoutCodeControl);
      $('.command-control', dialog).css('display', commandControlDisplay2);

      break;
    case 'relativeTime':
      if (!el.is(':visible')) {
        return;
      }

      const datePickerStartControlDisplay = $(el).is(':checked') ? 'none' : '';
      const datePickerEndControlDisplay =
        (
          $(el).is(':checked') ||
          $('#eventTypeId', dialog).val() == 2
        ) ? 'none' : '';
      relativeTimeControlDisplay = $(el).is(':checked') ? '' : 'none';

      $startTime = $('.starttime-control', dialog);
      $endTime = $('.endtime-control', dialog);
      $relative = $('.relative-time-control', dialog);

      if (dateFormat.indexOf('s') <= -1) {
        $('.schedule-now-seconds-field').remove();
      }

      if ($(el).is(':checked')) {
        scheduleEvaluateRelativeDateTime($(el).closest('form'));
      }

      $startTime.css('display', datePickerStartControlDisplay);
      $endTime.css('display', datePickerEndControlDisplay);
      $relative.css('display', relativeTimeControlDisplay);

      break;
    case 'syncTimezone':
      const relativeTimeChecked = $('#relativeTime', dialog).is(':checked');

      if (relativeTimeChecked) {
        scheduleEvaluateRelativeDateTime($(el).closest('form'));
      }

      break;
  }

  // If there's no more than 1 tab in step 4, hide tabs
  $('#schedule-step-4', dialog).find('.nav-tabs').toggle(
    $('#schedule-step-4', dialog).find('.nav-tabs li').filter((_idx, el) => {
      return ($(el).css('display') != 'none');
    }).length > 1,
  );
};

const duplicateScheduledEvent = function($scheduleForm) {
  // Set the edit form URL to that of the add form
  $scheduleForm.attr('action', $scheduleForm.data().addUrl)
    .attr('method', 'post');

  // Remove the duplicate button
  $('#scheduleDuplateButton').remove();

  // Remove the delete button
  $('#scheduleDeleteButton').remove();

  toastr.info($scheduleForm.data().duplicatedMessage);
};

/**
 * Evaluate dates on schedule form and fill the date input fields
 */
const scheduleEvaluateRelativeDateTime = function($form) {
  const hours = $form.find('#hours').val();
  const minutes = $form.find('#minutes').val();
  const seconds = $form.find('#seconds').val();

  // let fromDt = moment().add(-24, "hours");
  const fromDt = moment();
  const toDt = moment();

  // Use Hours, Minutes and Seconds to generate a from date
  const $messageDiv = $('.scheduleNowMessage');
  const $syncTimezone = $form.find('#syncTimezone');
  let messageTemplate = '';

  if (hours != '') {
    toDt.add(hours, 'hours');
  }

  if (minutes != '') {
    toDt.add(minutes, 'minutes');
  }

  if (seconds != '') {
    toDt.add(seconds, 'seconds');
  }

  if (hours == '' && minutes == '' && seconds == '') {
    $messageDiv.html('').addClass('d-none');
    updateDatePicker($form.find('#fromDt'), '');
    updateDatePicker($form.find('#toDt'), '');
  } else {
    // Update the message div
    if ($syncTimezone.is(':checked')) {
      messageTemplate = 'templateSync';
    } else {
      messageTemplate = 'templateNoSync';
    }
    $messageDiv.html(
      $messageDiv.data(messageTemplate)
        .replace(
          '[fromDt]',
          fromDt.format(jsDateFormat),
        ).replace('[toDt]', toDt.format(jsDateFormat)),
    ).removeClass('d-none');

    // Update the final submit fields
    updateDatePicker(
      $form.find('#fromDt'),
      fromDt.format(systemDateFormat),
      systemDateFormat,
      true,
    );
    updateDatePicker(
      $form.find('#toDt'),
      toDt.format(systemDateFormat),
      systemDateFormat,
      true,
    );
  }
};

const openAgendaModal = function(date) {
  if (userAgendaViewEnabled != '1') {
    console.error('Feature not enabled: Agenda view');
    return;
  }

  // Create modal
  const dialog = bootbox.dialog({
    title: `<span class="agenda-modal-title">
      ${translations.schedule.calendar.agendaView}</span>`,
    message: `<div id="agendaCalendar"></div>`,
    className: 'agenda-view-modal',
    closeButton: true,
    buttons: {
      close: {
        label: translations.schedule.calendar.closeAgendaView,
        className: 'btn-primary',
      },
    },
  }).attr('data-test', 'agendaViewModal');

  const showLoading = function() {
    const $modalTitle = $('.agenda-view-modal .modal-title');
    const $loading = $(`<span class="fa fa-spin fa-cog agenda-view-loading">
      </span>`);

    // Show loading on modal title if exists
    if ($modalTitle.length > 0) {
      $modalTitle.append($loading);
    } else {
      $('.agenda-view-modal #agendaCalendar').append($loading);
    }
  };

  const hideLoading = function() {
    $('.agenda-view-modal .agenda-view-loading').remove();
  };

  // Destroy calendar on close
  dialog.on('hidden.bs.modal', function() {
    window.agendaCalendar = null;

    destroyMiniLayoutPreview();
  });

  if (($('.agenda-view-modal #agendaCalendar').length > 0)) {
    const calendarOptions = $('#CalendarContainer').data();

    // Calendar options
    const options = {
      time_start: '00:00',
      time_end: '00:00',
      day: date,
      events_source: function() {
        return agendaEvents;
      },
      view: 'agenda',
      tmpl_path: function(name) {
        // Create underscore template
        // with translations and add to body
        if ($('#calendar-template-' + name).length === 0) {
          const $template = $('<div id="calendar-template-' + name + '">');

          $template.text(templates.calendar[name]({
            trans: translations.schedule.calendar,
          })).hide();
          $template.appendTo('body');
        }

        // Return name only
        // ( to work the same way in calendar and calendar-jalali)
        return 'calendar-template-' + name;
      },
      tmpl_cache: true,
      onBeforeEventsLoad: function(done) {
        // If there is already a request, abort it
        if (window.getJsonRequestControl) {
          return;
        }

        const $calendarErrorMessage = $('#calendar-error-message');
        const agendaCalendar = this;

        // Show time slider on agenda view and call
        // the calendar view on slide stop event
        $(
          '.cal-event-agenda-filter, ' +
          '.xibo-agenda-calendar-controls, ' +
          '#btn-month-view',
        ).show();
        $('#btn-agenda-view').hide();
        $('.non-agenda-filter').find('input, select').prop('disabled', true);

        // agenda has it is own error conditions.
        $calendarErrorMessage.hide();

        const $timePicker = $('#timePicker');
        const momentNow = moment().tz ? moment().tz(timezone) : moment();

        // Create slider ticks
        const ticks = [];
        const ticksLabels = [];
        const ticksPositions = [];
        for (let i = 0; i <= 1440; i += 120) {
          // Last step get one less minute
          const minutes = i === 1440 ? 1439 : i;
          ticks.push(minutes);
          ticksLabels.push(
            momentNow.clone().startOf('day').add(minutes, 'minutes')
              .format(jsTimeFormat),
          );
          ticksPositions.push(i / 1440 * 100);
        }

        $timePicker.slider({
          value: (momentNow.hour() * 60) + momentNow.minute(),
          tooltip: 'always',
          ticks: ticks,
          ticks_labels: ticksLabels,
          ticks_positions: ticksPositions,
          formatter: function(value) {
            return moment().startOf('day').minute(value).format(jsTimeFormat);
          },
        }).off('slideStop').on('slideStop', function(ev) {
          agendaCalendar.view();
        });

        $('.time-picker-step-btn').off('click.agenda')
          .on('click.agenda', function(ev) {
            $timePicker.slider(
              'setValue',
              $timePicker.slider('getValue') + $(ev.currentTarget).data('step'),
            );
            agendaCalendar.view();
          });

        // Get selected display groups
        let selectedDisplayGroup = $('.cal-context').data().selectedTab;
        const displayGroupsList = [];
        let chooseAllDisplays = false;

        $('#DisplayList, #DisplayGroupList').prop('disabled', false);

        // Find selected display group and create a
        // display group list used to create tabs
        $(
          'select[name="displayGroupIds[]"] option, ' +
          'select[name="displaySpecificGroupIds[]"] option',
        )
          .each(function(_idx, el) {
            const $self = $(el);

            // If the all option is selected
            if ($self.val() == -1 && $self.is(':selected')) {
              chooseAllDisplays = true;
              return true;
            }

            if ($self.is(':selected') || chooseAllDisplays) {
              displayGroupsList.push({
                id: $self.val(),
                name: $self.html(),
                isDisplaySpecific: $self.attr('type'),
              });
            }
          });

        // If there are no selected displays
        // use displays from events
        if (displayGroupsList.length === 0) {
          scheduleEvents.forEach((ev) => {
            ev.displayGroups.forEach((dp) => {
              if (!displayGroupsList.find(
                (dpl) => dpl.id == dp.displayGroupId)
              ) {
                displayGroupsList.push({
                  id: dp.displayGroupId,
                  name: dp.displayGroup,
                  isDisplaySpecific: dp.isDisplaySpecific,
                });
              }
            });
          });
        }

        // If no selected display on tab, select 1st from all displays
        if (typeof selectedDisplayGroup == 'undefined') {
          selectedDisplayGroup = displayGroupsList[0].id;
        }

        // Sort display group list by name
        displayGroupsList.sort(function(a, b) {
          const nameA =
            a.name.toLowerCase(); const nameB = b.name.toLowerCase();
          // sort string ascending
          if (nameA < nameB) {
            return -1;
          }
          if (nameA > nameB) {
            return 1;
          }

          return 0; // default return value (no sorting)
        });

        const url =
          calendarOptions.agendaLink.replace(':id', selectedDisplayGroup);

        const dateMoment =
          moment(this.options.position.start.getTime() / 1000, 'X');
        const timeFromSlider =
          ($('#timePickerSlider').length) ?
            $('#timePicker').slider('getValue') : 0;
        const timeMoment =
          moment(timeFromSlider * 60, 'X');

        // Add hour to date to get the selected date
        const dateSelected = moment(dateMoment + timeMoment);

        // Populate the events array via AJAX
        const params = {
          singlePointInTime: $('#showTimeline').is(':checked') ? 1 : 0,
        };
        if (params.singlePointInTime) {
          params.date = dateSelected.format(systemDateFormat);
        } else {
          params.startDate = dateMoment.format(systemDateFormat);
          params.endDate =
            dateMoment.clone().endOf('day').format(systemDateFormat);
        }

        // if the result are empty create a empty object and reset the results
        if (jQuery.isEmptyObject(agendaEvents['results'])) {
          // events let must be an array for
          // compatibility with the previous implementation
          agendaEvents = [];
          agendaEvents['results'] = {};
        }

        // Save displaygroup list and the selected display
        agendaEvents['displayGroupList'] = displayGroupsList;
        agendaEvents['selectedDisplayGroup'] = selectedDisplayGroup;

        // Clean error message
        agendaEvents['errorMessage'] = '';

        // Clean cache/results if its requested by the options
        if (calendar.options['clearCache'] == true) {
          agendaEvents['results'] = {};
        }

        // Show loading
        showLoading();

        // Make request to get the data for the events
        window.getJsonRequestControl = $.getJSON(url, params)
          .done(function(data) {
            let noEvents = true;

            if (
              !jQuery.isEmptyObject(data.data) &&
              data.data.events != undefined &&
              data.data.events.length > 0
            ) {
              agendaEvents['results'][String(selectedDisplayGroup)] =
                data.data;
              // eslint-disable-next-line max-len
              agendaEvents['results'][String(selectedDisplayGroup)]['request_date'] = params.date;

              noEvents = false;

              if (
                $('#geoLatitude').val() != undefined &&
                $('#geoLatitude').val() != '' &&
                $('#geoLongitude').val() != undefined &&
                $('#geoLongitude').val() != ''
              ) {
                // eslint-disable-next-line max-len
                agendaEvents['results'][String(selectedDisplayGroup)]['geoLatitude'] =
                  $('#geoLatitude').val();
                // eslint-disable-next-line max-len
                agendaEvents['results'][String(selectedDisplayGroup)]['geoLongitude'] =
                  $('#geoLongitude').val();

                // eslint-disable-next-line max-len
                agendaEvents['results'][String(selectedDisplayGroup)]['events'] =
                  filterEventsByLocation(
                    // eslint-disable-next-line max-len
                    agendaEvents['results'][String(selectedDisplayGroup)]['events'],
                  );

                noEvents = (data.data.events.length <= 0);
              }
            }

            if (noEvents) {
              agendaEvents['results'][String(selectedDisplayGroup)] = {};
              agendaEvents['errorMessage'] = 'no_events';
            }

            if (done != undefined) {
              done();
            }

            agendaCalendar._render();

            getJsonRequestControl = null;

            // Turn Layout table into datatable
            $('.agenda-table-layouts').DataTable({
              searching: false,
              destroy: true,
            });

            // Remove loading
            hideLoading();
          })
          .fail(function(res) {
            // Deal with the failed request
            if (res.statusText != 'abort') {
              agendaEvents['errorMessage'] = 'request_failed';
            }

            if (done != undefined) {
              done();
            }

            agendaCalendar._render();

            // Remove loading
            hideLoading();
          });
      },
      onAfterViewLoad: function(view) {
        if (
          typeof this.getTitle === 'function' &&
          $('.agenda-view-modal .modal-title .day-title').length === 0
        ) {
          $('.agenda-view-modal .agenda-modal-title').append(
            `<span class="day-title ml-2">- ${this.getTitle()}</span>`,
          );
        }
      },
      language: calendarLanguage,
    };

    options.type = calendarOptions.calendarType;
    agendaCalendar = $('.agenda-view-modal #agendaCalendar')
      .calendar(options);

    // Add filters
    $('.agenda-view-modal .modal-body').prepend(
      templates.calendar.agendaFilter({
        trans: translations.schedule.calendar.agendaFilters,
        defaultLat: calendarOptions.defaultLat,
        defaultLong: calendarOptions.defaultLong,
      }),
    );

    // Location filter init
    const $map = $('.cal-event-location-map #geoFilterAgendaMap');

    // Get location button
    $('#getLocation').off('click.getLoc').on('click.getLoc', function(ev) {
      const $self = $(ev.currentTarget);

      // Disable button
      $self.prop('disabled', true);

      navigator.geolocation.getCurrentPosition(function(location) { // success
        // Populate location fields
        $('#geoLatitude').val(location.coords.latitude).trigger('change');
        $('#geoLongitude').val(location.coords.longitude).trigger('change');

        // Reenable button
        $self.prop('disabled', false);

        // Redraw map
        generateFilterGeoMap();
      }, function error(err) { // error
        console.warn('ERROR(' + err.code + '): ' + err.message);

        // Reenable button
        $self.prop('disabled', false);
      }, { // options
        enableHighAccuracy: true,
        timeout: 5000,
        maximumAge: 0,
      });
    });

    // Location map button
    $('#toggleMap').off('click.toggleMap').on('click.toggleMap', function() {
      $map.toggleClass('d-none');

      if (!$map.hasClass('d-none')) {
        generateFilterGeoMap();
      }
    });

    // Clear location button
    $('#clearLocation').off('click.genMap').on('click.genMap', function() {
      // Populate location fields
      $('#geoLatitude').val('').trigger('change');
      $('#geoLongitude').val('').trigger('change');

      if (!$map.hasClass('d-none')) {
        generateFilterGeoMap();
      }
    });

    // Change events reloads the calendar view and map
    $('#geoLatitude, #geoLongitude, #showTimeline').off('change.agendaFilter')
      .on('change.agendaFilter', _.debounce(function() {
        agendaCalendar.view();
      }, 400));

    // Set event when clicking on a tab, to refresh the view
    $('.cal-context')
      .off('click.agenda')
      .on('click.agenda', '.nav-item:not(.active) a[data-toggle="tab"]',
        function(e) {
          $('.cal-context').data().selectedTab = $(e.currentTarget).data('id');
          agendaCalendar.view();
        },
      )
      // When selecting a layout row, create a Breadcrumb Trail
      // and select the correspondent Display Group(s) and the Campaign(s)
      .on('click.agenda', 'tbody tr', function(e) {
        const $self = $(e.currentTarget);
        const alreadySelected = $self.hasClass('selected');

        // Clean all selected elements
        $('.cal-event-breadcrumb-trail').hide();
        $('.cal-context tbody tr').removeClass('selected');
        $('.cal-context tbody tr').removeClass('selected-linked');

        // Remove previous layout preview
        destroyMiniLayoutPreview();

        // If the element was already selected return
        // so that it can deselect everything
        if (alreadySelected) {
          return;
        }

        // If the click was in a layout table row create the breadcrumb trail
        if ($self.closest('table').data('type') == 'layouts') {
          $('.cal-event-breadcrumb-trail').show();

          // Clean div content
          $('.cal-event-breadcrumb-trail #content').html('');

          // Get the template and render it on the div
          $('.cal-event-breadcrumb-trail #content').append(
            agendaCalendar._breadcrumbTrail(
              $self.data('elemId'),
              agendaEvents,
              $self.data('eventId'),
            ),
          );

          // Create mini layout preview
          createMiniLayoutPreview(
            layoutPreviewUrl.replace(':id', $self.data('elemId')),
          );

          // Initialize container for the Schedule modal handling
          XiboInitialise('#agendaCalendar');
        }

        // Select the clicked element and the linked elements
        agendaSelectLinkedElements(
          $self.closest('table').data('type'),
          $self.data('elemId'), agendaEvents,
          $self.data('eventId'),
        );
      });
  }

  // Agenda View timeline
  $('.cal-event-agenda-filter #showTimeline').off('click.agenda')
    .on('click.agenda', function(ev) {
      $('.cal-event-agenda-filter .cal-event-time-bar').toggle(
        $(ev.currentTarget).is(':checked'),
      );
    });
};

/**
 * Select the elements linked to the clicked element
 */
const agendaSelectLinkedElements = function(elemType, elemID, data, eventId) {
  const targetEvents = [];
  const selectClass = {
    layouts: 'selected-linked',
    overlays: 'selected-linked',
    displaygroups: 'selected-linked',
    campaigns: 'selected-linked',
  };

  results = data.results[data.selectedDisplayGroup];

  const allEvents = results.events;

  // Get the correspondent events
  for (let i = 0; i < allEvents.length; i++) {
    if (
      (elemType == 'layouts' || elemType == 'overlays') &&
      allEvents[i].layoutId == elemID &&
      allEvents[i].eventId == eventId
    ) {
      targetEvents.push(allEvents[i]);
      selectClass[elemType] = 'selected';
    } else if (
      elemType == 'displaygroups' &&
      allEvents[i].displayGroupId == elemID
    ) {
      targetEvents.push(allEvents[i]);
      selectClass['displaygroups'] = 'selected';
    } else if (elemType == 'campaigns' && allEvents[i].campaignId == elemID) {
      targetEvents.push(allEvents[i]);
      selectClass['campaigns'] = 'selected';
    }
  }

  // Use the target events to select the corresponding objects
  for (let i = 0; i < targetEvents.length; i++) {
    // Select the corresponding layout
    $(
      'table[data-type="layouts"] tr[data-elem-id~="' +
      targetEvents[i].layoutId + '"][data-event-id~="' +
      targetEvents[i].eventId + '"]').addClass(selectClass['layouts']);

    // Select the corresponding display group
    $(
      'table[data-type="displaygroups"] tr[data-elem-id~="' +
      targetEvents[i].displayGroupId + '"]',
    ).addClass(selectClass['displaygroups']);

    // Select the corresponding campaigns
    $('table[data-type="campaigns"] tr[data-elem-id~="' +
      targetEvents[i].campaignId + '"]').addClass(selectClass['campaigns']);
  }
};

const generateGeoMap = function($form) {
  if (mymap !== undefined && mymap !== null) {
    mymap.remove();
  }

  const defaultLat = $('#' + $form.attr('id')).data().defaultLat;
  const defaultLong = $('#' + $form.attr('id')).data().defaultLong;

  // base map
  mymap = L.map('geoScheduleMap').setView([defaultLat, defaultLong], 13);

  // base tile layer, provided by Open Street Map
  L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    subdomains: ['a', 'b', 'c'],
  }).addTo(mymap);

  // Add a layer for drawn items
  const drawnItems = new L.FeatureGroup();
  mymap.addLayer(drawnItems);

  // Add draw control (toolbar)
  const drawControl = new L.Control.Draw({
    position: 'topright',
    draw: {
      polyline: false,
      circle: false,
      marker: false,
      circlemarker: false,
    },
    edit: {
      featureGroup: drawnItems,
    },
  });

  const drawControlEditOnly = new L.Control.Draw({
    position: 'topright',
    draw: false,
    edit: {
      featureGroup: drawnItems,
    },
  });

  mymap.addControl(drawControl);

  // add search Control - allows searching by country/city
  // and automatically moves map to that location
  const searchControl = new L.Control.Search({
    url: 'https://nominatim.openstreetmap.org/search?format=json&q={s}',
    jsonpParam: 'json_callback',
    propertyName: 'display_name',
    propertyLoc: ['lat', 'lon'],
    marker: L.circleMarker([0, 0], {radius: 30}),
    autoCollapse: true,
    autoType: false,
    minLength: 2,
    hideMarkerOnCollapse: true,
    firstTipSubmit: true,
  });

  mymap.addControl(searchControl);

  let json = '';
  let layer = null;
  let layers = null;

  // when user draws a new polygon it will be added as
  // a layer to the map and as GeoJson to hidden field
  mymap.on('draw:created', function(e) {
    layer = e.layer;

    drawnItems.addLayer(layer);
    json = layer.toGeoJSON();

    $('#geoLocation').val(JSON.stringify(json));

    // disable adding new polygons
    mymap.removeControl(drawControl);
    mymap.addControl(drawControlEditOnly);
  });

  // update the hidden field geoJson with new coordinates
  mymap.on('draw:edited', function(e) {
    layers = e.layers;

    layers.eachLayer(function(layer) {
      json = layer.toGeoJSON();

      $('#geoLocation').val(JSON.stringify(json));
    });
  });

  // remove the layer and clear the hidden field
  mymap.on('draw:deleted', function(e) {
    layers = e.layers;

    layers.eachLayer(function(layer) {
      $('#geoLocation').val('');
      drawnItems.removeLayer(layer);
    });

    // re-enable adding new polygons
    if (drawnItems.getLayers().length === 0) {
      mymap.removeControl(drawControlEditOnly);
      mymap.addControl(drawControl);
    }
  });

  // if we are editing an event with existing Geo JSON
  // make sure we load it and add the layer to the map
  if ($('#geoLocation').val() != null && $('#geoLocation').val() !== '') {
    const geoJSON = JSON.parse($('#geoLocation').val());

    L.geoJSON(geoJSON, {
      onEachFeature: onEachFeature,
    });

    function onEachFeature(feature, layer) {
      drawnItems.addLayer(layer);
      mymap.fitBounds(layer.getBounds());
    }

    // disable adding new polygons
    mymap.removeControl(drawControl);
    mymap.addControl(drawControlEditOnly);
  }
};

const generateFilterGeoMap = function() {
  if (mymap !== undefined && mymap !== null) {
    mymap.remove();
  }

  // Get location values
  let defaultLat = $('#geoLatitude').val();
  let defaultLong = $('#geoLongitude').val();

  // If values are not set, get system default location
  if (
    defaultLat == undefined ||
    defaultLat == '' ||
    defaultLong == undefined ||
    defaultLong == ''
  ) {
    defaultLat = $('.cal-event-location-map').data('defaultLat');
    defaultLong = $('.cal-event-location-map').data('defaultLong');
  }

  // base map
  mymap = L.map('geoFilterAgendaMap').setView([defaultLat, defaultLong], 13);

  // base tile layer, provided by Open Street Map
  L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    subdomains: ['a', 'b', 'c'],
  }).addTo(mymap);

  // add search Control - allows searching by country/city
  // and automatically moves map to that location
  const searchControl = new L.Control.Search({
    url: 'https://nominatim.openstreetmap.org/search?format=json&q={s}',
    jsonpParam: 'json_callback',
    propertyName: 'display_name',
    propertyLoc: ['lat', 'lon'],
    marker: L.circleMarker([0, 0], {radius: 30}),
    autoCollapse: true,
    autoType: false,
    minLength: 2,
    hideMarkerOnCollapse: true,
  });

  mymap.addControl(searchControl);

  const setMarker = function(lat, lng) {
    if (mymapmarker != undefined) {
      mymap.removeLayer(mymapmarker);
    }

    mymapmarker = L.marker([lat, lng], mymap).addTo(mymap);
  };

  // Click to create marker
  mymap.on('click', function(e) {
    $('#geoLatitude').val(e.latlng.lat).change();
    $('#geoLongitude').val(e.latlng.lng).change();

    setMarker(e.latlng.lat, e.latlng.lng);
  });

  if ($('#geoLatitude').val() != undefined && $('#geoLatitude').val() != '' &&
    $('#geoLongitude').val() != undefined && $('#geoLongitude').val() != '') {
    setMarker($('#geoLatitude').val(), $('#geoLongitude').val());
  }
};

const filterEventsByLocation = function(events) {
  const eventsResult = [];

  for (let index = 0; index < events.length; index++) {
    const event = events[index];

    if (event.geoLocation != '') {
      const geoJSON = JSON.parse(event.geoLocation);
      const point = [$('#geoLongitude').val(), $('#geoLatitude').val()];
      const polygon = L.geoJSON(geoJSON);

      const test = leafletPip.pointInLayer(point, polygon);

      if (test.length > 0) {
        eventsResult.push(event);
      }
    } else {
      eventsResult.push(event);
    }
  }

  return eventsResult;
};

const setupSelectForSchedule = function(dialog) {
  // Select lists
  const $campaignSelect = $('#campaignId', dialog);
  $campaignSelect.select2({
    dropdownParent: $(dialog).find('form'),
    ajax: {
      url: $campaignSelect.data('searchUrl'),
      dataType: 'json',
      delay: 250,
      data: function(params) {
        const query = {
          isLayoutSpecific: $campaignSelect.data('searchIsLayoutSpecific'),
          retired: 0,
          totalDuration: 0,
          name: params.term,
          start: 0,
          length: 10,
          columns: [
            {
              data: 'isLayoutSpecific',
            },
            {
              data: 'campaign',
            },
          ],
          order: [
            {
              column: 0,
              dir: 'asc',
            },
            {
              column: 1,
              dir: 'asc',
            },
          ],
        };

        // Set the start parameter based on the page number
        if (params.page != null) {
          query.start = (params.page - 1) * 10;
        }

        return query;
      },
      processResults: function(data, params) {
        const results = [];

        $.each(data.data, function(index, el) {
          results.push({
            id: el['campaignId'],
            text: el['campaign'],
          });
        });

        let page = params.page || 1;
        page = (page > 1) ? page - 1 : page;

        return {
          results: results,
          pagination: {
            more: (page * 10 < data.recordsTotal),
          },
        };
      },
    },
  });

  $campaignSelect.on('select2:open', function(event) {
    setTimeout(function() {
      $(event.target).data('select2').dropdown.$search.get(0).focus();
    }, 10);
  });

  const $displaySelect = $('select[name="displayGroupIds[]"]', dialog);
  $displaySelect.select2({
    dropdownParent: $(dialog).find('form'),
    ajax: {
      url: $displaySelect.data('searchUrl'),
      dataType: 'json',
      delay: 250,
      data: function(params) {
        const query = {
          isDisplaySpecific: -1,
          forSchedule: 1,
          displayGroup: params.term,
          start: 0,
          length: 10,
          columns: [
            {
              data: 'isDisplaySpecific',
            },
            {
              data: 'displayGroup',
            },
          ],
          order: [
            {
              column: 0,
              dir: 'asc',
            },
            {
              column: 1,
              dir: 'asc',
            },
          ],
        };

        // Set the start parameter based on the page number
        if (params.page != null) {
          query.start = (params.page - 1) * 10;
        }

        return query;
      },
      processResults: function(data, params) {
        const groups = [];
        const displays = [];

        $.each(data.data, function(index, element) {
          if (element.isDisplaySpecific === 1) {
            displays.push({
              id: element.displayGroupId,
              text: element.displayGroup,
            });
          } else {
            groups.push({
              id: element.displayGroupId,
              text: element.displayGroup,
            });
          }
        });

        let page = params.page || 1;
        page = (page > 1) ? page - 1 : page;

        return {
          results: [
            {
              text: groups.length > 0 ?
                $displaySelect.data('transGroups') : null,
              children: groups,
            }, {
              text: displays.length > 0 ?
                $displaySelect.data('transDisplay') : null,
              children: displays,
            },
          ],
          pagination: {
            more: (page * 10 < data.recordsTotal),
          },
        };
      },
    },
  });

  // set initial displays on add form.
  if (
    [undefined, ''].indexOf($displaySelect.data('initialKey')) == -1 &&
    $(dialog).find('form').data('setDisplaysFromGridFilters')
  ) {
    // filter from the Schedule grid
    const displaySpecificGroups = $('#DisplayList').val() ?? [];
    const displayGroups = $('#DisplayGroupList').val() ?? [];
    // add values to one array
    const addFormDisplayGroup = displaySpecificGroups.concat(displayGroups);
    // set array of displayGroups as initial value
    $displaySelect.data('initial-value', addFormDisplayGroup);

    // query displayGroups and add all relevant options.
    const initialValue = $displaySelect.data('initialValue');
    const initialKey = $displaySelect.data('initialKey');
    const dataObj = {};
    dataObj[initialKey] = initialValue;
    dataObj['isDisplaySpecific'] = -1;
    dataObj['forSchedule'] = 1;

    // Skip populating the Display select input if no display
    // or display group filter is provided
    if (addFormDisplayGroup.length > 0) {
      $.ajax({
        url: $displaySelect.data('searchUrl'),
        type: 'GET',
        data: dataObj,
      }).then(function(data) {
        // create the option and append to Select2
        data.data.forEach((object) => {
          const option = new Option(
            object[$displaySelect.data('textProperty')],
            object[$displaySelect.data('idProperty')],
            true,
            true,
          );
          $displaySelect.append(option);
        });

        // Trigger change but skip auto save
        $displaySelect.trigger(
          'change',
          [{
            skipSave: true,
          }],
        );

        // manually trigger the `select2:select` event
        $displaySelect.trigger({
          type: 'select2:select',
          params: {
            data: data,
          },
        });
      });
    }
  }

  // Sync group
  $('#syncGroupId', dialog).on('select2:select', function(event) {
    const $target = $(event.currentTarget);
    const eventId =
      dialog.find('form').data().eventSyncGroupId ==
        $target.select2('data')[0].id ?
        dialog.find('form').data().eventId : null;
    $.ajax({
      type: 'GET',
      url: dialog.find('form').data().fetchSyncDisplays
        .replace(':id', $target.select2('data')[0].id),
      cache: false,
      dataType: 'json',
      data: {
        eventId: eventId,
      },
    })
      .then(
        (response) => {
          if (!response.success) {
            SystemMessageInline(
              (response.message === '') ?
                translations.failure : response.message,
              form.closest('.modal'),
            );
          }
          const $contentSelector = $('#content-selector');
          $contentSelector.show();

          dialog.find('#contentSelectorTable tbody')
            .html('').append(
              templates.calendar.syncEventContentSelector({
                ...response.data,
                ...{
                  urlForLayoutSearch: urlForLayoutSearch,
                  trans: translations.schedule.syncEventSelector,
                },
              }),
            );
          const formId = dialog.find('form').attr('id');
          dialog.find('.pagedSelect select.form-control.syncContentSelect')
            .each(function(_idx, el) {
              makePagedSelect($(el), '#' + formId);
            });

          dialog.find('.pagedSelect select.form-control.syncContentSelect')
            .on('select2:select', function(ev) {
              if (
                $(ev.currentTarget).data().displayId ===
                $(ev.currentTarget).data().leadDisplayId
              ) {
                $('#setMirrorContent').removeClass('d-none');
              }
            });

          dialog.find('#setMirrorContent').on('click', function(ev) {
            const leadDisplayId = $(ev.currentTarget).data().displayId;
            const leadLayoutId =
              $('#layoutId_' + leadDisplayId).select2('data')[0].id;

            dialog
              .find('.pagedSelect select.form-control.syncContentSelect')
              .not('#layoutId_' + leadDisplayId)
              .each(function(_idx, el) {
                $(el).data().initialValue = leadLayoutId;
                makePagedSelect($(el), '#' + formId);
              });
          });
        }, (xhr) => {
          SystemMessage(xhr.responseText, false);
        });
  });
};

/**
 * Configure criteria fields on the schedule add/edit forms.
 * @param {object} dialog - Dialog object
 */
const configureCriteriaFields = function(dialog) {
  const $fields = dialog.find('#scheduleCriteriaFields');
  if ($fields.length <= 0) {
    return;
  }

  // Get the scheduleCriteria from the data attribute
  const scheduleCriteria = $fields.data('scheduleCriteria');

  // Extract the types from scheduleCriteria
  const types = scheduleCriteria ? scheduleCriteria.types : [];

  // Function to populate type dropdowns
  const populateTypeDropdown = function($typeSelect) {
    if (types && types.length > 0) {
      types.forEach((type) => {
        $typeSelect.append(new Option(type.name, type.id));
      });
    }
  };

  // Function to update metrics field
  const updateMetricsField = function(
    $row,
    typeId,
    selectedMetric,
    elementValue,
    selectedCondition,
  ) {
    const $metricLabel = $row.find('label[for="criteria_metric[]"]');
    let $metricField;

    if (typeId === 'custom') {
      // change the input type to text
      $metricField = $('<input>', {
        class: 'form-control',
        name: 'criteria_metric[]',
        type: 'text',
        value: selectedMetric,
      });
    } else {
      // Create a dropdown or handle as a text input if only one metric
      const type = types ? types.find((t) => t.id === typeId) : null;

      if (type) {
        const metrics = type.metrics;

        if (metrics.length === 1) {
          const metric = metrics[0];
          selectedMetric = metric.id;

          // Initialize new text fields
          $metricField = createReadonlyAndHiddenFields(
            metric.name,
            metric.id,
            'criteria_metric[]',
          );
        } else {
          // Create a dropdown for multiple metrics
          $metricField = $('<select>', {
            class: 'form-control',
            name: 'criteria_metric[]',
          });

          metrics.forEach((metric) => {
            $metricField.append(new Option(metric.name, metric.id));
          });

          // Set the selected metric if provided, otherwise default to the first
          selectedMetric = selectedMetric || metrics[0]?.id;
          $metricField.val(selectedMetric);
        }

        // Find the selected metric's data
        const metric = metrics.find((m) => m.id === selectedMetric);

        if (metric) {
          // Update the value field based on the selected metric
          updateValueField($row, metric, elementValue);

          // Update the condition field based on the selected metric
          if (metric.conditions) {
            // use defined conditions
            updateConditionField($row, metric.conditions, selectedCondition);
          } else {
            // Use default conditions if none are defined
            const criteriaDefaultCondition = $('#scheduleCriteriaFields').data(
              'criteriaDefaultCondition',
            );
            updateConditionFieldToDefault(
              $row,
              criteriaDefaultCondition,
              selectedCondition,
            );
          }
        }
      } else {
        // change the input type back to text
        $metricField = $('<input>', {
          class: 'form-control',
          name: 'criteria_metric[]',
          type: 'text',
          value: '',
        });
      }
    }

    // Remove only input or select elements inside the label
    $metricLabel.find('input, select').remove();
    $metricLabel.append($metricField);
  };

  // Function to update value field
  const updateValueField = function($row, metric, elementValue) {
    const $valueLabel = $row.find('label[for="criteria_value[]"]');
    let $valueField;

    if (metric.values && metric.values.inputType === 'dropdown') {
      // Create a dropdown or handle as a text input if only one metric
      if (metric.values.values.length === 1) {
        const value = metric.values.values[0];

        // Initialize the text fields
        $valueField = createReadonlyAndHiddenFields(
          value.title,
          value.id,
          'criteria_value[]',
        );
      } else {
        // change input type to dropdown
        $valueField = $('<select>', {
          name: 'criteria_value[]',
          class: 'form-control',
        });
        if (metric.values.values) {
          metric.values.values.forEach((value) => {
            $valueField.append(new Option(value.title, value.id));
          });
        }

        // Set the selected value
        $valueField.val(elementValue);
      }
    } else {
      // change input type according to inputType's value
      const inputType = metric.values ? metric.values.inputType : 'text';
      const value = elementValue || '';
      $valueField = $('<input>', {
        class: 'form-control',
        name: 'criteria_value[]',
        type: inputType,
        value: value,
      });
    }

    // Remove only input or select elements inside the label
    $valueLabel.find('input, select').remove();
    $valueLabel.append($valueField);
  };

  // Existing criteria?
  const existingCriteria = $fields.data('criteria');
  if (existingCriteria && existingCriteria.length > 0) {
    // Yes there are existing criteria
    // Go through each one and add a field row to the form.
    let i = 0;
    $.each(existingCriteria, function(index, element) {
      i++;
      // Only the first element should have the 'Add' btn functionality
      element.isAdd = i === 1;
      element.i = i;
      const $newField = $(templates.schedule.criteriaFields({
        ...element,
        ...{
          trans: translations.schedule.criteriaFields,
        },
      }));
      $fields.append($newField);

      // Populate the type field
      const $typeSelect = $newField.find('select[name="criteria_type[]"]');
      populateTypeDropdown($typeSelect);

      // Set the selected type
      $typeSelect.val(element.type);

      // Update metrics and value fields based on the selected type and metric
      updateMetricsField(
        $newField,
        element.type,
        element.metric,
        element.value,
        element.condition,
      );
    });
  } else {
    // If no existing criterion, add an empty field at top
    const $newRow = $(templates.schedule.criteriaFields({
      isAdd: true,
      trans: translations.schedule.criteriaFields,
    }));
    const $newTypeSelect = $newRow.find('select[name="criteria_type[]"]');

    // Populate type dropdown based on scheduleCriteria
    populateTypeDropdown($newTypeSelect);
    $fields.append($newRow);
  }

  // Buttons we've added should be bound
  $fields.on('click', 'button', function(e) {
    e.preventDefault();
    const $button = $(e.currentTarget);
    if ($button.data('isAdd')) {
      // Only the first element should have the 'Add' btn functionality
      const newField = $(templates.schedule.criteriaFields({
        isAdd: false,
        trans: translations.schedule.criteriaFields,
      }));
      $fields.append(newField);

      // Populate the type field for the new row
      const $newTypeSelect = newField.find('select[name="criteria_type[]"]');
      populateTypeDropdown($newTypeSelect);

      $button.data('isAdd', true);
    } else {
      $button.closest('.form-group').remove();
    }
  });
};

const processCriteriaFields = function($form, data) {
  data.criteria = [];
  $.each(data.criteria_metric, function(index, element) {
    if (element) {
      data.criteria.push({
        id: data.criteria_id[index],
        type: data.criteria_type[index],
        metric: element,
        condition: data.criteria_condition[index],
        value: data.criteria_value[index],
      });
    }
  });

  // Tidy up fields.
  delete data['criteria_id'];
  delete data['criteria_type'];
  delete data['criteria_metric'];
  delete data['criteria_criteria'];
  delete data['criteria_value'];
};
