$(function() {
  $('.custom-date-range').addClass('d-none');

  // Select lists
  const dialog = 'body';

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
  })
    .on('change', function(e) {
      // Refresh the calendar view
      setTimeout(calendar.view(), 1000);
    })
    .on('select2:open', function(event) {
      setTimeout(function() {
        $(event.target).data('select2').dropdown.$search.get(0).focus();
      }, 10);
    });

  // Set up our show all selector control
  $('#showAll, #eventTypeId, #recurring, #geoAware,' +
    ' #DisplayList, #DisplayGroupList,' +
    ' #name, #useRegexForName, #logicalOperatorName', dialog)
    .on('change', function() {
      setTimeout(calendar.view(), 1000);
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
    ajax: {
      url: scheduleSearchUrl,
      data: function(d) {
        $.extend(
          d,
          $('#schedule-grid').closest('.XiboGrid')
            .find('.FilterDiv form').serializeObject(),
        );
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
  table.on('processing.dt', dataTableProcessing);
  dataTableAddButtons(
    table,
    $('#schedule-grid_wrapper').find('.dataTables_buttons'),
    true,
    true,
  );

  $('#refreshGrid').on('click', function() {
    table.ajax.reload();
  });
});
