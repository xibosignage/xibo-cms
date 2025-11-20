$(function() {
  // Select lists
  const dialog = 'body';
  window.scheduleEvents = [];

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
    serverSide: false,
    stateSave: true,
    responsive: true,
    stateDuration: 0,
    stateLoadCallback: dataTableStateLoadCallback,
    stateSaveCallback: dataTableStateSaveCallback,
    order: [],
    ajax: {
      url: scheduleSearchUrl,
      data: function(d) {
        const filterData = $('#schedule-grid').closest('.XiboGrid')
          .find('.FilterDiv form').serializeObject();

        // Disable paging on the back-end
        d.disablePaging = 1;

        $.extend(d, filterData);
      },
      dataSrc(json) {
        scheduleEvents = json.data;
        return json.data;
      },
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
      $('#calendar-progress').addClass('show');
    } else {
      $('#calendar-progress').removeClass('show');

      // Reload calendar view
      calendar.view();
    }

    dataTableProcessing(e, settings, processing);
  });

  dataTableAddButtons(
    table,
    $('#schedule-grid_wrapper').find('.dataTables_buttons'),
    true,
    true,
  );

  function changeCalendarView(calendarView = null) {
    // If we are in calendar view, and using custom dates
    // select month in the Range
    if (
      $('.XiboSchedule .card-header-tabs .nav-item .nav-link.active')
        .data().scheduleView === 'calendar' &&
      $('#schedule-filter #range').val() != 'month'
    ) {
      $('#schedule-filter #range').val('month').trigger('change');

      // Stop here, trigger above will call this method again
      return;
    }

    if (calendarView && calendarView != calendar.options.view) {
      // Reload calendar with tab view
      calendar.view(calendarView);
    } else if (
      !calendarView &&
      $('#schedule-filter #range').val() != 'custom'
    ) {
      // Reload calendar with range value as view
      calendar.view($('#schedule-filter #range').val());
    }
  }

  function changeRangeVisibility(show = true) {
    $('#schedule-filter .date-range-input').toggle(show);
  }

  // Save View tab preference
  $('.XiboSchedule .card-header-tabs .nav-item .nav-link')
    .on('shown.bs.tab', function(ev) {
      const tabData = $(ev.currentTarget).data();

      changeCalendarView(tabData.calendarView);
      changeRangeVisibility(tabData.scheduleView === 'grid');

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

  // On range change, change calendar view
  $('#schedule-filter #range').on('change', (_ev) => {
    changeCalendarView();
  });

  changeCalendarView();

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

  // Set up the navigational controls
  $('.btn-group button[data-calendar-nav]').on('click', function(ev) {
    const $el = $(ev.currentTarget);
    updateRangeFilter($('#range'), $('#fromDt'), $('#toDt'), () => {
      calendar.navigate($el.data('calendar-nav'));
    }, {direction: $el.data('calendar-nav')});
  });

  // Refresh grid button
  $('#refreshGrid').on('click', function() {
    table.ajax.reload();
  });

  // When closing a modal on this page, reload table
  // (to reflect possible changes)
  // except for the agenda view modal
  $(document).on('hidden.bs.modal', '.modal', function(e) {
    if (
      $(e.target).hasClass('bootbox') &&
      !$(e.target).hasClass('agenda-view-modal')
    ) {
      table.ajax.reload();
    }
  });
});
