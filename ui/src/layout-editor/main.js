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

/* eslint-disable prefer-promise-reject-errors */
// Include public path for webpack
require('../../public_path');

// Include handlebars templates
const designerMainTemplate = require('../templates/layout-editor.hbs');
const messageTemplate = require('../templates/message.hbs');
const loadingTemplate = require('../templates/loading.hbs');
const contextMenuTemplate = require('../templates/context-menu.hbs');
const deleteElementModalContentTemplate =
  require('../templates/delete-element-modal-content.hbs');
const confirmationModalTemplate =
  require('../templates/confirmation-modal.hbs');

// Include modules
const Layout = require('../layout-editor/layout.js');
const Viewer = require('../layout-editor/viewer.js');
const PropertiesPanel = require('../editor-core/properties-panel.js');
const HistoryManager = require('../editor-core/history-manager.js');
const TemplateManager = require('../layout-editor/template-manager.js');
const Toolbar = require('../editor-core/toolbar.js');
const Topbar = require('../editor-core/topbar.js');
const Bottombar = require('../editor-core/bottombar.js');
const Widget = require('../editor-core/widget.js');

// Common funtions/tools
const Common = require('../editor-core/common.js');

// Include CSS
require('../style/common.scss');
require('../style/layout-editor.scss');
require('../style/toolbar.scss');
require('../style/topbar.scss');
require('../style/bottombar.scss');

// Create layout designer namespace (lD)
window.lD = {
  // Read Only mode
  readOnlyMode: false,

  // Attach common functions to layout designer
  common: Common,

  // Main object info
  mainObjectType: 'layout',
  mainObjectId: '',

  // Layout
  layout: {},

  // History Manager
  historyManager: {},

  // Template manager
  templateManager: {},

  // Viewer
  viewer: {},

  // Designer DOM div
  editorContainer: $('#layout-editor'),

  // Selected object
  // and previous selected object
  selectedObject: {},
  previousSelectedObject: {},

  // Bottom toolbar
  toolbar: {},

  // Top toolbar
  topbar: {},

  // Properties Panel
  propertiesPanel: {},

  folderId: '',
};

