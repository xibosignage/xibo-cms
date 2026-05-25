$(function() {
  // Select lists
  const dialog = 'body';

  function checkScheduleView() {
    return $('.XiboSchedule .card-header-tabs .nav-item .nav-link.active')
      .data().scheduleView;
  }

  const $campaignSelect = $('#schedule-filter #campaignIdFilter');
  $campaignSelect.select2({
    dropdownParent: $(dialog),
    ajax: {
      url: $campaignSelect.data('searchUrl'),
      dataType: 'json',
      delay: 250,
      placeholder: 'This is my placeholder',
      allowClear: true,
      data: function(params) {
        const query = {
          isLayoutSpecific: -1,
          retired: 0,
          totalDuration: 0,
          name: params.term,
          start: 0,
          length: 10,
          excludeMedia: 1,
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
        const campaigns = [];
        const layouts = [];

        $.each(data.data, function(index, element) {
          if (element.isLayoutSpecific === 1) {
            layouts.push({
              id: element.campaignId,
              text: element.campaign,
            });
          } else {
            campaigns.push({
              id: element.campaignId,
              text: element.campaign,
            });
          }
        });

        if (campaigns.length > 0) {
          results.push({
            text: $campaignSelect.data('transCampaigns'),
            children: campaigns,
          });
        }

        if (layouts.length > 0) {
          results.push({
            text: $campaignSelect.data('transLayouts'),
            children: layouts,
          });
        }

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
  }).on('select2:open', function(event) {
    setTimeout(function() {
      $(event.target).data('select2').dropdown.$search.get(0).focus();
    }, 10);
  });

  const table = $('#schedule-grid').DataTable({
    language: dataTablesLanguage,
    dom: dataTablesTemplate,
    serverSide: true,
    stateSave: true,
    responsive: true,
    stateDuration: 0,
    stateLoadCallback: dataTableStateLoadCallback,
    stateSaveCallback: dataTableStateSaveCallback,
    filter: false,
    searchDelay: 3000,
    order: [],
    ajax: function(data, callback, _settings) {
      if (checkScheduleView() != 'grid') {
        // Return empty set
        callback({
          draw: data.draw,
          recordsTotal: 0,
          recordsFiltered: 0,
          data: [],
        });
        return false;
      }

      const filterData = $('#schedule-grid').closest('.XiboGrid')
        .find('.FilterDiv form').serializeObject();

      $.extend(data, filterData);

      // Fire the request manually
      $.ajax({
        url: scheduleSearchUrl,
        data: data,
        dataType: 'json',
        success: function(json) {
          callback(json);
        },
      });
    },
    columns: [
      {
        data: 'eventId',
        responsivePriority: 5,
        className: 'none',
      },
      {
        name: 'icon',
        className: 'align-middle',
        responsivePriority: 2,
        data: function(data) {
          let eventIcon = 'fa-desktop';
          let eventClass = 'event-warning';

          if (data.displayGroups.length <= 1) {
            eventClass = 'event-info';
          } else {
            eventClass = 'event-success';
          }

          if (data.isAlways == 1) {
            eventIcon = 'fa-retweet';
          }

          if (data.recurrenceType != null && data.recurrenceType != '') {
            eventClass = 'event-special';
            eventIcon = 'fa-repeat';
          }

          if (data.isPriority >= 1) {
            eventClass = 'event-important';
            eventIcon = 'fa-bullseye';
          }

          if (data.eventTypeId == 2) {
            eventIcon = 'fa-wrench';
          }

          if (data.eventTypeId == 4) {
            eventIcon = 'fa-hand-paper';
          }

          if (data.isGeoAware === 1) {
            eventIcon = 'fa-map-marker';
          }

          if (data.eventTypeId == 6) {
            eventIcon = 'fa-paper-plane';
          }

          if (data.eventTypeId == 9) {
            eventIcon = 'fa-refresh';
          }

          if (!data.isEditable) {
            eventIcon = 'fa-lock';
            eventClass = 'event-inverse';
          }

          return '<span class="fa ' + eventIcon + ' ' +
            eventClass + ' "></span>';
        },
      },
      {
        name: 'eventTypeId',
        className: 'align-middle',
        responsivePriority: 2,
        data: function(data) {
          return data.eventTypeName;
        },
      },
      {
        data: 'name',
        className: 'align-middle',
        responsivePriority: 3,
      },
      {
        name: 'fromDt',
        className: 'align-middle',
        responsivePriority: 2,
        data: function(data) {
          if (data.isAlways === 1) {
            return schedulePageTrans.always;
          } else {
            return moment(data.displayFromDt, systemDateFormat)
              .format(jsDateFormat);
          }
        },
      },
      {
        name: 'toDt',
        className: 'align-middle',
        responsivePriority: 2,
        data: function(data) {
          if (data.isAlways === 1) {
            return schedulePageTrans.always;
          } else {
            return moment(data.displayToDt, systemDateFormat)
              .format(jsDateFormat);
          }
        },
      },
      {
        name: 'campaign',
        className: 'align-middle',
        responsivePriority: 2,
        data: function(data) {
          if (data.eventTypeId === 9) {
            return data.syncType;
          } else if (data.eventTypeId === 2) {
            return data.command;
          } else {
            return data.campaign;
          }
        },
      },
      {
        data: 'campaignId',
        responsivePriority: 5,
        className: 'none',
      },
      {
        name: 'displayGroups',
        className: 'align-middle',
        responsivePriority: 2,
        sortable: false,
        data: function(data) {
          if (data.displayGroups.length > 1 && data.eventTypeId !== 9) {
            return '<span class="badge" ' +
              'style="background-color: green; color: white" ' +
              'data-toggle="popover" data-trigger="click" ' +
              'data-placement="top" data-content="' +
              data.displayGroupList + '">' + (data.displayGroups.length) +
              '</span>';
          } else {
            return data.displayGroupList;
          }
        },
      },
      {
        data: 'shareOfVoice',
        className: 'align-middle',
        responsivePriority: 4,
      },
      {
        name: 'maxPlaysPerHour',
        className: 'align-middle',
        responsivePriority: 4,
        data: function(data) {
          if (data.maxPlaysPerHour === 0) {
            return translations.unlimited;
          } else {
            return data.maxPlaysPerHour;
          }
        },
      },
      {
        data: 'isGeoAware',
        className: 'align-middle',
        responsivePriority: 4,
        render: dataTableTickCrossColumn,
      },
      {
        data: 'recurringEvent',
        className: 'align-middle',
        responsivePriority: 4,
        render: dataTableTickCrossColumn,
      },
      {
        data: 'recurringEventDescription',
        className: 'align-middle',
        responsivePriority: 4,
        sortable: false,
      },
      {
        data: 'recurrenceType',
        className: 'align-middle',
        visible: false,
        responsivePriority: 4,
      },
      {
        data: 'recurrenceDetail',
        className: 'align-middle',
        visible: false,
        responsivePriority: 4,
      },
      {
        name: 'recurrenceRepeatsOn',
        className: 'align-middle',
        visible: false,
        responsivePriority: 4,
        data: function(data) {
          if (data.recurringEvent) {
            if (data.recurrenceType === 'Week' && data.recurrenceRepeatsOn) {
              const daysOfTheWeek = [
                schedulePageTrans.daysOfTheWeek.monday,
                schedulePageTrans.daysOfTheWeek.tuesday,
                schedulePageTrans.daysOfTheWeek.wednesday,
                schedulePageTrans.daysOfTheWeek.thursday,
                schedulePageTrans.daysOfTheWeek.friday,
                schedulePageTrans.daysOfTheWeek.saturday,
                schedulePageTrans.daysOfTheWeek.sunday,
              ];

              const recurrenceArray = data.recurrenceRepeatsOn.split(',');

              if (recurrenceArray.length >= 1) {
                let stringToReturn = '';
                // go through each selected day, get the corresponding day name
                recurrenceArray.forEach((dayNumber, index) => {
                  stringToReturn += daysOfTheWeek[dayNumber - 1];
                  if (index < recurrenceArray.length - 1) {
                    stringToReturn += ' ';
                  }
                });

                return stringToReturn;
              } else {
                return '';
              }
            } else if (data.recurrenceType === 'Month') {
              return data.recurrenceMonthlyRepeatsOn;
            } else {
              return '';
            }
          } else {
            return '';
          }
        },
      },
      {
        name: 'recurrenceRange',
        className: 'align-middle',
        visible: false,
        responsivePriority: 4,
        data: function(data) {
          if (data.recurringEvent && data.recurrenceRange !== null) {
            return moment(data.recurrenceRange, 'X').format(jsDateFormat);
          } else {
            return '';
          }
        },
      },
      {
        data: 'isPriority',
        className: 'align-middle',
        responsivePriority: 2,
      },
      {
        name: 'criteria',
        className: 'align-middle',
        responsivePriority: 2,
        data: function(data, type, row) {
          return (data.criteria && data.criteria.length > 0) ?
            dataTableTickCrossColumn(1, type, row) : '';
        },
      },
      {
        data: 'createdOn',
        className: 'align-middle',
        responsivePriority: 4,
      },
      {
        data: 'updatedOn',
        className: 'align-middle',
        responsivePriority: 4,
      },
      {
        data: 'modifiedByName',
        className: 'align-middle',
        responsivePriority: 4,
      },
      {
        orderable: false,
        className: 'align-middle',
        responsivePriority: 1,
        data: dataTableButtonsColumn,
      },
    ],
  });

  table.on('draw', function(e, settings) {
    dataTableDraw(e, settings);
    $('[data-toggle="popover"]').popover();
  });

  table.on('processing.dt', function(e, settings, processing) {
    if (processing) {
      $('#calendar-progress-table').addClass('show');
    } else {
      $('#calendar-progress-table').removeClass('show');
    }

    dataTableProcessing(e, settings, processing);
  });

  dataTableAddButtons(
    table,
    $('#schedule-grid_wrapper').find('.dataTables_buttons'),
    true,
    true,
  );

  // Debounced version of updateScheduleView
  window.debouncedUpdateScheduleView = _.debounce((calendarView) => {
    updateScheduleView(calendarView);
  }, 50);

  function updateScheduleView(calendarView = null) {
    // If there's a get events request, abort it
    if (window.getEventsRequestControl) {
      window.getEventsRequestControl.abort();
      window.getEventsRequestControl = null;
    }

    // Calendar tab
    if (
      checkScheduleView() === 'calendar'
    ) {
      // Force using month in range for calendar view
      if ($('#schedule-filter #range').val() != 'month') {
        $('#schedule-filter #range').val('month').trigger('change');

        // Stop here, trigger above will call this method again
        return;
      }

      // Clear title when changing tabs
      // if calendar needs update
      if (window.calendarNeedsUpdate) {
        $('h1.page-header').text('');
      }

      // Check if calendar needs updating
      window.calendarEnabled = window.calendarNeedsUpdate;

      // Change the calendar view and render if enabled
      if (window.calendarEnabled) {
        if (calendarView && calendarView != calendar.options.view) {
          // Reload calendar with tab view
          window.calendar.view(calendarView);
        } else if (
          !calendarView &&
          $('#schedule-filter #range').val() != 'custom'
        ) {
          // Reload calendar with range value as view
          window.calendar.view($('#schedule-filter #range').val());
        } else {
          // Reload calendar normally
          window.calendar.view();
        }
      }
    } else { // Grid tab
      // Disable calendar render
      window.calendarEnabled = false;

      // Update title for table based on calendar
      let title = '';
      const range = $('#range').val();
      const currentDate = moment($('#fromDt').val());

      if (range == 'custom') {
        const dateFormat = translations.schedule.calendar.openDateFormat;
        const fromDate = ($('#fromDt').val()) ?
          moment($('#fromDt').val()).format(dateFormat) :
          translations.schedule.calendar.customFromToAlways;
        const toDate = ($('#toDt').val()) ?
          moment($('#toDt').val()).format(dateFormat) :
          translations.schedule.calendar.customFromToAlways;

        if (!$('#fromDt').val() && !$('#toDt').val()) {
          title = translations.schedule.calendar.customFromToAlways;
        } else if (!$('#toDt').val()) {
          title = translations.schedule.calendar.customAfter
            .replace(':from', fromDate);
        } else if (!$('#fromDt').val()) {
          title = translations.schedule.calendar.customBefore
            .replace(':to', toDate);
        } else {
          title = translations.schedule.calendar.customFromTo
            .replace(':from', fromDate)
            .replace(':to', toDate);
        }
      } else {
        // Build title manually
        if (range === 'year') {
          title = currentDate.format('YYYY');
        } else if (range === 'month') {
          title = currentDate.format('MMMM YYYY');
        } else if (range === 'week') {
          title = translations.schedule.calendar.weekTitle
            .replace('{0}', currentDate.isoWeek())
            .replace('{1}', currentDate.format('YYYY'));
        } else if (range === 'day') {
          title = currentDate.format('dddd, DD MMMM YYYY');
        }
      }

      $('h1.page-header').text(title);
    }
  };

  function changeRangeVisibility(show = true) {
    $('#schedule-filter .date-range-input').toggle(show);
  }

  // Change schedule tab view
  $('.XiboSchedule .card-header-tabs .nav-item .nav-link')
    .on('shown.bs.tab', function(ev) {
      const tabData = $(ev.currentTarget).data();
      const gridTabActive = tabData.scheduleView === 'grid';

      debouncedUpdateScheduleView(tabData.calendarView);
      changeRangeVisibility(gridTabActive);

      // If changing back to Grid, refresh table
      if (gridTabActive && typeof table !== 'undefined') {
        table.ajax.reload();
      }

      // Save View tab preference
      $.ajax({
        type: 'post',
        url: userPreferencesUrl,
        cache: false,
        dataType: 'json',
        data: {
          preference: [{
            option: 'schedulePageView',
            value: $(ev.currentTarget).attr('id'),
          }],
        },
      });
    });

  // In Calendar view, changing the filter
  // reloads the view
  $('#schedule-filter form').on('change', 'input, select, textarea', (ev) => {
    // Filter changed, we need to update calendar if needed
    window.calendarNeedsUpdate = true;

    // Call update schedule with debounce
    debouncedUpdateScheduleView();
  });

  // Set up the navigational controls
  $('.btn-group button[data-calendar-nav]').on('click', function(ev) {
    const $el = $(ev.currentTarget);

    updateRangeFilter($('#range'), $('#fromDt'), $('#toDt'), () => {
      window.calendar.navigate($el.data('calendar-nav'));
    }, {direction: $el.data('calendar-nav')});
  });

  // Update view on first load
  debouncedUpdateScheduleView();

  // Select tab on page load
  $.ajax({
    type: 'GET',
    async: false,
    url: userPreferencesUrl + '?preference=schedulePageView',
    dataType: 'json',
    success: function(json) {
      try {
        if (json.success) {
          // Open tab
          $('.XiboSchedule .card-header-tabs #' + json.data.value)
            .trigger('click');
        }
      } catch (e) {
        // Do nothing
        console.warn(e);
      }
    },
  });

  // Refresh grid button
  $('#refreshGrid').on('click', function() {
    table.ajax.reload();
  });

  // When closing a modal on this page, reload table
  // Or calendar if we're in calendar view
  // (to reflect possible changes)
  // except for the agenda view modal
  $(document).on('hidden.bs.modal', '.modal', function(e) {
    if (
      $(e.target).hasClass('bootbox') &&
      !$(e.target).hasClass('agenda-view-modal')
    ) {
      // Reload table
      table.ajax.reload();

      // Calendar needs to be updated
      window.calendarNeedsUpdate = true;

      // If in calendar tab, reload it
      if (checkScheduleView() === 'calendar') {
        debouncedUpdateScheduleView();
      }
    }
  });
});
