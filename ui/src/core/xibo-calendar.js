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
let events = [];
let mymap;
let mymapmarker;

$(function() {
  let getJsonRequestControl = null;

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

  // Set up the navigational controls
  $('.btn-group button[data-calendar-nav]').each(function(_idx, el) {
    const $this = $(el);
    $this.on('click', function() {
      calendar.navigate($this.data('calendar-nav'));
    });
  });

  $('.btn-group button[data-calendar-view]').each(function(_idx, el) {
    const $this = $(el);
    $this.on('click', function() {
      calendar.view($this.data('calendar-view'));
      $('#range').val($this.data('calendar-view'));
    });
  });

  $('a[data-toggle="tab"].schedule-nav').on('shown.bs.tab', function(e) {
    const activeTab = $(e.target).attr('href');
    if (activeTab === '#calendar-view') {
      $('#range').trigger('change');
    } else {
      if ($('#range').val() === 'agenda') {
        $('#range').val('day').trigger('change');
      }
    }
  });

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
    };

    $('#range').on('change', function() {
      if (calendar != undefined) {
        let range = $('#range').val();
        const isPast = range.includes('last');

        if (range === 'custom') {
          $('#fromDt, #toDt').on('change', function() {
            navigateToCalendarDate();
            const from = moment(
              moment($('#fromDt').val())
                .startOf('day')
                .format(systemDateFormat),
            );
            const to = moment(
              moment($('#toDt').val())
                .startOf('day')
                .format(systemDateFormat),
            );

            const diff = to.diff(from, 'days');

            if (diff < 1) {
              calendar.options.view === 'agenda' ?
                calendar.view('agenda') :
                calendar.view('day');
            } else if (diff >= 1 && diff <= 7) {
              calendar.view('week');
            } else if (diff > 7 && diff <= 31) {
              calendar.view('month');
            } else {
              calendar.view('year');
            }
          });
        } else {
          range = isPast ? range.replace('last', '') : range;
          calendar.view(range);
        }
        // for agenda, switch to calendar tab.
        if (range === 'agenda') {
          $('#calendar-tab').trigger('click');
        }
      }

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

    // Location filter init
    const $map = $('.cal-event-location-map #geoFilterAgendaMap');

    // Get location button
    $('#getLocation').off().on('click', function(ev) {
      const $self = $(ev.currentTarget);

      // Disable button
      $self.prop('disabled', true);

      navigator.geolocation.getCurrentPosition(function(location) { // success
        // Populate location fields
        $('#geoLatitude').val(location.coords.latitude).change();
        $('#geoLongitude').val(location.coords.longitude).change();

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
    $('#toggleMap').off().on('click', function() {
      $map.toggleClass('d-none');

      if (!$map.hasClass('d-none')) {
        generateFilterGeoMap();
      }
    });

    // Clear location button
    $('#clearLocation').off().on('click', function() {
      // Populate location fields
      $('#geoLatitude').val('').change();
      $('#geoLongitude').val('').change();

      if (!$map.hasClass('d-none')) {
        generateFilterGeoMap();
      }
    });

    // Change events reloads the calendar view and map
    $('#geoLatitude, #geoLongitude').off().change(_.debounce(function() {
      calendar.view();
    }, 400));

    // Calendar options
    const options = {
      time_start: '00:00',
      time_end: '00:00',
      events_source: function() {
        return events;
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
        const calendarOptions = $('#CalendarContainer').data();
        const $calendarErrorMessage = $('#calendar-error-message');

        // Append display groups and layouts
        const isShowAll = $('#showAll').is(':checked');

        // Enable or disable the display list
        // according to whether show all is selected
        // we do this before we serialise because
        // serialising a disabled list gives nothing
        $('#DisplayList, #DisplayGroupList').prop('disabled', isShowAll);

        if (this.options.view !== 'agenda') {
          $('.cal-event-agenda-filter, ' +
            '.xibo-agenda-calendar-controls, ' +
            '#btn-month-view').hide();
          $('#btn-agenda-view').show();
          $('.non-agenda-filter').find('input, select').prop('disabled', false);

          // Serialise
          const displayGroups =
            $('select[name="displayGroupIds[]"').serialize();
          const displaySpecificGroups =
            $('select[name="displaySpecificGroupIds[]"').serialize();
          const displayLayouts = $('#campaignIdFilter').serialize();
          const eventTypes = $('#eventTypeId').serialize();
          const geoAware = $('#geoAware').serialize();
          const recurring = $('#recurring').serialize();
          const name = $('#name').serialize();
          const nameRegEx =
            'useRegexForName=' + $('#useRegexForName').is('checked');
          const nameLogicalOperator = $('#logicalOperatorName').serialize();

          !displayGroups && !displayLayouts && !displaySpecificGroups ?
            $calendarErrorMessage.show() :
            $calendarErrorMessage.hide();

          let url = calendarOptions.eventSource;

          // Append the selected filters
          url += '?' + displayLayouts + '&' + eventTypes + '&' + geoAware +
            '&' + recurring + '&' + name +
            '&' + nameRegEx + '&' + nameLogicalOperator;

          // Should we append displays?
          if (
            !displayGroups && !displaySpecificGroups && displayLayouts !== ''
          ) {
            // Ignore the display list
            url += '&' + 'displayGroupIds[]=-1';
          } else if (displayGroups !== '' || displaySpecificGroups !== '') {
            // Append display list
            url += '&' + displayGroups + '&' + displaySpecificGroups;
          }

          events = [];

          // Populate the events array via AJAX
          const params = {
            from: moment(this.options.position.start.getTime())
              .format(systemDateFormat),
            to: moment(this.options.position.end.getTime())
              .format(systemDateFormat),
          };

          // If there is already a request, abort it
          if (getJsonRequestControl) {
            getJsonRequestControl.abort();
          }

          $('#calendar-progress').addClass('show');

          getJsonRequestControl = $.getJSON(url, params)
            .done(function(data) {
              events = data.result;

              if (done != undefined) {
                done();
              }

              calendar._render();

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

              $('#calendar-progress').removeClass('show');
            })
            .fail(function(res) {
              $('#calendar-progress').removeClass('show');

              if (done != undefined) {
                done();
              }

              calendar._render();

              if (res.statusText != 'abort') {
                toastr.error(translations.failure);
                console.error(res);
              }
            });
        } else {
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
            calendar.view();
          });

          $('.time-picker-step-btn').off().on('click', function(ev) {
            $timePicker.slider(
              'setValue',
              $timePicker.slider('getValue') + $(ev.currentTarget).data('step'),
            );
            calendar.view();
          });

          // Get selected display groups
          let selectedDisplayGroup = $('.cal-context').data().selectedTab;
          const displayGroupsList = [];
          let chooseAllDisplays = false;

          if (!isShowAll) {
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

                  if (typeof selectedDisplayGroup == 'undefined') {
                    selectedDisplayGroup = $self.val();
                  }
                }
              });
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
            date: dateSelected.format(systemDateFormat),
          };

          // if the result are empty create a empty object and reset the results
          if (jQuery.isEmptyObject(events['results'])) {
            // events let must be an array for
            // compatibility with the previous implementation
            events = [];
            events['results'] = {};
          }

          // Save displaygroup list and the selected display
          events['displayGroupList'] = displayGroupsList;
          events['selectedDisplayGroup'] = selectedDisplayGroup;

          // Clean error message
          events['errorMessage'] = '';

          // Clean cache/results if its requested by the options
          if (calendar.options['clearCache'] == true) {
            events['results'] = {};
          }

          // If there is already a request, abort it
          if (getJsonRequestControl) {
            getJsonRequestControl.abort();
          }

          // 0 - If all is selected, force the user to specify the displaygroups
          if (isShowAll) {
            events['errorMessage'] = 'all_displays_selected';

            if (done != undefined) {
              done();
            }

            calendar._render();
          } else if (
            displayGroupsList == null ||
            Array.isArray(displayGroupsList) &&
            displayGroupsList.length == 0
          ) {
            // 1 - if there are no displaygroups selected
            events['errorMessage'] = 'display_not_selected';

            if (done != undefined) {
              done();
            }

            calendar._render();
          } else if (
            !jQuery.isEmptyObject(events['results'][selectedDisplayGroup]) &&
            events['results'][selectedDisplayGroup]['request_date'] ==
            params.date &&
            events['results'][selectedDisplayGroup]['geoLatitude'] ==
            $('#geoLatitude').val() &&
            events['results'][selectedDisplayGroup]['geoLongitude'] ==
            $('#geoLongitude').val()
          ) {
            // 2 - Use cache if the element was already
            // saved for the requested date
            if (done != undefined) {
              done();
            }

            calendar._render();
          } else {
            $('#calendar-progress').addClass('show');

            // 3 - make request to get the data for the events
            getJsonRequestControl = $.getJSON(url, params)
              .done(function(data) {
                let noEvents = true;

                if (
                  !jQuery.isEmptyObject(data.data) &&
                  data.data.events != undefined &&
                  data.data.events.length > 0
                ) {
                  events['results'][String(selectedDisplayGroup)] = data.data;
                  // eslint-disable-next-line max-len
                  events['results'][String(selectedDisplayGroup)]['request_date'] = params.date;

                  noEvents = false;

                  if (
                    $('#geoLatitude').val() != undefined &&
                    $('#geoLatitude').val() != '' &&
                    $('#geoLongitude').val() != undefined &&
                    $('#geoLongitude').val() != ''
                  ) {
                    // eslint-disable-next-line max-len
                    events['results'][String(selectedDisplayGroup)]['geoLatitude'] =
                      $('#geoLatitude').val();
                    // eslint-disable-next-line max-len
                    events['results'][String(selectedDisplayGroup)]['geoLongitude'] =
                      $('#geoLongitude').val();

                    events['results'][String(selectedDisplayGroup)]['events'] =
                      filterEventsByLocation(
                        // eslint-disable-next-line max-len
                        events['results'][String(selectedDisplayGroup)]['events'],
                      );

                    noEvents = (data.data.events.length <= 0);
                  }
                }

                if (noEvents) {
                  events['results'][String(selectedDisplayGroup)] = {};
                  events['errorMessage'] = 'no_events';
                }

                if (done != undefined) {
                  done();
                }

                calendar._render();

                $('#calendar-progress').removeClass('show');
              })
              .fail(function(res) {
                // Deal with the failed request

                if (done != undefined) {
                  done();
                }

                if (res.statusText != 'abort') {
                  events['errorMessage'] = 'request_failed';
                }

                calendar._render();

                $('#calendar-progress').removeClass('show');
              });
          }
        }
      },
      onAfterEventsLoad: function(events) {
        if (this.options.view == 'agenda') {
          // When agenda panel is ready, turn tables into datatables with paging
          $('.agenda-panel').ready(function() {
            $('.agenda-table-layouts').DataTable({
              searching: false,
            });
          });
        }

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

        if (typeof this.getTitle === 'function') {
          $('h1.page-header').text(this.getTitle());
        }

        $('.btn-group button').removeClass('active');
        $('button[data-calendar-view="' + view + '"]').addClass('active');
      },
      language: calendarLanguage,
    };

    options.type = calendarOptions.calendarType;
    calendar = $('#Calendar').calendar(options);

    // Set event when clicking on a tab, to refresh the view
    $('.cal-context').on('click', 'a[data-toggle="tab"]', function(e) {
      $('.cal-context').data().selectedTab = $(e.currentTarget).data('id');
      calendar.view();
    });

    // When selecting a layout row, create a Breadcrumb Trail
    // and select the correspondent Display Group(s) and the Campaign(s)
    $('.cal-context').on('click', 'tbody tr', function(e) {
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
          calendar._breadcrumbTrail(
            $self.data('elemId'),
            events,
            $self.data('eventId'),
          ),
        );

        // Create mini layout preview
        createMiniLayoutPreview(
          layoutPreviewUrl.replace(':id', $self.data('elemId')),
        );

        // Initialize container for the Schedule modal handling
        XiboInitialise('#CalendarContainer');
      }

      // Select the clicked element and the linked elements
      agendaSelectLinkedElements(
        $self.closest('table').data('type'),
        $self.data('elemId'), events,
        $self.data('eventId'),
      );
    });
  }
});

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
  // console.log("Setup schedule form");

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
    const conversion = Math.round((3600 * percentage) / 100);
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
    $(dialog).find('#scheduleEditForm, #scheduleEditSyncForm');
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
          fieldVal == 10

        ) ? 'none' : '';
      endTimeControlDisplay = (fieldVal == 2 || relativeTime) ? 'none' : '';
      startTimeControlDisplay = (relativeTime && fieldVal != 2) ? 'none' : '';
      const dayPartControlDisplay = (fieldVal == 2) ? 'none' : '';
      let commandControlDisplay = (fieldVal == 2) ? '' : 'none';
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

      $('.layout-control', dialog).css('display', layoutControlDisplay);
      $('.endtime-control', dialog).css('display', endTimeControlDisplay);
      $('.starttime-control', dialog).css('display', startTimeControlDisplay);
      $('.day-part-control', dialog).css('display', dayPartControlDisplay);
      $('.command-control', dialog).css('display', commandControlDisplay);
      $('.interrupt-control', dialog).css('display', interruptControlDisplay);
      $('.action-control', dialog).css('display', actionControlDisplay);
      $('.max-plays-control', dialog).css('display', maxPlaysControlDisplay);
      $('.media-control', dialog).css('display', mediaScheduleControlDisplay);
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

        // Change Label and Help text when Layout event type is selected
        $layoutControl.children('label')
          .text($campaignSelect.data('transLayout'));
        $layoutControl.children('div').children('small.form-text.text-muted')
          .text($campaignSelect.data('transLayoutHelpText'));
      } else {
        // Load Campaigns only
        searchIsLayoutSpecific = 0;

        // Change Label and Help text when Campaign event type is selected
        $layoutControl.children('label')
          .text($campaignSelect.data('transCampaign'));
        $layoutControl.children('div').children('small.form-text.text-muted')
          .text($campaignSelect.data('transCampaignHelpText'));
      }

      // Set the search criteria
      $campaignSelect.data('searchIsLayoutSpecific', searchIsLayoutSpecific);

      break;

    case 'dayPartId':
      if (!el.is(':visible')) {
        return;
      }

      const meta = el.find('option[value=' + fieldVal + ']').data();

      endTimeControlDisplay =
        (meta.isCustom === 0 || relativeTime) ? 'none' : '';
      startTimeControlDisplay =
        (meta.isAlways === 1 || relativeTime) ? 'none' : '';
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
      commandControlDisplay = (fieldVal == 'command') ? '' : 'none';

      $('.layout-code-control', dialog).css('display', layoutCodeControl);
      $('.command-control', dialog).css('display', commandControlDisplay);

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
};

const duplicateScheduledEvent = function($scheduleForm) {
  // Set the edit form URL to that of the add form
  $scheduleForm.attr('action', $scheduleForm.data().addUrl)
    .attr('method', 'post');

  // Remove the duplicate button
  $('#scheduleDuplateButton').remove();

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

  $('#mediaId, #playlistId', dialog).on('select2:select', function(event) {
    let hasFullScreenLayout = false;
    if (event.params.data.data !== undefined) {
      hasFullScreenLayout = event.params.data.data[0].hasFullScreenLayout;
    } else if (event.params.data.hasFullScreenLayout !== undefined) {
      hasFullScreenLayout = event.params.data.hasFullScreenLayout;
    }

    if (hasFullScreenLayout) {
      $('.no-full-screen-layout').css('display', 'none');
    } else {
      if ($(event.currentTarget).attr('id') === 'mediaId') {
        $('.no-full-screen-layout').css('display', '');
      } else {
        $('.no-full-screen-layout.media-playlist-control').css('display', '');
      }
    }
  });

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
          $contentSelector.removeClass('d-none');

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
