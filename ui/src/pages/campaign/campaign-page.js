let table;
// Configure the DataTable
$(document).ready(function() {
  if (!folderViewEnabled) {
    disableFolders();
  }

  table = $('#campaigns').DataTable({
    language: dataTablesLanguage,
    serverSide: true,
    stateSave: true,
    stateDuration: 0,
    responsive: true,
    dom: dataTablesTemplate,
    stateLoadCallback: dataTableStateLoadCallback,
    stateSaveCallback: dataTableStateSaveCallback,
    filter: false,
    searchDelay: 3000,
    order: [[0, 'asc']],
    ajax: {
      url: campaignSearchURL,
      data: function(d) {
        $.extend(
          d,
          $('#campaigns').closest('.XiboGrid')
            .find('.FilterDiv form').serializeObject(),
        );
      },
    },
    columns: [
      {
        data: 'campaign',
        responsivePriority: 2,
        render: dataTableSpacingPreformatted,
      },
      // Add fields only if campaign is enabled
      ...(adCampaignEnabled ? [
        {
          data: 'type',
          responsivePriority: 2,
          render: function(data, type) {
            if (type !== 'display') {
              return data;
            } else if (data === 'list') {
              return campaignPageTrans.list;
            } else if (data === 'ad') {
              return campaignPageTrans.ad;
            }
            return data;
          },
        },
        {
          data: 'startDt',
          responsivePriority: 2,
          render: dataTableDateFromUnix,
        },
        {
          data: 'endDt',
          responsivePriority: 2,
          render: dataTableDateFromUnix,
        },
      ] : []),
      {data: 'numberLayouts', responsivePriority: 2},
      // Add tags only if enabled
      ...(taggingEnabled ? [{
        sortable: false,
        responsivePriority: 2,
        data: dataTableCreateTags,
      }] : []),
      {
        data: 'totalDuration',
        responsivePriority: 2,
        render: dataTableTimeFromSeconds,
      },
      {
        name: 'cyclePlaybackEnabled',
        responsivePriority: 3,
        data: function(data, type) {
          if (type != 'display') {
            return data.cyclePlaybackEnabled;
          }

          let icon = '';
          if (data.cyclePlaybackEnabled == 1) {
            icon = 'fa-check';
          } else {
            icon = 'fa-times';
          }

          return '<span class="fa ' + icon + '"></span>';
        },
      },
      {
        name: 'playCount',
        responsivePriority: 3,
        data: function(data, type) {
          if (type !== 'display') {
            return data.playCount;
          }

          if (!data.playCount) {
            return '';
          } else {
            return data.playCount;
          }
        },
      },
      // Add fields only if campaign is enabled
      ...(adCampaignEnabled ? [
        {
          data: 'targetType',
          responsivePriority: 3,
          render: function(data, type) {
            if (data === 'plays') {
              return campaignPageTrans.plays;
            } else if (data === 'budget') {
              return campaignPageTrans.budget;
            } else if (data === 'imp') {
              return campaignPageTrans.impressions;
            }
            return data;
          },
        },
        {
          data: 'target',
          responsivePriority: 3,
        },
        {
          data: 'plays',
          responsivePriority: 6,
        },
        {
          data: 'spend',
          responsivePriority: 6,
        },
        {
          data: 'impressions',
          responsivePriority: 6,
        },
      ] : []),
      {
        data: 'ref1',
        responsivePriority: 10,
        visible: false,
      },
      {
        data: 'ref2',
        responsivePriority: 10,
        visible: false,
      },
      {
        data: 'ref3',
        responsivePriority: 10,
        visible: false,
      },
      {
        data: 'ref4',
        responsivePriority: 10,
        visible: false,
      },
      {
        data: 'ref5',
        responsivePriority: 10,
        visible: false,
      },
      {
        data: 'createdAt',
        responsivePriority: 5,
        render: dataTableDateFromIso,
        visible: false,
      },
      {
        data: 'modifiedAt',
        responsivePriority: 5,
        render: dataTableDateFromIso,
        visible: false,
      },
      {
        data: 'modifiedByName',
        responsivePriority: 5,
        visible: false,
      },
      {
        orderable: false,
        responsivePriority: 1,
        data: dataTableButtonsColumn,
      },
    ],
  });

  // Data Table events
  table.on('draw', dataTableDraw);
  table.on('draw',
    {
      form: $('#campaigns').closest('.XiboGrid')
        .find('.FilterDiv form'),
    }, dataTableCreateTagEvents);
  table.on('processing.dt', dataTableProcessing);
  dataTableAddButtons(
    table,
    $('#campaigns_wrapper').find('.dataTables_buttons'),
  );

  $('#refreshGrid').click(function() {
    table.ajax.reload();
  });
});