// Load Layout and build app structure
$(() => {
  // Get layout id
  const layoutId = lD.editorContainer.attr('data-layout-id');

  lD.common.showLoadingScreen();

  // Append loading html to the main div
  lD.editorContainer.html(loadingTemplate());

  // Change toastr positioning
  toastr.options.positionClass = 'toast-top-center';

  // Load layout through an ajax request
  $.get(
    urlsForApi.layout.get.url + '?layoutId=' + layoutId +
    '&embed=regions,playlists,widgets,widget_validity,tags,permissions,actions',
  ).done(function(res) {
    if (res.data != null && res.data.length > 0) {
      // Append layout html to the main div
      lD.editorContainer.html(
        designerMainTemplate(
          {
            trans: layoutEditorTrans,
            exitURL: urlsForApi.layout.list.url,
          },
        ),
      );

      // Initialize template manager
      lD.templateManager = new TemplateManager(
        lD,
      );

      // Create layout
      lD.layout = new Layout(layoutId, res.data[0]);

      // Update main object id
      lD.mainObjectId = lD.layout.layoutId;

      // get Layout folder id
      lD.folderId = lD.layout.folderId;

      // Initialize manager
      lD.historyManager = new HistoryManager(
        lD,
        lD.editorContainer.find('#layout-manager'),
        false, // (serverMode == 'Test') Turn of manager visibility for now
      );

      // Initialize viewer
      lD.viewer = new Viewer(
        lD,
        lD.editorContainer.find('#layout-viewer'),
      );

      // Initialize bottom toolbar ( with custom buttons )
      lD.toolbar = new Toolbar(
        lD,
        lD.editorContainer.find('.editor-main-toolbar'),
        // Custom actions
        {
          deleteSelectedObjectAction: lD.deleteSelectedObject,
          deleteDraggedObjectAction: lD.deleteDraggedObject,
        },
      );

      // Initialize top topbar
      lD.topbar = new Topbar(
        lD,
        lD.editorContainer.find('.editor-top-bar'),
        // Custom dropdown options
        [
          {
            id: 'newLayout',
            title: layoutEditorTrans.newTitle,
            logo: 'fa-file',
            action: lD.addLayout,
            inactiveCheck: function() {
              return false;
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'publishLayout',
            title: layoutEditorTrans.publishTitle,
            logo: 'fa-check-square-o',
            class: 'btn-success',
            action: lD.showPublishScreen,
            inactiveCheck: function() {
              return (lD.layout.editable == false);
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'checkoutLayout',
            title: layoutEditorTrans.checkoutTitle,
            logo: 'fa-edit',
            action: lD.layout.checkout,
            inactiveCheck: function() {
              return (lD.layout.editable == true);
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'discardLayout',
            title: layoutEditorTrans.discardTitle,
            logo: 'fa-times-circle-o',
            action: lD.showDiscardScreen,
            inactiveCheck: function() {
              return (lD.layout.editable == false);
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'deleteLayout',
            title: layoutEditorTrans.deleteTitle,
            logo: 'fa-times-circle-o',
            class: 'btn-danger',
            action: lD.showDeleteScreen,
            inactiveCheck: function() {
              return (
                lD.layout.editable == true ||
                lD.layout.deletePermission == false
              );
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'scheduleLayout',
            title: layoutEditorTrans.scheduleTitle,
            logo: 'fa-clock-o',
            action: lD.showScheduleScreen,
            inactiveCheck: function() {
              return (
                lD.layout.editable == true ||
                lD.layout.scheduleNowPermission == false
              );
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'saveTemplate',
            title: layoutEditorTrans.saveTemplateTitle,
            logo: 'fa-floppy-o',
            action: lD.showSaveTemplateScreen,
            inactiveCheck: function() {
              return (lD.layout.editable == true);
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'unlockLayout',
            title: layoutEditorTrans.unlockTitle,
            logo: 'fa-unlock',
            class: 'btn-info show-on-lock',
            action: lD.showUnlockScreen,
          },
        ],
        // Custom actions
        {},
        // jumpList
        {
          searchLink: urlsForApi.layout.get.url,
          designerLink: urlsForApi.layout.designer.url,
          layoutId: lD.layout.layoutId,
          layoutName: lD.layout.name,
          callback: lD.reloadData,
        },
        true, // Show Options
      );

      // Initialize bottom toolbar ( with custom buttons )
      lD.bottombar = new Bottombar(
        lD,
        lD.editorContainer.find('.editor-bottom-bar'),
      );

      // Initialize properties panel
      lD.propertiesPanel = new PropertiesPanel(
        lD,
        lD.editorContainer.find('#properties-panel'),
      );

      if (res.data[0].publishedStatusId != 2) {
        const url = new URL(window.location.href);

        if (url.searchParams.get('vM') == '1') {
          // Enter view mode
          lD.enterReadOnlyMode();
        } else {
          // Enter welcome screen
          lD.welcomeScreen();
        }
      }

      // Setup helpers
      formHelpers.setup(lD, lD.layout);

      // Load user preferences
      lD.loadAndSavePref('useLibraryDuration', 0);

      // Call layout status every minute
      lD.checkLayoutStatus();
      setInterval(lD.checkLayoutStatus, 1000 * 60); // Every minute

      // Default selected object is the layout
      lD.selectedObject = lD.layout;
      lD.selectedObject.type = 'layout';

      // Refresh the designer containers
      lD.refreshEditor(true, true);

      // Load preferences
      lD.loadPrefs();
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        lD.showErrorMessage();
      }
    }

    lD.common.hideLoadingScreen();
  }).fail(function(jqXHR, textStatus, errorThrown) {
    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);

    lD.showErrorMessage();
  },
  );

  // Handle keyboard keys
  $('body').off('keydown').keydown(function(handler) {
    if ($(handler.target).is($('body'))) {
      if (handler.key == 'Delete' && lD.readOnlyMode == false) {
        lD.deleteSelectedObject();
      }
    }
  });

  if (window.addEventListener) {
    window.addEventListener('message', lD.handleMessage);
  } else {
    window.attachEvent('onmessage', lD.handleMessage);
  }
});

/**
 * Select a layout object (layout/region/widget)
 * @param {object=} target - Object to be selected
 * @param {bool=} forceSelect - Select object even if it was already selected
 * @param {object=} clickPosition - Position of the click
 * @param {bool=} reloadViewer - Force viewer reload
 * @param {bool=} refreshEditor - Force refresh of the editor
 */
lD.selectObject =
  function({
    target = null,
    forceSelect = false,
    clickPosition = null,
    refreshEditor = true,
    reloadViewer = false,
  } = {}) {
    // Clear rogue tooltips
    lD.common.clearTooltips();

    // If there is a selected card
    // use the drag&drop simulate to add that item to a object
    if (!$.isEmptyObject(this.toolbar.selectedCard)) {
      // Get card object
      const card = this.toolbar.selectedCard[0];
      // Check if droppable is active
      const activeDroppable = (target) ?
        target.hasClass('ui-droppable-active') :
        true;

      // Deselect cards and drop zones
      this.toolbar.deselectCardsAndDropZones();

      if (
        target &&
        (
          ['drawer', 'zone', 'playlist'].includes(target.data('subType')) ||
          (
            target.hasClass('designer-widget') &&
            activeDroppable
          ) ||
          target.hasClass('ui-droppable-actions-target')
        )
      ) {
        // Simulate drop item add
        this.dropItemAdd(target, card);
      } else {
        // No target - add to layout
        this.dropItemAdd(null, card, clickPosition);
      }
    } else if (
      !$.isEmptyObject(this.toolbar.selectedQueue)
    ) {
      // If there's a selected queue
      // use the drag&drop simulate to add those items to a object
      const selectedQueue = lD.toolbar.selectedQueue;

      // Add to layout, but create a new region
      lD.addRegion(clickPosition, 'frame').then((res) => {
        const playlistId = res.data.regionPlaylist.playlistId;
        // Add media to new region
        lD.importFromProvider(selectedQueue).then((res) => {
          // If res is empty, it means that the import failed
          if (res.length === 0) {
            // Delete new region
            lD.layout.deleteElement(
              'region',
              res.data.regionPlaylist.regionId,
            );
          } else {
            // Add media queue to playlist
            lD.addMediaToPlaylist(playlistId, res);
          }
        });
      });

      // Deselect cards and drop zones
      this.toolbar.deselectCardsAndDropZones();
    } else {
      // Get object properties from the DOM ( or set to layout if not defined )
      const newSelectedId =
        (target === null) ? this.layout.id : target.attr('id');
      const newSelectedType =
      (target === null || target.data('type') === undefined) ?
        'layout' :
        target.data('type');

      const isInDrawer = function(target) {
        if (target !== null) {
          return (target.data('isInDrawer')) ||
            (target.parent().data('subType') === 'drawer');
        } else {
          return false;
        }
      };

      // If the object is the drawer, skip the rest
      if (target && target.hasClass('designer-region-drawer')) {
        return;
      }

      const oldSelectedId = this.selectedObject.id;
      const oldSelectedType = this.selectedObject.type;

      // Unselect the previous selectedObject object if still selected
      if (this.selectedObject.selected) {
        this.selectedObject.selected = false;
      }

      // Set to the default object
      this.selectedObject = this.layout;
      this.selectedObject.type = 'layout';

      // If the selected object was different from the previous
      // select a new one
      if (
        oldSelectedId != newSelectedId ||
        oldSelectedType != newSelectedType ||
        forceSelect
      ) {
        // Save the new selected object
        if (newSelectedType === 'region') {
          this.layout.regions[newSelectedId].selected = true;
          this.selectedObject = this.layout.regions[newSelectedId];
        } else if (newSelectedType === 'widget') {
          if (isInDrawer(target)) {
            this.layout.drawer.widgets[newSelectedId].selected = true;
            this.selectedObject = this.layout.drawer.widgets[newSelectedId];
          } else {
            this.layout.regions[target.data('widgetRegion')]
              .widgets[newSelectedId]
              .selected = true;

            this.selectedObject =
                this.layout.regions[target.data('widgetRegion')]
                  .widgets[newSelectedId];
          }
        } else if (newSelectedType === 'element') {
          const parentRegion = target.data('regionId');
          const parentWidget = target.data('widgetId');

          const element = this.layout.canvas.widgets[
            'widget_' + parentRegion + '_' + parentWidget
          ].elements[newSelectedId];

          element.selected = true;
          this.selectedObject = element;
        } else if (newSelectedType === 'element-group') {
          const parentRegion = target.data('regionId');
          const parentWidget = target.data('widgetId');

          const element = this.layout.canvas.widgets[
            'widget_' + parentRegion + '_' + parentWidget
          ].elementGroups[newSelectedId];

          element.selected = true;
          this.selectedObject = element;
        }

        this.selectedObject.type = newSelectedType;

        // Refresh the designer containers
        (refreshEditor) && lD.refreshEditor(false, reloadViewer);
      }
    }
  };

/**
 * Refresh designer
 * @param {boolean} [updateToolbar=false] - Update toolbar
 * @param {boolean} [reloadViewer=false] - Reload viewer
 */
lD.refreshEditor = function(
  updateToolbar = false,
  reloadViewer = false,
) {
  // Remove temporary data
  this.clearTemporaryData();

  // Toolbars
  (updateToolbar) && this.toolbar.render();
  this.topbar.render();
  this.bottombar.render(this.selectedObject);

  // Manager ( hidden )
  this.historyManager.render();

  // Properties panel and viewer
  this.propertiesPanel.render(this.selectedObject);
  (reloadViewer) && this.viewer.render(reloadViewer);
};

/**
 * Reload API data and replace the layout structure with the new value
 * @param {object=} layout  - previous layout
 * @param {boolean} [refreshEditor=false] - refresh editor
 * @param {boolean} captureThumbnail - capture thumbnail
 * @param {callBack} callBack - callback function
 * @param {boolean} updateToolbar - update toolbar
 * @return {Promise} - Promise
 */
lD.reloadData = function(
  layout,
  refreshEditor = false,
  captureThumbnail = false,
  callBack = null,
  updateToolbar = false,
) {
  const layoutId =
    (typeof layout.layoutId == 'undefined') ? layout : layout.layoutId;

  lD.common.showLoadingScreen();

  return $.get(
    urlsForApi.layout.get.url + '?layoutId=' + layoutId +
    '&embed=regions,playlists,widgets,widget_validity,tags,permissions,actions',
  ).done(function(res) {
    if (res.data != null && res.data.length > 0) {
      lD.layout = new Layout(layoutId, res.data[0]);

      // Update main object id
      lD.mainObjectId = lD.layout.layoutId;
      // get Layout folder id
      lD.folderId = lD.layout.folderId;

      // Select the same object ( that will refresh the layout too )
      const selectObjectId = lD.selectedObject.id;
      lD.selectObject({
        target: $('#' + selectObjectId),
        forceSelect: true,
        refreshEditor: false, // Don't refresh the editor here
      });

      // Reload the form helper connection
      formHelpers.setup(lD, lD.layout);

      // Check layout status
      lD.checkLayoutStatus();

      // Add thumbnail
      captureThumbnail && lD.uploadThumbnail();

      // Refresh designer
      refreshEditor && lD.refreshEditor(updateToolbar, true);

      // Call callback function
      callBack && callBack();
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        lD.showErrorMessage();
      }
    }

    lD.common.hideLoadingScreen();
  }).fail(function(jqXHR, textStatus, errorThrown) {
    lD.common.hideLoadingScreen();

    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);

    lD.showErrorMessage();
  },
  );
};

/**
 * Show welcome screen
 */
lD.welcomeScreen = function() {
  // Turn on read only mode
  lD.readOnlyMode = true;

  bootbox.dialog({
    message: layoutEditorTrans.welcomeModalMessage,
    className: 'welcome-screen-modal',
    size: 'large',
    closeButton: false,
    buttons: {
      checkout: {
        label: layoutEditorTrans.checkoutTitle,
        className: 'btn-success btn-bb-checkout',
        callback: function(res) {
          $(res.currentTarget)
            .append('&nbsp;<i class="fa fa-cog fa-spin"></i>');

          // Unselect objects ( select layout )
          lD.selectObject();

          lD.layout.checkout();

          // Prevent the modal to close
          // ( close only when checkout layout resolves )
          return false;
        },
      },
      view: {
        label: layoutEditorTrans.viewModeTitle,
        className: 'btn-white btn-bb-view',
        callback: function(res) {
          lD.enterReadOnlyMode();
        },
      },

    },
  }).attr('data-test', 'welcomeModal');
};

/**
 * Read Only Mode
 */
lD.enterReadOnlyMode = function() {
  // Add alert message to the layout designer
  lD.editorContainer.addClass('view-mode');

  // Turn on read only mode
  lD.readOnlyMode = true;
};

/**
 * Layout loading error message
 */
lD.showErrorMessage = function() {
  // Output error on screen
  const htmlError = messageTemplate({
    messageType: 'danger',
    messageTitle: errorMessagesTrans.error,
    messageDescription: errorMessagesTrans.loadingLayout,
  });

  lD.editorContainer.html(htmlError);
};

/**
 * Layout checkout screen
 */
lD.showCheckoutScreen = function() {
  bootbox.dialog({
    title: layoutEditorTrans.checkoutTitle + ' ' + lD.layout.name,
    message: layoutEditorTrans.checkoutMessage,
    size: 'large',
    buttons: {
      checkout: {
        label: layoutEditorTrans.checkoutTitle,
        className: 'btn-success btn-bb-checkout',
        callback: function(res) {
          $(res.currentTarget)
            .append('&nbsp;<i class="fa fa-cog fa-spin"></i>');

          // Unselect objects ( select layout )
          lD.selectObject();

          lD.layout.checkout();

          // Prevent the modal to close
          // ( close only when checkout layout resolves )
          return false;
        },
      },
    },
  }).attr('data-test', 'checkoutModal');
};

/**
 * Layout new button
 */
lD.addLayout = function() {
  lD.selectObject();
  lD.common.showLoadingScreen();
  $.ajax({
    type: urlsForApi.layout.add.type,
    url: urlsForApi.layout.add.url,
    cache: false,
    dataType: 'json',
    success: function(response, textStatus, error) {
      lD.common.hideLoadingScreen();

      if (response.success && response.id) {
        // eslint-disable-next-line new-cap
        XiboRedirect(urlsForApi.layout.designer.url
          .replace(':id', response.id));
      } else {
        if (response.login) {
          // eslint-disable-next-line new-cap
          LoginBox(response.message);
        } else {
          // eslint-disable-next-line new-cap
          SystemMessage(response.message, false);
        }
      }
    },
    error: function(xhr, textStatus, errorThrown) {
      lD.common.hideLoadingScreen();

      // eslint-disable-next-line new-cap
      SystemMessage(xhr.responseText, false);
    },
  });
};

/**
 * Layout publish screen
 */
lD.showPublishScreen = function() {
  // Deselect all objects before opening the form
  lD.selectObject();

  lD.loadFormFromAPI(
    'publishForm',
    lD.layout.parentLayoutId,
    `formHelpers.setupCheckboxInputFields(
      $("#layoutPublishForm"),
      "#publishNow",
      "",
      ".publish-date-control"
    );
    lD.uploadThumbnail($("#layoutPublishForm #publishPreview"));`,
    'lD.layout.publish();',
  );
};

/**
 * Layout publish screen
 */
lD.showDiscardScreen = function() {
  lD.loadFormFromAPI(
    'discardForm',
    lD.layout.parentLayoutId,
    '',
    'lD.layout.discard();',
  );
};

/**
 * Layout schedule screen
 */
lD.showScheduleScreen = function() {
  lD.loadFormFromAPI('schedule', lD.layout.campaignId);
};

/**
 * Layout delete screen
 */
lD.showDeleteScreen = function() {
  lD.loadFormFromAPI(
    'deleteForm',
    lD.layout.layoutId,
    '',
    'lD.layout.delete();',
    [editorsTrans.retire],
  );
};

/**
 * Layout save template screen
 */
lD.showSaveTemplateScreen = function() {
  lD.loadFormFromAPI(
    'saveTemplate',
    lD.layout.layoutId,
    'initJsTreeAjax("#container-folder-form-tree","templateAddForm",true,600);',
  );
};

/**
 * Load form from the API
 * @param {string} type - Form type
 * @param {string} id - Target id
 * @param {function} apiFormCallback - API callbak to be executed after form
 * @param {function} mainActionCallback - Callback to be executed after form
 * @param {array} buttonsToRemove - Buttons to be removed from the form
 */
lD.loadFormFromAPI = function(
  type,
  id = null,
  apiFormCallback = null,
  mainActionCallback = null,
  buttonsToRemove = [],
) {
  // Load form the API
  const linkToAPI = urlsForApi.layout[type];

  let requestPath = linkToAPI.url;

  // Replace ID
  if (id != null) {
    requestPath = requestPath.replace(':id', id);
  }

  // Create dialog
  const calculatedId = new Date().getTime();

  // Request and load element form
  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      // Create buttons
      const generatedButtons = {
        cancel: {
          label: translations.cancel,
          className: 'btn-white',
        },
      };

      // Get buttons from form
      for (const button in res.buttons) {
        if (res.buttons.hasOwnProperty(button)) {
          if (res.buttons[button] != 'XiboDialogClose()') {
            let buttonType = 'btn-white';
            let mainButtonAction = false;

            if (
              button === translations.save ||
              button === editorsTrans.publish ||
              button === editorsTrans.discard ||
              button === editorsTrans.yes
            ) {
              buttonType = 'btn-primary';
              mainButtonAction = true;
            }

            const url = res.buttons[button];

            // Only add button if it's not in the buttons to remove list
            if (buttonsToRemove.indexOf(button) == -1) {
              generatedButtons[button] = {
                label: button,
                className: buttonType + ' btn-bb-' + button,
                callback: function() {
                  // Call global function by the function name
                  if (mainActionCallback != null && mainButtonAction) {
                    eval(mainActionCallback);
                  } else {
                    eval(url);
                  }

                  return false;
                },
              };
            }
          }
        }
      }

      // Create dialog
      const dialog = bootbox.dialog({
        className: 'second-dialog',
        title: res.dialogTitle,
        message: res.html,
        size: 'large',
        buttons: generatedButtons,
      }).attr('id', calculatedId).attr('data-test', type + 'LayoutForm');

      dialog.data('extra', res.extra);

      // Form open callback
      if (
        res.callBack != undefined && typeof window[res.callBack] ===
        'function'
      ) {
        window[res.callBack](dialog);
      }

      // Call Xibo Init for this form
      // eslint-disable-next-line new-cap
      XiboInitialise('#' + dialog.attr('id'));

      if (apiFormCallback != null) {
        eval(apiFormCallback);
      }
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        toastr.error(errorMessagesTrans.formLoadFailed);

        // Just an error we dont know about
        if (res.message == undefined) {
          console.error(res);
        } else {
          console.error(res.message);
        }
      }
    }
  }).catch(function(jqXHR, textStatus, errorThrown) {
    console.error(jqXHR, textStatus, errorThrown);
    toastr.error(errorMessagesTrans.formLoadFailed);
  });
};

