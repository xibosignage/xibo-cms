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
var calendar;
var events = [];
var mymap;
var mymapmarker;

$(document).ready(function() {
    var getJsonRequestControl = null;

    // Set a listener for popover clicks
    //  http://stackoverflow.com/questions/11703093/how-to-dismiss-a-twitter-bootstrap-popover-by-clicking-outside
    $('body').on('click', function (e) {
        $('[data-toggle="popover"]').each(function () {
            //the 'is' for buttons that trigger popups
            //the 'has' for icons within a button that triggers a popup
            if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                $(this).popover('hide');
            }
        });
    });

    // Set a listener for type change event
    $('body').on('change', '#scheduleCriteriaFields select[name="criteria_type[]"]', function(e) {
        // Capture the event target
        var $target = $(e.target);
        // Get the row where the type was changed
        var $row = $target.closest('.form-group');
        var selectedType = $target.val();
        var $fields = $('#scheduleCriteriaFields');
        var scheduleCriteria = $fields.data('scheduleCriteria');

        if (scheduleCriteria) {
            if (selectedType === 'custom') {
                // Use a text input for metrics
                updateMetricsFieldAsText($row);
                // Use a text input for values
                updateValueFieldAsText($row);
            } else if (scheduleCriteria) {
                // Update metrics based on the selected type and change text field to dropdown
                updateMetricsField($row, scheduleCriteria, selectedType);
            }
        }
    });

    // Function to update the metrics field based on the selected type
    function updateMetricsField($row, scheduleCriteria, type) {
        var $metricLabel = $row.find('label[for="criteria_metric[]"]');
        var $metricSelect = $('<select class="form-control" name="criteria_metric[]"></select>');

        // Check if scheduleCriteria has types
        if (scheduleCriteria.types) {
            var typeData = scheduleCriteria.types.find(t => t.id === type);
            if (typeData) {
                var metrics = typeData.metrics;
                metrics.forEach(function(metric) {
                    $metricSelect.append(new Option(metric.name, metric.id));
                });

                // Check for the currently selected metric and update the value field accordingly
                var selectedMetric = $metricSelect.val();
                var metricData = metrics.find(m => m.id === selectedMetric);

                // Update the value field based on the selected metric
                if (metricData && metricData.values) {
                    updateValueField($row, metricData.values);
                } else {
                    updateValueFieldAsText($row);
                }
            }
        }

        // Remove only input or select elements inside the label
        $metricLabel.find('input, select').remove();
        $metricLabel.append($metricSelect);
    }

    // Function to revert the metrics field to a text input
    function updateMetricsFieldAsText($row) {
        var $metricLabel = $row.find('label[for="criteria_metric[]"]');
        var $metricInput = $('<input class="form-control" name="criteria_metric[]" type="text" value="" />');

        // Remove only input or select elements inside the label
        $metricLabel.find('input, select').remove();
        $metricLabel.append($metricInput);
    }

    // Handle value field update outside of updateMetricsField
    $('body').on('change', '#scheduleCriteriaFields select[name="criteria_metric[]"]', function(e) {
        // Capture the event target
        var $target = $(e.target);
        // Get the row where the metric was changed
        var $row = $target.closest('.form-group');
        var selectedMetric = $target.val();
        var $fields = $('#scheduleCriteriaFields');
        var scheduleCriteria = $fields.data('scheduleCriteria');
        var selectedType = $row.find('select[name="criteria_type[]"]').val();

        if (scheduleCriteria && selectedType) {
            var typeData = scheduleCriteria.types.find(t => t.id === selectedType);
            if (typeData) {
                var metrics = typeData.metrics;
                var metricData = metrics.find(m => m.id === selectedMetric);

                // Update the value field based on the selected metric
                if (metricData && metricData.values) {
                    updateValueField($row, metricData.values);
                } else {
                    updateValueFieldAsText($row);
                }
            }
        }
    });

    // Function to update the value field based on the selected metric's values
    function updateValueField($row, values) {
        var $valueLabel = $row.find('label[for="criteria_value[]"]');

        // Remove only input or select elements inside the label
        $valueLabel.find('input, select').remove();

        // Check the inputType in the values object
        if (values.inputType === 'dropdown') {
            // change to dropdown and populate
            var $valueSelect = $('<select class="form-control" name="criteria_value[]"></select>');
            values.values.forEach(function(value) {
                $valueSelect.append(new Option(value.title, value.id));
            });
            $valueLabel.append($valueSelect);
        } else {
            // change to either text or number field
            var $valueInput;
            if (values.inputType === 'text' || values.inputType === 'number' || values.inputType === 'date') {
                $valueInput = $('<input class="form-control" name="criteria_value[]" type="' + values.inputType + '" value="" />');
            }
            $valueLabel.append($valueInput);
        }
    }

    // Function to revert the value field to a text input
    function updateValueFieldAsText($row) {
        var $valueLabel = $row.find('label[for="criteria_value[]"]');
        var $valueInput = $('<input class="form-control" name="criteria_value[]" type="text" value="" />');

        // Remove only input or select elements inside the label
        $valueLabel.find('input, select').remove();
        $valueLabel.append($valueInput);
    }

    // Set up the navigational controls
    $('.btn-group button[data-calendar-nav]').each(function() {
        var $this = $(this);
        $this.click(function() {
            calendar.navigate($this.data('calendar-nav'));
        });
    });

    $('.btn-group button[data-calendar-view]').each(function() {
        var $this = $(this);
        $this.click(function () {
            calendar.view($this.data('calendar-view'));
            $('#range').val($this.data('calendar-view'))
        });
    })

    $('a[data-toggle="tab"].schedule-nav').on('shown.bs.tab', function (e) {
        let activeTab = $(e.target).attr("href")
        if (activeTab === '#calendar-view') {
            $('#range').trigger('change');
        } else {
            if ($('#range').val() === 'agenda') {
                $('#range').val('day').trigger('change')
            }
        }
    });

    // Calendar is initialised without any event_source (that is changed when the selector is used)
    if (($('#Calendar').length > 0)) {
        // Get some options for the calendar
        var calendarOptions = $("#CalendarContainer").data();

        // Callback function to navigate to calendar date with the date picker
        const navigateToCalendarDate = function() {
            if(calendar != undefined) {
                let selectedDate = moment(moment($('#fromDt').val()).format(systemDateFormat));
                // Add event to the picker to update the calendar
                // only if the selected date is valid
                if (selectedDate.isValid()) {
                    calendar.navigate('date', selectedDate);
                }
            }
        };

        const navigateToCalendarDatePicker = function() {
            calendar.navigate('date', moment($('#dateInput input[data-input]').val()));
        }

        $('#range').on('change', function() {
            if(calendar != undefined) {
                let range = $('#range').val();
                let isPast = range.includes('last');

                if (range === 'custom') {
                    $('#fromDt, #toDt').on('change', function () {
                        navigateToCalendarDate()
                        let from =  moment(
                          moment($('#fromDt').val())
                            .startOf('day')
                            .format(systemDateFormat)
                        )
                        let to =  moment(
                          moment($('#toDt').val())
                            .startOf('day')
                            .format(systemDateFormat)
                        )

                        let diff = to.diff(from, 'days');

                        if (diff < 1) {
                            calendar.options.view === 'agenda'
                              ? calendar.view('agenda')
                              : calendar.view('day')
                        } else if (diff >= 1 && diff <= 7) {
                            calendar.view('week')
                        } else if (diff > 7 && diff <= 31) {
                            calendar.view('month')
                        } else {
                            calendar.view('year')
                        }
                    });
                } else {
                    range = isPast ? range.replace('last', '') : range;
                    calendar.view(range);
                }
                // for agenda, switch to calendar tab.
                if (range === 'agenda') {
                    $('#calendar-tab').trigger('click')
                }
            }

            updateRangeFilter($('#range'), $('#fromDt'), $('#toDt'), navigateToCalendarDate)
        });
        updateRangeFilter($('#range'), $('#fromDt'), $('#toDt'))

        // Select picker options
        var pickerOptions = {};

        if( calendarType == 'Jalali') {
            pickerOptions = {
                autoClose: true,
                altField: '#dateInputLink',
                altFieldFormatter: function(unixTime) {
                    var newDate = moment.unix(unixTime / 1000);
                    newDate.set('hour', 0);
                    newDate.set('minute', 0);
                    newDate.set('second', 0);
                    return newDate.format(jsDateFormat);
                },
                onSelect: function() {},
                onHide: function() {
                    // Trigger change after close
                    $('#dateInput').trigger('change');
                    $('#dateInputLink').trigger('change');
                }
            };
        } else if( calendarType == 'Gregorian') {
            pickerOptions = {
                wrap: true,
                altFormat: jsDateOnlyFormat
            };
        }

        // Create the date input shortcut
        initDatePicker(
          $('#dateInput'),
          systemDateFormat,
          jsDateOnlyFormat,
          pickerOptions,
          navigateToCalendarDatePicker,
          false // clear button
        );

        // Location filter init
        var $map = $('.cal-event-location-map #geoFilterAgendaMap');

        // Get location button
        $('#getLocation').off().click(function() {
            var $self = $(this);

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
                maximumAge: 0
            });
        });

        // Location map button
        $('#toggleMap').off().click(function() {
            $map.toggleClass('d-none');

            if(!$map.hasClass('d-none')) {
                generateFilterGeoMap();
            }
        });

        // Clear location button
        $('#clearLocation').off().click(function() {
            // Populate location fields
            $('#geoLatitude').val('').change();
            $('#geoLongitude').val('').change();

            if(!$map.hasClass('d-none')) {
                generateFilterGeoMap();
            }
        });

        // Change events reloads the calendar view and map
        $('#geoLatitude, #geoLongitude').off().change(_.debounce(function() {
            calendar.view();
        }, 400));

        // Calendar options
        var options = {
            time_start: '00:00',
            time_end: '00:00',
            events_source: function () { return events; },
            view: 'month',
            tmpl_path: function (name) {
                return 'calendar-template-' + name;
            },
            tmpl_cache: true,
            onBeforeEventsLoad: function (done) {

                var calendarOptions = $("#CalendarContainer").data();
                const $calendarErrorMessage = $('#calendar-error-message');

                // Append display groups and layouts
                var isShowAll = $('#showAll').is(':checked');

                // Enable or disable the display list according to whether show all is selected
                // we do this before we serialise because serialising a disabled list gives nothing
                $('#DisplayList, #DisplayGroupList').prop('disabled', isShowAll);

                if (this.options.view !== 'agenda') {
                    $('.cal-event-agenda-filter, .xibo-agenda-calendar-controls, #btn-month-view').hide();
                    $('#btn-agenda-view').show();
                    $('.non-agenda-filter').find('input, select').prop('disabled', false)

                    // Serialise
                    let displayGroups = $('select[name="displayGroupIds[]"').serialize();
                    let displaySpecificGroups = $('select[name="displaySpecificGroupIds[]"').serialize();
                    let displayLayouts = $('#campaignIdFilter').serialize();
                    let eventTypes = $('#eventTypeId').serialize();
                    let geoAware = $('#geoAware').serialize();
                    let recurring = $('#recurring').serialize();
                    let name = $('#name').serialize();
                    let nameRegEx = 'useRegexForName=' + $('#useRegexForName').is('checked');
                    let nameLogicalOperator = $('#logicalOperatorName').serialize();

                    !displayGroups && !displayLayouts && !displaySpecificGroups
                      ? $calendarErrorMessage.show()
                      : $calendarErrorMessage.hide()

                    var url = calendarOptions.eventSource;

                    // Append the selected filters
                    url += '?' + displayLayouts + '&' + eventTypes + '&' + geoAware +
                      '&' + recurring + '&' + name +
                      '&' + nameRegEx + '&' + nameLogicalOperator;

                    // Should we append displays?
                    if (!displayGroups && !displaySpecificGroups && displayLayouts !== '') {
                        // Ignore the display list
                        url += '&' + 'displayGroupIds[]=-1';
                    } else if (displayGroups !== '' || displaySpecificGroups !== '') {
                        // Append display list
                        url += '&' + displayGroups + '&' + displaySpecificGroups;
                    }

                    events = [];

                    // Populate the events array via AJAX
                    let params = {
                        from: moment(this.options.position.start.getTime()).format(systemDateFormat),
                        to: moment(this.options.position.end.getTime()).format(systemDateFormat)
                    };

                    // If there is already a request, abort it
                    if (getJsonRequestControl) {
                        getJsonRequestControl.abort();
                    }

                    $('#calendar-progress').addClass('show');

                    getJsonRequestControl = $.getJSON(url, params)
                      .done(function (data) {
                          events = data.result;

                          if (done != undefined)
                              done();

                          calendar._render();

                          // Hook up any pop-overs (for small events)
                          $('[data-toggle="popover"]').popover({
                              trigger: "manual",
                              html: true,
                              placement: "bottom",
                              content: function () {
                                  return $(this).html();
                              }
                          }).on("mouseenter", function () {
                              var self = this;

                              // Hide all other popover
                              $('[data-toggle="popover"]').not(this).popover("hide");

                              // Show this popover
                              $(this).popover("show");

                              // Hide popover when mouse leaves it
                              $(".popover").off("mouseleave").on("mouseleave", function () {
                                  $(self).popover('hide');
                              });
                          }).on('shown.bs.popover', function () {
                              var source = $(this);
                              var popover = source.attr("aria-describedby");

                              $("#" + popover + " a").click(function (e) {
                                  e.preventDefault();
                                  XiboFormRender($(this));
                                  source.popover("hide");
                              });
                          });

                          $('#calendar-progress').removeClass('show');
                      })
                      .fail(function (res) {
                          $('#calendar-progress').removeClass('show');

                          if (done != undefined)
                              done();

                          calendar._render();

                          if (res.statusText != 'abort') {
                              toastr.error(translations.failure);
                              console.error(res);
                          }
                      });
                } else {

                    // Show time slider on agenda view and call the calendar view on slide stop event
                    $('.cal-event-agenda-filter, .xibo-agenda-calendar-controls, #btn-month-view').show();
                    $('#btn-agenda-view').hide();
                    $('.non-agenda-filter').find('input, select').prop('disabled', true);

                    // agenda has it is own error conditions.
                    $calendarErrorMessage.hide()

                    var $timePicker = $('#timePicker');

                    var momentNow = moment().tz ? moment().tz(timezone) : moment();

                    // Create slider ticks
                    var ticks = [];
                    var ticksLabels = [];
                    var ticksPositions = [];
                    for (var i = 0; i <= 1440; i += 120) {
                        // Last step get one less minute
                        var minutes = i === 1440 ? 1439 : i;
                        ticks.push(minutes);
                        ticksLabels.push(momentNow.clone().startOf('day').add(minutes, 'minutes').format(jsTimeFormat));
                        ticksPositions.push(i / 1440 * 100);
                    }

                    $timePicker.slider({
                        value: (momentNow.hour() * 60) + momentNow.minute(),
                        tooltip: 'always',
                        ticks: ticks,
                        ticks_labels: ticksLabels,
                        ticks_positions: ticksPositions,
                        formatter: function (value) {
                            return moment().startOf("day").minute(value).format(jsTimeFormat);
                        }
                    }).off('slideStop').on('slideStop', function (ev) {
                        calendar.view();
                    });

                    $('.time-picker-step-btn').off().on('click', function () {
                        $timePicker.slider('setValue', $timePicker.slider('getValue') + $(this).data('step'));
                        calendar.view();
                    });

                    // Get selected display groups
                    var selectedDisplayGroup = $('.cal-context').data().selectedTab;
                    var displayGroupsList = [];
                    var chooseAllDisplays = false;

                    if (!isShowAll) {
                        $('#DisplayList, #DisplayGroupList').prop('disabled', false);

                        // Find selected display group and create a display group list used to create tabs
                        $('select[name="displayGroupIds[]"] option, select[name="displaySpecificGroupIds[]"] option')
                          .each(function () {
                              var $self = $(this);

                              // If the all option is selected
                              if ($self.val() == -1 && $self.is(':selected')) {
                                  chooseAllDisplays = true;
                                  return true;
                              }

                              if ($self.is(':selected') || chooseAllDisplays) {
                                  displayGroupsList.push({
                                      id: $self.val(),
                                      name: $self.html(),
                                      isDisplaySpecific: $self.attr('type')
                                  });

                                  if (typeof selectedDisplayGroup == 'undefined') {
                                      selectedDisplayGroup = $self.val();
                                  }
                              }
                          });
                    }

                    // Sort display group list by name
                    displayGroupsList.sort(function (a, b) {
                        var nameA = a.name.toLowerCase(), nameB = b.name.toLowerCase()
                        if (nameA < nameB) //sort string ascending
                            return -1;
                        if (nameA > nameB)
                            return 1;

                        return 0; //default return value (no sorting)
                    });

                    var url = calendarOptions.agendaLink.replace(":id", selectedDisplayGroup);

                    var dateMoment = moment(this.options.position.start.getTime() / 1000, "X");
                    var timeFromSlider = ($('#timePickerSlider').length) ? $('#timePicker').slider('getValue') : 0;
                    var timeMoment = moment(timeFromSlider * 60, "X");

                    // Add hour to date to get the selected date
                    var dateSelected = moment(dateMoment + timeMoment);

                    // Populate the events array via AJAX
                    var params = {
                        "date": dateSelected.format(systemDateFormat)
                    };

                    // if the result are empty create a empty object and reset the results
                    if (jQuery.isEmptyObject(events['results'])) {

                        // events var must be an array for compatibility with the previous implementation
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

                        if (done != undefined)
                            done();

                        calendar._render();
                    } else if (displayGroupsList == null || Array.isArray(displayGroupsList) && displayGroupsList.length == 0) {
                        // 1 - if there are no displaygroups selected
                        events['errorMessage'] = 'display_not_selected';

                        if (done != undefined)
                            done();

                        calendar._render();
                    } else if (!jQuery.isEmptyObject(events['results'][selectedDisplayGroup]) && events['results'][selectedDisplayGroup]['request_date'] == params.date && events['results'][selectedDisplayGroup]['geoLatitude'] == $('#geoLatitude').val() && events['results'][selectedDisplayGroup]['geoLongitude'] == $('#geoLongitude').val()) {
                        // 2 - Use cache if the element was already saved for the requested date
                        if (done != undefined)
                            done();

                        calendar._render();
                    } else {

                        $('#calendar-progress').addClass('show');

                        // 3 - make request to get the data for the events
                        getJsonRequestControl = $.getJSON(url, params)
                          .done(function (data) {

                              var noEvents = true;

                              if (!jQuery.isEmptyObject(data.data) && data.data.events != undefined && data.data.events.length > 0) {
                                  events['results'][String(selectedDisplayGroup)] = data.data;
                                  events['results'][String(selectedDisplayGroup)]['request_date'] = params.date;

                                  noEvents = false;

                                  if ($('#geoLatitude').val() != undefined && $('#geoLatitude').val() != '' &&
                                    $('#geoLongitude').val() != undefined && $('#geoLongitude').val() != '') {
                                      events['results'][String(selectedDisplayGroup)]['geoLatitude'] = $('#geoLatitude').val();
                                      events['results'][String(selectedDisplayGroup)]['geoLongitude'] = $('#geoLongitude').val();

                                      events['results'][String(selectedDisplayGroup)]['events'] = filterEventsByLocation(events['results'][String(selectedDisplayGroup)]['events']);

                                      noEvents = (data.data.events.length <= 0);
                                  }
                              }

                              if (noEvents) {
                                  events['results'][String(selectedDisplayGroup)] = {};
                                  events['errorMessage'] = 'no_events';
                              }

                              if (done != undefined)
                                  done();

                              calendar._render();

                              $('#calendar-progress').removeClass('show');
                          })
                          .fail(function (res) {
                              // Deal with the failed request

                              if (done != undefined)
                                  done();

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
                    $('.agenda-panel').ready(function () {
                        $('.agenda-table-layouts').DataTable({
                            "searching": false
                        });
                    });
                }

                if (!events) {
                    return;
                }
            },
            onAfterViewLoad: function(view) {
                // Sync the date of the date picker to the current calendar date
                if (this.options.position.start != undefined && this.options.position.start != "") {
                    // Update timepicker
                    updateDatePicker($('#dateInput'), moment.unix(this.options.position.start.getTime() / 1000).format(systemDateFormat), systemDateFormat);
                }

                if (typeof this.getTitle === "function")
                    $('h1.page-header').text(this.getTitle());

                $('.btn-group button').removeClass('active');
                $('button[data-calendar-view="' + view + '"]').addClass('active');
            },
            language: calendarLanguage
        };

        options.type = calendarOptions.calendarType;
        calendar = $('#Calendar').calendar(options);

        // Set event when clicking on a tab, to refresh the view
        $('.cal-context').on('click', 'a[data-toggle="tab"]', function (e) {
            $('.cal-context').data().selectedTab = $(this).data("id");
            calendar.view();
        });

        // When selecting a layout row, create a Breadcrumb Trail and select the correspondent Display Group(s) and the Campaign(s)
        $('.cal-context').on('click', 'tbody tr', function (e) {
            var $self = $(this);
            var alreadySelected = $self.hasClass('selected');

            // Clean all selected elements
            $('.cal-event-breadcrumb-trail').hide();
            $('.cal-context tbody tr').removeClass('selected');
            $('.cal-context tbody tr').removeClass('selected-linked');

            // If the element was already selected return so that it can deselect everything
            if (alreadySelected)
                return;

            // If the click was in a layout table row create the breadcrumb trail
            if ($self.closest('table').data('type') == 'layouts'){
                $('.cal-event-breadcrumb-trail').show();

                // Clean div content
                $('.cal-event-breadcrumb-trail #content').html('');

                // Get the template and render it on the div
                $('.cal-event-breadcrumb-trail #content').append(calendar._breadcrumbTrail($self.data("elemId"), events, $self.data("eventId")));

                // Create mini layout preview
                createMiniLayoutPreview(layoutPreviewUrl.replace(':id', $self.data("elemId")));

                XiboInitialise("");
            }

            // Select the clicked element and the linked elements
            agendaSelectLinkedElements($self.closest('table').data('type'), $self.data("elemId"), events, $self.data("eventId"));
        });
    }
});

