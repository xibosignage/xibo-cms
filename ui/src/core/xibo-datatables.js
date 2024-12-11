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
// Configure a global error handler for data tables
$.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
  console.error(message);
};

$(function() {
  XiboInitDatatables('');
});

/**
 * Initialises datatables
 * @param {Object} scope (the form or page)
 * @param {Object} [options] (options for the form)
 */
window.XiboInitDatatables = function(scope, options) {
  // If the scope isnt defined then assume the entire page
  if (scope == undefined || scope == '') {
    scope = ' ';
  }

  // Search for any grids on the page and render them
  $(scope + ' .XiboGrid').each(function(i, el) {
    const $target = $(el);
    const gridName = $target.data().gridName;
    const form = $target.find('.XiboFilter form');

    // Check to see if this grid is already in the local storage
    if (gridName != undefined) {
      // Populate the filter according to the values we already have.
      let formValues;
      try {
        formValues = JSON.parse(localStorage.getItem(gridName));

        if (formValues == null) {
          localStorage.setItem(
            gridName,
            JSON.stringify(form.serializeArray()),
          );
          formValues = JSON.parse(localStorage.getItem(gridName));
        }
      } catch (e) {
        console.warn(e);
        formValues = [];
      }

      // flatten the array
      // if we have multiple items with the same name.
      const formValuesUpdated = [];
      formValues.forEach((element) => {
        if (element.name in formValuesUpdated) {
          formValuesUpdated[element.name].value =
            [element.value, formValuesUpdated[element.name].value];
        } else {
          formValuesUpdated[element.name] = element;
        }
      });

      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search.slice(1));

      $.each(Object.values(formValuesUpdated), function(key, element) {
        // Does this field exist in the form
        const fieldName = element.name.replace(/\[\]/, '\\\\[\\\\]');
        try {
          const field = form.find(
            'input[name="' + fieldName + '"],' +
            ' select[name="' + fieldName + '"],' +
            ' select[name="' + element.name + '"]');

          if (params.get(fieldName) !== null) {
            field.val(params.get(fieldName));
          } else if (field.length > 0) {
            field.val(element.value);
          }

          // if we have pagedSelect as inline filter for the grid
          // set the initial value here
          // to ensure the correct option gets selected.
          if (field.parent().hasClass('pagedSelect')) {
            field.data('initial-value', element.value);
          }
        } catch (e) {
          console.warn(e);
          console.error(
            'Error populating form saved value with selector ' +
            'input[name=' + element.name + ']' +
            ', select[name=' + element.name + ']');
        }
      });
    }

    const filterRefresh = _.debounce(function() {
      if (gridName != undefined) {
        localStorage.setItem(
          gridName,
          JSON.stringify(form.serializeArray()),
        );
      }

      $target.closest('.XiboGrid').find('table.dataTable')
        .first().DataTable().ajax.reload();
    }, 500);

    // Add clear filter button and handle behaviour
    // Create template for the inputs
    const buttonTemplate = templates['xibo-filter-clear-button']({
      trans: translations,
    });

    // Append button to tabs or container (if we don't have tabs)
    if ($target.find('.XiboFilter .nav-tabs').length > 0) {
      if (
        $target.find('.XiboFilter .nav-tabs .clear-filter-btn-container')
          .length === 0 &&
        form.length > 0
      ) {
        $target.find('.XiboFilter .nav-tabs').append(buttonTemplate);
      }
    } else {
      if (
        $target.find('.XiboFilter .clear-filter-btn-container').length === 0 &&
        form.length > 0
      ) {
        $target.find('.XiboFilter').prepend(buttonTemplate);
        $target.find('.XiboFilter .FilterDiv').addClass('pt-0');
      }
    }

    // Prevent enter key to submit form
    $target.find('.XiboFilter .clear-filter-btn').off()
      .on('click', function() {
        // Reset fields
        form[0].reset();

        // Trigger change on select2
        form.find('.select2-hidden-accessible').val('')
          .trigger('change');

        // Clear tags input
        form.find('.bootstrap-tagsinput').tagsinput('clear');

        // Refresh filter
        filterRefresh.call(el);
      });

    // Prevent enter key to submit form
    $target.find('.XiboFilter form').on('keydown', function(event) {
      if (event.keyCode == 13) {
        event.preventDefault();
        return false;
      }
    });
    // Bind the filter form
    $target.find('.XiboFilter form input').on('keyup', filterRefresh);
    $target.find('.XiboFilter form input[type="number"]')
      .on('change', filterRefresh);
    $target.find('.XiboFilter form input[type="checkbox"]')
      .on('change', filterRefresh);
    $target.find('.XiboFilter form select').on('change', filterRefresh);
    $target.find('.XiboFilter form input.dateControl')
      .on('change', filterRefresh);

    // Folder navigation relies on triggering
    // the change event on this hidden field.
    $target.find('.XiboFilter form #folderId').on('change', filterRefresh);

    // Tags need on change trigger.
    $target.find('.XiboFilter form input[data-role="tagsInputInline"]')
      .on('change', filterRefresh);

    // Preview layout buttons
    $target.on(
      'click',
      '#dropdown-menu-right-container ' +
      'a.dropdown-item[data-custom-handler="createMiniLayoutPreview"]',
      function(e) {
        const eventParam = $(e.currentTarget).attr('data-custom-handler-url');
        $(e.currentTarget).on('click', createMiniLayoutPreview(eventParam));
      });

    // Check to see if we need to share
    // folder tree state globally or per page
    const gridFolderState = rememberFolderTreeStateGlobally ?
      'grid-folder-tree-state' :
      'grid_' + gridName;
    // init the jsTree
    initJsTreeAjax(
      $target.find('#container-folder-tree'),
      gridFolderState,
      false,
    );
  });
};