// Callback for the media form
// Fired when the media form opens
window.campaignAssignLayoutsFormOpen = function(dialog) {
  // setup checkbox behaviour for cycle based playback
  formHelpers.setupCheckboxInputFields(
    $(dialog).find('form:not(.form-inline)'),
    'input[name="cyclePlaybackEnabled"]',
    '.cycle-based-playback',
    '.no-cycle-based-playback',
  );

  // Layout element template
  const layoutElementTemplate = templates.campaign.campaignAssignLayout;

  const layoutAssignFilter = $(dialog).find('.layoutAssignFilterOptions');

  // Change input id of the tags filter on Layout assignment tab.
  layoutAssignFilter.find('input#tags').attr('id', 'tagsFilter');

  // Assignment table
  const $layoutAssignments = $('#layoutAssignments');

  const $layoutAssignSortable = $('#LayoutAssignSortable');

  // Update all the layout element positions
  const updateSortablePositions = function() {
    dialog.find('input[name="manageLayouts"]').val(1);

    $layoutAssignSortable.find('li').each(function(idx, el) {
      $(el).find('.layout-order').html(idx + 1);
    });
  };

  // Populate layouts
  const layoutsArray = $layoutAssignSortable.data('layouts');
  for (layoutIndex = 0; layoutIndex < layoutsArray.length; layoutIndex++) {
    const layout = layoutsArray[layoutIndex];

    // Append to our layouts list
    const newItem = layoutElementTemplate({
      index: (layoutIndex + 1),
      layoutId: layout.layoutId,
      layoutName: layout.layout,
      locked: layout.locked,
    });

    $(newItem).appendTo('#LayoutAssignSortable');
  }

  // Layout DataTable
  const layoutTable = $layoutAssignments.DataTable({
    language: dataTablesLanguage,
    serverSide: true,
    stateSave: true,
    stateDuration: 0,
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50],
    stateLoadCallback: dataTableStateLoadCallback,
    stateSaveCallback: dataTableStateSaveCallback,
    searchDelay: 3000,
    order: [[0, 'asc']],
    filter: false,
    ajax: {
      url: layoutSearchURL + '?retired=0',
      data: function(d) {
        $.extend(d, $layoutAssignments.closest('.XiboGrid')
          .find('.layoutAssignFilterOptions')
          .find('input, select')
          .serializeObject());
      },
    },
    columns: [
      {data: 'layoutId'},
      {
        data: 'layout',
        render: dataTableSpacingPreformatted,
      },
      {
        name: 'status',
        data: function(data, type) {
          if (type != 'display') {
            return data.status;
          }

          let icon = '';
          if (data.status == 1) {
            icon = 'fa-check';
          } else if (data.status == 2) {
            icon = 'fa-exclamation';
          } else if (data.status == 3) {
            icon = 'fa-cogs';
          } else {
            icon = 'fa-times';
          }

          return '<span class=\'fa ' + icon +
            '\' title=\'' + (data.statusDescription) +
            ((data.statusMessage == null) ? '' : ' - ' + (data.statusMessage)) +
            '\'></span>';
        },
      },
      {
        sortable: false,
        data: function(data, type, row, meta) {
          if (type !== 'display') {
            return '';
          }

          // Create a click-able span
          return '<a href="#" class="assignItem"><span class="fa fa-plus"></a>';
        },
      },
    ],
  });

  layoutTable.on(
    'draw',
    {
      form: $layoutAssignments.closest('.XiboGrid').find('form'),
    }, function(e, settings) {
      dataTableDraw(e, settings);
      dataTableCreateTagEvents(e, settings);

      // Bind a click event to each table rows + button (span)
      $layoutAssignments.find('.assignItem').on('click', function(ev) {
        // Get the row that this is in.
        const data = layoutTable.row($(ev.currentTarget).closest('tr')).data();

        // Append to our layouts list
        const newItem = layoutElementTemplate({
          index: ($('#LayoutAssignSortable').find('li').length + 1),
          layoutId: data.layoutId,
          layoutName: data.layout,
          locked: false,
        });

        $(newItem).appendTo('#LayoutAssignSortable');

        dialog.find('input[name="manageLayouts"]').val(1);
      });
    });
  layoutTable.on('processing.dt', dataTableProcessing);

  // Make our little list sortable
  $layoutAssignSortable.sortable({
    cancel: '.ui-state-disabled',
    update: function(event, ui) {
      updateSortablePositions();
    },
  });

  // Bind to the existing items in the list
  $layoutAssignSortable.on('click', '.layout-remove', function(ev) {
    $(ev.currentTarget).parent().remove();
    updateSortablePositions();
  });

  // Bind the filter form
  layoutAssignFilter.find('input, select').change(function() {
    layoutTable.ajax.reload();
  });

  // Adjust the datatable width once we've activated the tab
  $(dialog).find('.nav-tabs a').on('shown.bs.tab', function(event) {
    if ($(event.target).attr('href') === '#tab-layouts') {
      layoutAssignFilter.find('input, select').prop('disabled', false);
      layoutTable.columns.adjust().draw();
    }
  });
};