/**
 * Revert last action
 */
lD.undoLastAction = function() {
  lD.common.showLoadingScreen('undoLastAction');

  lD.historyManager.revertChange().then((res) => { // Success
    // Refresh designer according to local or API revert
    if (res.localRevert) {
      lD.refreshEditor(false, true);
    } else {
      lD.reloadData(lD.layout, true);
    }

    lD.common.hideLoadingScreen('undoLastAction');
  }).catch((error) => { // Fail/error
    lD.common.hideLoadingScreen('undoLastAction');

    // Show error returned or custom message to the user
    let errorMessage = '';

    if (typeof error == 'string') {
      errorMessage = error;
    } else {
      errorMessage = error.errorThrown;
    }

    toastr.error(
      errorMessagesTrans.revertFailed.replace('%error%', errorMessage),
    );
  });
};


/**
 * Delete selected object
 * @param {boolean} showConfirmModal - Show confirm modal
 */
lD.deleteSelectedObject = function(
  showConfirmModal = true,
) {
  // For now, we always delete the region
  if (lD.selectedObject.type === 'region') {
    lD.deleteObject(
      lD.selectedObject.type,
      lD.selectedObject[lD.selectedObject.type + 'Id'],
      null,
      showConfirmModal,
    );
  } else if (lD.selectedObject.type === 'widget') {
    // Delete widget's region
    const regionId =
      lD.getElementByTypeAndId('region', lD.selectedObject.regionId).regionId;
    lD.deleteObject(
      'region',
      regionId,
      null,
      showConfirmModal,
    );
  } else if (lD.selectedObject.type === 'element') {
    // Delete element
    lD.deleteObject(
      lD.selectedObject.type,
      lD.selectedObject[lD.selectedObject.type + 'Id'],
      'widget_' + lD.selectedObject.regionId + '_' + lD.selectedObject.widgetId,
      showConfirmModal,
    );
  } else if (lD.selectedObject.type === 'element-group') {
    // Delete element group
    lD.deleteObject(
      lD.selectedObject.type,
      lD.selectedObject.id,
      'widget_' + lD.selectedObject.regionId + '_' + lD.selectedObject.widgetId,
      showConfirmModal,
    );
  }
};

/**
 * Delete object
 * @param {string} objectType - Object type (widget, region)
 * @param {string} objectId - Object id
 * @param {*} objectAuxId - Auxiliary object id (f.e.region for a widget)
 * @param {boolean} showConfirmModal - Show confirm modal
 */
lD.deleteObject = function(
  objectType,
  objectId,
  objectAuxId = null,
  showConfirmModal = true,
) {
  /**
   * Delete element from layout
   * @param {*} objectType - Object type (widget, region, element)
   * @param {*} objectId - Object id
   * @param {*} options - Options
   */
  const deleteElement = function(
    objectType,
    objectId,
    options,
  ) {
    // For elements, we just delete from the widget
    if (objectType === 'element') {
      // Get parent widget
      const widget = lD.getElementByTypeAndId(
        'widget',
        objectAuxId,
        'canvas',
      );

      // Delete element from widget
      widget.removeElement(
        objectId,
        true,
      );
    } else if (objectType === 'element-group') {
      // For element groups, we delete all elements in the group
      // Get parent widget
      const widget = lD.getElementByTypeAndId(
        'widget',
        objectAuxId,
        'canvas',
      );

      // Delete element from widget
      widget.removeElementGroup(objectId);
    } else {
      lD.common.showLoadingScreen('deleteObject');

      lD.layout.deleteElement(
        objectType,
        objectId,
        options,
      ).then((res) => {
        // Behavior if successful
        lD.reloadData(lD.layout, true);

        lD.common.hideLoadingScreen('deleteObject');
      }).catch((error) => { // Fail/error
        lD.common.hideLoadingScreen('deleteObject');

        // Show error returned or custom message to the user
        let errorMessage = '';

        if (typeof error == 'string') {
          errorMessage = error;
        } else {
          errorMessage = error.errorThrown;
        }

        toastr.error(
          errorMessagesTrans.deleteFailed
            .replace('%error%', errorMessage),
        );
      });
    }
  };

  /**
   * Create modal before delete element
   * @param {*} objectType - Object type (widget, region, element)
   * @param {*} objectId - Object id
   * @param {*} hasMedia - Object has media
   * @param {*} showDeleteFromLibrary - Show delete from library option
   */
  const createDeleteModal = function(
    objectType,
    objectId,
    hasMedia = false,
    showDeleteFromLibrary = false,
  ) {
    bootbox.hideAll();

    const htmlContent = deleteElementModalContentTemplate({
      mainMessage: deleteMenuTrans.mainMessage.replace('%obj%', objectType),
      hasMedia: hasMedia,
      showDeleteFromLibrary: showDeleteFromLibrary,
      trans: deleteMenuTrans,
    });

    bootbox.dialog({
      title: editorsTrans.deleteTitle.replace('%obj%', objectType),
      message: htmlContent,
      size: 'large',
      buttons: {
        cancel: {
          label: editorsTrans.no,
          className: 'btn-white btn-bb-cancel',
        },
        confirm: {
          label: editorsTrans.yes,
          className: 'btn-danger btn-bb-confirm',
          callback: function() {
            // Empty options object
            let options = null;

            // If delete media is checked, pass that as a param for delete
            if ($(this).find('input#deleteMedia').is(':checked')) {
              options = {
                deleteMedia: 1,
              };
            }

            // Delete element from the layout
            deleteElement(
              objectType,
              objectId,
              options,
            );
          },
        },
      },
    }).attr('data-test', 'deleteObjectModal');
  };

  if (objectType === 'region') {
    if (showConfirmModal) {
      createDeleteModal(
        objectType,
        objectId,
      );
    } else {
      // Delete element from the layout
      deleteElement(
        objectType,
        objectId,
      );
    }
  } else if (objectType === 'widget') {
    const widgetToDelete =
      lD.getElementByTypeAndId(
        'widget',
        'widget_' + objectAuxId + '_' + objectId,
        'region_' + objectAuxId,
      );

    if (widgetToDelete.isRegionSpecific()) {
      if (showConfirmModal) {
        createDeleteModal(objectType, objectId);
      } else {
        // Delete element from the layout
        deleteElement(
          objectType,
          objectId,
        );
      }
    } else {
      lD.common.showLoadingScreen('checkMediaIsUsed');

      const linkToAPI = urlsForApi.media.isUsed;
      const requestPath =
        linkToAPI.url.replace(':id', widgetToDelete.mediaIds[0]);

      // Request with count as being 2, for the published layout and draft
      $.get(requestPath + '?count=1')
        .done(function(res) {
          if (res.success) {
            if (showConfirmModal) {
              createDeleteModal(objectType, objectId, true, !res.data.isUsed);
            } else {
              // Delete element from the layout
              deleteElement(
                objectType,
                objectId,
                {
                  deleteMedia: 1,
                },
              );
            }
          } else {
            if (res.login) {
              window.location.href = window.location.href;
              location.reload();
            } else {
              toastr.error(res.message);
            }
          }

          lD.common.hideLoadingScreen('checkMediaIsUsed');
        }).fail(function(jqXHR, textStatus, errorThrown) {
          lD.common.hideLoadingScreen('checkMediaIsUsed');

          // Output error to console
          console.error(jqXHR, textStatus, errorThrown);
        });
    }
  } else if (objectType === 'element' || objectType === 'element-group') {
    if (showConfirmModal) {
      createDeleteModal(objectType, objectId);
    } else {
      // Delete element from the layout
      deleteElement(
        objectType,
        objectId,
      );
    }
  }
};

/**
 * Add action to take after dropping a draggable item
 * @param {object} droppable - Target drop object
 * @param {object} draggable - Dragged object
 * @param {object=} dropPosition - Position of the drop
 */