/**
 * Callback for the schedule form
 */
var setupScheduleForm = function(dialog) {
    //console.log("Setup schedule form");

    // geo schedule
    var $geoAware = $('#isGeoAware');
    var isGeoAware = $geoAware.is(':checked');
    let $form = dialog.find('form');

  // Configure the schedule criteria fields.
  configureCriteriaFields(dialog);

    if (isGeoAware) {
        // without this additional check the map will not load correctly, it should be initialised when we are on the Geo Location tab
        $('.nav-tabs a').on('shown.bs.tab', function(event){
            if ($(event.target).text() === 'Geo Location') {

                $('#geoScheduleMap').removeClass('d-none');
                generateGeoMap($form);
            }
        });
    }

    // hide/show and generate map according to the Geo Schedule checkbox value
    $geoAware.change(function() {
        isGeoAware = $('#isGeoAware').is(':checked');

        if (isGeoAware) {
            $('#geoScheduleMap').removeClass('d-none');
            generateGeoMap($form);
        } else {
            $('#geoScheduleMap').addClass('d-none');
        }
    });

    // Share of voice
    var shareOfVoice = $("#shareOfVoice");
    var shareOfVoicePercentage = $("#shareOfVoicePercentage");
    shareOfVoice.on("change paste keyup", function() {
        convertShareOfVoice(shareOfVoice.val());
    });

    shareOfVoicePercentage.on("change paste keyup", function() {
        var percentage = shareOfVoicePercentage.val();
        var conversion;
        conversion = Math.round((3600 * percentage) / 100);
        shareOfVoice.val(conversion);
    });


    var convertShareOfVoice = function(seconds) {
        var conversion;
        conversion = (100 * seconds) / 3600;
        shareOfVoicePercentage.val(conversion.toFixed(2));
    };

    convertShareOfVoice(shareOfVoice.val());

    setupSelectForSchedule(dialog);

    $('select[name="recurrenceRepeatsOn[]"]', dialog).select2({
        width: "100%"
    });

    // Hide/Show form elements according to the selected options
    // Initial state of the components
    processScheduleFormElements($('#recurrenceType', dialog));
    processScheduleFormElements($('#eventTypeId', dialog));
    processScheduleFormElements($('#campaignId', dialog));
    processScheduleFormElements($('#actionType', dialog));
    processScheduleFormElements($('#relativeTime', dialog));

    // Events on change
    $('#recurrenceType, #eventTypeId, #dayPartId, #campaignId, #actionType, #fullScreenCampaignId, #relativeTime, #syncTimezone', dialog)
      .on('change', function() { processScheduleFormElements($(this)) });

    var evaluateDates = _.debounce(function() {
        scheduleEvaluateRelativeDateTime($form);
    }, 500);

    // Bind to the H:i:s fields
    $form.find("#hours").on("keyup", evaluateDates);
    $form.find("#minutes").on("keyup", evaluateDates);
    $form.find("#seconds").on("keyup", evaluateDates);

    // Handle the repeating monthly selector
    // Run when the tab changes
    $('a[data-toggle="tab"]', dialog).on('shown.bs.tab', function (e) {
        var nth = function(n) {
          return n + (["st","nd","rd"][((n+90)%100-10)%10-1] || "th")
        };
        var $fromDt = $(dialog).find("input[name=fromDt]");
        var fromDt = ($fromDt.val() === null || $fromDt.val() === "") ? moment() : moment($fromDt.val());
        var $recurrenceMonthlyRepeatsOn = $(dialog).find("select[name=recurrenceMonthlyRepeatsOn]");
        var $dayOption = $('<option value="0">' + $recurrenceMonthlyRepeatsOn.data("transDay").replace("[DAY]", fromDt.format("Do")) + '</option>');
        var $weekdayOption = $('<option value="1">' + $recurrenceMonthlyRepeatsOn.data("transWeekday").replace("[POSITION]", nth(Math.ceil(fromDt.date() / 7))).replace("[WEEKDAY]", fromDt.format("dddd")) + '</option>');

        $recurrenceMonthlyRepeatsOn.find("option").remove().end().append($dayOption).append($weekdayOption).val($recurrenceMonthlyRepeatsOn.data("value"));
    });

  // Bind to the dialog submit
  // this should make any changes to the form needed before we submit.
  // eslint-disable-next-line max-len
  $('#scheduleAddForm, #scheduleEditForm, #scheduleDeleteForm, #scheduleRecurrenceDeleteForm')
    .submit(function(e) {
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
          // eslint-disable-next-line new-cap
          XiboSubmitResponse(xhr, $form);

          if (xhr.success && calendar !== undefined) {
            // Reload the Calendar
            calendar.options['clearCache'] = true;
            calendar.view();
          }
        },
      });
    });

    // Popover
    $(dialog).find('[data-toggle="popover"]').popover();

    // Post processing on the schedule-edit form.
    var $scheduleEditForm = $(dialog).find('#scheduleEditForm, #scheduleEditSyncForm');
    if ($scheduleEditForm.length > 0) {
        // Add a button for duplicating this event
        var $button = $("<button>").addClass("btn btn-info")
            .attr("id", "scheduleDuplateButton")
            .html(translations.duplicate)
            .on("click", function() {
                duplicateScheduledEvent($scheduleEditForm);
            });

        $(dialog).find('.modal-footer').prepend($button);

        // Update the date/times for this event in the correct format.
        $scheduleEditForm.find("#instanceStartDate").html(moment($scheduleEditForm.data().eventStart, "X").format(jsDateFormat));
        $scheduleEditForm.find("#instanceEndDate").html(moment($scheduleEditForm.data().eventEnd, "X").format(jsDateFormat));

        // Add a button for deleting single recurring event
        $button = $("<button>").addClass("btn btn-primary")
            .attr("id", "scheduleRecurringDeleteButton")
            .html(translations.deleteRecurring)
            .on("click", function() {
                deleteRecurringScheduledEvent(
                    $scheduleEditForm.data('eventId'),
                    $scheduleEditForm.data('eventStart'),
                    $scheduleEditForm.data('eventEnd')
                );
            });

        $(dialog).find('#recurringInfo').prepend($button);
    }

    configReminderFields($(dialog));

};