window.campaignFormSubmit = function($form) {
  // Process layouts to add
  layoutAssignSubmit($form);

  // disable inputs from layout assignment filter
  // we do not want to submit them.
  $('.layoutAssignFilterOptions').find('input, select').prop('disabled', true);

  // Submit form
  $form.submit();
};

function layoutAssignSubmit($form) {
  if (parseInt($form.find('input[name="manageLayouts"]').val()) === 1) {
    // Get the final sortable positions
    const finalLayoutPositions = [];
    $('#LayoutAssignSortable').find('li').each(function(key, el) {
      finalLayoutPositions.push($(el).data('layoutId'));
    });

    // Build the array of layouts
    for (let i = 0; i < finalLayoutPositions.length; i++) {
      $('<input>').attr({
        type: 'hidden',
        name: 'layoutIds[' + i + ']',
      }).val(finalLayoutPositions[i]).appendTo($form.find('#assignLayouts'));
    }
  }
}

/**
 * Called when the campaign add form is opened
 * @param dialog
 */
window.campaignAddFormOpen = function(dialog) {
  // setup checkbox behaviour for cycle based playback
  formHelpers.setupCheckboxInputFields(
    $(dialog).find('form'),
    'input[name="cyclePlaybackEnabled"]',
    '.cycle-based-playback',
    '.no-cycle-based-playback',
  );

  const $type = $(dialog).find('select[name=type]');
  const $cycleBased = $('input[name="cyclePlaybackEnabled"]');

  $(dialog).find('.campaign-type-ad').toggle($type.val() === 'ad');

  $type.on('change', function() {
    $(dialog).find('.campaign-type-list').toggle($type.val() !== 'ad');
    $(dialog).find('.campaign-type-ad').toggle($type.val() === 'ad');

    $(dialog).find('.cycle-based-playback')
      .toggle($cycleBased.is(':checked') && $type.val() !== 'ad');
    $(dialog).find('.no-cycle-based-playback')
      .toggle(!$cycleBased.is(':checked') && $type.val() !== 'ad');
  });
};

/**
 * Called when the campaign add form is submitted.
 * @param xhr
 * @param form
 */
window.campaignAddFormSubmitCallback = function(xhr, form) {
  if (xhr.success) {
    if (xhr.data.type === 'ad') {
      // Navigate to the campaign builder
    } else {
      // Open the edit form.
      XiboFormRender(
        $(form).data('editFormUrl').replace(':id', xhr.data.campaignId),
      );
    }
  }
};