lD.dropItemAdd = function(droppable, draggable, dropPosition) {
  const draggableType = $(draggable).data('type');
  const draggableSubType = $(draggable).data('subType');
  const draggableData = $(draggable).data();
  const droppableIsDrawer = ($(droppable).data('subType') === 'drawer');
  const droppableIsZone = ($(droppable).data('subType') === 'zone');
  const droppableIsPlaylist =($(droppable).data('subType') === 'playlist');
  const droppableIsWidget = $(droppable).hasClass('designer-widget');

  /**
   * Import from provider or add media from library
   * @param {*} playlistId - Playlist id
   * @param {*} draggable - Dragged object
   * @param {string} mediaId - Media id
   * @param {boolean} drawerWidget - Is a drawer widget
   * @return {Promise}
   */
  const importOrAddMedia = function(
    playlistId,
    draggable,
    mediaId,
    drawerWidget = false,
  ) {
    return new Promise((resolve, reject) => {
      if ($(draggable).hasClass('from-provider')) {
        lD.importFromProvider(
          [$(draggable).data('providerData')],
        ).then((res) => {
          // If res is empty, it means that the import failed
          if (res.length === 0) {
            reject(res);
          } else {
            lD.addMediaToPlaylist(playlistId, res, null, drawerWidget)
              .then((_res) => {
                resolve(_res);
              });
          }
        });
      } else {
        lD.addMediaToPlaylist(playlistId, mediaId, null, drawerWidget)
          .then((_res) => {
            resolve(_res);
          });
      }
    });
  };

  if (draggableType == 'media') {
    // TODO If image, we need to chose if we want to create
    // a canvas or frame region, for now we create a frame

    // Adding media
    const mediaId = $(draggable).data('mediaId');

    // Deselect cards and drop zones
    lD.toolbar.deselectCardsAndDropZones();

    // If droppable is a drawer
    if (droppableIsDrawer) {
      importOrAddMedia(
        lD.layout.drawer.playlists.playlistId,
        draggable,
        mediaId,
        true,
      );
    } else if (droppableIsZone || droppableIsPlaylist) {
      // Get region
      const region =
        lD.getElementByTypeAndId(
          'region',
          'region_' + $(droppable).data('regionId'),
        );

      importOrAddMedia(
        region.playlists.playlistId,
        draggable,
        mediaId,
        false,
      ).then((_res) => {
        // Open playlist editor if it's a playlist
        if (droppableIsPlaylist) {
          lD.openPlaylistEditor(region.playlists.playlistId, region);
        }
      });
    } else if (droppableIsPlaylist) {
      // Get playlist id
      const playlistId = $(droppable).data('playlistId');

      importOrAddMedia(
        playlistId,
        draggable,
        mediaId,
        false,
      ).then((res) => {
        // Open playlist editor
        lD.openPlaylistEditor(res.data.regionPlaylist.playlistId);
      });
    } else {
      // Get dimensions of the draggable
      const startWidth = $(draggable).data('startWidth');
      const startHeight = $(draggable).data('startHeight');

      // If both dimensions exist and are not 0
      // add them to options
      const dimensions = {};
      if (startWidth && startHeight) {
        dimensions.width = startWidth;
        dimensions.height = startHeight;
      }

      // Add to layout, but create a new region
      lD.addRegion(dropPosition, 'frame', dimensions).then((res) => {
        // Add media to new region
        importOrAddMedia(
          res.data.regionPlaylist.playlistId,
          draggable,
          mediaId,
        ).catch((_error) => {
          // Delete new region
          lD.layout.deleteElement('region', res.data.regionPlaylist.regionId);
        });
      });
    }
  } else if (draggableType == 'actions') {
    // Get target type
    const targetType = ($(droppable).hasClass('layout')) ?
      'screen' :
      $(droppable).data('type');

    // Get target id
    const targetId = $(droppable).data(targetType + 'Id');

    let actionType = draggableSubType;

    // If action type is nextWidget or nextLayout
    // change to next
    actionType =
      (['nextWidget', 'nextLayout'].includes(draggableSubType)) ?
        'next' :
        actionType;

    // If action type is previousWidget or previousLayout
    // change to previous
    actionType =
      (['previousWidget', 'previousLayout'].includes(draggableSubType)) ?
        'previous' :
        actionType;

    // Adding action
    lD.addAction({
      actionType: actionType,
      layoutId: lD.layout.layoutId,
      target: targetType,
      targetId: targetId,
    });
  } else if (
    draggableType == 'element' ||
    draggableType == 'element-group'
  ) {
    const isGroup = (draggableType == 'element-group');
    const self = this;

    // Get canvas
    this.layout.getCanvas().then((canvas) => {
      // Add elements to widget
      const addElementsToWidget = function(
        elements,
        widget,
      ) {
        // Loop through elements
        elements.forEach((element) => {
          // Create a unique id for the element
          element.elementId =
            'element_' + element.id + '_' +
            Math.floor(Math.random() * 1000000);

          // Add element to the widget
          widget.addElement(element, false);
        });

        // Save JSON with new element into the widget
        widget.saveElements().then((_res) => {
          const firstElement = elements[0];

          // If it's group, save it as temporary object
          if (isGroup) {
            lD.viewer.saveTemporaryObject(
              firstElement.groupId,
              'element-group',
              {
                type: 'element-group',
                parentType: 'widget',
                widgetId: widget.widgetId,
                regionId: widget.regionId.split('_')[1],
              },
            );
          } else {
            // Save the first element as a temporary object
            lD.viewer.saveTemporaryObject(
              firstElement.elementId,
              'element',
              {
                type: 'element',
                parentType: 'widget',
                widgetId: widget.widgetId,
                regionId: widget.regionId.split('_')[1],
              },
            );
          }

          // Reload data and select element when data reloads
          lD.reloadData(lD.layout, true, true);
        });
      };

      // Create element
      const createElement = function({
        id,
        type,
        left,
        top,
        width,
        height,
        layer,
        rotation,
        extendsTemplate,
        extendsOverride,
        extendsOverrideId,
        groupId,
        properties,
        groupProperties,
      } = {},
      ) {
        // Create element object
        const element =
        {
          id: id,
          type: type,
          left: left,
          top: top,
          width: width,
          height: height,
          properties: properties,
          layer: layer,
          rotation: rotation,
        };

        // Add group id if it belongs to a group
        if (groupId) {
          element.groupId = groupId;
          element.groupProperties = groupProperties;
        }

        // Check if the element is extending a template
        if (extendsTemplate) {
          element.extends = {
            templateId: extendsTemplate,
            override: extendsOverride,
            overrideId: extendsOverrideId,
          };
        }

        return element;
      };

      const createWidgetAndAddElements = function(
        elements,
      ) {
        // Check if we have a canvas widget with
        // subtype equal to the draggableSubType
        // If we do, add the element to that widget
        // If we don't, create a new widget
        const currentWidget = Object.values(canvas.widgets).find((w) => {
          return w.subType === draggableSubType;
        });

        // If we don't have a widget, create a new one
        // but don't reload data
        if (!currentWidget) {
          lD.addModuleToPlaylist(
            canvas.regionId,
            canvas.playlists.playlistId,
            draggableSubType,
            draggableData,
            null,
            false,
            false,
            false,
          ).then((res) => {
            // Create new temporary widget for the elements
            newWidget = new Widget(
              res.data.widgetId,
              res.data,
              canvas.regionId,
              self,
            );

            // Add element to the new widget
            addElementsToWidget(
              elements,
              newWidget,
            );
          });
        } else {
          // Add element to the current widget
          addElementsToWidget(
            elements,
            currentWidget,
          );
        }
      };

      // Elements to add
      const elements = [];

      // If group, get all elements by templates and overrides
      if (isGroup) {
        // Generate a random group id
        const groupId = 'group_' + Math.floor(Math.random() * 1000000);

        // Get template
        lD.templateManager.getTemplateById(
          draggableData.templateId,
          draggableData.dataType,
        ).then((template) => {
          // Check if we have elements in stencil
          if (template?.stencil?.elements) {
            // Loop through elements
            template.stencil.elements.forEach((element) => {
              // Create element
              const newElement = createElement({
                id: element.id,
                type: draggableData.dataType,
                left: dropPosition.left + element.left,
                top: dropPosition.top + element.top,
                width: element.width,
                height: element.height,
                layer: element.layer,
                rotation: element.rotation,
                properties: element.properties,
                groupId: groupId,
                groupProperties: {
                  width: draggableData.templateStartWidth,
                  height: draggableData.templateStartHeight,
                  top: dropPosition.top,
                  left: dropPosition.left,
                },
              });

              // Add element to elements array
              elements.push(newElement);
            });

            // Create widget and add elements
            createWidgetAndAddElements(elements);
          }
        });
      } else {
        // Create element
        const element = createElement({
          id: draggableData.templateId,
          type: draggableData.dataType,
          left: dropPosition.left,
          top: dropPosition.top,
          width: draggableData.templateStartWidth,
          height: draggableData.templateStartHeight,
          layer: 0,
          rotation: 0,
          extendsTemplate: draggableData.extendsTemplate,
          extendsOverride: draggableData.extendsOverride,
          extendsOverrideId: draggableData.extendsOverrideId,
        });

        // Add element to elements array
        elements.push(element);

        // Create widget and add elements
        createWidgetAndAddElements(elements);
      }
    });
  } else if (
    draggableSubType === 'playlist' &&
    (
      droppableIsWidget ||
      droppableIsZone
    )
  ) {
    // Convert region to playlist
    const regionId = (droppableIsWidget) ?
      $(droppable).data('widgetRegion') :
      $(droppable).attr('id');

    const region =
      lD.getElementByTypeAndId(
        'region',
        regionId,
      );

    region.subType = 'playlist';

    let requestPath = urlsForApi.region.saveForm.url;
    requestPath = requestPath.replace(
      ':id',
      region['regionId'],
    );

    $.ajax({
      url: requestPath,
      type: urlsForApi.region.saveForm.type,
      data: jQuery.param({
        type: region.subType,
        name: region.name,
        top: region.dimensions.top,
        left: region.dimensions.left,
        width: region.dimensions.width,
        height: region.dimensions.height,
        zIndex: region.zIndex,
      }),
      contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
    }).done(function(res) {
      if (!res.success) {
        // Login Form needed?
        if (res.login) {
          window.location.href = window.location.href;
          location.reload();
        } else {
          // Just an error we dont know about
          if (res.message == undefined) {
            console.error(res);
          } else {
            console.error(res.message);
          }
        }
      } else {
        // Reload Data
        lD.reloadData(lD.layout, true);
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
      // Output error to console
      console.error(jqXHR, textStatus, errorThrown);
    });
  } else if (draggableType === 'layout_template') {
    // Show loading screen
    lD.common.showLoadingScreen('addLayoutTemplate');

    // Call the replace function and reload on success.
    $.ajax({
      method: urlsForApi.layout.applyTemplate.type,
      url: urlsForApi.layout.applyTemplate.url
        .replace(':id', lD.layout.layoutId),
      cache: false,
      dataType: 'json',
      data: {
        templateId: draggableData?.templateId,
        source: draggableData?.source,
        download: draggableData?.download,
      },
      success: function(response) {
        // Hide loading screen
        lD.common.hideLoadingScreen('addLayoutTemplate');

        if (response.success && response.id) {
          // eslint-disable-next-line new-cap
          lD.reloadData(response.data, true);
        } else if (response.login) {
          // eslint-disable-next-line new-cap
          LoginBox();
        } else {
          // eslint-disable-next-line new-cap
          SystemMessage(response.message || errorMessagesTrans.unknown);
        }
      },
      error: function(xhr) {
        // Hide loading screen
        lD.common.hideLoadingScreen('addLayoutTemplate');

        console.error(xhr);
      },
    });
  } else {
    // Adding a module
    // Check if the module has data type, if not
    // create a frame region
    // or playlist
    const regionType = (draggableSubType === 'playlist') ?
      'playlist' : 'frame';

    // Deselect cards and drop zones
    lD.toolbar.deselectCardsAndDropZones();

    // If droppable is a drawer
    if (droppableIsDrawer) {
      lD.addModuleToPlaylist(
        lD.layout.drawer.regionId,
        lD.layout.drawer.playlists.playlistId,
        draggableSubType,
        draggableData,
        null,
        true,
      );
    } else if (droppableIsZone || droppableIsPlaylist) {
      // Get zone region
      const region =
      lD.getElementByTypeAndId(
        'region',
        'region_' + $(droppable).data('regionId'),
      );

      // Add module to zone
      lD.addModuleToPlaylist(
        region.regionId,
        region.playlists.playlistId,
        draggableSubType,
        draggableData,
        null,
        false,
        true,
      ).then((_res) => {
        // Open playlist editor if it's a playlist
        if (droppableIsPlaylist) {
          lD.openPlaylistEditor(region.playlists.playlistId, region);
        }
      });
    } else {
      // Get dimensions of the draggable
      const startWidth = $(draggable).data('startWidth');
      const startHeight = $(draggable).data('startHeight');

      // If both dimensions exist and are not 0
      // add them to options
      const dimensions = {};
      if (startWidth && startHeight) {
        dimensions.width = startWidth;
        dimensions.height = startHeight;
      }
      // Add module to layout, but create a region first
      lD.addRegion(dropPosition, regionType, dimensions).then((res) => {
        // Add module to new region if it's not a playlist
        if (regionType !== 'playlist') {
          lD.addModuleToPlaylist(
            res.data.regionId,
            res.data.regionPlaylist.playlistId,
            draggableSubType,
            draggableData,
          );
        } else {
          // Reload data ( and viewer )
          lD.reloadData(lD.layout, true);
        }
      });
    }
  }
};

/**
 * Get the class name for the upload dialog, used by form-helpers.
 * @return {null}
 */
lD.getUploadDialogClassName = function() {
  return null;
};

/**
 * Add module to playlist
 * @param {number} regionId
 * @param {number} playlistId
 * @param {string} moduleType
 * @param {object} moduleData
 * @param {number=} addToPosition
 * @param {boolean} drawerWidget If the widget is in the drawer
 * @param {boolean} zoneWidget If the widget is in a zone
 * @param {boolean} reloadData If the layout should be reloaded
 * @return {Promise} Promise
 */
lD.addModuleToPlaylist = function(
  regionId,
  playlistId,
  moduleType,
  moduleData,
  addToPosition = null,
  drawerWidget = false,
  zoneWidget = false,
  reloadData = true,
) {
  if (moduleData.regionSpecific == 0) { // Upload form if not region specific
    const validExt = moduleData.validExt.replace(/,/g, '|');
    let numUploads = 0;

    // Close the current dialog
    bootbox.hideAll();

    // On hide callback
    const onHide = function() {
      // If there are no uploads, and it's not a zone, delete the region
      if (numUploads === 0 && !zoneWidget) {
        lD.layout.deleteElement(
          'region',
          regionId,
        ).then(() => {
          // Reload data ( and viewer )
          (reloadData) && lD.reloadData(lD.layout, true);
        });
      } else {
        // Reload data ( and viewer )
        (reloadData) && lD.reloadData(lD.layout, true);
      }
    };

    openUploadForm({
      url: libraryAddUrl,
      title: uploadTrans.uploadMessage,
      animateDialog: false,
      initialisedBy: 'layout-designer-upload',
      buttons: {
        viewLibrary: {
          label: uploadTrans.viewLibrary,
          className: 'btn-white btn-bb-viewlibrary',
          callback: function() {
            lD.toolbar.openNewTabAndSearch(moduleType);
          },
        },
        main: {
          label: translations.done,
          className: 'btn-primary btn-bb-main',
        },
      },
      onHideCallback: onHide,
      templateOptions: {
        trans: uploadTrans,
        upload: {
          maxSize: moduleData.maxSize,
          maxSizeMessage: moduleData.maxSizeMessage,
          validExtensionsMessage: translations.validExtensions
            .replace('%s', moduleData.validExt),
          validExt: validExt,
        },
        playlistId: playlistId,
        displayOrder: addToPosition,
        currentWorkingFolderId: lD.folderId,
        showWidgetDates: true,
        folderSelector: true,
      },
      uploadDoneEvent: function(data) {
        // If the upload is successful, increase the number of uploads
        numUploads += 1;

        // Get added widget id
        const widgetId = data.response().result.files[0].widgetId;

        // The new selected object as the id based
        // on the previous selected region
        if (!drawerWidget) {
          lD.viewer.saveTemporaryObject(
            'widget_' + regionId + '_' + widgetId,
            'widget',
            {
              type: 'widget',
              parentType: 'region',
              widgetRegion: 'region_' + regionId,
            },
          );
        }
      },
    }).attr('data-test', 'uploadFormModal');
  } else { // Add widget to a region
    lD.common.showLoadingScreen('addModuleToPlaylist');

    const linkToAPI = urlsForApi.playlist.addWidget;

    let requestPath = linkToAPI.url;

    // Replace type
    requestPath = requestPath.replace(':type', moduleType);

    // Replace playlist id
    requestPath = requestPath.replace(':id', playlistId);

    // Set position to add if selected
    let addOptions = null;
    if (addToPosition != null) {
      addOptions = {
        displayOrder: addToPosition,
      };
    }

    // Set template if if exists
    // for elements, we use the elements template
    if (
      moduleData.type === 'element' ||
      moduleData.type === 'element-group'
    ) {
      addOptions = addOptions || {};
      addOptions.templateId = 'elements';
    } else if (moduleData.templateId) {
      // For other modules, we use the template id
      addOptions = addOptions || {};
      addOptions.templateId = moduleData.templateId;
    }

    return lD.historyManager.addChange(
      'addWidget',
      'playlist', // targetType
      playlistId, // targetId
      null, // oldValues
      addOptions, // newValues
      {
        updateTargetId: true,
        updateTargetType: 'widget',
        customRequestPath: {
          url: requestPath,
          type: linkToAPI.type,
        },
      },
    ).then((res) => { // Success
      // Check if we added a element
      if (moduleData.type === 'element') {
        // Hide loading screen
        lD.common.hideLoadingScreen('addModuleToPlaylist');

        // Return the promise with the data
        return res;
      }

      // Save the new widget as temporary
      lD.viewer.saveTemporaryObject(
        'widget_' + regionId + '_' + res.data.widgetId,
        'widget',
        {
          type: 'widget',
          parentType: 'region',
          widgetRegion: 'region_' + regionId,
        },
      );

      if (!drawerWidget) {
        // Reload data ( and viewer )
        (reloadData) && lD.reloadData(lD.layout, true);
      } else {
        const newWidgetId = res.data.widgetId;
        // Reload data ( and viewer )
        (reloadData) && lD.reloadData(
          lD.layout,
          false,
          false,
          () => {
            const $actionForm =
              lD.propertiesPanel.DOMObject.find('.action-element-form');

            lD.populateDropdownWithLayoutElements(
              $actionForm.find('[name=widgetId]'),
              {
                value: newWidgetId,
                filters: ['drawerWidgets'],
              },
              $actionForm.data(),
            );
          },
        );
      }

      lD.common.hideLoadingScreen('addModuleToPlaylist');

      // Return the promise with the data
      return res;
    }).catch((error) => { // Fail/error
      lD.common.hideLoadingScreen('addModuleToPlaylist');

      // Show error returned or custom message to the user
      let errorMessage = '';

      if (typeof error == 'string') {
        errorMessage = error;
      } else {
        errorMessage = error.errorThrown;
      }

      // Remove added change from the history manager
      lD.historyManager.removeLastChange();

      // Show toast message
      toastr.error(
        errorMessagesTrans.addModuleFailed.replace('%error%', errorMessage),
      );
    });
  }
};

/**
 * Add media from library to a playlist
 * @param {number} playlistId
 * @param {Array.<number>} media
 * @param {number=} addToPosition
 * @param {boolean} drawerWidget If the widget is in the drawer
 * @return {Promise} Promise
 */
lD.addMediaToPlaylist = function(
  playlistId,
  media,
  addToPosition = null,
  drawerWidget = false,
) {
  // Get media Id
  let mediaToAdd = {};

  if (Array.isArray(media)) {
    if (media.length == 0) {
      return;
    }
    mediaToAdd = {
      media: media,
    };
  } else {
    mediaToAdd = {
      media: [
        media,
      ],
    };
  }

  // Check if library duration options exists and add it to the query
  if (lD.useLibraryDuration != undefined) {
    mediaToAdd.useDuration = (lD.useLibraryDuration == '1');
  }

  lD.common.showLoadingScreen('addMediaToPlaylist');

  // Set position to add if selected
  if (addToPosition != null) {
    mediaToAdd.displayOrder = addToPosition;
  }

  // Create change to be uploaded
  return lD.historyManager.addChange(
    'addMedia',
    'playlist', // targetType
    playlistId, // targetId
    null, // oldValues
    mediaToAdd, // newValues
    {
      updateTargetId: true,
      updateTargetType: 'widget',
    },
  ).then((res) => { // Success
    if (!drawerWidget) {
      // Save the new widget as temporary
      lD.viewer.saveTemporaryObject(
        'widget_' +
          res.data.regionId + '_' +
          res.data.newWidgets[0].widgetId,
        'widget',
        {
          type: 'widget',
          parentType: 'region',
          widgetRegion: 'region_' + res.data.regionId,
          isInDrawer: drawerWidget,
        },
      );

      // Reload data ( and viewer )
      lD.reloadData(lD.layout, true);
    } else {
      const newWidgetId = res.data.newWidgets[0].widgetId;
      // Reload data ( and viewer )
      lD.reloadData(
        lD.layout,
        false,
        false,
        () => {
          const $actionForm =
            lD.propertiesPanel.DOMObject.find('.action-element-form');

          lD.populateDropdownWithLayoutElements(
            $actionForm.find('[name=widgetId]'),
            {
              value: newWidgetId,
              filters: ['drawerWidgets'],
            },
            $actionForm.data(),
          );
        },
      );
    }

    lD.common.hideLoadingScreen('addMediaToPlaylist');
  }).catch((error) => { // Fail/error
    lD.common.hideLoadingScreen('addMediaToPlaylist');

    // Show error returned or custom message to the user
    let errorMessage = '';

    if (typeof error == 'string') {
      errorMessage = error;
    } else {
      errorMessage = error.errorThrown;
    }

    // Show toast message
    toastr.error(
      errorMessagesTrans.addMediaFailed.replace('%error%', errorMessage),
    );
  });
};

/**
 * Clear Temporary Data ( Cleaning cached variables )
 */
lD.clearTemporaryData = function() {
  // Fix for remaining ckeditor elements or colorpickers
  $('.colorpicker').remove();
  $('.cke').remove();

  // Fix for remaining ckeditor elements or colorpickers
  destroyColorPicker(lD.editorContainer.find('.colorpicker-element'));

  // Clean and hide inline editor controls
  lD.editorContainer.find('#inline-editor-templates').html('');

  // Hide open tooltips
  lD.editorContainer.find('.tooltip').remove();

  // Destroy select2 opened dropdowns
  lD.propertiesPanel.DOMObject.find('select[data-select2-id]')
    .select2('destroy');

  // Remove text callback editor structure variables
  formHelpers.destroyCKEditor();

  // Clear action highlights on the viewer
  // if we don't have an action form open
  if (lD.propertiesPanel.DOMObject.find('.action-element-form').length == 0) {
    lD.viewer.clearActionHighlights();
  }
};

/**
 * Get element from the main object ( Layout )
 * @param {string} type - Type of the element
 * @param {number} id - Id of the element
 * @param {number} auxId - Auxiliary id of the element
 * @return {Object} element
 */
lD.getElementByTypeAndId = function(type, id, auxId) {
  let element = {};

  if (type === 'layout') {
    element = lD.layout;
  } else if (type === 'region') {
    element = lD.layout.regions[id];
  } else if (type === 'drawer') {
    element = lD.layout.drawer;
  } else if (type === 'canvas') {
    element = lD.layout.canvas;
  } else if (type === 'element') {
    element = lD.layout.canvas.widgets[auxId].elements[id];
  } else if (type === 'element-group') {
    element = lD.layout.canvas.widgets[auxId].elementGroups[id];
  } else if (type === 'widget') {
    if (
      lD.layout.drawer.id != undefined &&
      (lD.layout.drawer.id == auxId || auxId == 'drawer')
    ) {
      element = lD.layout.drawer.widgets[id];
    } else if (
      lD.layout.canvas.id != undefined &&
      (lD.layout.canvas.id == auxId || auxId == 'canvas')
    ) {
      element = lD.layout.canvas.widgets[id];
    } else {
      element = lD.layout.regions[auxId].widgets[id];
    }
  }

  return element;
};

/**
 * Call layout status
 */
lD.checkLayoutStatus = function() {
  const linkToAPI = urlsForApi.layout.status;
  let requestPath = linkToAPI.url;

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', lD.layout.layoutId);

  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    if (!res.success) {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        // Just an error we dont know about
        if (res.message == undefined) {
          console.error(res);
        } else {
          console.error(res.message);
        }
      }
    } else {
      // Update layout status
      lD.layout.updateStatus(
        res.extra.status,
        res.html,
        res.extra.statusMessage,
      );

      if (
        Array.isArray(res.extra.isLocked) &&
        res.extra.isLocked.length == 0
      ) {
        // isLocked is not defined
        lD.toggleLockedMode(false);

        // Remove locked class to main container
        lD.editorContainer.removeClass('locked');
      } else {
        // Add locked class to main container
        lD.editorContainer.addClass('locked');

        // Toggle locked mode according to the user flag
        lD.toggleLockedMode(
          res.extra.isLocked.lockedUser,
          moment(
            res.extra.isLocked.expires,
            systemDateFormat,
          ).format(jsDateFormat),
        );
      }
    }
  }).fail(function(jqXHR, textStatus, errorThrown) {
    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);
  });
};