var deleteRecurringScheduledEvent = function(id, eventStart, eventEnd) {
    var url = scheduleRecurrenceDeleteUrl.replace(":id", id);
    var data = {
        eventStart: eventStart,
        eventEnd: eventEnd,
    };
    XiboSwapDialog(url, data);
}

var beforeSubmitScheduleForm = function(form) {
    var checkboxes = form.find('[name="reminder_isEmail[]"]');

    checkboxes.each(function (index) {
        $(this).parent().find('[type="hidden"]').val($(this).is(":checked") ? "1" : "0");
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
var fullscreenBeforeSubmit = function(form, callBack, populateHiddenFields = true) {
    const eventTypeId = form.find('#eventTypeId').val();

    let data = {
        id: eventTypeId == 7 ? form.find('#mediaId').val() : form.find('#playlistId').val(),
        type: eventTypeId == 7 ? 'media' : 'playlist',
        layoutDuration: eventTypeId == 7 ? form.find('#layoutDuration').val() : null,
        resolutionId: form.find('#resolutionId').select2('data').length > 0 ? form.find('#resolutionId').select2('data')[0].id : null,
        backgroundColor: form.find('#backgroundColor').val()
    }

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
                    $('#fullScreen-media').val(form.find('#mediaId').select2('data')[0].text);
                    $('#fullScreen-mediaId').val(form.find('#mediaId').select2('data')[0].id);
                } else if (eventTypeId == 8) {
                    const $fullScreenControl = $('#fullScreenControl_playlist')
                    $fullScreenControl.text($fullScreenControl.data('hasLayout'));
                    $('#fullScreen-playlist').val(form.find('#playlistId').select2('data')[0].text);
                    $('#fullScreen-playlistId').val(form.find('#playlistId').select2('data')[0].id);
                }
            }

            $('#fullScreenCampaignId').val(response.data.campaignId).trigger('change');

            (typeof callBack === 'function') && callBack(form);

            // close this modal, return to main schedule modal.
            $('#full-screen-schedule-modal').modal('hide')
        }, (xhr) => {
            SystemMessage(xhr.responseText, false);
        })
};