/**
 * DataTable processing event
 * @param e
 * @param settings
 * @param processing
 */
window.dataTableProcessing = function(e, settings, processing) {
  if (processing) {
    if (
      $(e.target).closest('.widget').closest('.widget')
        .find('.saving').length === 0
    ) {
      $(e.target).closest('.widget').children('.widget-title')
        .append('<span class="saving fa fa-cog fa-spin p-1"></span>');
    }
  } else {
    $(e.target).closest('.widget').closest('.widget').find('.saving').remove();
  }
};

/**
 * DataTable Draw Event
 * @param e
 * @param settings
 * @param callBack
 */
window.dataTableDraw = function(e, settings, callBack) {
  const target = $('#' + e.target.id);

  // Check to see if we have any buttons that are multi-select
  const enabledButtons = target.find('div.dropdown-menu a.multi-select-button');

  // Check to see if we have tag filter for the current table
  const $tagsElement = target.closest('.XiboGrid').find('.FilterDiv #tags');

  // Check to see if we have a folder system for this table
  const $folderController =
    target.closest('.XiboGrid').find('.folder-controller');

  if (enabledButtons.length > 0) {
    const searchByKey = function(array, item, key) {
      // return Object from array where array[object].item matches key
      for (const i in array) {
        if (array[i][item] == key) {
          return true;
        }
      }
      return false;
    };

    // Bind a click event to our table
    target.find('tbody').off('click', 'tr').on('click', 'tr', function(ev) {
      $(ev.currentTarget).toggleClass('selected');
      target.data().initialised = true;
    });

    // Add a button set to the table
    let buttons = [];

    // Get every enabled button
    $(enabledButtons).each(function(_idx, el) {
      const $button = $(el);
      if (!searchByKey(buttons, 'id', $button.data('id'))) {
        buttons.push({
          id: $button.data('id'),
          gridId: e.target.id,
          text: $button.data('text'),
          customHandler: $button.data('customHandler'),
          customHandlerUrl: $button.data('customHandlerUrl'),
          contentIdName: $button.data('contentIdName'),
          sortGroup: ($button.data('sortGroup') != undefined) ?
            $button.data('sortGroup') : 0,
        });
      }
    });

    // Add tag button if exist in the filter ( and user has permissions)
    if ($tagsElement.length > 0 && userRoutePermissions.tags == 1) {
      buttons.push({
        id: $tagsElement.attr('id'),
        gridId: e.target.id,
        text: translations.editTags,
        contentType: target.data('contentType'),
        contentIdName: target.data('contentIdName'),
        customHandler: 'XiboMultiSelectTagFormRender',
        sortGroup: 0,
      });
    }

    // Sort buttons by groups/importance
    buttons = buttons.sort(function(a, b) {
      return ((a.sortGroup > b.sortGroup) ? 1 : -1);
    });

    // Add separators
    let groupAux = 0;
    if (buttons.length > 1) {
      for (let index = 0; index < buttons.length; index++) {
        const btn = buttons[index];

        // If there's a new group ( and it's not the first element on the list)
        if (btn.sortGroup > groupAux && index > 0) {
          buttons.splice(index, 0, {divider: true});
          groupAux = btn.sortGroup;
        }
      }
    }

    const output =
      templates.dataTable.multiSelectButton(
        {
          selectAll: translations.selectAll,
          withSelected: translations.withselected,
          buttons: buttons,
        },
      );
    target.closest('.dataTables_wrapper')
      .find('.dataTables_info').prepend(output);

    // Bind to our output
    target.closest('.dataTables_wrapper')
      .find('.dataTables_info a.XiboMultiSelectFormButton')
      .on('click', function(ev) {
        const $target = $(ev.currentTarget);
        if (
          $target.data('customHandler') != undefined &&
          typeof window[$target.data('customHandler')] == 'function'
        ) {
          window[$target.data('customHandler')](ev.currentTarget);
        } else {
          XiboMultiSelectFormRender(ev.currentTarget);
        }
      });

    target.closest('.dataTables_wrapper')
      .find('.dataTables_info a.XiboMultiSelectFormCustomButton')
      .on('click', function(ev) {
        window[$(ev.currentTarget).data('customHandler')](ev.currentTarget);
      });

    // Bind click to select all button
    target.closest('.dataTables_wrapper')
      .find('.dataTables_info button.select-all')
      .on('click', function() {
        const allRows = target.find('tbody tr');
        const numberSelectedRows = target.find('tbody tr.selected').length;

        // If there are more rows selected than unselected
        // unselect all, otherwise, selected them all
        if (numberSelectedRows > allRows.length / 2) {
          allRows.removeClass('selected');
        } else {
          allRows.addClass('selected');
        }
      });
  }

  // Move and show folder controller if it's not inside of the table container
  if (
    $folderController.length > 0 &&
    target.closest('.dataTables_wrapper')
      .find('.dataTables_folder .folder-controller').length == 0
  ) {
    $folderController.appendTo('.dataTables_folder');
    $folderController.removeClass('d-none').addClass('d-inline-flex');
  }

  (typeof callBack === 'function') && callBack();

  // Bind any buttons
  XiboInitialise('#' + e.target.id);
};

