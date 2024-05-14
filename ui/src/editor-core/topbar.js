/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

// Load templates
const topbarTemplate = require('../templates/topbar.hbs');
const topbarLayoutJumpList =
  require('../templates/toolbar-layout-jump-list.hbs');

/**
 * Bottom topbar contructor
 * @param {object} parent - The parent object
 * @param {object} container - the container to render the topbar to
 * @param {object[]} [customDropdownOptions] - customized dropdown buttons
 * @param {object} [customActions] - customized actions
 * @param {object} [jumpList] - jump list
 * @param {boolean=} [showOptions] - show options menu
 */
const Topbar = function(parent,
  container,
  customDropdownOptions = null,
  customActions = {},
  jumpList = {},
  showOptions = false) {
  this.parent = parent;

  this.DOMObject = container;

  // Layout jumplist
  this.jumpList = jumpList;

  // Custom dropdown buttons
  this.customDropdownOptions = customDropdownOptions;

  // Custom actions
  this.customActions = customActions;

  // Options menu
  this.showOptions = showOptions;
};

/**
 * Render topbar
 */
Topbar.prototype.render = function() {
  const self = this;
  const app = this.parent;

  // Get main object
  const mainObject =
    app.getObjectByTypeAndId(app.mainObjectType, app.mainObjectId);

  // Format duration
  mainObject.duration = Math.round(Number(mainObject.duration) * 100) / 100;

  // Get topbar trans
  const newTopbarTrans = $.extend({}, toolbarTrans, topbarTrans, editorsTrans);

  // Clear temp data
  app.common.clearContainer(this.DOMObject);

  // Compile layout template with data
  const html = topbarTemplate({
    customDropdownOptions: this.customDropdownOptions,
    displayTooltips: app.common.displayTooltips,
    deleteConfirmation: app.common.deleteConfirmation,
    trans: newTopbarTrans,
    mainObject: mainObject,
    showOptions: self.showOptions,
    exitURL: (lD != 'undefined') && lD.exitURL,
  });

  // Append layout html to the main div
  this.DOMObject.html(html);

  const setButtonActionAndState = function(button) {
    if (button.isDivider) {
      return;
    }

    let buttonInactive = false;

    // Bind action to button
    self.DOMObject.find('#' + button.id).click(
      button.action,
    );

    // If there is a inactiveCheck, use that function to switch button state
    if (button.inactiveCheck != undefined) {
      const inactiveClass =
        (button.inactiveCheckClass != undefined) ?
          button.inactiveCheckClass :
          'disabled';
      const toggleValue = button.inactiveCheck();
      self.DOMObject.find('#' + button.id)
        .toggleClass(inactiveClass, toggleValue);
      buttonInactive = toggleValue;
    }

    return buttonInactive;
  };

  // Setup layout edit form.
  this.DOMObject.find('#layoutInfo').off('click').on('click', function() {
    // Pop open the layout edit form.
    // eslint-disable-next-line new-cap
    XiboFormRender(urlsForApi.layout.editForm.url.replace(
      ':id',
      self.parent.layout.parentLayoutId || self.parent.layout.layoutId),
    );

    /**
     * Wait for name field to show and select it
     * so it can be easier to replace
     */
    function selectField() {
      const $field = $('#layoutEditForm input#name');
      // If field doesn't exist, wait and call method again
      if ($field.length === 0) {
        setTimeout(selectField, 200);
      } else {
        // Select name
        $field.trigger('select');
      }
    }
    selectField();
  });

  // Handle custom dropwdown buttons
  if (this.customDropdownOptions != null) {
    let activeDropdown = false;

    for (let index = 0; index < this.customDropdownOptions.length; index++) {
      const buttonInactive =
        setButtonActionAndState(this.customDropdownOptions[index]);

      if (!buttonInactive) {
        activeDropdown = true;
      }
    }

    self.DOMObject.find('.dropdown.navbar-submenu:not(.navbar-submenu-options)')
      .toggle(activeDropdown);
  }

  // Set layout jumpList if exists
  if (
    !$.isEmptyObject(this.jumpList) &&
    self.DOMObject.find('#layoutJumpList').length == 0
  ) {
    self.setupJumpList();
  }

  // Options menu
  if (self.showOptions) {
    self.DOMObject.find('.navbar-submenu-options-container').off()
      .click(function(e) {
        e.stopPropagation();
      });

    // Toggle tooltips
    self.DOMObject.find('#displayTooltips').off().click(function() {
      app.common.displayTooltips = $('#displayTooltips').prop('checked');

      app.toolbar.savePrefs();

      app.common.reloadTooltips(app.editorContainer);
    });

    // Show delete confirmation modals
    self.DOMObject.find('#deleteConfirmation').off().click(function() {
      app.common.deleteConfirmation = $('#deleteConfirmation').prop('checked');
      app.toolbar.savePrefs();
    });
  }

  // Update layout status
  this.updateLayoutStatus();

  // Reload tooltips
  app.common.reloadTooltips(self.DOMObject);
};