/**
 * Configure the query builder ( order and filter )
 * @param {object} dialog - Dialog object
 */
 var configReminderFields = function(dialog) {

    var reminderFields = dialog.find("#reminderFields");

    if(reminderFields.length == 0)
        return;

    var reminderEventTemplate = Handlebars.compile($("#reminderEventTemplate").html());

    //console.log(reminderFields.data().reminders.length);
    if(reminderFields.data().reminders.length == 0) {
        // Add a template row
        var context = {
            title: 0,
            buttonGlyph: "fa-plus"
        };
        reminderFields.append(reminderEventTemplate(context));
    } else {
        // For each of the existing codes, create form components
        var i = 0;
        $.each(reminderFields.data().reminders, function(index, field) {
            i++;

            var context = {
                scheduleReminderId: field.scheduleReminderId,
                value: field.value,
                type: field.type,
                option: field.option,
                isEmail: field.isEmail,
                title: i,
                buttonGlyph: ((i == 1) ? "fa-plus" : "fa-minus")
            };

            reminderFields.append(reminderEventTemplate(context));
        });
    }

    // Nabble the resulting buttons
    reminderFields.on("click", "button", function(e) {
        e.preventDefault();

        // find the gylph
        if($(this).find("i").hasClass("fa-plus")) {
            var context = {title: reminderFields.find('.form-group').length + 1, buttonGlyph: "fa-minus"};
            reminderFields.append(reminderEventTemplate(context));
        } else {
            // Remove this row
            $(this).closest(".form-group").remove();
        }
    });
};