/**
 * DataTable Filter for Button Column
 * @param data
 * @param type
 * @param row
 * @param meta
 * @returns {*}
 */
window.dataTableButtonsColumn = function(data, type, row, meta) {
  if (type != 'display') {
    return '';
  }

  if (data.buttons.length <= 0) {
    return '';
  }

  return templates.dataTable.buttons({
    translations,
    buttons: data.buttons,
  });
};

window.dataTableTickCrossColumn = function(data, type, row) {
  if (type != 'display') {
    return data;
  }

  let icon = '';
  if (data == 1) {
    icon = 'fa-check';
  } else if (data == 0) {
    icon = 'fa-times';
  } else {
    icon = 'fa-exclamation';
  }

  return '<span class=\'fa ' + icon + '\'></span>';
};

window.dataTableTickCrossInverseColumn = function(data, type, row) {
  if (type != 'display') {
    return data;
  }

  let icon = '';
  if (data == 1) {
    icon = 'fa-times';
  } else if (data == 0) {
    icon = 'fa-check';
  } else {
    icon = 'fa-exclamation';
  }

  return '<span class=\'fa ' + icon + '\'></span>';
};

window.dataTableDateFromIso = function(data, type, row) {
  if (type !== 'display' && type !== 'export') {
    return data;
  }

  if (data == null) {
    return '';
  }

  return moment(data, systemDateFormat).format(jsDateFormat);
};

window.dataTableRoundDecimal = function(data, type, row) {
  if (type !== 'display' && type !== 'export') {
    return data;
  }

  if (data == null) {
    return '';
  }

  return parseFloat(data).toFixed(2);
};

window.dataTableDateFromUnix = function(data, type, row) {
  if (type !== 'display' && type !== 'export') {
    return data;
  }

  if (data == null || data == 0) {
    return '';
  }

  return moment(data, 'X').tz ?
    moment(data, 'X').tz(timezone).format(jsDateFormat) :
    moment(data, 'X').format(jsDateFormat);
};

window.dataTableTimeFromSeconds = function(data, type, row) {
  if (type !== 'display' && type !== 'export') {
    return data;
  }

  if (data == null || data == 0) {
    return '';
  }

  // Get duration
  const duration = moment.duration(data * 1000);

  // Get the number of hours
  const hours = Math.floor(duration.asHours());

  // Format string with leading zero
  const hoursString = (hours < 10) ? '0' + hours : hours;

  return hoursString + moment.utc(duration.asMilliseconds()).format(':mm:ss');
};

window.dataTableSpacingPreformatted = function(data, type, row) {
  if (type !== 'display') {
    return data;
  }

  if (data === null || data === '') {
    return '';
  }

  return '<span class="spacing-whitespace-pre">' + data + '</span>';
};

/**
 * DataTable Create tags
 * @param data
 * @returns {*}
 */
window.dataTableCreateTags = function(data, type) {
  if (type !== 'display') {
    return data.tags;
  }

  let returnData = '';

  if (typeof data.tags !== undefined && data.tags != null) {
    returnData += '<div id="tagDiv">';
    data.tags.forEach(
      (element) => returnData += '<li class="btn btn-sm btn-white btn-tag">' +
        element.tag + ((element.value) ? '|' + element.value : '') + '</li>');
    returnData += '</div>';
  }

  return returnData;
};

/**
 * DataTable Create permissions
 * @param data
 * @returns {*}
 */
window.dataTableCreatePermissions = function(data, type) {
  if (type !== 'display') {
    return data;
  }

  let returnData = '';

  if (typeof data != undefined && data != null) {
    const arrayOfTags = data.split(',');

    returnData += '<div class="permissionsDiv">';

    for (let i = 0; i < arrayOfTags.length; i++) {
      if (arrayOfTags[i] != '') {
        returnData += '<li class="badge">' + arrayOfTags[i] + '</span></li>';
      }
    }

    returnData += '</div>';
  }

  return returnData;
};

/**
 * DataTable Create tags
 * @param e
 * @param settings
 */
window.dataTableCreateTagEvents = function(e, settings) {
  const table = $('#' + e.target.id);
  const tableId = e.target.id;
  const form = e.data.form;
  // Unbind all
  table.off('click');

  table.on('click', '.btn-tag', function(ev) {
    // See if its the first element, if not add comma
    const tagText = $(ev.currentTarget).text();

    if (tableId == 'playlistLibraryMedia') {
      form.find('#filterMediaTag')
        .tagsinput('add', tagText, {allowDuplicates: false});
    } else if (tableId == 'displayGroupDisplays') {
      form.find('#dynamicCriteriaTags')
        .tagsinput('add', tagText, {allowDuplicates: false});
    } else {
      // Add text to form
      form.find('#tags').tagsinput('add', tagText, {allowDuplicates: false});
    }
    // Refresh table to apply the new tag search
    table.DataTable().ajax.reload();
  });
};