/**
 * New open playlist editor
 * @param {string} playlistId - Id of the playlist
 * @param {object} region - Region related to the playlist
 */
lD.openPlaylistEditor = function(playlistId, region) {
  // Deselect previous selected object
  lD.selectObject();

  // Get main panel
  const $mainPanel = lD.editorContainer.find('.main-panel');

  // Create or load container
  const $playlistEditorPanel = $('.playlist-panel');

  // Add inline class and id to the container
  $playlistEditorPanel
    .attr('id', 'editor-container')
    .addClass('playlist-editor-inline-container');

  // Attach region id to editor data
  $playlistEditorPanel.data('regionObj', region);

  // Populate container
  $playlistEditorPanel.html(
    '<div id="playlist-editor" playlist-id="' +
    playlistId +
    '"></div>',
  );

  // Hide layout designer editor
  $mainPanel.addClass('hidden');

  // Hide layout topbar
  lD.editorContainer.find('.editor-top-bar').addClass('hidden');

  // Switch back button to edit button
  lD.editorContainer.find('.back-button #backBtn').addClass('hidden');
  lD.editorContainer.find('.back-button #backToLayoutEditorBtn')
    .removeClass('hidden');

  // Deselect viewer element
  lD.viewer.selectElement();

  // Show playlist editor
  $playlistEditorPanel.removeClass('hidden');

  // Load playlist editor
  pE.loadEditor(true);

  // On close, remove container and refresh designer
  lD.editorContainer.find('.back-button #backToLayoutEditorBtn')
    .off().on('click', function() {
      // Close playlist editor
      pE.close();

      // Remove region id from data
      $playlistEditorPanel.removeData('regionObj');

      // Hide layout designer editor
      $mainPanel.removeClass('hidden');

      // Show playlist editor
      $playlistEditorPanel.addClass('hidden');

      // Show layout topbar
      lD.editorContainer.find('.editor-top-bar').removeClass('hidden');

      // Switch back button to edit button
      lD.editorContainer.find('.back-button #backBtn').removeClass('hidden');
      lD.editorContainer.find('.back-button #backToLayoutEditorBtn')
        .addClass('hidden');

      // Reopen properties panel
      lD.editorContainer.find('.properties-panel-container').addClass('opened');

      // Reload data
      lD.reloadData(lD.layout, true);
    });
};