/**
 * Process schedule form elements for the purpose of showing/hiding them
 * @param el jQuery element
 */
var processScheduleFormElements = function(el) {
    var fieldVal = el.val();
    let relativeTime = $('#relativeTime').is(':checked');

    switch (el.attr('id')) {
        case 'recurrenceType':
            //console.log('Process: recurrenceType, val = ' + fieldVal);

            var repeatControlGroupDisplay = (fieldVal == "") ? "none" : "";
            var repeatControlGroupWeekDisplay = (fieldVal != "Week") ? "none" : "";
            var repeatControlGroupMonthDisplay = (fieldVal !== "Month") ? "none" : "";

            $(".repeat-control-group").css('display', repeatControlGroupDisplay);
            $(".repeat-weekly-control-group").css('display', repeatControlGroupWeekDisplay);
            $(".repeat-monthly-control-group").css('display', repeatControlGroupMonthDisplay);
            $('#recurrenceDetail').parent().find('.input-group-addon').html(el.val());

            break;

        case 'eventTypeId':
            //console.log('Process: eventTypeId, val = ' + fieldVal);

            var layoutControlDisplay =
              (fieldVal == 2 || fieldVal == 6 || fieldVal == 7 || fieldVal == 8 || fieldVal == 10) ? 'none' : '';
            var endTimeControlDisplay = (fieldVal == 2 || relativeTime) ? 'none' : '';
            var startTimeControlDisplay = (relativeTime && fieldVal != 2) ? 'none' : '';
            var dayPartControlDisplay = (fieldVal == 2) ? 'none' : '';
            var commandControlDisplay = (fieldVal == 2) ? '' : 'none';
            var interruptControlDisplay = (fieldVal == 4) ? '' : 'none';
            var actionControlDisplay = (fieldVal == 6) ? '' : 'none';
            var maxPlaysControlDisplay = (fieldVal == 2 || fieldVal == 6 || fieldVal == 10) ? 'none' : '';
            var mediaScheduleControlDisplay = (fieldVal == 7) ? '' : 'none';
            var playlistScheduleControlDisplay = (fieldVal == 8) ? '' : 'none';
            var playlistMediaScheduleControlDisplay = (fieldVal == 7 || fieldVal == 8) ? '' : 'none';
            var relativeTimeControlDisplay = (fieldVal == 2 || !relativeTime) ? 'none' : '';
            var relativeTimeCheckboxDisplay = (fieldVal == 2) ? 'none' : '';
            var dataConnectorDisplay = fieldVal == 10 ? '' : 'none';

            $('.layout-control').css('display', layoutControlDisplay);
            $('.endtime-control').css('display', endTimeControlDisplay);
            $('.starttime-control').css('display', startTimeControlDisplay);
            $('.day-part-control').css('display', dayPartControlDisplay);
            $('.command-control').css('display', commandControlDisplay);
            $('.interrupt-control').css('display', interruptControlDisplay);
            $('.action-control').css('display', actionControlDisplay);
            $('.max-plays-control').css('display', maxPlaysControlDisplay);
            $('.media-control').css('display', mediaScheduleControlDisplay);
            $('.playlist-control').css('display', playlistScheduleControlDisplay);
            $('.media-playlist-control').css('display', playlistMediaScheduleControlDisplay);
            $('.relative-time-control').css('display', relativeTimeControlDisplay);
            $('.relative-time-checkbox').css('display', relativeTimeCheckboxDisplay);
            $('.data-connector-control').css('display', dataConnectorDisplay);

            // action event type
            if (fieldVal === 6) {
                $(".displayOrder-control").css('display', 'none');
            }

            // If the fieldVal is 2 (command), then we should set the dayPartId to be 0 (custom)
            if (fieldVal == 2) {
                // Determine what the custom day part is.
                var $dayPartId = $("#dayPartId");
                var customDayPartId = 0;
                $dayPartId.find("option").each(function(i, el) {
                    if ($(el).data("isCustom") === 1) {
                        customDayPartId = $(el).val();
                    }
                });

                //console.log('Setting dayPartId to custom: ' + customDayPartId);
                $dayPartId.val(customDayPartId);

                var $startTime = $(".starttime-control");
                $startTime.find("input[name=fromDt_Link2]").show();
                $startTime.find(".help-block").html($startTime.closest("form").data().daypartMessage);

                // Set the repeats/reminders tabs to visible.
                $("li.repeats").css("display", "block");
                $("li.reminders").css("display", "block");
            }

            // Call function for the daypart ID
            processScheduleFormElements($('#dayPartId'));

            // Change the help text and label of the campaignId dropdown
            var $campaignSelect = el.closest("form").find("#campaignId");
            var $layoutControl = $(".layout-control");
            var searchIsLayoutSpecific = -1;

            if (fieldVal === "1" || fieldVal === "3" || fieldVal === "4") {
                // Load Layouts only
                searchIsLayoutSpecific = 1;

                // Change Label and Help text when Layout event type is selected
                $layoutControl.children("label").text($campaignSelect.data("transLayout"));
                $layoutControl.children("div").children("small.form-text.text-muted").text($campaignSelect.data("transLayoutHelpText"));

            } else {

                // Load Campaigns only
                searchIsLayoutSpecific = 0;

                // Change Label and Help text when Campaign event type is selected
                $layoutControl.children("label").text($campaignSelect.data("transCampaign"));
                $layoutControl.children("div").children("small.form-text.text-muted").text($campaignSelect.data("transCampaignHelpText"));
            }

            // Set the search criteria
            $campaignSelect.data("searchIsLayoutSpecific", searchIsLayoutSpecific);

            break;

        case 'dayPartId':
            //console.log('Process: dayPartId, val = ' + fieldVal + ', visibility = ' + el.is(":visible"));

            if (!el.is(":visible"))
                return;

            var meta = el.find('option[value=' + fieldVal + ']').data();

            var endTimeControlDisplay = (meta.isCustom === 0 || relativeTime) ? 'none' : '';
            var startTimeControlDisplay = (meta.isAlways === 1 || relativeTime) ? 'none' : '';
            var repeatsControlDisplay = (meta.isAlways === 1) ? 'none' : '';
            var reminderControlDisplay = (meta.isAlways === 1) ? 'none' : '';
            var relativeTimeControlDisplay =
              (meta.isCustom === 0 || !relativeTime) ? 'none' : '';
            var relativeTimeCheckboxDisplay = (meta.isCustom === 0) ? 'none' : '';

            var $startTime = $('.starttime-control');
            var $endTime = $('.endtime-control');
            var $repeats = $('li.repeats');
            var $reminder = $('li.reminders');
            var $relative = $('.relative-time-control');
            var $relativeCheckbox = $('.relative-time-checkbox');

            // Set control visibility
            $startTime.css('display', startTimeControlDisplay);
            $endTime.css('display', endTimeControlDisplay);
            $repeats.css('display', repeatsControlDisplay);
            $reminder.css('display', reminderControlDisplay);
            $relative.css('display', relativeTimeControlDisplay);
            $relativeCheckbox.css('display', relativeTimeCheckboxDisplay);

            // Dayparts only show the start control
            if (meta.isAlways === 0 && meta.isCustom === 0) {
                // We need to update the date/time controls to only accept the date element
                $startTime.find('input[name=fromDt_Link2]').hide();
                $startTime.find('small.text-muted').html($startTime.closest('form').data().notDaypartMessage);
            } else {
                $startTime.find('input[name=fromDt_Link2]').show();
                $startTime.find('small.text-muted').html($startTime.closest('form').data().daypartMessage);
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
            //console.log('Process: campaignId, val = ' + fieldVal + ', visibility = ' + el.is(":visible"));

            // Update the preview button URL
            var $previewButton = $("#previewButton");

            if (fieldVal === null || fieldVal === '' || fieldVal === 0) {
                $previewButton.closest('.preview-button-container').hide();
            } else {
                $previewButton.closest('.preview-button-container').show();
                $previewButton.attr("href", $previewButton.data().url.replace(":id", fieldVal));
            }

            break;

        case 'actionType' :
            //console.log('Action type changed, val = ' + fieldVal+ ', visibility = ' + el.is(":visible"));
            if (!el.is(":visible")) {
                return;
            }

            var layoutCodeControl = (fieldVal == 'navLayout' && el.is(":visible")) ? "" : "none";
            commandControlDisplay = (fieldVal == 'command') ? "" : "none";

            $('.layout-code-control').css('display', layoutCodeControl);
            $('.command-control').css('display', commandControlDisplay);

            break;
        case 'relativeTime' :
            if (!el.is(":visible")) {
                return;
            }

            var datePickerStartControlDisplay = $(el).is(':checked') ? 'none' : '';
            var datePickerEndControlDisplay =
              ($(el).is(':checked') || $('#eventTypeId').val() == 2) ? 'none' : ''
            var relativeTimeControlDisplay = $(el).is(':checked') ? '' : 'none';

            var $startTime = $(".starttime-control");
            var $endTime = $(".endtime-control");
            var $relative = $('.relative-time-control');

            if (dateFormat.indexOf('s') <= -1) {
                $('.schedule-now-seconds-field').remove();
            }

            if ($(el).is(':checked')) {
                scheduleEvaluateRelativeDateTime($(el).closest('form'))
            }

            $startTime.css('display', datePickerStartControlDisplay);
            $endTime.css('display', datePickerEndControlDisplay);
            $relative.css('display', relativeTimeControlDisplay);

            break;
        case 'syncTimezone' :
            var relativeTimeChecked = $('#relativeTime').is(':checked');

            if (relativeTimeChecked) {
                scheduleEvaluateRelativeDateTime($(el).closest('form'))
            }

            break;
    }
};

var duplicateScheduledEvent = function($scheduleForm) {
    // Set the edit form URL to that of the add form
    $scheduleForm.attr("action", $scheduleForm.data().addUrl).attr("method", "post");

    // Remove the duplicate button
    $("#scheduleDuplateButton").remove();

    toastr.info($scheduleForm.data().duplicatedMessage);
}

/**
 * Evaluate dates on schedule form and fill the date input fields
 */
var scheduleEvaluateRelativeDateTime = function($form) {
    var hours = $form.find("#hours").val();
    var minutes = $form.find("#minutes").val();
    var seconds = $form.find("#seconds").val();

    //var fromDt = moment().add(-24, "hours");
    var fromDt = moment();
    var toDt = moment();

    // Use Hours, Minutes and Seconds to generate a from date
    var $messageDiv = $('.scheduleNowMessage');
    let $syncTimezone = $form.find('#syncTimezone');
    let messageTemplate = '';

    if (hours != '') {
        toDt.add(hours, "hours");
    }

    if (minutes != '') {
        toDt.add(minutes, "minutes");
    }

    if (seconds != '') {
        toDt.add(seconds, "seconds");
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
        $messageDiv.html($messageDiv.data(messageTemplate).replace("[fromDt]", fromDt.format(jsDateFormat)).replace("[toDt]", toDt.format(jsDateFormat))).removeClass("d-none");

        // Update the final submit fields
        updateDatePicker($form.find('#fromDt'), fromDt.format(systemDateFormat), systemDateFormat, true);
        updateDatePicker($form.find('#toDt'), toDt.format(systemDateFormat), systemDateFormat, true);
    }
};

/**
 * Select the elements linked to the clicked element
 */
var agendaSelectLinkedElements = function(elemType, elemID, data, eventId) {

    var targetEvents = [];
    var selectClass = {
            'layouts': 'selected-linked',
            'overlays': 'selected-linked',
            'displaygroups': 'selected-linked',
            'campaigns': 'selected-linked',
    };

    results = data.results[data.selectedDisplayGroup];

    var allEvents = results.events;

    // Get the correspondent events
    for (var i = 0; i < allEvents.length; i++) {
        if ( (elemType == 'layouts' || elemType == 'overlays') && allEvents[i].layoutId == elemID && allEvents[i].eventId == eventId ) {
            targetEvents.push(allEvents[i]);
            selectClass[elemType] = 'selected';
        } else if (elemType == 'displaygroups' && allEvents[i].displayGroupId == elemID) {
            targetEvents.push(allEvents[i]);
            selectClass['displaygroups'] = 'selected';
        } else if (elemType == 'campaigns' && allEvents[i].campaignId == elemID) {
            targetEvents.push(allEvents[i]);
            selectClass['campaigns'] = 'selected';
        }
    }

    // Use the target events to select the corresponding objects
    for (var i = 0; i < targetEvents.length; i++) {
        // Select the corresponding layout
        $('table[data-type="layouts"] tr[data-elem-id~="' + targetEvents[i].layoutId + '"][data-event-id~="' + targetEvents[i].eventId + '"]').addClass(selectClass['layouts']);

        // Select the corresponding display group
        $('table[data-type="displaygroups"] tr[data-elem-id~="' + targetEvents[i].displayGroupId + '"]').addClass(selectClass['displaygroups']);

        // Select the corresponding campaigns
        $('table[data-type="campaigns"] tr[data-elem-id~="' + targetEvents[i].campaignId + '"]').addClass(selectClass['campaigns']);

    }

};

var generateGeoMap = function ($form) {

    if (mymap !== undefined && mymap !== null) {
        mymap.remove();
    }

    var defaultLat = $('#' + $form.attr('id')).data().defaultLat;
    var defaultLong = $('#' + $form.attr('id')).data().defaultLong;

    // base map
    mymap = L.map('geoScheduleMap').setView([defaultLat, defaultLong], 13);

    // base tile layer, provided by Open Street Map
    L.tileLayer( 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        subdomains: ['a','b','c']
    }).addTo( mymap );

    // Add a layer for drawn items
    var drawnItems = new L.FeatureGroup();
    mymap.addLayer(drawnItems);

    // Add draw control (toolbar)
    var drawControl = new L.Control.Draw({
        position: 'topright',
        draw: {
            polyline: false,
            circle: false,
            marker: false,
            circlemarker: false
        },
        edit: {
            featureGroup: drawnItems
        }
    });

    var drawControlEditOnly = new L.Control.Draw({
        position: 'topright',
        draw: false,
        edit: {
            featureGroup: drawnItems
        }
    });

    mymap.addControl(drawControl);

    // add search Control - allows searching by country/city and automatically moves map to that location
    var searchControl = new L.Control.Search({
        url: 'https://nominatim.openstreetmap.org/search?format=json&q={s}',
        jsonpParam: 'json_callback',
        propertyName: 'display_name',
        propertyLoc: ['lat','lon'],
        marker: L.circleMarker([0,0],{radius:30}),
        autoCollapse: true,
        autoType: false,
        minLength: 2,
        hideMarkerOnCollapse: true,
        firstTipSubmit: true,
    });

    mymap.addControl(searchControl);

    var json = '';
    var layer = null;
    var layers = null;

    // when user draws a new polygon it will be added as a layer to the map and as GeoJson to hidden field
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
    mymap.on('draw:edited', function (e) {
        layers = e.layers;

        layers.eachLayer(function(layer) {

            json = layer.toGeoJSON();

            $('#geoLocation').val(JSON.stringify(json));
        });
    });

    // remove the layer and clear the hidden field
    mymap.on('draw:deleted', function (e) {
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

    // if we are editing an event with existing Geo JSON, make sure we load it and add the layer to the map
    if ($('#geoLocation').val() != null && $('#geoLocation').val() !== '') {

        var geoJSON = JSON.parse($('#geoLocation').val());

        L.geoJSON(geoJSON, {
            onEachFeature: onEachFeature
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

var generateFilterGeoMap = function() {
    if(mymap !== undefined && mymap !== null) {
        mymap.remove();
    }

    // Get location values
    var defaultLat = $('#geoLatitude').val();
    var defaultLong = $('#geoLongitude').val();

    // If values are not set, get system default location
    if(defaultLat == undefined || defaultLat == '' || defaultLong == undefined || defaultLong == '') {
        defaultLat = $('.cal-event-location-map').data('defaultLat');
        defaultLong = $('.cal-event-location-map').data('defaultLong');
    }

    // base map
    mymap = L.map('geoFilterAgendaMap').setView([defaultLat, defaultLong], 13);

    // base tile layer, provided by Open Street Map
    L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        subdomains: ['a', 'b', 'c']
    }).addTo(mymap);

    // add search Control - allows searching by country/city and automatically moves map to that location
    var searchControl = new L.Control.Search({
        url: 'https://nominatim.openstreetmap.org/search?format=json&q={s}',
        jsonpParam: 'json_callback',
        propertyName: 'display_name',
        propertyLoc: ['lat', 'lon'],
        marker: L.circleMarker([0, 0], {radius: 30}),
        autoCollapse: true,
        autoType: false,
        minLength: 2,
        hideMarkerOnCollapse: true
    });

    mymap.addControl(searchControl);

    var setMarker = function(lat, lng) {
        if(mymapmarker != undefined) {
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

    if($('#geoLatitude').val() != undefined && $('#geoLatitude').val() != '' &&
    $('#geoLongitude').val() != undefined && $('#geoLongitude').val() != '') {
        setMarker($('#geoLatitude').val(), $('#geoLongitude').val());
    }
};

var filterEventsByLocation = function(events) {
    var eventsResult = [];

    for(var index = 0;index < events.length; index++) {

        var event = events[index];

        if(event.geoLocation != '') {
            var geoJSON = JSON.parse(event.geoLocation);
            var point = [$('#geoLongitude').val(), $('#geoLatitude').val()];
            var polygon = L.geoJSON(geoJSON);

            var test = leafletPip.pointInLayer(point, polygon);

            if(test.length > 0) {
                eventsResult.push(event);
            }
        } else {
            eventsResult.push(event);
        }
    }

    return eventsResult;
};

var setupSelectForSchedule = function (dialog) {
    // Select lists
    var $campaignSelect = $('#campaignId', dialog);
    $campaignSelect.select2({
        dropdownParent: $(dialog).find('form'),
        ajax: {
            url: $campaignSelect.data('searchUrl'),
            dataType: 'json',
            delay: 250,
            data: function(params) {
                var query = {
                    isLayoutSpecific: $campaignSelect.data('searchIsLayoutSpecific'),
                    retired: 0,
                    totalDuration: 0,
                    name: params.term,
                    start: 0,
                    length: 10,
                    columns: [
                        {
                            data: 'isLayoutSpecific'
                        },
                        {
                            data: 'campaign'
                        }
                    ],
                    order: [
                        {
                            column: 0,
                            dir: 'asc'
                        },
                        {
                            column: 1,
                            dir: 'asc'
                        }
                    ]
                };

                // Set the start parameter based on the page number
                if (params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                return query;
            },
            processResults: function(data, params) {
                var results = [];

                $.each(data.data, function(index, el) {
                    results.push({
                        id: el['campaignId'],
                        text: el['campaign']
                    });
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: results,
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                }
            }
        }
    });

    $campaignSelect.on('select2:open', function(event) {
        setTimeout(function() {
            $(event.target).data('select2').dropdown.$search.get(0).focus();
        }, 10);
    })

    var $displaySelect = $('select[name="displayGroupIds[]"]', dialog);
    $displaySelect.select2({
        dropdownParent: $(dialog).find('form'),
        ajax: {
            url: $displaySelect.data('searchUrl'),
            dataType: 'json',
            delay: 250,
            data: function(params) {
                var query = {
                    isDisplaySpecific: -1,
                    forSchedule: 1,
                    displayGroup: params.term,
                    start: 0,
                    length: 10,
                    columns: [
                        {
                            data: 'isDisplaySpecific'
                        },
                        {
                            data: 'displayGroup'
                        }
                    ],
                    order: [
                        {
                            column: 0,
                            dir: 'asc'
                        },
                        {
                            column: 1,
                            dir: 'asc'
                        }
                    ]
                };

                // Set the start parameter based on the page number
                if (params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                return query;
            },
            processResults: function(data, params) {
                var groups = [];
                var displays = [];

                $.each(data.data, function(index, element) {
                    if (element.isDisplaySpecific === 1) {
                        displays.push({
                            id: element.displayGroupId,
                            text: element.displayGroup
                        });
                    } else {
                        groups.push({
                            id: element.displayGroupId,
                            text: element.displayGroup
                        });
                    }
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: [
                        {
                            text: groups.length > 0 ? $displaySelect.data('transGroups') : null,
                            children: groups
                        },{
                            text: displays.length > 0 ? $displaySelect.data('transDisplay') : null,
                            children: displays
                        }
                    ],
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                }
            }
        }
    });

    // set initial displays on add form.
    if(
      [undefined, ''].indexOf($displaySelect.data('initialKey')) == -1 &&
      $(dialog).find('form').data('setDisplaysFromGridFilters')
    ) {
        // filter from the Schedule grid
        let displaySpecificGroups = $('#DisplayList').val() ?? [];
        let displayGroups = $('#DisplayGroupList').val() ?? [];
        // add values to one array
        let addFormDisplayGroup = displaySpecificGroups.concat(displayGroups);
        // set array of displayGroups as initial value
        $displaySelect.data('initial-value', addFormDisplayGroup);

        // query displayGroups and add all relevant options.
        var initialValue = $displaySelect.data('initialValue');
        var initialKey = $displaySelect.data('initialKey');
        var dataObj = {};
        dataObj[initialKey] = initialValue;
        dataObj['isDisplaySpecific'] = -1;
        dataObj['forSchedule'] = 1;

        $.ajax({
            url: $displaySelect.data('searchUrl'),
            type: 'GET',
            data: dataObj
        }).then(function(data) {
            // create the option and append to Select2
            data.data.forEach(object => {
                var option = new Option(
                  object[$displaySelect.data('textProperty')],
                  object[$displaySelect.data('idProperty')],
                  true,
                  true
                );
                $displaySelect.append(option)
            });

            // Trigger change but skip auto save
            $displaySelect.trigger(
              'change',
              [{
                  skipSave: true,
              }]
            );

            // manually trigger the `select2:select` event
            $displaySelect.trigger({
                type: 'select2:select',
                params: {
                    data: data
                }
            });
        });
    }

    $('#mediaId, #playlistId', dialog).on('select2:select', function(event) {
        let hasFullScreenLayout = false;
        if (event.params.data.data !== undefined) {
            hasFullScreenLayout = event.params.data.data[0].hasFullScreenLayout;
        } else if (event.params.data.hasFullScreenLayout !== undefined) {
            hasFullScreenLayout = event.params.data.hasFullScreenLayout;
        }

        if (hasFullScreenLayout) {
            $('.no-full-screen-layout').css('display', 'none')
        } else {
            if ($(this).attr('id') === 'mediaId') {
                $('.no-full-screen-layout').css('display', '')
            } else {
                $('.no-full-screen-layout.media-playlist-control').css('display', '')
            }
        }
    })

    $('#syncGroupId', dialog).on('select2:select', function(event) {
        let eventId = dialog.find('form').data().eventSyncGroupId == $(this).select2('data')[0].id
          ? dialog.find('form').data().eventId
          : null
        $.ajax({
            type: 'GET',
            url: dialog.find('form').data().fetchSyncDisplays.replace(':id', $(this).select2('data')[0].id),
            cache: false,
            dataType: 'json',
            data: {
                eventId : eventId
            }
        })
          .then(
            (response) => {
                if (!response.success) {
                    SystemMessageInline(
                      (response.message === '') ? translations.failure : response.message,
                      form.closest('.modal'),
                    );
                }
                const $contentSelector = $('#content-selector');
                $contentSelector.removeClass('d-none')

                let syncScheduleContent = Handlebars.compile($("#syncEventContentSelector").html());
                dialog.find('#contentSelectorTable tbody').html('').append(syncScheduleContent(response.data))
                let formId = dialog.find('form').attr('id');
                dialog.find('.pagedSelect select.form-control.syncContentSelect').each(function() {
                    makePagedSelect($(this), '#' + formId);
                });

                dialog.find('.pagedSelect select.form-control.syncContentSelect').on('select2:select', function() {
                    if ($(this).data().displayId === $(this).data().leadDisplayId) {
                        $('#setMirrorContent').removeClass('d-none')
                    }
                })

                dialog.find('#setMirrorContent').on('click', function() {
                    let leadDisplayId = $(this).data().displayId;
                    let leadLayoutId = $('#layoutId_'+leadDisplayId).select2('data')[0].id;

                    dialog
                      .find('.pagedSelect select.form-control.syncContentSelect')
                      .not('#layout_'+leadDisplayId)
                      .each(function() {
                          $(this).data().initialValue = leadLayoutId
                          makePagedSelect($(this), '#' + formId);
                      });
                });
            }, (xhr) => {
                SystemMessage(xhr.responseText, false);
            })
    })
};

/**
 * Configure criteria fields on the schedule add/edit forms.
 * @param {object} dialog - Dialog object
 */
// eslint-disable-next-line no-unused-vars
const configureCriteriaFields = function(dialog) {
    const $fields = dialog.find('#scheduleCriteriaFields');
    if ($fields.length <= 0) {
        return;
    }

    // Get the scheduleCriteria from the data attribute
    const scheduleCriteria = $fields.data('scheduleCriteria');

    // Extract the types from scheduleCriteria
    const types = scheduleCriteria ? scheduleCriteria.types : [];

    // We use a template
    const templateScheduleCriteriaFields =
        Handlebars.compile($('#templateScheduleCriteriaFields').html());

    // Function to populate type dropdowns
    const populateTypeDropdown = function($typeSelect) {
        if (types && types.length > 0) {
            types.forEach(type => {
                $typeSelect.append(new Option(type.name, type.id));
            });
        }
    };

    // Function to update metrics field
    const updateMetricsField = function($row, typeId, selectedMetric, elementValue) {
        const $metricLabel = $row.find('label[for="criteria_metric[]"]');
        let $metricSelect;

        if (typeId === 'custom') {
            // change the input type to text
            $metricSelect = $('<input class="form-control" name="criteria_metric[]" type="text" value="" />');
        } else {
            // change input type to dropdown
            $metricSelect = $('<select class="form-control" name="criteria_metric[]"></select>');
            const type = types ? types.find(t => t.id === typeId) : null;
            if (type) {
                type.metrics.forEach(metric => {
                    $metricSelect.append(new Option(metric.name, metric.id));
                });
            } else {
                // change the input type back to text
                $metricSelect = $('<input class="form-control" name="criteria_metric[]" type="text" value="' + selectedMetric + '" />');
            }
        }

        // Remove only input or select elements inside the label
        $metricLabel.find('input, select').remove();
        $metricLabel.append($metricSelect);

        // Set the selected metric if provided
        if (selectedMetric) {
            $metricSelect.val(selectedMetric);
            const type = types?  types.find(t => t.id === typeId) : null;
            if (type) {
                const metric = type.metrics.find(m => m.id === selectedMetric);
                // update value field if metric is present
                if (metric) {
                    updateValueField($row, metric, elementValue);
                }
            }
        }
    };

    // Function to update value field
    const updateValueField = function($row, metric, elementValue) {
        const $valueLabel = $row.find('label[for="criteria_value[]"]');
        let $valueInput;

        if (metric.values && metric.values.inputType === 'dropdown') {
            // change input type to dropdown
            $valueInput = $('<select class="form-control" name="criteria_value[]"></select>');
            if (metric.values.values) {
                metric.values.values.forEach(value => {
                    $valueInput.append(new Option(value.title, value.id));
                });
            }
            // Set the selected value
            $valueInput.val(elementValue);
        } else {
            // change input type according to inputType's value
            const inputType = metric.values ? metric.values.inputType : 'text';
            const value = elementValue || '';
            $valueInput = $('<input class="form-control" name="criteria_value[]" type="' + inputType + '" value="' + value + '" />');
        }

        // Remove only input or select elements inside the label
        $valueLabel.find('input, select').remove();
        $valueLabel.append($valueInput);
    };

    // Existing criteria?
    const existingCriteria = $fields.data('criteria');
    if (existingCriteria && existingCriteria.length >= 0) {
        // Yes there are existing criteria
        // Go through each one and add a field row to the form.
        let i = 0;
        $.each(existingCriteria, function(index, element) {
            i++;
            element.isAdd = false;
            element.i = i;
            const $newField = $(templateScheduleCriteriaFields(element));
            $fields.append($newField);

            // Populate the type field
            const $typeSelect = $newField.find('select[name="criteria_type[]"]');
            populateTypeDropdown($typeSelect);

            // Set the selected type
            $typeSelect.val(element.type);

            // Update metrics and value fields based on the selected type and metric
            updateMetricsField($newField, element.type, element.metric, element.value);
        });
    }

    // Add a row at the end for configuring a new criterion
    const $newRow = $(templateScheduleCriteriaFields({
        isAdd: true,
    }));
    const $newTypeSelect = $newRow.find('select[name="criteria_type[]"]');

    // populate type dropdown based on scheduleCriteria
    populateTypeDropdown($newTypeSelect);
    $fields.append($newRow);

    // Buttons we've added should be bound
    $fields.on('click', 'button', function(e) {
        e.preventDefault();
        const $button = $(this);
        if ($button.data('isAdd')) {
            const newField = $(templateScheduleCriteriaFields({ isAdd: true }));
            $fields.append(newField);

            // Populate the type field for the new row
            const $newTypeSelect = newField.find('select[name="criteria_type[]"]');
            populateTypeDropdown($newTypeSelect);

            $button.data('isAdd', false);
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