window.dataTableAddButtons = function(table, filter, allButtons, resetSort) {
  allButtons = (allButtons === undefined) ? true : allButtons;
  resetSort = (resetSort === undefined) ? false : resetSort;

  const buttons = [
    {
      extend: 'colvis',
      columns: ':not(.rowMenu)',
      text: function(dt, button, config) {
        return dt.i18n('buttons.colvis');
      },
    },
  ];

  if (resetSort) {
    buttons.push(
      {
        text: translations.defaultSorting,
        action: function(e, dt, node, config) {
          table.order([]).draw();
        },
      },
    );
  }

  if (allButtons) {
    buttons.push(
      {
        extend: 'print',
        text: function(dt, button, config) {
          return dt.i18n('buttons.print');
        },
        exportOptions: {
          orthogonal: 'export',
          format: {
            body: function(data, row, column, node) {
              if (data === null || data === '' || data === 'null') {
                return '';
              } else {
                return data;
              }
            },
          },
        },
        customize: function(win) {
          const table = $(win.document.body).find('table');
          table.removeClass('nowrap responsive dataTable no-footer dtr-inline');
          if (table.find('th').length > 16) {
            table.addClass('table-sm');
            table.css('font-size', '6px');
          }
        },
      },
      {
        extend: 'csv',
        exportOptions: {
          orthogonal: 'export',
          format: {
            body: function(data, row, column, node) {
              if (data === null || data === '') {
                return '';
              } else {
                return data;
              }
            },
          },
        },
      },
    );
  }

  new $.fn.dataTable.Buttons(table, {buttons: buttons});

  table.buttons(0, null).container().prependTo(filter);
  $(filter).addClass('text-right');
  $('.ColVis_MasterButton').addClass('btn');
  $(filter).find('.dt-buttons button.btn-secondary')
    .addClass('btn-outline-primary').removeClass('btn-secondary');
};

/**
 * State Load Callback
 * @param settings
 * @param callback
 * @return {{}}
 */
window.dataTableStateLoadCallback = function(settings, callback) {
  const statePreferenceName =
    $('#' + settings.sTableId).data().statePreferenceName;
  const option = (statePreferenceName !== undefined) ?
    statePreferenceName : settings.sTableId + 'Grid';
  let data = {};
  $.ajax({
    type: 'GET',
    async: false,
    url: userPreferencesUrl + '?preference=' + option,
    dataType: 'json',
    success: function(json) {
      try {
        if (json.success) {
          data = JSON.parse(json.data.value);
        }
      } catch (e) {
        // Do nothing
        console.warn(e);
      }
    },
  });
  return data;
};

/**
 * Save State Callback
 * @param settings
 * @param data
 */
window.dataTableStateSaveCallback = function(settings, data) {
  const statePreferenceName =
    $('#' + settings.sTableId).data().statePreferenceName;
  const option = (statePreferenceName !== undefined) ?
    statePreferenceName : settings.sTableId + 'Grid';
  updateUserPref([{
    option: option,
    value: JSON.stringify(data),
  }], function() {
    // ignore
  });
};

window.XiboMultiSelectPermissionsFormOpen = function(button) {
  const $targetTable = $(button).parents('.XiboGrid').find('.dataTable');
  const $matches = $targetTable.find('tr.selected');
  const targetDataTable = $targetTable.DataTable();
  const requestUrl = $(button).data('customHandlerUrl');
  const elementIdName = $(button).data('contentIdName');
  const matchIds = [];

  // Get matches from the selected elements
  $matches.each(function(index, row) {
    // Get data
    const rowData = targetDataTable.row(row).data();

    // Add match id to the array
    matchIds.push(rowData[elementIdName]);
  });

  if ($matches.length == 0) {
    // If there are no matches, show dialog with no element selected message
    bootbox.dialog({
      message: translations.multiselectNoItemsMessage,
      title: translations.multiselect,
      animate: false,
      size: 'large',
      buttons: {
        cancel: {
          label: translations.close,
          className: 'btn-white btn-bb-cancel',
        },
      },
    });
  } else {
    // Render multi edit permissions form
    XiboFormRender(requestUrl, {ids: matchIds.toString()});
  }
};