/**
 * Open object context menu
 * @param {object} obj - Target object
 * @param {object=} position - Page menu position
 */
lD.openContextMenu = function(obj, position = {x: 0, y: 0}) {
  const objId = $(obj).attr('id');
  const objType = $(obj).data('type');
  let objAuxId = null;

  // Don't open context menu in read only mode
  if (lD.readOnlyMode) {
    return;
  }

  if (objType == 'widget') {
    objAuxId = $(obj).data('widgetRegion');
  } else if (objType == 'element' || objType == 'element-group') {
    objAuxId =
      'widget_' + $(obj).data('regionId') + '_' + $(obj).data('widgetId');
  }

  // Get object
  const layoutObject = lD.getElementByTypeAndId(objType, objId, objAuxId);

  // Create menu and append to the designer div
  // ( using the object extended with translations )
  lD.editorContainer.append(
    contextMenuTemplate(Object.assign(layoutObject, {trans: contextMenuTrans})),
  );

  // Set menu position ( and fix page limits )
  const contextMenuWidth =
    lD.editorContainer.find('.context-menu').outerWidth();
  const contextMenuHeight =
    lD.editorContainer.find('.context-menu').outerHeight();

  const positionLeft =
    ((position.x + contextMenuWidth) > $(window).width()) ?
      (position.x - contextMenuWidth) :
      position.x;
  const positionTop =
    ((position.y + contextMenuHeight) > $(window).height()) ?
      (position.y - contextMenuHeight) :
      position.y;

  lD.editorContainer.find('.context-menu')
    .offset({top: positionTop, left: positionLeft});

  // Click overlay to close menu
  lD.editorContainer.find('.context-menu-overlay').click((ev) => {
    if ($(ev.target).hasClass('context-menu-overlay')) {
      lD.editorContainer.find('.context-menu-overlay').remove();

      // Unmark selected object as context menu is open
      $(obj).removeClass('contextMenuOpen');
    }
  });

  // Handle buttons
  lD.editorContainer.find('.context-menu .context-menu-btn').click((ev) => {
    const target = $(ev.currentTarget);

    if (target.data('action') == 'Delete') {
      let auxId = null;

      // Delete element
      if (objType == 'element' || objType == 'element-group') {
        auxId = objAuxId;
      } else {
        if (objAuxId != null) {
          auxId = objAuxId.split('region_')[1];
        }
      }

      // If layoutObject[objType + 'Id'] is null, use objId
      const newObjId = layoutObject[objType + 'Id'] || objId;

      lD.deleteObject(
        objType,
        newObjId,
        auxId,
        // don't show confirm modal for elements and groups
        (objType != 'element' && objType != 'element-group'),
      );
    } else if (target.data('action') == 'Move') {
      // Move widget in the timeline
      lD.layout.moveWidgetInRegion(
        layoutObject.regionId, layoutObject.id, target.data('actionType'),
      );
    } else if (target.data('action') == 'editPlaylist') {
      // Open playlist editor
      lD.openPlaylistEditor(layoutObject.playlists.playlistId, layoutObject);
    } else {
      layoutObject.editPropertyForm(
        target.data('property'), target.data('propertyType'),
      );
    }

    // Remove context menu
    lD.editorContainer.find('.context-menu-overlay').remove();

    // Unmark selected object as context menu is open
    $(obj).removeClass('contextMenuOpen');
  });
};

/**
 * Load user preference
 * @param {string} prefToLoad - Key of the preference
 * @param {string} defaultValue - Default value of the preference
 */
lD.loadAndSavePref = function(prefToLoad, defaultValue = 0) {
  // Load using the API
  const linkToAPI = urlsForApi.user.getPref;

  // Request elements based on filters
  $.ajax({
    url: linkToAPI.url + '?preference=' + prefToLoad,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      if (res.data.option == prefToLoad) {
        lD[prefToLoad] = res.data.value;
      } else {
        lD[prefToLoad] = defaultValue;
      }
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload(false);
      } else {
        // Just an error we dont know about
        if (res.message == undefined) {
          console.warn(res);
        } else {
          console.warn(res.message);
        }
      }
    }
  }).catch(function(jqXHR, textStatus, errorThrown) {
    console.error(jqXHR, textStatus, errorThrown);
    toastr.error(errorMessagesTrans.userLoadPreferencesFailed);
  });
};

/**
 * Reset tour
 */
lD.resetTour = function() {
  if (localStorage.tour_playing == undefined) {
    if (cmsTours.layoutEditorTour.ended()) {
      cmsTours.layoutEditorTour.restart();
    } else {
      cmsTours.layoutEditorTour.start();
    }
  }
  toastr.info(editorsTrans.resetTourNotification);
};

/**
 * Locked mode
 * @param {boolean} enable - True to lock, false to unlock
 * @param {string} expiryDate - Expiration date
 */
lD.toggleLockedMode = function(enable = true, expiryDate = '') {
  if (enable && !lD.readOnlyMode) {
    // Enable overlay
    let $customOverlay = lD.editorContainer.find('#lockedOverlay');
    let $lockedMessage = $customOverlay.find('#lockedLayoutMessage');

    const lockedMainMessage =
      layoutEditorTrans.lockedModeMessage.replace('[expiryDate]', expiryDate);

    if ($customOverlay.length == 0) {
      $customOverlay = $('.custom-overlay').clone();
      $customOverlay.attr('id', 'lockedOverlay').addClass('locked').show();
      $customOverlay.appendTo(lD.editorContainer);

      // Create the read only alert message
      $lockedMessage =
        $(`<div id="lockedLayoutMessage" 
          class="alert alert-warning text-center"
          role="alert"></div>`);

      // Prepend the element to the custom overlay
      $customOverlay.after($lockedMessage);
    }

    // Update locked overlay message content
    $lockedMessage.html('<strong>' +
      layoutEditorTrans.lockedModeTitle +
      '</strong>&nbsp;' +
      lockedMainMessage);

    // Add locked class to main container
    lD.editorContainer.addClass('locked-for-user');
  } else {
    // Remove overlay
    lD.editorContainer.find('#lockedOverlay').remove();

    // Remove message
    lD.editorContainer.find('#lockedLayoutMessage').remove();

    // Remove locked class from main container
    lD.editorContainer.removeClass('locked-for-user');
  }
};

