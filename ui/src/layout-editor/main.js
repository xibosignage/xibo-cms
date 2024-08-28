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

/* eslint-disable prefer-promise-reject-errors */
// Include public path for webpack
require('../../public_path');

window.Handlebars = require('handlebars/dist/handlebars.min.js');
require('../../../modules/src/handlebars-helpers.js');

// Add image render lib
import '/modules/src/xibo-image-render.js';
// Add text scaler lib
import '/modules/src/xibo-text-scaler.js';

// Include handlebars templates
const designerMainTemplate = require('../templates/layout-editor.hbs');
const messageTemplate = require('../templates/message.hbs');
const loadingTemplate = require('../templates/loading.hbs');
const contextMenuTemplate = require('../templates/context-menu.hbs');
const contextMenuGroupTemplate = require('../templates/context-menu-group.hbs');
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
const ElementGroup = require('../editor-core/element-group.js');

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

  // Template edit mode
  templateEditMode: false,

  // Exit url (based on layout or template editing)
  exitURL: urlsForApi.layout.list.url,

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

  // Save all element layer in a map
  layerMap: [],

  // Is the playlist editor opened
  playlistEditorOpened: false,

  // Show minimum dimensions message
  showMinDimensionsMessage: false,
};

// Load Layout and build app structure
$(() => {
  // Add class to body so we can use CSS specifically on it
  $('body').addClass('editor-opened');

  // Get layout id
  const layoutId = lD.editorContainer.attr('data-layout-id');

  lD.common.showLoadingScreen();

  // Append loading html to the main div
  lD.editorContainer.html(loadingTemplate());

  // Change toastr positioning
  toastr.options.positionClass = 'toast-bottom-right';

  // Load layout through an ajax request
  $.get(
    urlsForApi.layout.get.url + '?layoutId=' + layoutId +
    '&embed=regions,playlists,widgets,widget_validity,tags,permissions,actions',
  ).done(function(res) {
    if (res.data != null && res.data.length > 0) {
      const url = new URL(window.location.href);
      // Check if we are in template edit mode
      if (url.searchParams.get('isTemplateEditor') == '1') {
        lD.templateEditMode = true;
        lD.exitURL = urlsForApi.template.list.url;
      }

      // Append layout html to the main div
      lD.editorContainer.html(
        designerMainTemplate(
          {
            trans: layoutEditorTrans,
            exitURL: lD.exitURL,
          },
        ),
      );

      // Check if we are in read only mode
      if (res.data[0].publishedStatusId != 2) {
        if (url.searchParams.get('vM') == '1') {
          // Enter view mode
          lD.enterReadOnlyMode();
        } else {
          // Enter welcome screen
          lD.welcomeScreen();
        }
      }

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
            isDivider: true,
          },
          {
            id: 'newLayout',
            title: layoutEditorTrans.newTitle,
            logo: 'fa-file',
            action: lD.addLayout,
            inactiveCheck: function() {
              return lD.templateEditMode;
            },
            inactiveCheckClass: 'd-none',
          },
          {
            id: 'deleteLayout',
            title: layoutEditorTrans.deleteTitle,
            logo: 'fa-times-circle-o',
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
            id: 'saveTemplate',
            title: layoutEditorTrans.saveTemplateTitle,
            logo: 'fa-floppy-o',
            action: lD.showSaveTemplateScreen,
            inactiveCheck: function() {
              return lD.templateEditMode ||
                lD.layout.editable;
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
          {
            id: 'scheduleLayout',
            title: layoutEditorTrans.scheduleTitle,
            logo: 'fa-clock-o',
            action: lD.showScheduleScreen,
            inactiveCheck: function() {
              return lD.templateEditMode ||
                (lD.layout.editable ||
                  !lD.layout.scheduleNowPermission);
            },
            inactiveCheckClass: 'd-none',
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
      lD.refreshEditor({
        reloadToolbar: true,
        reloadViewer: true,
        reloadPropertiesPanel: true,
      });

      // Initialise tooltips on main container
      lD.common.reloadTooltips(lD.editorContainer);

      // Handle editor minimum dimensions when resizing
      lD.common.handleEditorMinimumDimensions(lD);

      // Load preferences
      lD.loadPrefs();
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
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

  lD.handleInputs();

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
 * @param {bool=} refreshEditor - Force refresh of the editor
 * @param {bool=} reloadViewer - Force viewer reload
 * @param {bool=} reloadPropertiesPanel - Force properties panel reload
 * @param {bool=} reloadLayerManager - Force layer manager reload
 */
lD.selectObject =
  function({
    target = null,
    forceSelect = false,
    clickPosition = null,
    refreshEditor = true,
    reloadViewer = false,
    reloadPropertiesPanel = true,
    reloadLayerManager = false,
  } = {}) {
    // Clear rogue tooltips
    lD.common.clearTooltips();

    // If there is a selected card
    // use the drag&drop simulate to add that item to a object
    if (!$.isEmptyObject(this.toolbar.selectedCard)) {
      // Get card object
      const card = this.toolbar.selectedCard[0];

      // Drop to target validations
      const dropToPlaylist = (
        target &&
        target.data('subType') == 'playlist' &&
        lD.common.hasTarget(card, 'playlist')
      );

      const dropToDrawerOrZone = (
        target &&
        ['drawer', 'zone'].includes(target.data('subType'))
      );

      const dropToWidget = (
        target &&
        target.hasClass('designer-widget') &&
        target.hasClass('ui-droppable-active')
      );

      const dropToActionTarget = (
        target &&
        target.hasClass('ui-droppable-actions-target')
      );

      const dropToElementAndElGroup = (
        target &&
        (
          target.hasClass('designer-element-group') ||
          target.hasClass('designer-element')
        ) &&
        lD.common.hasTarget(card, 'element')
      );

      const dropToImagePlaceholder = (
        target &&
        (
          target.is('.designer-element[data-sub-type="image_placeholder"]')
        ) &&
        (
          $(card).data('type') === 'media' &&
          $(card).data('subType') === 'image'
        )
      );

      // Deselect cards and drop zones
      this.toolbar.deselectCardsAndDropZones();

      if (
        target &&
        (
          dropToPlaylist ||
          dropToDrawerOrZone ||
          dropToWidget ||
          dropToActionTarget ||
          dropToElementAndElGroup ||
          dropToImagePlaceholder
        )
      ) {
        // Send click position if we're adding to elements and element groups
        const clickPositionElement = (
          (
            target.hasClass('designer-element-group') ||
            target.hasClass('designer-element')
          ) &&
          lD.common.hasTarget(card, 'element')
        ) ? clickPosition : null;

        // Simulate drop item add
        this.dropItemAdd(target, card, clickPositionElement);
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
            lD.layout.deleteObject(
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

      const oldSelectedId = (this.selectedObject.type === 'element') ?
        this.selectedObject.elementId : this.selectedObject.id;
      const oldSelectedType = this.selectedObject.type;
      const oldSelectedMultiple = lD.viewer.getMultipleSelected();

      // If the selected object was different from the previous
      // and we are focused on a properties panel field, save before continuing
      if (
        (
          oldSelectedId != newSelectedId ||
          oldSelectedType != newSelectedType
        ) && (
          this.propertiesPanel.toSave ||
          this.propertiesPanel.toSaveElementCallback != null
        )
      ) {
        // Select previous object
        const selectPrevious = function() {
          // Select object again, with the same params
          lD.selectObject({
            target: target,
            forceSelect: forceSelect,
            clickPosition: clickPosition,
            refreshEditor: refreshEditor,
            reloadViewer: reloadViewer,
            reloadPropertiesPanel: reloadPropertiesPanel,
          });
        };

        // Save elements
        if (this.propertiesPanel.toSaveElementCallback != null) {
          // Set flag back to false
          this.propertiesPanel.toSaveElement = false;

          // Run callback to save element property
          this.propertiesPanel.toSaveElementCallback();

          // Set callback back to null
          this.propertiesPanel.toSaveElementCallback = null;

          // Select object again
          selectPrevious();
        } else if (this.propertiesPanel.toSave) {
          // Save normal form fields

          // Set flag back to false
          this.propertiesPanel.toSave = false;

          // Save previous object
          this.propertiesPanel.save({
            target: this.selectedObject, // Save previous object
            callbackNoWait: selectPrevious,
          });
        }

        // Prevent select to continue
        return;
      }

      // Unselect the previous selectedObject object if still selected
      if (this.selectedObject.selected) {
        this.selectedObject.selected = false;
      }

      // Set to the default object
      this.selectedObject = this.layout;
      this.selectedObject.type = 'layout';

      // If the selected object was different from the previous
      // or we force select
      // select a new one
      if (
        oldSelectedId != newSelectedId ||
        oldSelectedType != newSelectedType ||
        forceSelect
      ) {
        let newObjectSelected = false;

        // Save the new selected object
        if (newSelectedType === 'region') {
          this.layout.regions[newSelectedId].selected = true;
          this.selectedObject = this.layout.regions[newSelectedId];
          newObjectSelected = true;
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

            newObjectSelected = true;
          }
        } else if (newSelectedType === 'element') {
          const parentRegion = target.data('regionId');
          const parentWidget = target.data('widgetId');

          const element = this.layout.canvas.widgets[
            'widget_' + parentRegion + '_' + parentWidget
          ].elements[newSelectedId];

          if (element) {
            element.selected = true;
            this.selectedObject = element;
            newObjectSelected = true;
          }
        } else if (newSelectedType === 'element-group') {
          const parentRegion = target.data('regionId');
          const parentWidget = target.data('widgetId');

          const elementGroup = this.layout.canvas.widgets[
            'widget_' + parentRegion + '_' + parentWidget
          ].elementGroups[newSelectedId];

          if (elementGroup) {
            elementGroup.selected = true;
            this.selectedObject = elementGroup;
            newObjectSelected = true;
          }
        }

        if (newObjectSelected) {
          this.selectedObject.type = newSelectedType;
        }

        // Refresh the designer containers
        (refreshEditor) && lD.refreshEditor({
          reloadToolbar: false,
          reloadViewer: reloadViewer,
          reloadPropertiesPanel: reloadPropertiesPanel,
        });
      } else {
        // Still reload layer manager
        // even if we're not refreshing the whole editor
        (reloadLayerManager) &&
          lD.viewer.layerManager.render();

        // Render bottombar to fix issue with coming
        // from selecting multiple objects
        (oldSelectedMultiple && oldSelectedMultiple.multiple) &&
          lD.bottombar.render(this.selectedObject, false);
      }
    }
  };

/**
 * Refresh designer
 * @param {boolean} [reloadToolbar=false] - Update toolbar
 * @param {boolean} [reloadViewer=false] - Reload viewer
 * @param {object} [reloadViewerTarget={}] - Reload viewer target
 * @param {boolean} [reloadPropertiesPanel=false] - Reload properties panel
 * @param {boolean} [reloadLayerManager=true] - Reload layer manager
 */
lD.refreshEditor = function(
  {
    reloadToolbar = false,
    reloadViewer = false,
    reloadViewerTarget = {},
    reloadPropertiesPanel = false,
    reloadLayerManager = true,
  } = {},
) {
  // Remove temporary data only when reloading properties panel
  (reloadPropertiesPanel) && this.clearTemporaryData();

  // Toolbars
  (reloadToolbar) && this.toolbar.render();
  this.topbar.render();
  this.bottombar.render(this.selectedObject);

  // Manager ( hidden )
  this.historyManager.render(false);

  // Properties panel and viewer
  (reloadPropertiesPanel) && this.propertiesPanel.render(this.selectedObject);
  (reloadViewer) && this.viewer.render(reloadViewer, reloadViewerTarget);
  (reloadLayerManager) && this.viewer.layerManager.render();
};

/**
 * Reload API data and replace the layout structure with the new value
 * @param {object=} layout  - previous layout
 * @param {boolean} [refreshEditor=false] - refresh editor
 * @param {boolean} [captureThumbnail=false] - capture thumbnail
 * @param {callBack} [callBack=null]- callback function
 * @param {boolean} [reloadToolbar=false] - update toolbar
 * @param {boolean} [reloadViewer=true] - Reload viewer
 * @param {boolean} [reloadPropertiesPanel=true] - Reload properties panel
 * @param {boolean} [resetPropertiesPanelOpenedTab=false]
 * - Reset properties panel opened tab
 * @return {Promise} - Promise
 */
lD.reloadData = function(
  layout,
  {
    refreshEditor = false,
    captureThumbnail = false,
    callBack = null,
    reloadToolbar = false,
    reloadViewer = true,
    reloadPropertiesPanel = true,
    resetPropertiesPanelOpenedTab = false,
  } = {},
) {
  const layoutId =
    (typeof layout.layoutId == 'undefined') ? layout : layout.layoutId;

  lD.common.showLoadingScreen();

  // Reset tab to be opened
  if (resetPropertiesPanelOpenedTab) {
    lD.propertiesPanel.openTabOnRender = '';
  }

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

      // Select the same object
      const selectObjectId = (lD.selectedObject.type === 'element') ?
        lD.selectedObject.elementId :
        lD.selectedObject.id;

      const $selectedDOMTarget = $('#' + selectObjectId);

      lD.selectObject({
        target: $selectedDOMTarget,
        forceSelect: true,
        refreshEditor: false, // Don't refresh the editor here
        reloadPropertiesPanel: false,
      });

      // Check if the selected object is a temporary one
      const targetToRender =
        $selectedDOMTarget.hasClass('viewer-temporary-object') ?
          lD.selectedObject :
          {};

      // Reload the form helper connection
      formHelpers.setup(lD, lD.layout);

      // Check layout status
      lD.checkLayoutStatus();

      // Add thumbnail
      captureThumbnail && lD.uploadThumbnail();

      // Update topbar jumplist if changed
      if (
        lD.topbar.jumpList.layoutId != lD.layout.layoutId ||
        lD.topbar.jumpList.layoutName != lD.layout.name
      ) {
        lD.topbar.jumpList.layoutId = lD.layout.layoutId;
        lD.topbar.jumpList.layoutName = lD.layout.name;

        // Update jumplist
        lD.topbar.setupJumpList($('#layoutJumpListContainer'));
      }

      // Refresh designer
      refreshEditor && lD.refreshEditor({
        reloadToolbar: reloadToolbar,
        reloadViewer: reloadViewer,
        reloadViewerTarget: targetToRender,
        reloadPropertiesPanel: reloadPropertiesPanel,
      });

      // We always reload the layer manager after reloading data
      lD.viewer.layerManager.render();

      // Call callback function
      callBack && callBack();
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
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
    data: {folderId: lD.folderId},
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
    );`,
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
                callback: function(ev) {
                  // Show loading cog
                  $(ev.currentTarget).append(
                    '&nbsp;<i class="fa fa-cog fa-spin"></i>',
                  );

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
        window.location.reload();
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
  lD.common.showLoadingScreen();

  lD.historyManager.revertChange().then((res) => { // Success
    // Refresh designer according to local or API revert
    if (res.localRevert) {
      lD.refreshEditor({
        reloadToolbar: false,
        reloadViewer: true,
        reloadPropertiesPanel: true,
      });
    } else {
      lD.reloadData(lD.layout,
        {
          refreshEditor: true,
        });
    }

    lD.common.hideLoadingScreen();
  }).catch((error) => { // Fail/error
    lD.common.hideLoadingScreen();

    // Show error returned or custom message to the user
    let errorMessage = '';

    if (typeof error == 'string') {
      errorMessage = error;
    } else {
      errorMessage = error.errorThrown;
    }

    // Remove last change
    lD.historyManager.removeLastChange();

    toastr.error(
      errorMessagesTrans.revertFailed.replace('%error%', errorMessage),
    );
  });
};

/**
 * Delete selected object
 */
lD.deleteSelectedObject = function() {
  // Check if we have multiple objects selected
  const selectedInViewer = lD.viewer.getMultipleSelected();

  // Prevent delete if it doesn't have permissions
  if (
    (
      lD.selectedObject.isDeletable === false &&
      selectedInViewer.multiple === false
    ) ||
    (
      selectedInViewer.multiple === true &&
      selectedInViewer.canBeDeleted == false
    )
  ) {
    return;
  }

  if (selectedInViewer.multiple) {
    // Delete multiple objects
    lD.deleteMultipleObjects();
  } else if (lD.selectedObject.type === 'region') {
    // For now, we always delete the region
    // Check if region or playlist and it has at least one object in it
    const needsConfirmationModal = (
      Object.values(lD.selectedObject.widgets).length > 0
    );

    lD.deleteObject(
      lD.selectedObject.type,
      lD.selectedObject[lD.selectedObject.type + 'Id'],
      null,
      false,
      needsConfirmationModal,
      lD.selectedObject.isPlaylist ? 'playlist' : 'region',
    );
  } else if (lD.selectedObject.type === 'widget') {
    // Drawer widget
    if (lD.selectedObject.drawerWidget) {
      const drawerId = lD.getObjectByTypeAndId('drawer').regionId;

      lD.deleteObject(
        'widget',
        lD.selectedObject.widgetId,
        drawerId,
        true,
      );
    } else {
      // Delete widget's region
      const regionId = (lD.selectedObject.drawerWidget) ?
        lD.getObjectByTypeAndId('drawer').regionId :
        lD.getObjectByTypeAndId('region', lD.selectedObject.regionId).regionId;

      lD.deleteObject(
        'region',
        regionId,
        null,
        false,
        true,
        'widget',
      );
    }
  } else if (lD.selectedObject.type === 'element') {
    // Get element's widget
    const widget = lD.getObjectByTypeAndId(
      'widget',
      lD.selectedObject.widgetId,
      'canvas',
    );

    // Check if element is not global and is the last one on widget
    const showConfirmationModal = (
      widget.subType != 'global' &&
      Object.values(widget.elements).length === 1
    );

    // Delete element
    lD.deleteObject(
      lD.selectedObject.type,
      lD.selectedObject[lD.selectedObject.type + 'Id'],
      'widget_' + lD.selectedObject.regionId + '_' + lD.selectedObject.widgetId,
      false,
      showConfirmationModal,
      'element',
    );
  } else if (lD.selectedObject.type === 'element-group') {
    // Get element's widget
    const widget = lD.getObjectByTypeAndId(
      'widget',
      lD.selectedObject.widgetId,
      'canvas',
    );

    // Check if element group is not global and
    // the widget only has the elements from the group
    const showConfirmationModal = (
      widget.subType != 'global' &&
      Object.values(widget.elements).length ===
        Object.values(lD.selectedObject.elements).length
    );

    // Delete element group
    lD.deleteObject(
      lD.selectedObject.type,
      lD.selectedObject.id,
      'widget_' + lD.selectedObject.regionId + '_' + lD.selectedObject.widgetId,
      false,
      showConfirmationModal,
      'elementGroup',
    );
  }
};

/**
 * Delete object
 * @param {string} objectType - Object type (widget, region)
 * @param {string} objectId - Object id
 * @param {*} objectAuxId - Auxiliary object id (f.e.region for a widget)
 * @param {boolean=} drawerWidget - If we're deleting a drawer widget
 * @param {boolean=} showConfirmationModal
 *   - If we need to show a confirmation modal
 * @param {string} confirmationModalType
 *   - Type of object to be deleted (playlist, widget, element)
 */
lD.deleteObject = function(
  objectType,
  objectId,
  objectAuxId = null,
  drawerWidget = false,
  showConfirmationModal = false,
  confirmationModalType,
) {
  // Create modal before delete element
  const createDeleteModal = function() {
    bootbox.hideAll();

    bootbox.dialog({
      title: deleteModalTrans[confirmationModalType].title,
      message: deleteModalTrans[confirmationModalType].message,
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
            // Delete
            lD.deleteObject(
              objectType,
              objectId,
              objectAuxId,
              drawerWidget,
              false,
            );
          },
        },
      },
    }).attr('data-test', 'deleteObjectModal');
  };

  // Show confirmation modal if needed
  if (showConfirmationModal && lD.common.deleteConfirmation) {
    createDeleteModal();
    return;
  }

  // For elements, we just delete from the widget
  if (objectType === 'element') {
    // Get parent widget
    const widget = lD.getObjectByTypeAndId(
      'widget',
      objectAuxId,
      'canvas',
    );

    // Delete element from widget
    widget.removeElement(
      objectId,
      {
        reload: false,
      });
  } else if (objectType === 'element-group') {
    // For element groups, we delete all elements in the group
    // Get parent widget
    const widget = lD.getObjectByTypeAndId(
      'widget',
      objectAuxId,
      'canvas',
    );

    // Delete element from widget
    widget.removeElementGroup(
      objectId,
      {
        reload: false,
      });
  } else {
    lD.common.showLoadingScreen();

    // Hide in viewer
    lD.viewer.toggleObject(
      objectType,
      objectId,
      true,
    );

    // Select layout if we're deleting the selected region or widget
    const regionId = (lD.selectedObject.type === 'widget') ?
      lD.selectedObject.regionId :
      'region_' + lD.selectedObject.regionId;

    if (
      objectType === 'region' &&
      regionId === 'region_' + objectId
    ) {
      lD.selectObject();
    }

    lD.layout.deleteObject(
      objectType,
      objectId,
      null,
      true,
      !drawerWidget, // don't deselect Object if it's a drawer widget
    ).then((_res) => {
      if (drawerWidget) {
        // Detach action form
        lD.propertiesPanel.detachActionsForm();

        // Remove object manually from drawer (to avoid refresh)
        delete lD.layout.drawer.widgets[
          `widget_${lD.layout.drawer.regionId}_${objectId}`
        ];

        // Update dropdown with existing widgets
        const $actionOpenedForm = lD.propertiesPanel.actionForm.find('form');
        const actionFormData = $actionOpenedForm.data();
        lD.populateDropdownWithLayoutElements(
          $actionOpenedForm.find('[name="widgetId"]'),
          {
            value: actionFormData.widgetId,
            filters: ['drawerWidgets'],
          },
          actionFormData,
        );

        // Deselect object
        lD.selectObject({
          target: null,
          reloadViewer: false,
          reloadPropertiesPanel: false,
        });
        lD.viewer.selectObject();

        // Render properties panel with action tab
        lD.propertiesPanel.render(
          lD.selectedObject,
          false, // Action edit mode
          true, // Open action tab
        );
      } else {
        // Remove widget from viewer
        lD.viewer.removeObject(
          objectType,
          objectId,
        );

        // Reload data ( if not a drawer widget)
        lD.reloadData(lD.layout,
          {
            refreshEditor: false,
          });
      }

      lD.common.hideLoadingScreen();
    }).catch((error) => { // Fail/error
      lD.common.hideLoadingScreen();

      // Show error returned or custom message to the user
      let errorMessage = '';

      // Show back in viewer
      lD.viewer.toggleObject(
        objectType,
        objectId,
        false,
      );

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
 * Delete multiple selected objects
 * @param {boolean=} showConfirmationModal
 */
lD.deleteMultipleObjects = function(showConfirmationModal = true) {
  const deleteElementsOrGroupElements = function(
    itemsArray,
    type = 'elements',
  ) {
    let auxWidget = null;
    itemsArray.each((idx, item) => {
      const itemId = $(item).attr('id');
      const widgetId = $(item).data('widgetId');
      const widgetFullId =
        'widget_' + $(item).data('regionId') +
        '_' + $(item).data('widgetId');

      // If it's the last element
      // or the next element has another widget ID, save
      const save = (
        idx == itemsArray.length ||
        (
          idx < itemsArray.length &&
          widgetId != $(itemsArray[idx + 1]).data('widgetId')
        )
      );

      // Get parent widget if doesn't exist or we need to save
      if (
        !auxWidget ||
        save
      ) {
        auxWidget = lD.getObjectByTypeAndId(
          'widget',
          widgetFullId,
          'canvas',
        );
      }

      // Delete element from widget
      if (type === 'elements') {
        auxWidget.removeElement(
          itemId,
          {
            save: save,
            reload: false,
          },
        );
      } else {
        auxWidget.removeElementGroup(
          itemId,
          {
            save: save,
            reload: false,
          },
        );
      }
    });
  };

  // Create modal before delete elements
  const createDeleteMultipleModal = function() {
    bootbox.hideAll();

    bootbox.dialog({
      title: deleteModalTrans.multiple.title,
      message: deleteModalTrans.multiple.message,
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
            // Delete
            lD.deleteMultipleObjects(false);
          },
        },
      },
    }).attr('data-test', 'deleteMultipleObjectModal');
  };

  // Show confirmation modal
  if (showConfirmationModal && lD.common.deleteConfirmation) {
    createDeleteMultipleModal();
    return;
  }

  // First delete elements if they exist
  const $elementsToBeDeleted =
    lD.viewer.DOMObject.find('.selected.designer-element').sort((a, b) => {
      return Number($(b).data('widgetId')) - Number($(a).data('widgetId'));
    });

  if ($elementsToBeDeleted.length > 0) {
    deleteElementsOrGroupElements($elementsToBeDeleted);
  }

  // Then delete element groups
  const $elementGroupsToBeDeleted =
    lD.viewer.DOMObject.find('.selected.designer-element-group')
      .sort((a, b) => {
        return (
          Number($(b).data('widgetId')) -
          Number($(a).data('widgetId'))
        );
      });

  if ($elementGroupsToBeDeleted.length > 0) {
    deleteElementsOrGroupElements(
      $elementGroupsToBeDeleted,
      'elementGroups',
    );
  }

  // Finally, delete regions one by one
  const $regionsToBeDeleted =
    lD.viewer.DOMObject.find('.selected.designer-region');

  if ($regionsToBeDeleted.length > 0) {
    let deletedIndex = 0;

    lD.common.showLoadingScreen('deleteMultiObject');

    // Delete all selected objects
    const deleteNext = function() {
      const $item = $($regionsToBeDeleted[deletedIndex]);

      const objId = $item.data('regionId');
      const objType = $item.data('type');

      lD.layout.deleteObject(
        objType,
        objId,
        null,
        false,
      ).then((_res) => {
        deletedIndex++;

        if (deletedIndex == $regionsToBeDeleted.length) {
          // Stop deleting and deselect all elements
          lD.viewer.selectObject();

          // Hide loader
          lD.common.hideLoadingScreen('deleteMultiObject');

          // Reload data and select element when data reloads
          lD.reloadData(lD.layout,
            {
              refreshEditor: true,
            });
        } else {
          deleteNext();
        }
      });
    };

    // Start deleting
    deleteNext();
  } else {
    // If we're not deleting any region, deselect elements now
    lD.viewer.selectObject();
  }
};

/**
 * Duplicate selected object
 */
lD.duplicateSelectedObject = function() {
  // Only duplicate for now if it's an element or element group
  if (
    lD.selectedObject.type === 'element' ||
    lD.selectedObject.type === 'element-group'
  ) {
    lD.duplicateObject(lD.selectedObject);
  }
};

/**
 * Duplicate object ( element or element group )
 * @param {object} objectToDuplicate
 */
lD.duplicateObject = function(objectToDuplicate) {
  // For now, use an offset value to position the new element
  const offsetMove = 20;

  // Get widget
  const objectParentWidget =
    lD.getObjectByTypeAndId('widget', objectToDuplicate.widgetId, 'canvas');

  // Element array to add
  const elementArray = [];

  const createNewElementFromCopy = function(element, groupId = null) {
    // Create temporary copy element
    const elementCopy = Object.assign(
      {},
      element,
    );

    // Set type as element type before adding
    elementCopy.type = elementCopy.elementType;

    // Get new random id
    elementCopy.elementId =
      'element_' + element.id + '_' +
      Math.floor(Math.random() * 1000000);

    // If it's in a group
    if (groupId) {
      // Add group id
      elementCopy.groupId = groupId;

      // If group doesn't exist, create it and add it to the elementGroup
      if (!objectParentWidget[groupId]) {
        // Create copy of previous group
        elementCopy.group = Object.assign(
          {},
          elementCopy.group,
        );

        // Addign new id and position
        elementCopy.group.id = groupId;
        elementCopy.group.top += offsetMove;
        elementCopy.group.left += offsetMove;

        // Add group to widget element groups
        objectParentWidget[groupId] = elementCopy.group;

        // Clear elements from group and add new
        objectParentWidget[groupId] = {};
        objectParentWidget[groupId][elementCopy.elementId] = elementCopy;
      } else {
        // Assign group to new element
        elementCopy.group = objectParentWidget[groupId];
        objectParentWidget[groupId][elementCopy.elementId] = elementCopy;
      }
    }

    // Also add offset to each element, even in groups
    // the top/left position is globally based in the editor
    elementCopy.top += offsetMove;
    elementCopy.left += offsetMove;

    return elementCopy;
  };

  if (objectToDuplicate.type == 'element') {
    elementArray.push(createNewElementFromCopy(objectToDuplicate));
  } else if (objectToDuplicate.type == 'element-group') {
    // Generate a random group id
    const groupId = 'group_' + Math.floor(Math.random() * 1000000);

    Object.values(objectToDuplicate.elements).forEach((el) => {
      elementArray.push(createNewElementFromCopy(el, groupId));
    });
  }

  // Add element to widget
  lD.addElementsToWidget(
    elementArray,
    objectParentWidget,
    (objectToDuplicate.type == 'element-group'),
  );
};

/**
 * Add action to take after dropping a draggable item
 * @param {object} droppable - Target drop object
 * @param {object} draggable - Dragged object
 * @param {object=} dropPosition - Position of the drop
 */
lD.dropItemAdd = function(droppable, draggable, dropPosition) {
  let draggableType = $(draggable).data('type');
  let draggableSubType = $(draggable).data('subType');
  const draggableData = Object.assign({}, $(draggable).data());
  const droppableIsDrawer = ($(droppable).data('subType') === 'drawer');
  const droppableIsZone = ($(droppable).data('subType') === 'zone');
  const droppableIsPlaylist = ($(droppable).data('subType') === 'playlist');
  const droppableIsWidget = $(droppable).hasClass('designer-widget');
  let droppableIsElement = $(droppable).hasClass('designer-element');
  const droppableIsImagePlaceholder =
    $(droppable).is('.designer-element[data-sub-type="image_placeholder"]');
  const droppableIsElementGroup =
    $(droppable).hasClass('designer-element-group');
  let getTemplateBeforeAdding = '';
  const fromProvider = $(draggable).hasClass('from-provider');

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
      if (fromProvider) {
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

  const reloadRegion = function(regionId) {
    // Save zone or playlist to a temp object
    // so it can be selected after refreshing
    lD.viewer.saveTemporaryObject(
      'region_' + regionId,
      'region',
      {
        type: 'region',
      },
    );

    // Reload data ( and viewer )
    lD.reloadData(lD.layout,
      {
        refreshEditor: true,
      });
  };

  const createSubplaylistInPlaylist = function(regionId, playlistId) {
    lD.addModuleToPlaylist(
      regionId,
      playlistId,
      'subplaylist',
      draggableData,
      null,
      false,
      false,
      false,
      false,
      false,
    ).then((res) => {
      // Update playlist values in the new widget
      lD.historyManager.addChange(
        'saveForm',
        'widget', // targetType
        res.data.widgetId, // targetId
        null, // oldValues
        {
          subPlaylists: JSON.stringify([
            {
              rowNo: 1,
              playlistId: draggableData.subPlaylistId,
              spots: '',
              spotLength: '',
              spotFill: 'repeat',
            },
          ]),
        }, // newValues
        {
          addToHistory: false,
        },
      ).then((_res) => {
        reloadRegion(regionId);
      }).catch((_error) => {
        toastr.error(_error);
      });
    });
  };

  // If draggable is a media image, we need to choose
  // if it's going to be added as static widget or element
  if (
    draggableSubType === 'image' &&
    // If droppable is a playlist, drawer or zone, do nothing
    !(
      droppableIsPlaylist ||
      droppableIsZone ||
      droppableIsDrawer
    )
  ) {
    // Make a fake image element so it can go to the add element flow
    draggableType = 'element';
    droppableIsElement = true;
    draggableSubType = 'global';
    getTemplateBeforeAdding = 'global_library_image';
  }

  if (draggableType === 'media') {
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
        lD.getObjectByTypeAndId(
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
          lD.layout.deleteObject('region', res.data.regionPlaylist.regionId);
        });
      });
    }
  } else if (draggableType == 'actions') {
    // Get target type
    const targetType = (
      $(droppable).hasClass('layout') || droppable === null
    ) ?
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
    let addToGroupId = null;
    let addToGroupWidgetId = null;
    let addToExistingElementId = null;
    let placeholderElement = {};

    if (droppableIsImagePlaceholder) {
      placeholderElement = {
        id: $(droppable).attr('id'),
        widgetId: $(droppable).data('widgetId'),
        regionId: $(droppable).data('regionId'),
      };
    }

    // If target is type global ( and being edited )
    // if draggable is type global or if both are the same type
    const canBeAddedToGroup = function() {
      return $(droppable).hasClass('editing') &&
        (
          draggableData.dataType == 'global' ||
          draggableData.dataType == $(droppable).data('elementType')
        );
    };

    // Create group if group is type global
    // if draggable is type global or if both are the same type
    if (droppableIsElement) {
      if (canBeAddedToGroup()) {
        addToExistingElementId = $(droppable).attr('id');
        addToGroupWidgetId = 'widget_' + $(droppable).data('regionId') +
          '_' + $(droppable).data('widgetId');
      }
    }

    // Add to group if group is type global
    // if draggable is type global or if both are the same type
    if (droppableIsElementGroup) {
      if (canBeAddedToGroup()) {
        addToGroupId = $(droppable).attr('id');
        addToGroupWidgetId = 'widget_' + $(droppable).data('regionId') +
          '_' + $(droppable).data('widgetId');
      }
    }

    // Calculate next available top layer
    const topLayer = lD.calculateLayers().availableTop;

    // Get canvas
    this.layout.getCanvas(topLayer).then((canvas) => {
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
        mediaId,
        mediaName,
        isVisible,
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
          mediaId: mediaId,
          mediaName: mediaName,
          isVisible: isVisible,
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
        inGroup = false,
        newGroupType,
        recalculateGroupBeforeSaving,
      ) {
        // Widget type
        (!newGroupType) && (newGroupType = draggableSubType);

        const addToWidget = function(widget) {
          self.addElementsToWidget(
            elements,
            widget,
            inGroup,
            recalculateGroupBeforeSaving,
          );
        };

        // Create new widget and add to it
        const createNewWidget = function() {
          lD.addModuleToPlaylist(
            canvas.regionId,
            canvas.playlists.playlistId,
            newGroupType,
            draggableData,
            null,
            false,
            false,
            false,
            false,
            false, // don't save to history
          ).then((res) => {
            // Create new temporary widget for the elements
            const newWidget = new Widget(
              res.data.widgetId,
              res.data,
              canvas.regionId,
              self,
            );

            newWidget.editorObject = lD;

            // Add element to the new widget
            addToWidget(newWidget);
          });
        };

        // Get a target widget
        const targetWidget = canvas.getActiveWidgetOfType(newGroupType);

        if (newGroupType === 'global') {
          // If it's type global, add to canvas widget
          const canvasWidget = self.getObjectByTypeAndId(
            'canvasWidget',
          );
          addToWidget(canvasWidget);
        } else if ($.isEmptyObject(targetWidget)) {
          // If we don't have a widget, create a new one
          createNewWidget();
        } else {
          // Add element to the target widget
          addToWidget(targetWidget);
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
            let skipSelect = false;

            // Loop through elements
            template.stencil.elements.forEach((element) => {
              let elementGroupId = groupId;
              const elementGroupProperties = {
                width: draggableData.templateStartWidth,
                height: draggableData.templateStartHeight,
                top: (dropPosition) ? dropPosition.top : 0,
                left: (dropPosition) ? dropPosition.left : 0,
              };
              const elementPositions = {
                left: (dropPosition) ?
                  dropPosition.left + element.left :
                  element.left,
                top: (dropPosition) ?
                  dropPosition.top + element.top :
                  element.top,
              };

              // If element has a subgroup
              if (
                element.elementGroupId &&
                template?.stencil?.elementGroups
              ) {
                skipSelect = true;
                elementGroupId = element.elementGroupId + groupId;

                template?.stencil?.elementGroups.forEach((group) => {
                  if (group.id === element.elementGroupId) {
                    elementGroupProperties.top += group.top;
                    elementGroupProperties.left += group.left;
                    elementGroupProperties.width = group.width;
                    elementGroupProperties.height = group.height;
                    elementGroupProperties.layer = group.layer;
                    elementGroupProperties.slot = group.slot;
                    elementGroupProperties.pinSlot = group.pinSlot;

                    elementPositions.top =
                      elementGroupProperties.top + element.top;
                    elementPositions.left =
                      elementGroupProperties.left + element.left;
                  }
                });
              }

              // Create element
              const newElement = createElement({
                id: element.id,
                type: draggableData.dataType,
                left: elementPositions.left,
                top: elementPositions.top,
                width: element.width,
                height: element.height,
                layer: element.layer,
                rotation: element.rotation,
                properties: element.properties,
                groupId: elementGroupId,
                groupProperties: elementGroupProperties,
                mediaId: draggableData.mediaId,
                isVisible: draggableData.isVisible,
              });

              // Mark to skip select after add
              newElement.skipSelect = skipSelect;

              // Add element to elements array
              elements.push(newElement);
            });

            // Create widget and add elements
            createWidgetAndAddElements(
              elements,
              true,
            );
          }
        });
      } else {
        const addElement = function() {
          // Element options
          const elementOptions = {
            id: draggableData.templateId,
            type: draggableData.dataType,
            left: (dropPosition) ? dropPosition.left : 0,
            top: (dropPosition) ? dropPosition.top : 0,
            width: draggableData.templateStartWidth,
            height: draggableData.templateStartHeight,
            layer: 0,
            rotation: 0,
            extendsTemplate: draggableData.extendsTemplate,
            extendsOverride: draggableData.extendsOverride,
            extendsOverrideId: draggableData.extendsOverrideId,
            mediaId: draggableData.mediaId,
            mediaName: draggableData.title,
            isVisible: draggableData.isVisible,
          };

          let addToGroup = false;
          let addToGroupType = null;

          // If element has a group, add to it
          if (addToGroupId) {
            elementOptions.groupId = addToGroupId;

            // Get group
            const elementGroup = lD.getObjectByTypeAndId(
              'element-group',
              addToGroupId,
              addToGroupWidgetId,
            );

            const targetWidget = lD.getObjectByTypeAndId(
              'widget',
              'widget_' + $(droppable).data('regionId') + '_' +
              $(droppable).data('widgetId'),
              'canvas',
            );

            // Set group type as the same as the target widget
            addToGroupType = targetWidget.subType;

            // Add group object
            elementOptions.group = elementGroup;

            addToGroup = true;
          }

          // If we want to create a new element group
          if (addToExistingElementId) {
            // Generate a random group id
            const groupId = 'group_' + Math.floor(Math.random() * 1000000);

            // Get previous element
            const previousElement = lD.getObjectByTypeAndId(
              'element',
              addToExistingElementId,
              addToGroupWidgetId,
            );

            const targetWidget = lD.getObjectByTypeAndId(
              'widget',
              'widget_' + $(droppable).data('regionId') + '_' +
              $(droppable).data('widgetId'),
              'canvas',
            );

            // Create new element group
            targetWidget.elementGroups[groupId] = new ElementGroup(
              Object.assign(
                {
                  width: previousElement.width,
                  height: previousElement.height,
                  top: previousElement.top,
                  left: previousElement.left,
                },
                {
                  id: groupId,
                },
              ),
              $(droppable).data('widgetId'),
              $(droppable).data('regionId'),
              targetWidget,
            );

            // Set group if for the elements
            previousElement.groupId = groupId;
            previousElement.group = targetWidget.elementGroups[groupId];
            elementOptions.groupId = groupId;

            // Check group type
            addToGroupType = targetWidget.subType;

            // Set new element layer to be the top layer
            elementOptions.layer = previousElement.layer + 1;

            // Add previous widget to the elements array
            // and to element group
            elements.push(previousElement);
            targetWidget.elementGroups[groupId]
              .elements[previousElement.elementId] = previousElement;

            addToGroup = true;
          }

          // If we have a placeholder, change new element
          // dimensions to match it
          if (!$.isEmptyObject(placeholderElement)) {
            const widgetId =
              'widget_' +
              placeholderElement.regionId +
              '_' +
              placeholderElement.widgetId;

            const placeholder = lD.getObjectByTypeAndId(
              'element',
              placeholderElement.id,
              widgetId,
            );

            const widget = lD.getObjectByTypeAndId(
              'widget',
              widgetId,
              'canvas',
            );

            // Position options
            elementOptions.top = placeholder.top;
            elementOptions.left = placeholder.left;
            elementOptions.width = placeholder.width;
            elementOptions.height = placeholder.height;
            elementOptions.layer = placeholder.layer;

            // Properties
            elementOptions.properties = placeholder.properties;

            // If placeholder was in a group, add to same
            if (
              placeholder.groupId != '' &&
              placeholder.groupId != undefined
            ) {
              // Set group type as the same as the target widget
              addToGroupType = widget.subType;

              // Add group object
              elementOptions.groupId = placeholder.groupId;

              addToGroup = true;
            }

            // Remove placeholder
            widget.removeElement(
              placeholderElement.id,
              {
                save: (elementOptions.type != 'global'),
                reloadLayerManager: false,
                reload: false,
              },
            );
          }

          // Create element
          const element = createElement(elementOptions);

          // Add element to elements array
          elements.push(element);

          // Create widget and add elements
          createWidgetAndAddElements(
            elements,
            addToGroup,
            addToGroupType,
            addToGroup,
          );
        };

        // If we need to get template first
        if (getTemplateBeforeAdding != '') {
          // Get template, create a fake image to data and add
          const getTemplateAndAdd = function() {
            lD.templateManager.getTemplateById(getTemplateBeforeAdding)
              .then((template) => {
                // Make a fake image element so
                // it can go to the add element flow
                draggableData.templateId = 'global_library_image';
                draggableData.dataType = 'global';
                draggableData.subType = 'global';
                draggableData.extendsTemplate = 'global_image';
                draggableData.extendsOverride = 'url';
                draggableData.templateStartWidth = template.startWidth;
                draggableData.templateStartHeight = template.startHeight;

                addElement();
              });
          };

          // If we need to upload media
          if (draggableData.regionSpecific == 0) {
            // On hide callback
            const onHide = function(numUploads) {
              if (numUploads > 0) {
                getTemplateAndAdd();
              }
            };

            // On upload done callback
            const onUploadDone = function(data) {
              // Add media id to data
              draggableData.mediaId = data.response().result.files[0].mediaId;
              draggableData.title = data.response().result.files[0].name;
            };

            lD.openUploadForm({
              moduleData: draggableData,
              onHide: onHide,
              onUploadDone: onUploadDone,
            });
          } else if (fromProvider) {
            lD.importFromProvider(
              [draggableData.providerData],
            ).then((res) => {
              // If res is empty, it means that the import failed
              if (res.length === 0) {
                console.error(errorMessagesTrans.failedToImportMedia);
              } else {
                // Add media to draggableData
                draggableData.mediaId = res[0];

                getTemplateAndAdd();
              }
            });
          } else {
            // We don't need to upload, add right away
            getTemplateAndAdd();
          }
        } else {
          // Just add the element
          addElement();
        }
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
      lD.getObjectByTypeAndId(
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
          window.location.reload();
        } else {
          // Just an error we dont know about
          if (res.message == undefined) {
            console.error(res);
          } else {
            console.error(res.message);
          }
        }
      } else {
        if (draggableData.subPlaylistId) {
          createSubplaylistInPlaylist(
            res.data.regionId,
            res.data.regionPlaylist.playlistId,
          );
        } else {
          // Reload Data
          lD.reloadData(lD.layout,
            {
              refreshEditor: true,
              resetPropertiesPanelOpenedTab: true,
            });
        }
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
      // Output error to console
      console.error(jqXHR, textStatus, errorThrown);
    });
  } else if (draggableType === 'layout_template') {
    const addTemplateToLayout = function() {
      // Show loading screen
      lD.common.showLoadingScreen();

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
          lD.common.hideLoadingScreen();

          if (response.success && response.id) {
            // Deselect previous object
            lD.selectObject();

            // eslint-disable-next-line new-cap
            lD.reloadData(response.data,
              {
                refreshEditor: true,
                resetPropertiesPanelOpenedTab: true,
              });
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
          lD.common.hideLoadingScreen();

          console.error(xhr);
        },
      });
    };

    // Check if we have content on the layout
    if (lD.layout.isEmpty()) {
      addTemplateToLayout();
    } else {
      // Layout not empty, show modal
      // Show confirmation modal
      const $modal = $(confirmationModalTemplate(
        {
          title: editorsTrans.layoutTemplateReplace.title,
          message: editorsTrans.layoutTemplateReplace.message,
          buttons: {
            cancel: {
              label: editorsTrans.layoutTemplateReplace.buttons.cancel,
              class: 'btn-default cancel',
            },
            delete: {
              label: editorsTrans.layoutTemplateReplace.buttons.delete,
              class: 'btn-primary confirm',
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
        // Remove modal
        removeModal();

        // Add template and replace content
        addTemplateToLayout();
      });

      // Cancel button
      $modal.find('button.cancel').on('click', removeModal);
    }
  } else {
    // Adding a module, zone or playlist
    let regionType = 'frame';

    if (
      draggableSubType === 'playlist' ||
      draggableSubType === 'zone'
    ) {
      regionType = draggableSubType;
    }

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
        lD.getObjectByTypeAndId(
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
        if (regionType === 'frame') {
          lD.addModuleToPlaylist(
            res.data.regionId,
            res.data.regionPlaylist.playlistId,
            draggableSubType,
            draggableData,
            null,
            false,
            false,
            true,
            true,
            false,
          );
        } else {
          // If we're adding a specific playlist, we need to create a
          // subplaylist inside the new playlist
          if (draggableData.subPlaylistId) {
            createSubplaylistInPlaylist(
              res.data.regionId,
              res.data.regionPlaylist.playlistId,
            );
          } else {
            reloadRegion(res.data.regionId);
          }
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
 * @param {boolean} selectNewWidget Select the new widget after being added
 * @param {boolean} addToHistory Add change to history?
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
  selectNewWidget = true,
  addToHistory = true,
) {
  if (moduleData.regionSpecific == 0) {
    // Upload form if not region specific
    return new Promise((resolve) => {
      // On hide callback
      const onHide = function(numUploads) {
        // If there are no uploads, and it's not a zone, delete the region
        if (numUploads === 0 && !zoneWidget) {
          lD.layout.deleteObject(
            'region',
            regionId,
          ).then(() => {
            // Reload data ( and viewer )
            (reloadData) && lD.reloadData(lD.layout,
              {
                refreshEditor: true,
                resetPropertiesPanelOpenedTab: true,
              });

            resolve();
          });
        } else {
          // Reload data ( and viewer )
          (reloadData) && lD.reloadData(lD.layout,
            {
              refreshEditor: true,
              resetPropertiesPanelOpenedTab: true,
            });

          resolve();
        }
      };

      // On upload done callback
      const onUploadDone = function(data) {
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
      };

      lD.openUploadForm({
        playlistId: playlistId,
        moduleData: moduleData,
        addToPosition: addToPosition,
        onHide: onHide,
        onUploadDone: onUploadDone,
      });
    });
  } else { // Add widget to a region
    lD.common.showLoadingScreen();

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
        addToHistory: addToHistory,
      },
    ).then((res) => { // Success
      // Check if we added a element
      if (moduleData.type === 'element') {
        // Hide loading screen
        lD.common.hideLoadingScreen();

        // Return the promise with the data
        return res;
      }

      // Save the new widget as temporary
      if (selectNewWidget) {
        lD.viewer.saveTemporaryObject(
          'widget_' + regionId + '_' + res.data.widgetId,
          'widget',
          {
            type: 'widget',
            parentType: 'region',
            widgetRegion: 'region_' + regionId,
            isInDrawer: drawerWidget,
          },
        );
      }

      if (!drawerWidget) {
        // Reload data ( and viewer )
        (reloadData) && lD.reloadData(lD.layout,
          {
            refreshEditor: true,
            resetPropertiesPanelOpenedTab: true,
          });
      } else {
        const newWidgetId = res.data.widgetId;
        // Reload data ( and viewer )
        (reloadData) && lD.reloadData(
          lD.layout,
          {
            resetPropertiesPanelOpenedTab: true,
            callBack: () => {
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
          },
        );
      }

      lD.common.hideLoadingScreen();

      // Return the promise with the data
      return res;
    }).catch((error) => { // Fail/error
      lD.common.hideLoadingScreen();

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
 * Open upload form
 * @param {number} playlistId
 * @param {object} moduleData
 * @param {object} addToPosition
 * @param {function} onHide
 * @param {function} onUploadDone
 */
lD.openUploadForm = function({
  playlistId,
  moduleData,
  addToPosition,
  onHide,
  onUploadDone,
} = {},
) {
  const validExt = moduleData.validExt.replace(/,/g, '|');
  let numUploads = 0;

  // Close the current dialog
  bootbox.hideAll();

  openUploadForm({
    url: libraryAddUrl,
    title: uploadTrans.uploadMessage,
    animateDialog: false,
    initialisedBy: 'layout-designer-upload',
    buttons: {
      main: {
        label: translations.done,
        className: 'btn-primary btn-bb-main',
      },
    },
    onHideCallback: function() {
      if ( typeof onHide === 'function') {
        onHide(numUploads);
      }
    },
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
      showWidgetDates: false,
      folderSelector: true,
    },
    uploadDoneEvent: function(data) {
      // If the upload is successful, increase the number of uploads
      numUploads += 1;

      if ( typeof onUploadDone === 'function') {
        onUploadDone(data);
      }
    },
  }).attr('data-test', 'uploadFormModal');
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

  lD.common.showLoadingScreen();

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
      lD.reloadData(lD.layout,
        {
          refreshEditor: true,
          resetPropertiesPanelOpenedTab: true,
        });
    } else {
      const newWidgetId = res.data.newWidgets[0].widgetId;
      // Reload data ( and viewer )
      lD.reloadData(
        lD.layout,
        {
          resetPropertiesPanelOpenedTab: true,
          callBack: () => {
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
        },
      );
    }

    lD.common.hideLoadingScreen();
  }).catch((error) => { // Fail/error
    lD.common.hideLoadingScreen();

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
  // Hide open tooltips
  lD.editorContainer.find('.tooltip').remove();

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
lD.getObjectByTypeAndId = function(type, id, auxId) {
  let targetObject = {};

  if (type === 'layout') {
    targetObject = lD.layout;
  } else if (type === 'region') {
    // If id is a number, we need to get the unique id
    if (!isNaN(id)) {
      id = 'region_' + id;
    }

    targetObject = lD.layout.regions[id];
  } else if (type === 'drawer') {
    targetObject = lD.layout.drawer;
  } else if (type === 'canvas') {
    targetObject = lD.layout.canvas;
  } else if (type === 'canvasWidget') {
    targetObject = Object.values(lD.layout.canvas.widgets).find((el) => {
      return el.subType === 'global';
    });
  } else if (type === 'element') {
    targetObject = lD.layout.canvas.widgets[auxId].elements[id];
  } else if (type === 'element-group') {
    targetObject = lD.layout.canvas.widgets[auxId].elementGroups[id];
  } else if (type === 'widget') {
    const getWidgetFromRegion = function(widgetId, region) {
      // If id is a number, we need to get the unique id
      if (!isNaN(widgetId)) {
        widgetId = 'widget_' + region.regionId + '_' + widgetId;
      }

      return region.widgets[widgetId];
    };

    if (
      lD.layout.drawer.id != undefined &&
      (lD.layout.drawer.id == auxId || auxId == 'drawer')
    ) {
      targetObject = getWidgetFromRegion(id, lD.layout.drawer);
    } else if (
      lD.layout.canvas.id != undefined &&
      (lD.layout.canvas.id == auxId || auxId == 'canvas')
    ) {
      targetObject = getWidgetFromRegion(id, lD.layout.canvas);
    } else if (auxId == 'search') {
      // Search on drawer if canvas exist
      (lD.layout.drawer.id != undefined) &&
        (targetObject = getWidgetFromRegion(id, lD.layout.drawer));

      // Search on canvas if drawer exist, and we don't have target yet
      ($.isEmptyObject(targetObject) && lD.layout.canvas.id != undefined) &&
        (targetObject = getWidgetFromRegion(id, lD.layout.canvas));

      // If we still don't have target, check on all layout regions
      if ($.isEmptyObject(targetObject)) {
        Object.values(lD.layout.regions).every((region) => {
          targetObject = getWidgetFromRegion(id, region);

          // If we found the widget, break the loop
          if (!$.isEmptyObject(targetObject)) {
            return false;
          }

          return true;
        });
      }
    } else {
      targetObject = lD.layout.regions[auxId].widgets[id];
    }
  }

  return targetObject;
};

/**
 * Call layout status
 */
lD.checkLayoutStatus = function() {
  const self = this;

  // If there was still a status request
  // don't make another one
  if (this.checkStatusRequest != undefined) {
    return;
  }

  const linkToAPI = urlsForApi.layout.status;
  let requestPath = linkToAPI.url;

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', lD.layout.layoutId);

  this.checkStatusRequest = $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    // Clear request var after response
    self.checkStatusRequest = undefined;

    if (!res.success) {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
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
        res.extra.duration,
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
    // Clear request var after response
    self.checkStatusRequest = undefined;

    // Output error to console
    if (textStatus != 'requestAborted') {
      console.error(jqXHR, textStatus, errorThrown);
    }
  });
};

/**
 * New open playlist editor
 * @param {string} playlistId - Id of the playlist
 * @param {object} region - Region related to the playlist
 * @param {boolean} regionSpecific
 * @param {boolean} showExternalPlaylistMessage
 * @param {boolean} switchStatus
 * @param {string} auxPlaylistId - Id of the parent/child playlist
 */
lD.openPlaylistEditor = function(
  playlistId,
  region,
  regionSpecific = true,
  showExternalPlaylistMessage = false,
  switchStatus = false,
  auxPlaylistId,
) {
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
  lD.viewer.selectObject();

  // Show playlist editor
  $playlistEditorPanel.removeClass('hidden');

  // Load playlist editor
  pE.loadEditor(
    true,
    regionSpecific, // Region specific?
    showExternalPlaylistMessage, // Show external playlist message?
  );

  // Mark as opened
  lD.playlistEditorOpened = true;

  // On close, remove container and refresh designer
  lD.editorContainer.find('.back-button #backToLayoutEditorBtn')
    .off('click').on('click', function() {
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

      // Mark as closed
      lD.playlistEditorOpened = false;

      // Reopen properties panel
      lD.editorContainer.find('.properties-panel-container').addClass('opened');

      // Re-run handle inputs
      lD.handleInputs();

      // Reload data
      lD.reloadData(
        lD.layout,
        {
          refreshEditor: true,
          reloadToolbar: true,
          reloadPropertiesPanel: true,
        });
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
  let canBeCopied = false;
  let canHaveNewConfig = false;
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
    const elementWidget = lD.getObjectByTypeAndId('widget', objAuxId, 'canvas');

    // Check if the element or group can have a new config
    if (elementWidget.subType === 'global') {
      // Global elements or groups can't get a new config
      canHaveNewConfig = false;
    } else if (objType == 'element') {
      // We just need to have more than 1 element
      canHaveNewConfig =
        (Object.values(elementWidget.elements).length > 1);
    } else if (objType == 'element-group') {
      // We need to have either another group
      canHaveNewConfig =
        (Object.values(elementWidget.elementGroups).length > 1);

      // Or 1 elements that doesn't belong to the group
      if (canHaveNewConfig === false) {
        Object.values(elementWidget.elements).every((el) => {
          // If we found the widget, break the loop
          if (el.groupId != objId) {
            canHaveNewConfig = true;
            // Break the loop
            return false;
          }

          // Keep going
          return true;
        });
      }
    }

    // All elements and groups can be duplicated
    canBeCopied = true;
  }

  // Get object
  const layoutObject = lD.getObjectByTypeAndId(objType, objId, objAuxId);

  // Check if we can change the object layer
  const canChangeLayer = (
    layoutObject.isEditable
  );

  // If it's an editable group show ungroup option
  const canUngroup = (
    layoutObject.isEditable &&
    layoutObject.type === 'element-group'
  );

  // If target is a frame or zone, send single widget info
  const singleWidget = (
    layoutObject.type === 'region' &&
    (
      layoutObject.subType === 'frame' ||
      layoutObject.subType === 'zone'
    )
  ) ? Object.values(layoutObject.widgets)[0] : {};

  // If target is group or element group, send parent widget
  const elementWidget = (
    layoutObject.type === 'element' ||
    layoutObject.type === 'element-group'
  ) ? lD.getObjectByTypeAndId('widget', objAuxId, 'canvas') : {};

  // Check if it's global text to be edited inline
  const canEditText = (
    layoutObject.type === 'element' &&
    layoutObject.selected &&
    layoutObject.elementType === 'global' &&
    layoutObject.template.templateId === 'text'
  );

  // If it's a playlist, check if it's a layout playlist only
  // and has at least one widget
  layoutObject.playlistCanBeConverted = (
    layoutObject.isPlaylist &&
    !$(obj).hasClass('playlist-global-editable') &&
    layoutObject.playlists.widgets.length > 0
  );

  // Check if it's a dynamic playlist
  layoutObject.isDynamicPlaylist =
    (
      layoutObject.isPlaylist &&
      $(obj).hasClass('playlist-dynamic')
    );

  // Create menu and append to the designer div
  // ( using the object extended with translations )
  lD.editorContainer.append(
    contextMenuTemplate(Object.assign(layoutObject, {
      trans: contextMenuTrans,
      canBeCopied: canBeCopied,
      canHaveNewConfig: canHaveNewConfig,
      canChangeLayer: canChangeLayer,
      canUngroup: canUngroup,
      canEditText: canEditText,
      widget: singleWidget,
      isElementBased: (
        layoutObject.type === 'element' ||
        layoutObject.type === 'element-group'
      ),
      elementWidget: elementWidget,
      canvas: lD.getObjectByTypeAndId('canvas'),
    })),
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

      // Check if we need confirmation modal
      let showConfirmationModal = false;
      let deleteObjectType;

      if (
        layoutObject.type === 'region' &&
        Object.values(layoutObject.widgets).length > 0
      ) {
        // Check if region or playlist has at least one object in it
        showConfirmationModal = true;
        deleteObjectType = (layoutObject.isPlaylist) ?
          'playlist' :
          'widget';
      } else if (objType === 'element') {
        const elementWidget =
          lD.getObjectByTypeAndId('widget', objAuxId, 'canvas');

        // Check if element is not global and is the last one on widget
        showConfirmationModal = (
          elementWidget.subType != 'global' &&
          Object.values(elementWidget.elements).length === 1
        );
        deleteObjectType = 'element';
      } else if (objType === 'element-group') {
        const elementGroupWidget =
          lD.getObjectByTypeAndId('widget', objAuxId, 'canvas');

        // Check if element group is not global and
        // the widget only has the elements from the group
        showConfirmationModal = (
          elementGroupWidget.subType != 'global' &&
          Object.values(elementGroupWidget.elements).length ===
            Object.values(layoutObject.elements).length
        );
        deleteObjectType = 'elementGroup';
      }

      lD.deleteObject(
        objType,
        newObjId,
        auxId,
        false,
        showConfirmationModal,
        deleteObjectType,
      );
    } else if (target.data('action') == 'Move') {
      // Move widget in the timeline
      lD.layout.moveWidgetInRegion(
        layoutObject.regionId, layoutObject.id, target.data('actionType'),
      );
    } else if (target.data('action') == 'Layer') {
      const actionType = target.data('actionType');
      const originalLayer = (layoutObject.type === 'region') ?
        layoutObject.zIndex :
        layoutObject.layer;
      let newLayer = null;
      let groupElements;

      // If we're changing for a grouped element, get all elements from group
      if (
        layoutObject.type === 'element' &&
        layoutObject.groupId != undefined
      ) {
        groupElements = layoutObject.group.elements;
      }

      // Calculate Layers
      const calculatedLayers = lD.calculateLayers(
        originalLayer,
        (
          layoutObject.type === 'element' ||
          layoutObject.type === 'element-group'
        ),
        groupElements,
      );

      let updateLayerAbove = false;
      let updateLayerAboveTarget = 0;
      switch (actionType) {
        case 'bringToFront':
          // Only update layer if original isn't the top one
          if (originalLayer != calculatedLayers.availableTop) {
            // Find top layer and get 1 over it
            newLayer = calculatedLayers.availableTop;
          }
          break;
        case 'bringForward':
          // Only update layer if original isn't the top one
          if (originalLayer != calculatedLayers.availableUp) {
            // Find above layer and get 1 over it
            newLayer = calculatedLayers.availableUp;
          }
          break;
        case 'sendBackwards':
          // Only update layer if original isn't the bottom one
          if (originalLayer != calculatedLayers.availableDown) {
            // Find below layer and get 1 under it
            newLayer = calculatedLayers.availableDown;
          }
          // Still update layers
          updateLayerAboveTarget =
            (newLayer != null) ? newLayer : originalLayer;
          updateLayerAbove = true;
          break;
        case 'sendToBack':
          // Only update layer if original isn't the bottom one
          if (originalLayer != calculatedLayers.availableBottom) {
            // Find bottom layer and add 1 under it
            newLayer = calculatedLayers.availableBottom;
          }

          // Still update layers
          updateLayerAboveTarget =
            (newLayer != null) ? newLayer : originalLayer;
          updateLayerAbove = true;
          break;
      }

      // Update layer manager
      lD.viewer.layerManager.updateObjectLayer(
        layoutObject,
        newLayer,
        {
          widgetId: objAuxId,
          updateObjectsInFront: updateLayerAbove,
          updateObjectsInFrontTargetLayer: updateLayerAboveTarget,
        },
      );
    } else if (target.data('action') == 'Copy') {
      lD.duplicateObject(layoutObject);
    } else if (target.data('action') == 'editPlaylist') {
      // Open playlist editor
      lD.openPlaylistEditor(layoutObject.playlists.playlistId, layoutObject);
    } else if (target.data('action') == 'editFrame') {
      // Select widget frame to edit it
      const $viewerRegion =
        lD.viewer.DOMObject.find('#' + layoutObject.id);
      lD.selectObject({
        target: lD.viewer.DOMObject.find('#' + layoutObject.id),
      });
      lD.viewer.selectObject($viewerRegion);
    } else if (target.data('action') == 'Ungroup') {
      // Get widget
      const elementsWidget =
        lD.getObjectByTypeAndId('widget', objAuxId, 'canvas');

      // Canvas widget
      const canvasWidget = lD.getObjectByTypeAndId('canvasWidget');

      let saveCanvas = false;

      // Remove group from elements
      Object.values(layoutObject.elements).forEach((el) => {
        // Remove group from element
        el.groupId = undefined;
        delete el.group;

        // If there's a global element in a non global widget
        // move it to the global widget instead
        if (
          elementsWidget.subType != 'global' &&
          el.elementType === 'global'
        ) {
          // Make a copy and add to canvas
          canvasWidget.addElement(Object.assign({}, el), false);
          saveCanvas = true;

          // Delete old element
          elementsWidget.removeElement(el.elementId, {
            reloadLayerManager: false,
            removeFromViewer: false,
            save: false,
            reload: false,
          });
        }
      });

      // Remove group from widget
      delete elementsWidget.elementGroups[layoutObject.id];

      // Save elements for the target widget
      const saveElementsRequests = [
        elementsWidget.saveElements({
          forceRequest: true,
        }),
      ];

      // If we also need to save canvas
      if (saveCanvas) {
        saveElementsRequests.push(canvasWidget.saveElements({
          forceRequest: true,
        }));
      }

      // Save all requests and reload data
      Promise.all(saveElementsRequests).then((_res) => {
        // Deselect object
        lD.selectObject();

        // Reload data and select element when data reloads
        lD.reloadData(lD.layout,
          {
            refreshEditor: true,
          });
      });
    } else if (target.data('action') == 'newConfig') {
      const elementsToMove = [];
      const groupsToMove = [];

      // Get current widget
      const oldWidget =
        lD.getObjectByTypeAndId(
          'widget',
          objAuxId,
          'canvas',
        );

      // Get elements or groups to be moved
      if (layoutObject.type === 'element-group') {
        groupsToMove.push(layoutObject);
      } else {
        elementsToMove.push(layoutObject);
      }

      // Create new widget
      lD.addModuleToPlaylist(
        lD.layout.canvas.regionId,
        lD.layout.canvas.playlists.playlistId,
        oldWidget.subType,
        {
          type: layoutObject.type,
        },
        null,
        false,
        false,
        false,
        false,
      ).then((res) => {
        const widgetId = res.data.widgetId;
        // Reload data
        lD.reloadData(
          lD.layout,
          {
            reloadPropertiesPanel: false,
          },
        ).done(() => {
          // Get new widget
          const newWidget =
            lD.getObjectByTypeAndId(
              'widget',
              widgetId,
              'canvas',
            );

          // Move elements between widgets in canvas
          lD.layout.canvas.moveElementsBetweenWidgets(
            oldWidget.getFullId(),
            newWidget.getFullId(),
            elementsToMove,
            groupsToMove,
          );
        });
      });
    } else if (target.data('action') == 'editText') {
      lD.viewer.editText(layoutObject);
    } else if (target.data('action') == 'convertPlaylist') {
      lD.convertPlaylist(
        layoutObject.playlists.playlistId,
        layoutObject.playlists.name,
      );
    } else {
      const property = target.data('property');
      const propertyType = target.data('propertyType');

      // If we're editing permissions and it's a frame ( or zone )
      // edit the widget's permissions instead
      if (
        property === 'PermissionsWidget' &&
        layoutObject.type === 'region' &&
        (
          layoutObject.subType === 'frame' ||
          layoutObject.subType === 'zone'
        )
      ) {
        // Call edit for widget instead
        const regionWidget = Object.values(layoutObject.widgets)[0];
        regionWidget.editPropertyForm('Permissions');
      } else if (
        property === 'PermissionsCanvasWidget' &&
        (
          layoutObject.type === 'element' ||
          layoutObject.type === 'element-group'
        )
      ) {
        // Call edit for canvas widget instead
        const canvasWidget =
          lD.getObjectByTypeAndId('widget', objAuxId, 'canvas');
        canvasWidget.editPropertyForm('Permissions');
      } else {
        // Call normal edit form
        layoutObject.editPropertyForm(
          property,
          propertyType,
        );
      }
    }

    // Remove context menu
    lD.editorContainer.find('.context-menu-overlay').remove();

    // Unmark selected object as context menu is open
    $(obj).removeClass('contextMenuOpen');
  });
};

/**
 * Open object context menu for group of elements
 * @param {object} objs - Target objects
 * @param {object=} position - Page menu position
 */
lD.openGroupContextMenu = function(objs, position = {x: 0, y: 0}) {
  const objectsArray = $.makeArray(
    (objs) ? objs : lD.viewer.DOMObject.find('.selected'),
  );

  // Don't open context menu in read only mode
  if (lD.readOnlyMode) {
    return;
  }

  // Check if all elements are all elements
  // and belong to the same widget or to canvas
  let tempWidgetId;
  const canBeGrouped = objectsArray.every((el) => {
    // If it's not an element, return false
    if ($(el).data('type') != 'element') {
      return false;
    }

    // If it's an element inside a group, fail condition
    if (
      $(el).parent().hasClass('designer-element-group-elements')
    ) {
      return false;
    }

    // If it's not a global element
    // check if the widget is the same as the other elements
    if ($(el).data('elementType') != 'global') {
      if (tempWidgetId === undefined) {
        tempWidgetId = $(el).data('widgetId');
        return true;
      } else {
        // Check if it's the same widget
        return (tempWidgetId === $(el).data('widgetId'));
      }
    }

    return true;
  });

  // Check if element(s) can be added to group to group
  let groupCount = 0;
  let canBeAddedToGroup = true;
  tempWidgetId = undefined;
  for (let index = 0; index < objectsArray.length; index++) {
    const $item = $(objectsArray[index]);

    // If it's a group, count it
    ($item.hasClass('designer-element-group')) && groupCount++;

    // If we have more than one group, fail condition
    if (groupCount > 1) {
      canBeAddedToGroup = false;
      break;
    }

    // If it's a static widget (region), fail condition
    if ($item.data('type') === 'region') {
      canBeAddedToGroup = false;
      break;
    }

    // If it's not a global element
    // check if the widget is the same as the other elements
    if (
      $item.data('type') === 'element' &&
      $item.data('elementType') != 'global'
    ) {
      if (tempWidgetId === undefined) {
        tempWidgetId = $item.data('widgetId');
      } else {
        // Check if it's the same widget
        if (tempWidgetId != $item.data('widgetId')) {
          canBeAddedToGroup = false;
          break;
        }
      }
    }
  }

  // Check if we have exactly one group
  if (groupCount != 1) {
    canBeAddedToGroup = false;
  }

  // Check if all can be deleted ( have class deletable or editable )
  const canBeDeleted = objectsArray.every((el) => {
    return $(el).is('.deletable, .editable');
  });

  // Don't open context menu if we can't group or delete elements
  if (
    canBeGrouped === false &&
    canBeDeleted === false &&
    canBeAddedToGroup === false
  ) {
    return;
  }

  // Create menu and append to the designer div
  lD.editorContainer.append(
    contextMenuGroupTemplate({
      trans: contextMenuTrans,
      canBeGrouped: canBeGrouped,
      canBeDeleted: canBeDeleted,
      canBeAddedToGroup: canBeAddedToGroup,
    }),
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
    }
  });

  // Handle buttons
  lD.editorContainer.find('.context-menu .context-menu-btn').click((ev) => {
    const target = $(ev.currentTarget);

    if (target.data('action') == 'Delete') {
      lD.deleteMultipleObjects();
    } else if (target.data('action') == 'Group') {
      // Group elements
      const $elementsToBeGrouped =
        lD.viewer.DOMObject.find('.selected.designer-element');

      const elementsIds = [];

      // Check if not all elements are global typed
      let elementsType = 'global';
      const canvasWidget = lD.getObjectByTypeAndId('canvasWidget');
      let elementsWidget = canvasWidget;
      let saveCanvas = false;

      $elementsToBeGrouped.each((_idx, el) => {
        const elData = $(el).data();

        if (elementsType != elData.elementType) {
          elementsType = elData.elementType;
          elementsWidget = lD.getObjectByTypeAndId(
            'widget',
            'widget_' + elData.regionId + '_' + elData.widgetId,
            'canvas',
          );
          // Stop loop
          return false;
        }
      });

      // Generate a random group id
      const groupId = 'group_' + Math.floor(Math.random() * 1000000);

      // Create new group
      const newGroup = new ElementGroup(
        {
          id: groupId,
        },
        elementsWidget.widgetId,
        elementsWidget.regionId.split('_')[1],
        elementsWidget,
      );

      // Add group to widget
      elementsWidget.elementGroups[groupId] = newGroup;

      // Add group to all elements
      $elementsToBeGrouped.each((_idx, el) => {
        const elData = $(el).data();
        const elId = $(el).attr('id');

        elementsIds.push(elId);

        const element = lD.getObjectByTypeAndId(
          'element',
          elId,
          'widget_' + elData.regionId + '_' + elData.widgetId,
        );

        // Add group to element
        element.groupId = groupId;
        element.group = newGroup;

        // If element is type global and widget isn't type canvas
        // we need to move it to the new widget
        if (
          element.elementType === 'global' &&
          elementsWidget.subType != 'global'
        ) {
          // Make a copy of the element
          const copy = Object.assign({}, element);

          // Add to new widget
          elementsWidget.addElement(copy, false);

          // Delete old element
          canvasWidget.removeElement(element.elementId, {
            reloadLayerManager: false,
            removeFromViewer: false,
            save: false,
            reload: false,
          });
          saveCanvas = true;
        }

        // Add element to group
        newGroup.elements[elId] = element;
      });

      // Deselect all objects
      lD.selectObject();

      // Select new group
      lD.viewer.saveTemporaryObject(
        groupId,
        'element-group',
        {
          type: 'element-group',
          parentType: 'widget',
          widgetId: elementsWidget.widgetId,
          regionId: elementsWidget.regionId.split('_')[1],
        },
      );

      // Update group dimensions
      newGroup.updateGroupDimensions(false);

      // Save elements for the target widget
      const saveElementsRequests = [
        elementsWidget.saveElements({
          forceRequest: true,
        }),
      ];

      // If we also need to save canvas
      if (saveCanvas) {
        saveElementsRequests.push(canvasWidget.saveElements({
          forceRequest: true,
        }));
      }

      // Save all requests and reload data
      Promise.all(saveElementsRequests).then((_res) => {
        // Deselect object
        lD.selectObject();

        // Reload data and select element when data reloads
        lD.reloadData(
          lD.layout,
          {
            refreshEditor: true,
          },
        ).then(() => {
          // Move elements to the new group
          elementsIds.forEach((elId) => {
            const $el = lD.viewer.DOMObject.find('#' + elId);
            $el.appendTo(lD.viewer.DOMObject.find('#' + groupId));
          });
        });
      });
    } else if (target.data('action') == 'addToGroup') {
      // Elements
      const $elementsToBeGrouped =
        lD.viewer.DOMObject.find('.selected.designer-element');
      // Original group
      const $originalGroup =
        lD.viewer.DOMObject.find('.selected.designer-element-group');

      // Elements to be grouped and elements from group
      const $allElementsToBeGrouped = $elementsToBeGrouped.add(
        $originalGroup.find('.designer-element'),
      );

      // Check if not all items are global typed
      let elementsToBeAddedType = 'global';
      let elementToBeAddedWidget;
      $elementsToBeGrouped.each((_idx, el) => {
        const elData = $(el).data();

        if (elementsToBeAddedType != elData.elementType) {
          elementsToBeAddedType = elData.elementType;
          elementToBeAddedWidget = lD.getObjectByTypeAndId(
            'widget',
            'widget_' + elData.regionId + '_' +
            elData.widgetId,
            'canvas',
          );
          // Stop loop
          return false;
        }
      });


      // Target group widget comes from the original group
      let targetWidget = lD.getObjectByTypeAndId(
        'widget',
        'widget_' + $originalGroup.data('regionId') + '_' +
          $originalGroup.data('widgetId'),
        'canvas',
      );

      // Get original group id and type
      const originalGroupId = $originalGroup.prop('id');
      const originalGroupType = $originalGroup.data('elementType');

      // Flag to see if we need to save original widget
      let saveOriginalWidget = false;
      let widgetToSave;

      // Get group from elements
      let targetGroup;

      // If original group type is global
      // but elements are not, we need to move group between widgets
      if (
        originalGroupType === 'global' &&
        elementsToBeAddedType != 'global'
      ) {
        // Get group from original widget
        targetGroup = targetWidget.elementGroups[originalGroupId];

        // Add group to target widget
        targetGroup.regionId = elementToBeAddedWidget.regionId.split('_')[1];
        targetGroup.widgetId = elementToBeAddedWidget.widgetId;
        elementToBeAddedWidget.elementGroups[originalGroupId] = targetGroup;

        // Remove group from original widget
        delete targetWidget.elementGroups[originalGroupId];

        // Change target widget
        targetWidget = elementToBeAddedWidget;
      }

      // Get target group
      targetGroup = targetWidget.elementGroups[originalGroupId];

      // Add group to elements
      $allElementsToBeGrouped.each((_idx, el) => {
        const elData = $(el).data();
        const elId = $(el).attr('id');

        const element = lD.getObjectByTypeAndId(
          'element',
          elId,
          'widget_' + elData.regionId + '_' + elData.widgetId,
        );

        // Add group to element
        element.groupId = originalGroupId;
        element.group = targetGroup;

        // Element widget
        const elementsWidget = lD.getObjectByTypeAndId(
          'widget',
          'widget_' + elData.regionId + '_' + elData.widgetId,
          'canvas',
        );

        // If element's widget is different from the target
        // we need to move it to the new one and remove it from the original
        if (
          targetWidget.id != elementsWidget.id
        ) {
          // Make a copy of the element
          const copy = Object.assign({}, element);

          // Add to target widget
          targetWidget.addElement(copy, false);

          // Remove from original widget
          elementsWidget.removeElement(element.elementId, {
            reloadLayerManager: false,
            removeFromViewer: false,
            save: false,
            reload: false,
          });

          // Save original widget
          saveOriginalWidget = true;
          widgetToSave = elementsWidget;
        }

        // Add element to group
        targetGroup.elements[elId] = element;
      });

      // Deselect all objects
      lD.selectObject();

      // Change group properties so it can be the selected object on reload
      lD.selectedObject.id = targetGroup.id;
      lD.selectedObject.type = 'element-group';
      const $groupInViewer =
        lD.viewer.DOMObject.find('.designer-element-group#' + targetGroup.id);
      $groupInViewer.data('widgetId', targetGroup.widgetId);
      $groupInViewer.data('regionId', targetGroup.regionId);

      // Update group dimensions
      targetGroup.updateGroupDimensions(false);

      // Save elements for the target widget
      const saveElementsRequests = [
        targetWidget.saveElements({
          forceRequest: true,
        }),
      ];

      // If we also need to save canvas
      if (saveOriginalWidget) {
        saveElementsRequests.push(widgetToSave.saveElements({
          forceRequest: true,
        }));
      }

      // Save all requests and reload data
      Promise.all(saveElementsRequests).then((_res) => {
        // Deselect object
        lD.selectObject();

        // Reload data and select element when data reloads
        lD.reloadData(lD.layout,
          {
            refreshEditor: true,
          });
      });
    }

    // Remove context menu
    lD.editorContainer.find('.context-menu-overlay').remove();
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
        window.location.reload();
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
      $customOverlay.attr('id', 'lockedOverlay')
        .removeClass('custom-overlay')
        .addClass('custom-overlay-clone locked').show();
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

      // Redirect to the layout or template grid
      window.location.href = lD.exitURL;
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
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
      const actionTargetType =
        (lastAction.target.subType) ?
          lastAction.target.subType :
          lastAction.target.type;

      undoActiveTitle =
        historyManagerTrans.revert[lastAction.type]
          .replace('%target%', historyManagerTrans.target[actionTargetType]);
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
 * Import from provider
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

  // Get item type, if not image/audio/video, set it as library
  const itemType =
    ['image', 'audio', 'video'].indexOf(itemsResult[0].type) == -1 ?
      'library' : itemsResult[0].type;
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

        // Empty toolbar content for this type of media
        // so it can be reloaded
        const menuId = lD.toolbar.getMenuIdFromType(itemType);
        lD.toolbar.DOMObject.find('#content-' + menuId).empty();

        resolve(itemsResult);
      } else {
        lD.common.hideLoadingScreen();

        // Login Form needed?
        if (res.login) {
          window.location.reload();
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
 * @return {Promise}
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

  return new Promise(function(resolve, reject) {
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

        resolve();
      },
      fail: function() {
        reject();
      },
    });
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

  // Calculate next available top layer
  const topLayer = lD.calculateLayers().availableTop;

  // Add layer to the next top layer
  dimensions.zIndex = (dimensions.zIndex) ?
    (dimensions.zIndex + topLayer) :
    topLayer;

  // If region type is not defined, use the default (frame)
  if (regionType == undefined) {
    regionType = 'frame';
  }

  return lD.layout.addObject(
    'region',
    {
      positionToAdd: positionToAdd,
      objectSubtype: regionType,
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
    lD.refreshEditor({
      reloadToolbar: false,
      reloadViewer: true,
      reloadPropertiesPanel: true,
    });

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
      const loadedData = JSON.parse(res.data.value ?? '{}');
      if (loadedData.snapOptions) {
        self.viewer.moveableOptions = loadedData.snapOptions;

        // Update moveable options
        self.viewer.updateMoveableOptions({
          savePreferences: false,
        });

        // Update moveable UI
        self.viewer.updateMoveableUI();
      }

      if (loadedData.layerManagerOptions) {
        // Render layer manager
        self.viewer.layerManager
          .setVisible(loadedData.layerManagerOptions.visible, false);
      }
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
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
lD.savePrefs = _.debounce(function(clearPrefs = false) {
  // Clear values to defaults
  if (clearPrefs) {
    console.debug('Clearing user preferences');
  }

  // Data to be saved
  const dataToSave = {
    preference: [
      {
        option: 'editor',
        value: JSON.stringify({
          snapOptions: lD.viewer.moveableOptions,
          layerManagerOptions: {
            visible: lD.viewer.layerManager.visible,
          },
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
        window.location.reload();
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
}, 200);

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
          window.location.reload();
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
        openActionTab: _res?.data?.actionId,
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
        self.propertiesPanel.DOMObject.find('.item-actions'),
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
  const getPlaylists = filters.indexOf('playlist') !== -1;
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
    // If there's no typeInput, stop
    if (!$typeInput) {
      return;
    }

    const $form = $typeInput.parents('form');
    // If input is target, and widgetId has value
    // then update the widget drawer edit element
    const $widgetIDInput = $form.find('[name=widgetId]');

    let typeInputValue = $dropdown.find(':selected').data('type');

    // Update targetId and target
    // Input fields are prepended with input_
    if (
      $typeInput.attr('id') === 'input_target'
    ) {
      const dropdownVal = $dropdown.val();
      // Update targetId and target
      actionData.targetId = dropdownVal;
      actionData.target = typeInputValue;

      // Update also on form
      $form.data('targetId', dropdownVal);
      $form.data('target', typeInputValue);
    }

    // Update sourceId and source
    if (
      $typeInput.attr('id') === 'input_source'
    ) {
      const dropdownVal = $dropdown.val();
      // Update sourceId and source
      actionData.sourceId = dropdownVal;
      actionData.source = typeInputValue;

      // Update also on form
      $form.data('sourceId', dropdownVal);
      $form.data('source', typeInputValue);
    }

    // Update widgetId
    if (
      $widgetIDInput.length > 0 &&
      $widgetIDInput.val() != '' &&
      getDrawerWidgets
    ) {
      // Call update widget drawer edit element
      handleEditWidget($widgetIDInput.val());
    }

    // For target, if target is layout, change it to screen
    if ($typeInput.attr('id') === 'input_target' &&
      typeInputValue === 'layout'
    ) {
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
    const $dropdownParent = $dropdown.parent();

    const removeDeleteButton = function() {
      // Remove delete widget button
      $dropdown.siblings('.delete-widget-btn').remove();

      // Remove class from container
      $dropdownParent.removeClass('delete-active');
    };

    if (dropdownValue === 'create') {
      // Create new
      lD.viewer.addActionEditArea(actionData, 'create');

      removeDeleteButton();
    } else if (dropdownValue != '' && dropdownValue != null) {
      // Update action widget data
      actionData.widgetId = dropdownValue;

      // Edit existing
      lD.viewer.addActionEditArea(actionData, 'edit');

      // Show delete button on dropdown
      if ($dropdown.siblings('.delete-widget-btn').length === 0) {
        const $deleteBtn = $(
          '<div class="btn btn-danger delete-widget-btn" title="' +
          editorsTrans.actions.deleteWidget + '">' +
          editorsTrans.actions.deleteWidget +
          '</div>',
        ).on('click', function() {
          const widgetId = $dropdown.val();
          const drawerId = lD.getObjectByTypeAndId('drawer').regionId;

          removeDeleteButton();

          lD.deleteObject(
            'widget',
            widgetId,
            drawerId,
            true,
          );
        });

        // Append to dropdown parent
        $dropdownParent.append($deleteBtn);

        // Add class to container
        $dropdownParent.addClass('delete-active');
      }
    } else {
      // Remove edit area
      lD.viewer.removeActionEditArea();

      removeDeleteButton();
    }
  };

  // Clear dropdown
  $dropdown.find('option:not([value=""]):not([value="create"])').remove();

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

  // Playlists
  if (getPlaylists) {
    addGroupToDropdown(
      editorsTrans.actions.playlists,
    );
  }

  // Get regions and/or widgets
  if (getRegions || getWidgets || getPlaylists) {
    for (const region of Object.values(lD.layout.regions)) {
      if (
        (
          getRegions &&
          region.isPlaylist === false
        ) ||
        (
          getPlaylists &&
          region.isPlaylist === true
        )
      ) {
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
    $dropdown.val(value).trigger('change');
    updateTypeValue();

    if (getDrawerWidgets) {
      handleEditWidget($dropdown.val());
    }
  }

  // Handle dropdown change
  // and update type
  $dropdown.off('change').on('change', function() {
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
 * @param {boolean=} actionEditMode - Enter edit mode
 */
lD.editDrawerWidget = function(actionData, actionEditMode = true) {
  // 1. Detach actions form to a temporary container or body
  lD.propertiesPanel.detachActionsForm();

  // 2. Open property panel with drawer widget
  const widget = lD.getObjectByTypeAndId(
    'widget',
    'widget_' + lD.layout.drawer.regionId + '_' + actionData.widgetId,
    'drawer',
  );

  // 3. Select widget
  const $widgetInViewer = lD.viewer.DOMObject
    .find('#widget_' + lD.layout.drawer.regionId + '_' + actionData.widgetId);

  // Save previous selected object
  lD.previousSelectedObject = lD.selectedObject;

  // Target
  const $target = actionEditMode ? $widgetInViewer : null;

  lD.selectObject({
    target: $target,
    forceSelect: true,
  });

  // Select element in viewer
  lD.viewer.selectObject($target);

  // 4. Open property panel with drawer widget or same object
  lD.propertiesPanel.render(
    actionEditMode ? widget : lD.previousSelectedObject,
    actionEditMode,
    true,
  );
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

/**
 * Add elements to widget
 * @param {object[]} elements - One or more elements to be added
 * @param {widget} widget - Target widget
 * @param {boolean} isGroup
 * @param {boolean} addingToExistingGroup
 *  - Adding to existing group, we need to recalculate group dimensions
 */
lD.addElementsToWidget = function(
  elements,
  widget,
  isGroup = false,
  addingToExistingGroup = false,
) {
  // Calculate next available global top layer
  let topLayer = lD.calculateLayers(null, true).availableTop;

  // Add element promise array
  const addElementPromise = [];

  // Loop through elements
  elements.forEach((element) => {
    // Check if first element has a group
    if (
      addingToExistingGroup &&
      element.groupId
    ) {
      // Get group
      const widgetGroup = widget.elementGroups[
        element.groupId
      ];

      // Calculate next available global top layer
      topLayer = lD.calculateLayers(
        null,
        true,
        (widgetGroup) ? widgetGroup.elements : null,
      ).availableTop;
    }

    // Add only if elements doesn't exist on widget already
    if (!(
      element.elementId &&
      widget.elements[element.elementId]
    )) {
      // Create a unique id for the element
      element.elementId =
        'element_' + element.id + '_' +
        Math.floor(Math.random() * 1000000);

      // Add top layer to the group properties
      if (
        element.groupId
      ) {
        (element.groupProperties) &&
         (element.groupProperties.layer = topLayer);
      } else {
        // Add element to the top layer
        element.layer = topLayer;
      }

      // Add element to the widget and push to array
      addElementPromise.push(widget.addElement(element, false));
    }
  });

  // Recalculate group dimensions
  if (addingToExistingGroup) {
    // Get group
    const widgetGroup = widget.elementGroups[
      elements[0].groupId
    ];

    widgetGroup.updateGroupDimensions();
  }

  // Save JSON with new element into the widget
  // after all the promises are completed
  Promise.all(addElementPromise).then(() => {
    widget.saveElements().then((_res) => {
      const firstElement = elements[0];

      if (firstElement.skipSelect) {
        lD.selectedObject = lD.layout;
        lD.selectedObject.type = 'layout';
        lD.selectedObject.id = lD.layout.id;
      } else if (isGroup && elements.length > 1 && !firstElement.skipSelect) {
        // If it's group with more than one element being added
        // select group
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
            selectInGroupEdit: isGroup,
            widgetId: widget.widgetId,
            regionId: widget.regionId.split('_')[1],
          },
        );
      }

      // Reload data and select element when data reloads
      lD.reloadData(
        lD.layout,
        {
          refreshEditor: true,
          resetPropertiesPanelOpenedTab: true,
        },
      ).then(() => {
        const widgetAux = lD.layout.canvas.widgets[widget.id];
        // Recalculate required elements
        widgetAux.validateRequiredElements();

        // Update viewer to revalidate all elements
        lD.viewer.update();
      });
    });
  });
};


/**
 * Convert playlist into global
 * @param {string} playlistId
 * @param {string} playlistName
 */
lD.convertPlaylist = function(playlistId, playlistName) {
  if (playlistName === undefined || playlistName === '') {
    toastr.error(
      errorMessagesTrans.convertPlaylistFailed +
      ' ' + errorMessagesTrans.convertPlaylistNoName);
    return;
  }

  // Load form the API
  const linkToAPI = urlsForApi.playlist.convert;

  let requestPath = linkToAPI.url;

  // Replace ID
  if (playlistId != null) {
    requestPath = requestPath.replace(':id', playlistId);
  }

  lD.common.showLoadingScreen('convertPlaylist');

  // Request and load element form
  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      // Show success message
      toastr.info(editorsTrans.convertPlaylistSuccess);

      // Reload editor
      lD.reloadData(lD.layout,
        {
          refreshEditor: true,
        });
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.href = window.location.href;
        location.reload();
      } else {
        // Just an error we dont know about
        if (res.message == undefined) {
          console.error(res);
          toastr.error(errorMessagesTrans.convertPlaylistFailed);
        } else {
          console.error(res.message);
          toastr.error(
            errorMessagesTrans.convertPlaylistFailed + ' ' + res.message,
          );
        }
      }
    }

    lD.common.hideLoadingScreen('convertPlaylist');
  }).catch(function(jqXHR, textStatus, errorThrown) {
    lD.common.hideLoadingScreen('convertPlaylist');
    console.error(jqXHR, textStatus, errorThrown);
    toastr.error(errorMessagesTrans.convertPlaylistFailed);
  });
};

/**
 * Calculate top layer
 * @param {number} baseLayer - Base layer to be compared
 * @param {boolean=} calculateInCanvas
 *  - Calculate layers only for canvas
 * @param {object=} groupElements - Calculate only for group elements
 * @return {object} Calculated layers
 */
lD.calculateLayers = function(
  baseLayer,
  calculateInCanvas = false,
  groupElements,
) {
  const self = this;

  const limits = {
    bottom: 0,
    top: 1000,
  };

  // Reset layer map
  const layerMap = [];

  // Target layer
  const calculatedLayers = {
    top: null,
    bottom: null,
    availableTop: null,
    availableUp: null,
    availableDown: null,
    availableBottom: null,
    shift: {
      position: null,
      direction: null, // 0- down, 1- up
    },
  };

  const addObjToLayerMap = function(layer, objType, objId) {
    if (
      layerMap[layer] == undefined
    ) {
      layerMap[layer] = [];
    }

    layerMap[layer].push({
      id: objId,
      type: objType,
    });
  };

  const checkAndSaveLayer = function(layer, objType, objId) {
    if (
      calculatedLayers.top === null ||
      layer > calculatedLayers.top) {
      calculatedLayers.top = layer;
    }

    if (
      calculatedLayers.bottom === null ||
      layer < calculatedLayers.bottom
    ) {
      calculatedLayers.bottom = layer;
    }

    // Save to layer map
    addObjToLayerMap(layer, objType, objId);
  };

  // Calculate only for elements in a group
  if (groupElements) {
    Object.values(groupElements).forEach((element) => {
      const elementLayer = Number(element.layer);

      checkAndSaveLayer(elementLayer, 'element', element.elementId);
    });
  } else if (calculateInCanvas) {
    // Calculate only for canvas elements
    if (lD.layout.canvas.widgets) {
      Object.values(lD.layout.canvas.widgets).forEach((widget) => {
        // Elements
        Object.values(widget.elements).forEach((element) => {
          // Only calculate if it's a group-less element
          if (
            element.groupId === '' ||
            element.groupId === undefined
          ) {
            const elementLayer = Number(element.layer);
            checkAndSaveLayer(elementLayer, 'element', element.elementId);
          }
        });

        // Element groups
        Object.values(widget.elementGroups).forEach((elementGroup) => {
          const elementGroupLayer = Number(elementGroup.layer);
          checkAndSaveLayer(elementGroupLayer, 'elementGroup', elementGroup.id);
        });
      });
    }
  } else {
    // Add canvas layer
    if (!$.isEmptyObject(lD.layout.canvas)) {
      checkAndSaveLayer(lD.layout.canvas.zIndex, 'region', lD.layout.canvas.id);
    }

    // Check regions layers
    Object.values(lD.layout.regions).forEach((region) => {
      const regionLayer = Number(region.zIndex);
      checkAndSaveLayer(regionLayer, 'region', region.id);
    });

    // Save layerMap to the global var
    self.layerMap = layerMap;
  }

  // Find if the element is already the only one on top and bottom layers
  const isSingleOnTopLayer =
    (baseLayer === undefined || baseLayer === null) ? false : (
      layerMap[calculatedLayers.top].length === 1 &&
      calculatedLayers.top === baseLayer
    );

  const isSingleOnBottomLayer =
    (baseLayer === undefined || baseLayer === null) ? false : (
      layerMap[calculatedLayers.bottom].length === 1 &&
      calculatedLayers.bottom === baseLayer
    );

  // Find the next available layer at top
  if (
    layerMap[calculatedLayers.top + 1] === undefined &&
    !isSingleOnTopLayer
  ) {
    // If we don't have any layers yet, set to 0
    if (layerMap.length === 0) {
      calculatedLayers.availableTop = 0;
    } else {
      // Set top value, but not over the limit
      calculatedLayers.availableTop = Math.min(
        (calculatedLayers.top + 1),
        limits.top,
      );
    }
  }

  // Find the next available layer at bottom
  if (
    layerMap[calculatedLayers.bottom - 1] === undefined &&
    !isSingleOnBottomLayer
  ) {
    // Set bottom value, but not below the limit
    calculatedLayers.availableBottom = Math.max(
      (calculatedLayers.bottom - 1),
      limits.bottom,
    );
  }

  // If we have a base layer target...
  if (baseLayer != undefined) {
    // Find the item above the baseLayer, and get layer over it
    for (let layer = baseLayer + 1; layer < layerMap.length; layer++) {
      if (layerMap[layer] != undefined) {
        calculatedLayers.availableUp = Math.min(
          (layer + 1),
          limits.top,
        );
        break;
      }
    }

    // Find the item below the baseLayer, and get layer under it
    for (let layer = baseLayer - 1; layer >= 0; layer--) {
      if (layerMap[layer] != undefined) {
        calculatedLayers.availableDown = Math.max(
          (layer - 1),
          limits.bottom,
        );
        break;
      }
    }
  }

  // Return calculated layers
  return calculatedLayers;
};

/**
 * Handle inputs
 */
lD.handleInputs = function() {
  const allowInputs = (
    lD.readOnlyMode == false &&
    lD.playlistEditorOpened === false
  );

  // Handle keyboard keys
  $('body').off('keydown.editor')
    .on('keydown.editor', function(handler) {
      const controlOrCommandPressed = (
        handler.ctrlKey ||
        handler.metaKey
      );

      if ($(handler.target).is($('body'))) {
        // Delete ( Del or Backspace )
        if (
          (
            handler.key == 'Delete' ||
            handler.key == 'Backspace'
          ) &&
          allowInputs
        ) {
          lD.deleteSelectedObject();
        }

        // Undo ( Ctrl + Z )
        if (
          handler.code == 'KeyZ' &&
          controlOrCommandPressed &&
          allowInputs
        ) {
          lD.undoLastAction();
        }

        // Duplicate selected object ( Shift + D )
        if (
          handler.code == 'KeyD' &&
          handler.shiftKey &&
          allowInputs
        ) {
          lD.duplicateSelectedObject();
        }
      }
    });
};