window.XiboMultiSelectTagFormRender = function(button) {
  const elementType = $(button).data('contentType');
  const elementIdName = $(button).data('contentIdName');
  const matches = [];
  const $targetTable = $(button).parents('.XiboGrid').find('.dataTable');
  const targetDataTable = $targetTable.DataTable();
  let dialogContent = '';
  const dialogId = 'multiselectTagEditForm';
  const matchIds = [];
  const existingTags = [];

  // Get matches from the selected elements
  $targetTable.find('tr.selected').each(function(_idx, el) {
    matches.push($(el));
  });

  // If there are no matches, show form with no element selected message
  if (matches.length == 0) {
    dialogContent = translations.multiselectNoItemsMessage;
  } else {
    // Create the data for the request
    matches.forEach(function(row) {
      // Get data
      const rowData = targetDataTable.row(row).data();

      // Add match id to the array
      matchIds.push(rowData[elementIdName]);

      // Add existing tags to the array
      if (['', null].indexOf(rowData.tags) === -1) {
        rowData.tags.forEach(function(tag) {
          if (existingTags.indexOf(tag) === -1) {
            existingTags.push(tag.tag + ((tag.value) ? '|' + tag.value : ''));
          }
        });
      }
    });

    dialogContent = templates['multiselect-tag-edit-form']({
      trans: translations.multiSelectTagEditForm,
    });
  }

  // Create dialog
  const dialog = bootbox.dialog({
    message: dialogContent,
    title: translations.multiselect,
    size: 'large',
    animate: false,
  });

  // Append a footer to the dialog
  const dialogBody = dialog.find('.modal-body');
  const footer = $('<div>').addClass('modal-footer');
  dialog.find('.modal-content').append(footer);
  dialog.attr('id', dialogId);

  // Add some buttons
  let extrabutton;

  if (matches.length > 0) {
    // Save button
    extrabutton = $('<button class="btn">').html(translations.save)
      .addClass('btn-primary save-button');

    extrabutton.on('click', function(ev) {
      const newTagsToRemove = dialogBody.find('#tagsToRemove').val().split(',');
      const requestURL = dialogBody.find('#requestURL').val();

      const tagsToBeRemoved = function() {
        const tags = [];
        existingTags.forEach(function(oldTag) {
          if (newTagsToRemove.indexOf(oldTag) == -1) {
            tags.push(oldTag);
          }
        });

        return tags;
      };

      const requestData = {
        targetIds: matchIds.toString(),
        targetType: elementType,
        addTags: dialogBody.find('#tagsToAdd').val(),
        removeTags: tagsToBeRemoved().toString(),
      };

      // Add loading icon to the button
      $(ev.currentTarget)
        .append('<span class="saving fa fa-cog fa-spin"></span>');

      // Make an AJAX call
      $.ajax({
        type: 'PUT',
        url: requestURL,
        cache: false,
        dataType: 'json',
        data: requestData,
        success: function(response, textStatus, error) {
          if (response.success) {
            toastr.success(response.message);

            // Hide modal
            dialog.modal('hide');
            targetDataTable.ajax.reload(null, false);
          } else {
            // Why did we fail?
            if (response.login) {
              // We were logged out
              LoginBox(response.message);
            } else {
              // Likely just an error that we want to report on
              footer.find('.saving').remove();
              SystemMessageInline(response.message, footer.closest('.modal'));
            }


            // Remove loading icon
            $(this).find('.saving').remove();
          }
        },
        error: function(responseText) {
          SystemMessage(responseText, false);

          // Remove loading icon
          $(this).find('.saving').remove();
        },
      });

      // Keep the modal open
      return false;
    });

    footer.append(extrabutton);

    // Initialise existing tags ( and save a backup )
    if (existingTags.length > 0) {
      const tagsString = existingTags.toString();
      dialogBody.find('#tagsToRemove').val(tagsString);
    } else {
      dialogBody.find('#tagsToRemoveContainer').hide();
    }

    // Add element type to the request hidden input
    dialogBody.find('#requestURL').val(dialogBody.find('#requestURL')
      .val().replace('[type]', elementType));

    // Prevent tag add
    dialogBody.find('#tagsToRemove').on('beforeItemAdd', function(event) {
      // Cancel event if the tag doesn't belong in the starting tags
      event.cancel = (existingTags.indexOf(event.item) == -1);
    });
  }

  // Close button
  extrabutton = $('<button class="btn">').html(translations.close)
    .addClass('btn-white');
  extrabutton.on('click', function(ev) {
    $(ev.currentTarget)
      .append(' <span class="saving fa fa-cog fa-spin"></span>');

    // Do our thing
    dialog.modal('hide');

    // Bring other modals back to focus
    if ($('.modal').hasClass('in')) {
      $('body').addClass('modal-open');
    }

    // Keep the modal window open!
    return false;
  });

  // Append button
  footer.prepend(extrabutton);

  // Initialise controls
  XiboInitialise('#' + dialogId);
};

window.XiboRefreshAllGrids = function() {
  // We should refresh the grids (this is a global refresh)
  $(' .XiboGrid table.dataTable').each(function(_idx, el) {
    const refresh = $(el).closest('.XiboGrid').data('refreshOnFormSubmit');
    if (refresh === undefined || refresh === null || refresh) {
      const table = $(el).DataTable();
      const tableOptions = table.init();

      // Only refresh if we have ajax enabled
      if (tableOptions.serverSide) {
        // Reload
        table.ajax.reload(null, false);
      }
    }
  });
};