/**
 * Layout unlock screen
 */
lD.showUnlockScreen = function() {
  bootbox.dialog({
    title: layoutEditorTrans.unlockTitle,
    message: layoutEditorTrans.unlockMessage,
    size: 'large',
    buttons: {
      unlock: {
        label: layoutEditorTrans.unlockTitle,
        className: 'btn-info btn-bb-unlock',
        callback: function(res) {
          $(res.currentTarget).append(
            '&nbsp;<i class="fa fa-cog fa-spin"></i>',
          );

          lD.unlockLayout();

          // Prevent the modal to close
          // ( close only when checkout layout resolves )
          return false;
        },
      },
    },
  }).attr({
    'data-test': 'unlockLayoutModal',
    id: 'unlockLayoutModal',
  });
};

/**
 * Unlock layout
 */
lD.unlockLayout = function() {
  const linkToAPI = urlsForApi.layout.unlock;
  let requestPath = linkToAPI.url;

  lD.common.showLoadingScreen();

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', lD.layout.layoutId);

  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      bootbox.hideAll();

      // Redirect to the layout grid
      window.location.href = urlsForApi.layout.list.url;
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        toastr.error(res.message);
      }

      lD.common.hideLoadingScreen();
    }
  }).fail(function(jqXHR, textStatus, errorThrown) {
    lD.common.hideLoadingScreen();

    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);
  });
};

/**
 * Check history and return last step description
 * @return {object} - Last step description
 */
lD.checkHistory = function() {
  // Check if there are some changes
  const undoActive = lD.historyManager.changeHistory.length > 0;
  let undoActiveTitle = '';

  // Get last action text for popup
  if (undoActive) {
    const lastAction =
      lD.historyManager
        .changeHistory[lD.historyManager.changeHistory.length - 1];

    if (
      typeof historyManagerTrans != 'undefined' &&
      historyManagerTrans.revert[lastAction.type] != undefined
    ) {
      undoActiveTitle =
        historyManagerTrans.revert[lastAction.type]
          .replace('%target%', lastAction.target.type);
    } else {
      undoActiveTitle =
        '[' + lastAction.target.type + '] ' + lastAction.type;
    }
  }

  return {
    undoActive: undoActive,
    undoActiveTitle: undoActiveTitle,
  };
};

/**
 * Toggle panel and refresh view containers
 * @param {object} $panel
 * @param {boolean} forceToggle - Toggle state
 */
lD.togglePanel = function($panel, forceToggle) {
  $panel.toggleClass('opened', forceToggle);

  lD.viewer.refresh();
};

/**
 * Toggle panel and refresh view containers
 * @param {Array.<number, object>} items - list of items (id or a provider obj)
 * @return {Promise}
 */
lD.importFromProvider = function(items) {
  const requestItems = [];
  let itemsResult = items;

  itemsResult.forEach((element) => {
    if (isNaN(element)) {
      requestItems.push(element);
    }
  });

  const linkToAPI = urlsForApi.library.connectorImport;
  const requestPath = linkToAPI.url;

  // Run ajax request and save promise
  return new Promise(function(resolve, reject) {
    // If none of the items are from a provider, return the original array
    if (requestItems.length == 0) {
      resolve(itemsResult);
    }

    lD.common.showLoadingScreen();

    $.ajax({
      url: requestPath,
      type: linkToAPI.type,
      dataType: 'json',
      data: {
        folderId: lD.layout.folderId,
        items: requestItems,
      },
    }).done(function(res) {
      if (res.success) {
        lD.common.hideLoadingScreen();

        res.data.forEach((newElement) => {
          let addFlag = true;
          if (newElement.isError) {
            addFlag = false;
            toastr.error(newElement.error, newElement.item.id);
          }

          itemsResult.forEach((oldElement, key) => {
            if (isNaN(oldElement) && newElement.item.id == oldElement.id) {
              itemsResult[key] = (addFlag) ? newElement.media.mediaId : null;
            }
          });
        });

        // Filter null results
        itemsResult = itemsResult.filter((el) => el);

        resolve(itemsResult);
      } else {
        lD.common.hideLoadingScreen();

        // Login Form needed?
        if (res.login) {
          window.location.href = window.location.href;
          location.reload();
        } else {
          // Just an error we dont know about
          if (res.message == undefined) {
            reject(res);
          } else {
            reject(res.message);
          }
        }
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
      lD.common.hideLoadingScreen();

      // Reject promise and return an object with all values
      reject({jqXHR, textStatus, errorThrown});
    });
  }).catch(function() {
    toastr.error(errorMessagesTrans.importingMediaFailed);
  });
};

/**
 * Take and upload a thumbnail
 * @param {object} targetToAttach DOM object to attach the thumbnail to
 */
lD.uploadThumbnail = function(targetToAttach) {
  if ($(targetToAttach).length > 0) {
    $(targetToAttach).append(
      $(`<div class="thumb-preview" 
        style="padding: 2rem 0; font-weight: bold;">`)
        .html('Loading Preview...'),
    );
    $(targetToAttach).removeClass('d-none');
  }
  const linkToAPI = urlsForApi.layout.addThumbnail;
  const requestPath = linkToAPI.url.replace(':id', lD.layout.layoutId);
  $.ajax({
    url: requestPath,
    type: 'POST',
    success: function() {
      // Attach to target
      if ($(targetToAttach).length > 0) {
        $(targetToAttach).find('.thumb-preview')
          .replaceWith($('<img style="max-width: 150px; max-height: 100%;">')
            .attr('src', requestPath));
      }
    },
  });
};

/**
 * Add a new region to the layout
 * @param {object} positionToAdd - Position to add the region to
 * @param {object} regionType - Region type (frame, playlist or canvas)
 * @param {object =} dimensions - Dimensions of the region
 * @return {Promise} - Promise object
 */
lD.addRegion = function(positionToAdd, regionType, dimensions) {
  lD.common.showLoadingScreen();

  if (lD.selectedObject.type == 'region') {
    lD.propertiesPanel.saveRegion();
    lD.selectObject();
  }

  // If region type is not defined, use the default (frame)
  if (regionType == undefined) {
    regionType = 'frame';
  }

  return lD.layout.addElement(
    'region',
    {
      positionToAdd: positionToAdd,
      elementSubtype: regionType,
      dimensions: dimensions,
    },
  ).catch((error) => {
    // Show error returned or custom message to the user
    let errorMessage = '';

    if (typeof error == 'string') {
      errorMessage = error;
    } else {
      errorMessage = error.errorThrown;
    }

    toastr.error(
      errorMessagesTrans.createRegionFailed.replace('%error%', errorMessage),
    );
  }).finally(() => {
    lD.common.hideLoadingScreen();
  });
};

/**
 * Handle messages coming other windows (iframe)
 * @param {object} event
 */
lD.handleMessage = function(event) {
  const messageFromSender = event.data;
  if (messageFromSender == 'viewerStoppedPlaying') {
    // Refresh designer
    lD.refreshEditor(false, true);

    // Show tooltip on play button
    lD.bottombar.showPlayMessage();
  }
};

/**
 * Load user preferences
 */
lD.loadPrefs = function() {
  const self = this;
  // Load using the API
  const linkToAPI = urlsForApi.user.getPref;

  // Request elements based on filters
  $.ajax({
    url: linkToAPI.url + '?preference=editor',
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      const loadedData = JSON.parse(res.data.value);
      if (loadedData.snapOptions) {
        self.viewer.moveableOptions = loadedData.snapOptions;

        // Update moveable options
        self.viewer.updateMoveableOptions();

        // Update moveable UI
        self.viewer.updateMoveableUI();
      }
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        // Just an error we dont know about
        if (res.message == undefined) {
          console.error(res);
        } else {
          console.error(res.message);
        }
      }
    }
  }).catch(function(jqXHR, textStatus, errorThrown) {
    console.error(jqXHR, textStatus, errorThrown);
    toastr.error(errorMessagesTrans.userLoadPreferencesFailed);
  });
};

/**
 * Save user preferences
 * @param {bool=} [clearPrefs = false] - Force reseting user prefs
 */
lD.savePrefs = function(clearPrefs = false) {
  // Clear values to defaults
  if (clearPrefs) {
    console.log('Clearing user preferences');
  }

  // Data to be saved
  const dataToSave = {
    preference: [
      {
        option: 'editor',
        value: JSON.stringify({
          snapOptions: this.viewer.moveableOptions,
        }),
      },
    ],
  };

  // Save using the API
  const linkToAPI = urlsForApi.user.savePref;

  // Request elements based on filters
  $.ajax({
    url: linkToAPI.url,
    type: linkToAPI.type,
    data: dataToSave,
  }).done(function(res) {
    if (!res.success) {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        toastr.error(errorMessagesTrans.userSavePreferencesFailed);

        // Just an error we dont know about
        if (res.message == undefined) {
          console.error(res);
        } else {
          console.error(res.message);
        }
      }
    }
  }).catch(function(jqXHR, textStatus, errorThrown) {
    console.error(jqXHR, textStatus, errorThrown);
    toastr.error(errorMessagesTrans.userSavePreferencesFailed);
  });
};

/**
 * Create the drawer in the layout object
 * @param {Object} data - the drawer data
 * @return {Promise} - Promise object
 */
lD.initDrawer = function(data) {
  const readOnlyModeOn = (this?.readOnlyMode === true);

  // Check if the drawer is already created/added
  if (!$.isEmptyObject(lD.layout.drawer)) {
    return Promise.resolve('Drawer already created');
  }

  // If layout is published and the drawer doesn't exist, cancel request
  if (readOnlyModeOn) {
    return Promise.reject('Layout is published');
  }

  if (data == undefined) {
    // Create a new drawer
    const linkToAPI = urlsForApi.layout.addDrawer;
    let requestPath = linkToAPI.url;

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.layoutId);

    $.ajax({
      url: requestPath,
      type: linkToAPI.type,
      data: {
        type: 'playlist',
      },
    }).done(function(res) {
      if (res.success) {
        // Create drawer in the layout object
        lD.layout.createDrawer(res.data);
      } else {
        // Login Form needed?
        if (res.login) {
          window.location.href = window.location.href;
          location.reload(false);
        } else {
          toastr.error(res.message);
        }

        lD.common.hideLoadingScreen();
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
      lD.common.hideLoadingScreen();

      // Output error to console
      console.error(jqXHR, textStatus, errorThrown);
    });
  }
};

/**
 * Add action
 * @param {object} options - Options for the action
 * @return {boolean} false if unsuccessful
  */