/**
* Setup layout jumplist
*/
Topbar.prototype.setupJumpList = function() {
  const app = this.parent;
  const $jumpListContainer = $('#layoutJumpListContainer');

  // If we are in template edit mode, don't show
  if (
    this.parent.templateEditMode != undefined &&
    this.parent.templateEditMode === true
  ) {
    return;
  }

  const html = topbarLayoutJumpList(this.jumpList);

  // Clear temp data
  app.common.clearContainer($jumpListContainer);

  // Append layout html to the main div
  $jumpListContainer.html(html);
  $jumpListContainer.removeClass('d-none');

  const jumpList = $jumpListContainer.find('#layoutJumpList');
  jumpList.select2({
    ajax: {
      url: jumpList.data().url,
      dataType: 'json',
      delay: 250,
      data: function(params) {
        const query = {
          layout: params.term,
          onlyMyLayouts: $('#onlyMyLayouts').is(':checked'),
          retired: 0,
          start: 0,
          length: 10,
        };

        localStorage.liveSearchOnlyMyLayouts =
          $('#onlyMyLayouts').is(':checked') ? 1 : 0;

        // Tags
        if (query.layout != undefined) {
          const tags = query.layout.match(/\[([^}]+)\]/);
          if (tags != null) {
            // Add tags to search
            query.tags = tags[1];

            // Replace tags in the query text
            query.layout = query.layout.replace(tags[0], '');
          }
        }

        // Set the start parameter based on the page number
        if (params.page != null) {
          query.start = (params.page - 1) * 10;
        }

        // Find out what is inside the search box for this list
        // and save it (so we can replay it when the list
        // is opened again)
        if (params.term !== undefined) {
          localStorage.liveSearchPlaceholder = params.term;
        }

        return query;
      },
      processResults: function(data, params) {
        const results = [];

        $.each(data.data, function(index, element) {
          results.push({
            id: element.layoutId,
            text: element.layout,
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

  jumpList.on('select2:select', function(e) {
    // OPTIMIZE: Maybe use the layout load without reloading page
    // self.jumpList.callback(e.params.data.id);

    // Go to the Layout we've selected.
    window.location = jumpList.data().designerUrl
      .replace(':id', e.params.data.id);
  }).on('select2:opening', function(e) {
    // Set the search box according to the saved value (if we have one)

    if (
      localStorage.liveSearchPlaceholder != null &&
      localStorage.liveSearchPlaceholder !== ''
    ) {
      const $search = jumpList.data('select2').dropdown.$search;
      $search.val(localStorage.liveSearchPlaceholder);

      setTimeout(function() {
        $search.trigger('input');
      }, 100);
    }
  }).on('select2:open', function(e) {
    // append checkbox after select2 search input (only once)
    if ($('#onlyMyLayouts').length === 0) {
      $('<input style=\'margin-left: 5px; margin-bottom: 15px\' ' +
        'type=\'checkbox\' id=\'onlyMyLayouts\' name=\'onlyMyLayouts\'> ' +
        topbarTrans.onlyMyLayouts + '</input>')
        .insertAfter('.select2-search');

      // if checkbox was checked last time, check it now
      if (Number(localStorage.getItem('liveSearchOnlyMyLayouts')) === 1) {
        $('#onlyMyLayouts').prop('checked', true);
      }
    }

    const $search = jumpList.data('select2').dropdown.$search;

    // when checkbox state is changed trigger the select2 to make a new search
    $('#onlyMyLayouts').on('change', function(e) {
      setTimeout(function() {
        $search.trigger('input');
      }, 100);
    });

    // Force search field focus
    setTimeout(function() {
      $search.get(0).focus();
    }, 10);
  });
};

/**
 * Update layout status in the info fields
 */
Topbar.prototype.updateLayoutStatus = function() {
  const statusContainer = this.DOMObject.find('#layout-info-status');

  // Use status loader icon
  statusContainer.find('i').removeClass().addClass('fa fa-spinner fa-spin');
  statusContainer.removeClass().addClass('badge badge-default');

  // Prevent the update if there's no layout status yet
  if (lD.layout.status == undefined) {
    return;
  }

  let title = '';
  let content = '';

  const labelCodes = {
    1: 'success',
    2: 'warning',
    3: 'info',
    '': 'danger',
  };

  const iconCodes = {
    1: 'check',
    2: 'exclamation',
    3: 'cogs',
    '': 'times',
  };

  // Create title and description
  if (lD.layout.status.messages.length > 0) {
    title = lD.layout.status.description;
    for (let index = 0; index < lD.layout.status.messages.length; index++) {
      content += '<div class="status-message">' +
        lD.layout.status.messages[index] +
        '</div>';
    }
  } else {
    title = '';
    content = '<div class="status-title text-center">' +
      lD.layout.status.description +
      '</div>';
  }

  // Update label
  const labelType = (labelCodes[lD.layout.status.code] != undefined) ?
    labelCodes[lD.layout.status.code] :
    labelCodes[''];
  statusContainer.removeClass().addClass('badge badge-' + labelType)
    .attr('data-status-code', lD.layout.status.code);

  // Create or update popover
  if (statusContainer.data('bs.popover') == undefined) {
    // Create popover
    statusContainer.popover(
      {
        delay: tooltipDelay,
        title: title,
        content: content,
      },
    );
  } else {
    // Update popover
    statusContainer.data('bs.popover').config.title = title;
    statusContainer.data('bs.popover').config.content = content;
  }

  // Change Icon
  const iconType = (iconCodes[lD.layout.status.code] != undefined) ?
    iconCodes[lD.layout.status.code] :
    iconCodes[''];
  statusContainer.find('i').removeClass().addClass('fa fa-' + iconType);

  // Update duration
  this.DOMObject.find('.layout-info-duration-value').html(
    lD.common.timeFormat(
      lD.layout.duration,
    ));
};

module.exports = Topbar;