window.adjustDatatableSize = function(reload) {
  // Display Map Resize
  function resizeDisplayMap() {
    if (typeof refreshDisplayMap === 'function') {
      refreshDisplayMap();
    }
  }

  reload = (typeof reload == 'undefined') ? true : reload;
  // Shrink table to ease animation
  if ($('#grid-folder-filter').is(':hidden')) {
    resizeDisplayMap();
  }

  $('#grid-folder-filter').toggle('fast', function(ev) {
    if ($(ev.currentTarget).is(':hidden')) {
      if (!$('#folder-tree-clear-selection-button').is(':checked')) {
        // if folder tree is hidden and select everywhere
        // is not checked, then show breadcrumbs
        $('#breadcrumbs').show('slow');
      }
      resizeDisplayMap();
    } else {
      // if the tree folder view is visible, then hide breadcrumbs
      $('#breadcrumbs').hide('slow');
    }

    if (reload) {
      $(ev.currentTarget).closest('.XiboGrid').find('table.dataTable')
        .DataTable().ajax.reload();
    }
    // set current state of the folder tree visibility to local storage,
    // this is then used to hide/show the tree when User navigates
    // to a different grid or reloads this page
    localStorage.setItem(
      'hideFolderTree', JSON.stringify($('#grid-folder-filter').is(':hidden')),
    );
  });
};