lD.addAction = function(options) {
  const self = this;

  $.ajax({
    url: urlsForApi.actions.add.url,
    type: urlsForApi.actions.add.type,
    data: {
      actionType: options.actionType,
      layoutId: options.layoutId,
      target: options.target,
      targetId: options.targetId,
    },
  }).done(function(_res) {
    // Render the action tab
    self.propertiesPanel.renderActionTab(
      self.selectedObject,
      {
        clearPrevious: true,
        selectAfterRender: true,
        openEditActionAfterRender: _res?.data?.actionId,
      },
    );
  }).fail(function(_data) {
    toastr.error(
      errorMessagesTrans.deleteFailed,
      errorMessagesTrans.error,
    );
  });

  return true;
};


/**
 * Save action
 * @param {object} action - Action to save
 * @param {object} form - Form to get data from
 * @return {boolean} false if unsuccessful
 */
lD.saveAction = function(action, form) {
  const actionData = action.data();
  const self = this;
  const requestURL = urlsForApi.actions.edit.url.replace(
    ':id',
    actionData.actionId,
  );

  $.ajax({
    url: requestURL,
    type: urlsForApi.actions.edit.type,
    data: $(form).serialize(),
  }).done(function(_res) {
    if (_res.success) {
      // Hide error message
      $(form).find('.error-message').hide();

      const $hiddenAction =
        $(form).parent().find('.action-element.hidden');
      // Close edit form and open action
      self.propertiesPanel.closeEditAction(
        $(form),
        $hiddenAction,
      );

      // Add new action ( to replace old one )
      self.propertiesPanel.addActionToContainer(
        _res.data,
        lD.selectObject,
        self.propertiesPanel.DOMObject.find('.element-actions'),
        self.propertiesPanel.DOMObject.find('.other-actions'),
        $hiddenAction,
      );
    } else {
      // Add message to form
      $(form).find('.error-message').html(_res.message).show();
    }
  }).fail(function(_data) {
    // Show error message on console
    toastr.error(
      errorMessagesTrans.deleteFailed,
      errorMessagesTrans.error,
    );
  });

  return false;
};

/**
 * Delete action
 * @param {object} action
  */
lD.deleteAction = function(action) {
  // Show confirmation modal
  const $modal = $(confirmationModalTemplate(
    {
      title: editorsTrans.actions.deleteModal.title,
      message: editorsTrans.actions.deleteModal.message,
      buttons: {
        cancel: {
          label: editorsTrans.actions.deleteModal.buttons.cancel,
          class: 'btn-default cancel',
        },
        delete: {
          label: editorsTrans.actions.deleteModal.buttons.delete,
          class: 'btn-danger confirm',
        },
      },
    },
  ));

  const removeModal = function() {
    $modal.modal('hide');
    // Remove modal
    $modal.remove();

    // Remove backdrop
    $('.modal-backdrop.show').remove();
  };

  // Add modal to the DOM
  this.editorContainer.append($modal);

  // Show modal
  $modal.modal('show');

  // Confirm button
  $modal.find('button.confirm').on('click', function() {
    const actionData = action.data();
    const requestURL = urlsForApi.actions.delete.url.replace(
      ':id',
      actionData.actionId,
    );

    $.ajax({
      url: requestURL,
      type: urlsForApi.actions.delete.type,
    }).done(function(_res) {
      const $actionParent = $(action).parent();

      // Delete action from the form
      $(action).remove();

      // Check if there are any actions left in the container
      // and if not, show the "no actions" message
      if ($actionParent.find('.action-element').length == 0) {
        $actionParent.append(
          $('<div />').addClass('text-center no-actions').text(
            propertiesPanelTrans.actions.noActionsToShow,
          ),
        );
      }

      // Remove modal
      removeModal();
    }).fail(function(_data) {
      toastr.error(
        errorMessagesTrans.replace('%error%', _data.message),
        errorMessagesTrans.error,
      );
    });
  });

  // Cancel button
  $modal.find('button.cancel').on('click', removeModal);
};

/**
 * Populate dropdown with layout elements
 * @param {object} $dropdown - Dropdown to populate
 * @param {object} Options.$typeInput - Input type to be updated
 * @param {string} Options.value - Initial value for the input
 * @param {string[]} Options.filters - Types to be included
 * @param {object} actionData - Data for the action
 * @return {boolean} false if unsuccessful
 */
lD.populateDropdownWithLayoutElements = function(
  $dropdown,
  {
    $typeInput = null,
    value = null,
    filters = ['layout', 'regions', 'widgets'],
  } = {},
  actionData = null,
) {
  const getRegions = filters.indexOf('regions') !== -1;
  const getWidgets = filters.indexOf('widgets') !== -1;
  const getLayouts = filters.indexOf('layout') !== -1;
  const getDrawerWidgets = filters.indexOf('drawerWidgets') !== -1;

  const addGroupToDropdown = function(groupName) {
    // Add group to dropdown
    const $group = $('<optgroup/>', {
      label: groupName,
    });

    // Add group to dropdown
    $dropdown.append($group);
  };

  const addElementToDropdown = function(element) {
    // Create option
    const $option = $('<option/>', {
      value: element.id,
      text: element.name + ' (' + element.id + ')',
      'data-type': element.type,
    });

    // Add to dropdown
    $dropdown.append($option);
  };

  // Update type value
  const updateTypeValue = function() {
    // If input is target, and widgetId has value
    // then update the widget drawer edit element
    const $widgetIDInput = ($typeInput) ?
      $typeInput.parents('form').find('[name=widgetId]') : null;

    // If there's no typeInput, stop
    if (!$typeInput) {
      return;
    }

    let typeInputValue = $dropdown.find(':selected').data('type');

    // Update targetId and target
    if (
      $typeInput.attr('id') === 'target'
    ) {
      // Update targetId and target
      actionData.targetId = $dropdown.val();
      actionData.target = typeInputValue;
    }

    // Update sourceId and source
    if (
      $typeInput.attr('id') === 'source'
    ) {
      // Update sourceId and source
      actionData.sourceId = $dropdown.val();
      actionData.source = typeInputValue;
    }

    // Update widgetId
    if (
      $widgetIDInput.length > 0 &&
      $widgetIDInput.val() != ''
    ) {
      // Call update widget drawer edit element
      handleEditWidget($widgetIDInput.val());
    }

    // For target, if target is layout, change it to screen
    if ($typeInput.attr('id') === 'target' && typeInputValue === 'layout') {
      typeInputValue = 'screen';
    }

    // Update type value
    $typeInput.val(typeInputValue);
  };

  // Update highlight on viewer
  const updateHighlightOnViewer = function() {
    $typeInput && (actionData[$typeInput.attr('id')] = $typeInput.val());
    actionData[$dropdown.attr('id')] = $dropdown.val();

    lD.viewer.createActionHighlights(actionData, 1);
  };

  // Open or edit drawer widget
  const handleEditWidget = function(dropdownValue) {
    if (dropdownValue === 'create') {
      // Create new
      lD.viewer.addActionEditArea(actionData, 'create');
    } else if (dropdownValue != '') {
      // Update action widget data
      actionData.widgetId = dropdownValue;

      // Edit existing
      lD.viewer.addActionEditArea(actionData, 'edit');
    } else {
      // Remove edit area
      lD.viewer.removeActionEditArea();
    }
  };

  // Layout
  if (getLayouts) {
    // Layout group
    addGroupToDropdown(
      editorsTrans.actions.layouts,
    );

    addElementToDropdown({
      id: lD.layout.layoutId,
      name: lD.layout.name,
      type: 'layout',
    });
  }

  // Regions
  const widgets = [];
  // Region group
  if (getRegions) {
    addGroupToDropdown(
      editorsTrans.actions.regions,
    );
  }

  // Get regions and/or widgets
  if (getRegions || getWidgets) {
    for (const region of Object.values(lD.layout.regions)) {
      if (getRegions && region.isPlaylist === false) {
        addElementToDropdown({
          id: region.regionId,
          name: region.name,
          type: 'region',
        });
      }

      // Save widgets
      for (const widget of Object.values(region.widgets)) {
        if (getWidgets && region.isPlaylist === false) {
          widgets.push({
            id: widget.widgetId,
            name: widget.widgetName,
            type: 'widget',
          });
        }
      }
    }
  }

  // Add widgets to dropdown
  if (getWidgets) {
    // Widget group
    addGroupToDropdown(
      editorsTrans.actions.widgets,
    );

    // Add widgets to dropdown
    for (const widget of widgets) {
      addElementToDropdown(widget);
    }
  }

  // Add drawer widgets to dropdown
  if (getDrawerWidgets) {
    // Check if we have any widget array
    if (lD.layout.drawer.widgets) {
      // Add widgets to dropdown
      for (const widget of Object.values(lD.layout.drawer.widgets)) {
        addElementToDropdown({
          id: widget.widgetId,
          name: widget.widgetName,
          type: 'widget',
        });
      }
    }
  }


  // Set initial value if provided
  if (value !== null) {
    $dropdown.val(value);
    updateTypeValue();

    if (getDrawerWidgets) {
      handleEditWidget($dropdown.val());
    }
  }

  // Handle dropdown change
  // and update type
  $dropdown.on('change', function() {
    if (getDrawerWidgets) {
      // Open/edit widget
      handleEditWidget($dropdown.val());
    } else {
      // Update type and highlight
      updateTypeValue();
      updateHighlightOnViewer();
    }
  });

  return true;
};

/**
 * Edit drawer widget
 * @param {object} actionData - Data for the action
 */
lD.editDrawerWidget = function(actionData) {
  // 1. Detach actions form to a temporary container or body
  lD.propertiesPanel.detachActionsForm();

  // 2. Open property panel with drawer widget
  const widget = lD.getElementByTypeAndId(
    'widget',
    'widget_' + lD.layout.drawer.regionId + '_' + actionData.widgetId,
    'drawer',
  );

  // 3. Select widget
  const $widgetInViewer = lD.viewer.DOMObject
    .find('#widget_' + lD.layout.drawer.regionId + '_' + actionData.widgetId);

  // Save previous selected object
  lD.previousSelectedObject = lD.selectedObject;

  lD.selectObject({
    target: $widgetInViewer,
    forceSelect: true,
  });

  // Select element in viewer
  lD.viewer.selectElement($widgetInViewer);

  // 4. Open property panel with drawer widget
  lD.propertiesPanel.render(widget, undefined, true);
};

/**
 * Close drawer widget
 */
lD.closeDrawerWidget = function() {
  // If previous selected object exists and current selected is a drawer widget
  if (
    $.isEmptyObject(lD.previousSelectedObject) === false &&
    lD.selectedObject.drawerWidget
  ) {
    // Select object with previous selected object
    const selectObjectId = lD.previousSelectedObject.id;
    lD.selectObject({
      target: $('#' + selectObjectId),
      forceSelect: true,
      reloadViewer: true,
    });
  }

  // Clear previous selected object
  lD.previousSelectedObject = {};
};