/* Folders */
window.initJsTreeAjax = function(
  container,
  id,
  isForm,
  ttl,
  onReady = null,
  onSelected = null,
  onBuildContextMenu = null,
  plugins = [],
  homeFolderId = null,
) {
  // Default values
  isForm = (typeof isForm == 'undefined') ? false : isForm;
  ttl = (typeof ttl == 'undefined') ? false : ttl;
  let homeNodeId;


  // if there is no modal appended to body and we
  // are on a form that needs this modal, then append it
  if (
    $('#folder-tree-form-modal').length === 0 &&
    $('#' + id + ' #folderId').length &&
    $('#select-folder-button').length
  ) {
    // compile tree folder modal and append it to Form
    const folderTreeModal = templates['folder-tree'];
    const treeConfig = {
      container: 'container-folder-form-tree',
      modal: 'folder-tree-form-modal',
    };

    // append to body, instead of the form as it
    // was before to make it more bootstrap friendly
    $('body').append(folderTreeModal({
      ...treeConfig,
      ...{
        trans: translations.folderTree,
      },
    }));

    $('#folder-tree-form-modal').on('hidden.bs.modal', function(ev) {
      // Fix for 2nd/overlay modal
      $('.modal:visible').length && $(document.body).addClass('modal-open');
      $(ev.currentTarget).data('bs.modal', null);
    });
  }

  let state = {};
  if ($(container).length) {
    // difference here is, that for grid trees we don't set ttl at all
    // add/edit forms have short ttl, multi select will
    // be cached for couple of minutes
    if (isForm) {
      state = {key: id + '_folder_tree', ttl: ttl};
    } else {
      state = {key: id + '_folder_tree'};
    }

    $(container).jstree({
      state: state,
      plugins: [
        'contextmenu',
        'state',
        'unique',
        'sort',
        'types',
        'search',
      ].concat(plugins),
      contextmenu: {
        items: function($node, checkContextMenuPermissions) {
          // items in context menu need to check
          // user permissions before we render them
          // as such each click on the node will execute
          // the below ajax to check what permissions user has
          // permission may be different per node, therefore we
          // cannot look this up just once for whole tree.
          let items = {};
          const tree = $(container).jstree(true);
          let buttonPermissions = null;

          $.ajax({
            url: foldersUrl + '/contextButtons/' + $node.id,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
              buttonPermissions = data;

              if (buttonPermissions.create) {
                items['Create'] = {
                  separator_before: false,
                  separator_after: false,
                  label: translations.folderTreeCreate,
                  action: function(obj) {
                    $node = tree.create_node($node);
                    tree.edit($node);
                  },
                };
              }

              if (buttonPermissions.modify) {
                items['Rename'] = {
                  separator_before: false,
                  separator_after: false,
                  label: translations.folderTreeEdit,
                  action: function(obj) {
                    tree.edit($node);
                  },
                };
              }

              if (buttonPermissions.delete) {
                items['Remove'] = {
                  separator_before: true,
                  separator_after: false,
                  label: translations.folderTreeDelete,
                  action: function(obj) {
                    tree.delete_node($node);
                  },
                };
              }

              if (isForm === false && buttonPermissions.share) {
                items['Share'] = {
                  separator_before: true,
                  separator_after: false,
                  label: translations.folderTreeShare,
                  _class: 'XiboFormRender',
                  action: function(obj) {
                    XiboFormRender(
                      permissionsUrl.replace(':entity', 'form/Folder/') +
                      $node.id,
                    );
                  },
                };
              }

              if (isForm === false && buttonPermissions.move) {
                items['Move'] = {
                  separator_before: true,
                  separator_after: false,
                  label: translations.folderTreeMove,
                  _class: 'XiboFormRender',
                  action: function(obj) {
                    XiboFormRender(foldersUrl + '/form/' + $node.id + '/move');
                  },
                };
              }

              if (
                onBuildContextMenu !== null &&
                onBuildContextMenu instanceof Function
              ) {
                items = onBuildContextMenu($node, items);
              }
            },
            complete: function(data) {
              checkContextMenuPermissions(items);
            },
          });
        },
      },
      types: {
        root: {
          icon: 'fa fa-file text-xibo-primary',
        },
        home: {
          icon: 'fa fa-home text-xibo-primary',
        },
        default: {
          icon: 'fa fa-folder text-xibo-primary',
        },
        open: {
          icon: 'fa fa-folder-open text-xibo-primary',
        },
      },
      search: {
        show_only_matches: true,
      },
      core: {
        check_callback: function(operation, node, parent, position, more) {
          // prevent edit/delete of the root node.
          if (operation === 'delete_node' || operation === 'rename_node') {
            if (node.id === '#' || node.id === '1') {
              toastr.error(translations.folderTreeError);
              return false;
            }
          }
          return true;
        },
        data: {
          url: foldersUrl +
            (homeFolderId ? '?homeFolderId=' + homeFolderId : ''),
        },
        themes: {
          responsive: true,
          dots: false,
        },
      },
    }).bind('ready.jstree', function(e, data) {
      // depending on the state of folder tree
      // hide/show as needed when we load the grid page
      if (localStorage.getItem('hideFolderTree') !== undefined &&
        localStorage.getItem('hideFolderTree') !== null &&
        JSON.parse(localStorage.getItem('hideFolderTree')) !==
        $('#grid-folder-filter').is(':hidden')
      ) {
        adjustDatatableSize(false);
      }
      // if node has children and User does not have
      // suitable permissions, disable the node
      // If node does NOT have children and User does
      // not have suitable permissions, hide the node completely
      $.each(data.instance._model.data, function(index, e) {
        if (e?.original?.type === 'disabled') {
          const node = $(container).jstree().get_node(e.id);
          if (e.children.length === 0) {
            $(container).jstree().hide_node(node);
          } else {
            $(container).jstree().disable_node(node);
          }
        }

        if (e?.original?.isRoot === 1) {
          $(container).find('a#' + e.id + '_anchor')
            .attr('title', translations.folderRootTitle);
        }

        // get the home folder
        if (e.type !== undefined && e.type === 'home') {
          homeNodeId = e.id;

          // check state
          const currentState = localStorage.getItem(id + '_folder_tree');
          // if we have no state saved, select the homeFolderId in the tree.
          if (
            (currentState === undefined || currentState === null) &&
            !isForm
          ) {
            $(container).jstree(true).select_node(homeNodeId);
          }
        }
      });

      // if we are on the form, we need to
      // select tree node (currentWorkingFolder)
      // this is set/passed to twigs on render time
      if (isForm) {
        let folderIdInputSelector = '#' + id + ' #folderId';

        // for upload forms
        if ($(folderIdInputSelector).length === 0) {
          folderIdInputSelector = '#formFolderId';
        }

        const selectedFolder = !$(folderIdInputSelector).val() ?
          homeNodeId : $(folderIdInputSelector).val();

        if (selectedFolder !== undefined && selectedFolder !== '') {
          // eslint-disable-next-line no-invalid-this
          const self = this;
          $(self).jstree('select_node', selectedFolder);
          if ($('#originalFormFolder').length) {
            $('#originalFormFolder').text($(self).jstree().get_path($(self)
              .jstree('get_selected', true)[0], ' > '));
          }

          if (
            $('#selectedFormFolder').length &&
            folderIdInputSelector === '#formFolderId'
          ) {
            $('#selectedFormFolder').text($(self).jstree().get_path($(self)
              .jstree('get_selected', true)[0], ' > '));
          }
        }
      }

      if (onReady && onReady instanceof Function) {
        onReady($(container).jstree(true), $(container));
      }
    }).bind('rename_node.jstree', function(e, data) {
      const dataObject = {};
      const folderId = data.node.id;
      dataObject['text'] = data.text;

      $.ajax({
        url: foldersUrl + '/' + folderId,
        method: 'PUT',
        dataType: 'json',
        data: dataObject,
        success: function(data) {
          if (container === '#container-folder-form-tree') {
            // if we rename node on a form, make sure
            // to refresh the js tree in the grid
            $('#container-folder-tree').jstree(true).refresh();
          }
        },
      });
    }).bind('create_node.jstree', function(e, data) {
      const node = data.node;
      node.text = translations.folderNew;

      const dataObject = {};
      dataObject['parentId'] = data.parent;
      dataObject['text'] = data.node.text;

      // when we create a new node, by default it will get jsTree default id
      // we need to change it to the folderId we have in our folder table
      // rename happens just after add, therefore
      // this needs to be set as soon as possible
      $.ajax({
        url: foldersUrl,
        method: 'POST',
        dataType: 'json',
        data: dataObject,
        success: function(data) {
          $(container).jstree(true).set_id(node, data.data.id);
          // if we add a new node on a form, make sure
          // to refresh the js tree in the grid
          if (container === '#container-folder-form-tree') {
            $('#container-folder-tree').jstree(true).refresh();
          }
        },
      });
    }).bind('delete_node.jstree', function(e, data) {
      const dataObject = {};
      dataObject['parentId'] = data.parent;
      dataObject['text'] = data.node.text;
      const folderId = data.node.id;

      // delete has a check built-in, if it fails to remove node
      // it will show suitable message in toast
      // and reload the tree
      $.ajax({
        url: foldersUrl + '/' + folderId,
        method: 'DELETE',
        dataType: 'json',
        data: dataObject,
        success: function(data) {
          if (data.success) {
            toastr.success(translations.done);
            // if we delete node on a form, make sure to refresh
            // the js tree in the grid
            if (container === '#container-folder-form-tree') {
              $('#container-folder-tree').jstree(true).refresh();
            }
          } else {
            if (data.message !== undefined) {
              toastr.error(data.message);
            } else {
              toastr.error(translations.folderWithContent);
            }
            $(container).jstree(true).refresh();
          }
        },
      });
    }).bind('changed.jstree', function(e, data) {
      const selectedFolderId = data.selected[0];
      let folderIdInputSelector = (isForm) ?
        '#' + id + ' #folderId' : '#folderId';
      const node = $(container).jstree('get_selected', true);

      // for upload and multi select forms.
      if (isForm && $(folderIdInputSelector).length === 0) {
        folderIdInputSelector = '#formFolderId';
      }

      if (selectedFolderId !== undefined && isForm === false) {
        $('#breadcrumbs').text($(container).jstree()
          .get_path(node[0], ' > ')).hide();
        $('#folder-tree-clear-selection-button').prop('checked', false);
      }

      // on grids, depending on the selected folder
      // we need to handle the breadcrumbs
      if (
        $(folderIdInputSelector).val() != selectedFolderId &&
        isForm === false
      ) {
        if (selectedFolderId !== undefined) {
          $(folderIdInputSelector).val(selectedFolderId).trigger('change');
        } else {
          $('#breadcrumbs').text('');
          $('#folder-tree-clear-selection-button').prop('checked', true);
          $('.XiboFilter').find('#folderId').val(null).trigger('change');
        }
      }

      // on form we always want to show the breadcrumbs
      // to current and selected folder
      if (isForm && selectedFolderId !== undefined) {
        $(folderIdInputSelector).val(selectedFolderId).trigger('change');
        if ($('#selectedFormFolder').length) {
          $('#selectedFormFolder').text($(container)
            .jstree().get_path(node[0], ' > '));
        }
      }

      if (onSelected && onSelected instanceof Function) {
        onSelected(data);
      }
    }).bind('open_node.jstree', function(e, data) {
      if (data.node.type !== 'root' && data.node.type !== 'home') {
        data.instance.set_type(data.node, 'open');
      }
    }).bind('close_node.jstree', function(e, data) {
      if (data.node.type !== 'root' && data.node.type !== 'home') {
        data.instance.set_type(data.node, 'default');
      }
    }).bind('search.jstree', function(nodes, str, res) {
      // by default the plugin shows all folders
      // if search does not match anything
      // make it so we hide the tree in such cases,
      if (str.nodes.length === 0) {
        $(container).jstree(true).hide_all();
        $(container).parent().find('.folder-search-no-results')
          .removeClass('d-none');
      } else {
        $(container).parent().find('.folder-search-no-results')
          .addClass('d-none');
      }
    });

    // on froms that have more than one modal active
    // this is needed to not confuse bootstrap
    // the (X) needs to close just the inner modal
    // clicking outside of the tree select modal will work as well.
    $('.btnCloseInnerModal').on('click', function(e) {
      e.preventDefault();
      const folderTreeModalId = (isForm) ?
        '#folder-tree-form-modal' : '#folder-tree-modal';
      $(folderTreeModalId).modal('hide');
    });

    // this handler for the search everywhere checkbox on grid pages
    $('#folder-tree-clear-selection-button').on('click', function() {
      if ($('#folder-tree-clear-selection-button').is(':checked')) {
        $(container).jstree('deselect_all');
        $('.XiboFilter').find('#folderId').val(null).trigger('change');
      } else {
        $(container).jstree('select_node', homeNodeId ?? 1);
      }
    });

    // this is handler for the hamburger button on grid pages
    $('#folder-tree-select-folder-button').off('click')
      .on('click', adjustDatatableSize);

    const folderSearch = _.debounce(function() {
      // show all folders, as it might be hidden
      // if previous search returned empty.
      $(container).jstree(true).show_all();
      // for reasons, search event is not triggered on clear/empty search
      // make sure we hide the div with message about no results here.
      $(container).parent().find('.folder-search-no-results')
        .addClass('d-none');
      // search for the folder via entered string.
      // eslint-disable-next-line no-invalid-this
      $(container).jstree(true).search($(this).val());
    }, 500);
    $('#jstree-search').on('keyup', folderSearch);
    $('#jstree-search-form').on('keyup', folderSearch);
  }

  // Make container resizable
  $('#grid-folder-filter').resizable({
    handles: 'e',
    minWidth: 200,
    maxWidth: 500,
  });
};

window.disableFolders = function() {
  // if user does not have Folders feature enabled
  // then we need to remove couple of elements from the page
  // to prevent jsTree from executing, make the datatable take
  // whole available width as well.
  $('#folder-tree-select-folder-button').parent().remove();
  $('#container-folder-tree').remove();
  $('#grid-folder-filter').remove();
};
