/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
// Include public path for webpack
require('../../public_path');

// Include handlebars templates
const playlistEditorTemplate =
  require('../templates/playlist-editor.hbs');
const playlistEditorExternalContainerTemplate =
  require('../templates/playlist-editor-external-container.hbs');
const messageTemplate = require('../templates/message.hbs');
const loadingTemplate = require('../templates/loading.hbs');
const contextMenuTemplate = require('../templates/context-menu.hbs');

// Include modules
const Playlist = require('../playlist-editor/playlist.js');
const PlaylistTimeline = require('../playlist-editor/playlist-timeline.js');
const Toolbar = require('../editor-core/toolbar.js');
const PropertiesPanel = require('../editor-core/properties-panel.js');
const HistoryManager = require('../editor-core/history-manager.js');
const TemplateManager = require('../layout-editor/template-manager.js');
const topbarTemplatePlaylistEditor =
  require('../templates/topbar-playlist-editor.hbs');

// Include CSS
if (typeof lD == 'undefined') {
  // Include the layout designer code if we're in the playlist editor only
  require('../style/common.scss');
  require('../style/layout-editor.scss');
  require('../style/toolbar.scss');
  require('../style/topbar.scss');
}

require('../style/playlist-editor.scss');

// Common funtions/tools
const Common = require('../editor-core/common.js');

// Create layout designer namespace (pE)
window.pE = {

  // Attach common functions to layout designer
  common: Common,

  // Main object info
  mainObjectType: 'playlist',
  mainObjectId: '',

  // Playlist
  playlist: {},

  // Editor DOM div
  editorContainer: {},

  // Timeline
  timeline: {},

  // Properties Panel
  propertiesPanel: {},

  // History Manager
  historyManager: {},

  // Template manager
  templateManager: {},

  // Selected object
  selectedObject: {},

  // Bottom toolbar
  toolbar: {},

  // folderId
  folderId: '',

  // inline playlist editor?
  inline: false,
};

/**
 * Load Playlist and build app structure
 * @param {string} inline - Is this an inline playlist editor?
 */
pE.loadEditor = function(inline = false) {
  // Add class to body so we can use CSS specifically on it
  (!inline) && $('body').addClass('editor-opened');

  pE.common.showLoadingScreen();

  // Save and change toastr positioning
  pE.toastrPosition = toastr.options.positionClass;
  toastr.options.positionClass = 'toast-top-center';

  // Get DOM main object
  pE.editorContainer = $('#playlist-editor');

  // If the editor is being loaded from within
  // the layout designer, change the region specific flag
  pE.regionSpecificQuery = '';

  if (inline) {
    pE.regionSpecificQuery = '&regionSpecific=1';
    pE.mainRegion =
      pE.editorContainer.parents('#editor-container').data('regionObj');
    pE.inline = true;
  }

  // Get playlist id
  const playlistId = pE.editorContainer.attr('playlist-id');

  // Update main object id
  pE.mainObjectId = playlistId;

  // Show loading template
  pE.editorContainer.html(loadingTemplate());

  // Load playlist through an ajax request
  $.get(
    urlsForApi.playlist.get.url +
    '?playlistId=' +
    playlistId +
    '&embed=widgets,widget_validity,tags,permissions' +
    pE.regionSpecificQuery,
  )
    .done(function(res) {
      if (res.data != null && res.data.length > 0) {
        // Template type
        const template = inline ?
          playlistEditorTemplate :
          playlistEditorExternalContainerTemplate;

        // Append layout html to the main div
        pE.editorContainer.html(
          template(
            {
              trans: {...playlistEditorTrans, ...editorsTrans},
            },
          ),
        );

        // Initialize template manager
        pE.templateManager = new TemplateManager(
          pE,
        );

        // Initialize timeline and create data structure
        pE.playlist = new Playlist(playlistId, res.data[0]);
        // folder Id
        pE.folderId = pE.playlist.folderId;

        // Initialize properties panel
        // TODO: Initialise in a new container or in the existing one
        pE.propertiesPanel = new PropertiesPanel(
          pE,
          pE.editorContainer.parents('.container-designer')
            .find('.properties-panel'),
        );

        // Initialize timeline
        pE.timeline = new PlaylistTimeline(
          pE.editorContainer.find('#playlist-timeline'),
        );

        // Append manager to the modal container
        $('#layout-manager').appendTo('#playlist-editor');

        // Initialize manager
        if (typeof lD != 'undefined') {
          pE.historyManager = lD.historyManager;
        } else {
          pE.historyManager = new HistoryManager(
            pE,
            $('#playlist-editor').find('#layout-manager'),
            false, // (serverMode == 'Test') Turn of manager visibility for now
          );
        }

        // Initialize toolbar
        pE.toolbar = new Toolbar(
          pE,
          pE.editorContainer.parents('.container-designer')
            .find('.editor-side-bar'),
          {
            deleteSelectedObjectAction: pE.deleteSelectedObject,
          },
          true,
        );
        pE.toolbar.parent = pE;

        // Add topbar
        pE.editorContainer.parents('.container-designer')
          .find('.editor-top-bar')
          .html(topbarTemplatePlaylistEditor({
            trans: editorsTrans,
            playlist: pE.playlist,
          }));

        // Default selected
        pE.selectObject();

        // Setup helpers
        formHelpers.setup(pE, pE.playlist);

        // Handle inputs
        pE.handleInputs();

        // Load user preferences
        pE.loadAndSavePref('useLibraryDuration', 0);

        // Reload toolbar
        pE.toolbar.render();

        pE.common.hideLoadingScreen();

        // Handle editor close button
        pE.editorContainer.find('#closePlaylistEditorBtn')
          .on('click', function() {
            pE.close();
          });
      } else {
        // Login Form needed?
        if (res.login) {
          window.location.href = window.location.href;
          location.reload();
        } else {
          pE.showErrorMessage();
        }
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
      // Output error to console
      console.error(jqXHR, textStatus, errorThrown);

      pE.showErrorMessage();
    });
};

/**
 * Select a playlist object (playlist/widget)
 * @param {object=} target - Object to be selected
 * @param {number=} [positionToAdd = null] - Order position for widget
 * @param {bool=} reloadPropertiesPanel - Force properties panel reload
 */
pE.selectObject = function({
  target = null,
  positionToAdd = null,
  reloadPropertiesPanel = true,
} = {}) {
  // Clear rogue tooltips
  pE.common.clearTooltips();

  // If there is a selected card, use the
  // drag&drop simulate to add that item to a object
  if (!$.isEmptyObject(this.toolbar.selectedCard)) {
    if (
      target.data('type') == 'playlist'
    ) {
      // Get card object
      const card = this.toolbar.selectedCard[0];

      // Deselect cards and drop zones
      this.toolbar.deselectCardsAndDropZones();

      // Simulate drop item add
      this.dropItemAdd(target, card, {positionToAdd: positionToAdd});
    }
  } else if (!$.isEmptyObject(this.toolbar.selectedQueue)) {
    // If there's a selected queue, use the drag&drop
    // simulate to add those items to a object
    if (target.data('type') == 'region') {
      pE.importFromProvider(this.toolbar.selectedQueue).then((res) => {
        // Add media queue to playlist
        this.playlist.addMedia(res, positionToAdd);
      }).catch(function() {
        toastr.error(errorMessagesTrans.importingMediaFailed);
      });
    }

    // Deselect cards and drop zones
    this.toolbar.deselectCardsAndDropZones();
  } else {
    // Get object properties from the DOM ( or set to layout if not defined )
    const noTarget =
      (target == null || typeof target.data('type') == 'undefined');
    const newSelectedId = (noTarget) ? null : target.attr('id');
    const newSelectedType = (noTarget) ? null : target.data('type');
    const oldSelectedId = this.selectedObject.id;
    const oldSelectedType = this.selectedObject.type;

    // If the selected object was different from the previous
    // and we are focused on a properties panel field, save before continuing
    if (
      (
        oldSelectedId != newSelectedId ||
        oldSelectedType != newSelectedType
      ) && this.propertiesPanel.toSave
    ) {
      // Set flag back to false
      this.propertiesPanel.toSave = false;

      // Save previous element
      this.propertiesPanel.save({
        target: this.selectedObject, // Save previous object
        callbackNoWait: function() {
          // Select object again, with the same params
          pE.selectObject({
            target: target,
            positionToAdd: positionToAdd,
            reloadPropertiesPanel: reloadPropertiesPanel,
          });
        },
      });

      // Prevent current select to continue
      return;
    }

    // If the selected object was different from the previous
    // or we force select
    // select a new one
    if (
      oldSelectedId != newSelectedId ||
      oldSelectedType != newSelectedType ||
      noTarget
    ) {
      // Unselect the previous selectedObject object if still selected
      if (this.selectedObject.selected) {
        if (this.selectedObject.type == 'widget') {
          if (this.playlist.widgets[this.selectedObject.id]) {
            this.playlist.widgets[this.selectedObject.id].selected = false;
          }
        }
      }

      // If there are no objects to select
      if ($.isEmptyObject(pE.playlist.widgets)) {
        this.selectedObject = {};
      } else if (noTarget) {
        // If we don't have a target, select the first object in the playlist
        // Select first widget
        const newId = Object.keys(this.playlist.widgets)[0];

        this.playlist.widgets[newId].selected = true;
        this.selectedObject.type = 'widget';
        this.selectedObject = this.playlist.widgets[newId];
      } else {
        // Select new object
        if (newSelectedType === 'widget') {
          this.playlist.widgets[newSelectedId].selected = true;
          this.selectedObject = this.playlist.widgets[newSelectedId];
        }

        this.selectedObject.type = newSelectedType;
      }

      // Refresh the designer containers
      pE.refreshEditor({
        reloadPropertiesPanel: reloadPropertiesPanel,
      });
    }
  }
};

/**
 * Add action to take after dropping a draggable item
 * @param {object} droppable - Target drop object
 * @param {object} card - Target Card
 * @param {object =} [options] - Options
 * @param {object/number=} [options.positionToAdd = null]
 *  - order position for widget
 */
pE.dropItemAdd = function(droppable, card, {positionToAdd = null} = {}) {
  this.playlist.addObject(droppable, card, positionToAdd);
};

/**
 * Revert last action
 */
pE.undoLastAction = function() {
  pE.common.showLoadingScreen();

  pE.historyManager.revertChange().then((res) => { // Success
    pE.common.hideLoadingScreen();
    // Refresh designer according to local or API revert
    if (res.localRevert) {
      pE.refreshEditor();
    } else {
      pE.reloadData();
    }
  }).catch((error) => { // Fail/error
    pE.common.hideLoadingScreen();

    // Show error returned or custom message to the user
    let errorMessage = '';

    if (typeof error == 'string') {
      errorMessage = error;
    } else {
      errorMessage = error.errorThrown;
    }

    toastr.error(errorMessagesTrans.revertFailed
      .replace('%error%', errorMessage));
  });
};

/**
 * Delete selected object
 */
pE.deleteSelectedObject = function() {
  if (pE.editorContainer.hasClass('multi-select')) {
    // Get selected widgets
    const selectedWidgetsIds = [];

    pE.timeline.DOMObject.find('.playlist-widget.multi-selected')
      .each(function(_key, el) {
        selectedWidgetsIds.push($(el).data('widgetId'));
      });

    pE.deleteMultipleObjects('widget', selectedWidgetsIds);
  } else {
    pE.deleteObject(
      pE.selectedObject.type,
      pE.selectedObject[pE.selectedObject.type + 'Id'],
    );
  }
};

/**
 * Delete object
 * @param {string} objectType
 * @param {number} objectId
 */
pE.deleteObject = function(objectType, objectId) {
  pE.common.showLoadingScreen('deleteObject');

  // Delete object from the layout
  pE.playlist.deleteObject(objectType, objectId)
    .then((_res) => {
      // Success
      pE.common.hideLoadingScreen('deleteObject');

      // Remove selected object if the deleted was selected
      if (pE.selectedObject.widgetId === objectId) {
        pE.selectedObject = {};
      }

      // Reload data
      pE.reloadData();
    }).catch((error) => { // Fail/error
      pE.common.hideLoadingScreen('deleteObject');

      // Show error returned or custom message to the user
      let errorMessage = '';

      if (typeof error == 'string') {
        errorMessage = error;
      } else {
        errorMessage = error.errorThrown;
      }

      toastr.error(errorMessagesTrans.deleteFailed
        .replace('%error%', errorMessage));
    });
};

/**
 * Delete multiple objects
 * @param {string} objectsType - Type of objects to delete
 * @param {string[]} objectIds - Object ids to delete
 */
pE.deleteMultipleObjects = function(objectsType, objectIds) {
  if (objectsType === 'widget') {
    pE.common.showLoadingScreen('deleteObjects');

    let deletedIndex = 0;

    const deleteNext = function() {
      const widgetId = objectIds[deletedIndex];

      // Leave multi select mode
      pE.toggleMultiselectMode(false);

      // Delete object from the playlist
      pE.playlist.deleteObject('widget', widgetId)
        .then((_res) => { // Success
          deletedIndex++;

          if (deletedIndex == objectIds.length) {
            // Hide loading screen
            pE.common.hideLoadingScreen('deleteObjects');

            // Remove selected object if it's one in the objectIds
            if (
              objectIds.indexOf(pE.selectedObject.widgetId) > -1
            ) {
              pE.selectedObject = {};
            }

            // Reload data
            pE.reloadData();

            // Hide/close modal
            bootbox.hideAll();
          } else {
            deleteNext();
          }
        }).catch((error) => { // Fail/error
          pE.common.hideLoadingScreen('deleteObjects');

          // Show error returned or custom message to the user
          let errorMessage = '';

          if (typeof error == 'string') {
            errorMessage = error;
          } else {
            errorMessage = error.errorThrown;
          }

          toastr.error(errorMessagesTrans.deleteFailed
            .replace('%error%', errorMessage));

          // Reload data
          pE.reloadData();

          // Hide/close modal
          bootbox.hideAll();
        });
    };

    // Start deleting
    deleteNext();
  }
};

/**
 * Refresh designer
 * @param {boolean} [reloadToolbar=false] - Reload toolbar
 * @param {boolean} [reloadPropertiesPanel=false] - Reload properties panel
 */
pE.refreshEditor = function(
  {
    reloadToolbar = false,
    reloadPropertiesPanel = false,
  } = {},
) {
  // if we don't have a selected widget
  // and there's at least one on the playlist, select the first one
  if (
    $.isEmptyObject(this.selectedObject) &&
    Object.values(this.playlist.widgets).length > 0
  ) {
    this.selectedObject = Object.values(this.playlist.widgets)[0];
    this.selectedObject.selected = true;
  }

  // Remove temporary data
  (reloadPropertiesPanel) && this.clearTemporaryData();

  // Render containers
  (reloadToolbar) && this.toolbar.render();
  this.historyManager.render();

  // Render timeline
  this.timeline.render();

  // Render properties panel
  (reloadPropertiesPanel) && this.propertiesPanel.render(this.selectedObject);

  // Update elements based on manager changes
  this.updateObjects();

  // Show properties panel
  $('.properties-panel-container').addClass('opened');
};

/**
 * Reload API data and replace the playlist structure with the new value
 * @param {boolean} [reloadEditor=true] - Reload editor
 * @param {boolean} [reloadToolbar=true] - Reload toolbar
 * @param {boolean} [reloadPropertiesPanel=true] - Reload properties panel
 */
pE.reloadData = function(
  {
    reloadEditor = true,
    reloadToolbar = false,
    reloadPropertiesPanel = true,
  } = {},
) {
  pE.common.showLoadingScreen();

  $.get(
    urlsForApi.playlist.get.url +
    '?playlistId=' +
    pE.playlist.playlistId +
   '&embed=widgets,widget_validity,tags,permissions' +
    pE.regionSpecificQuery,
  )
    .done(function(res) {
      pE.common.hideLoadingScreen();

      if (res.data != null && res.data.length > 0) {
        pE.playlist = new Playlist(pE.playlist.playlistId, res.data[0]);

        // Mark selected widget as selected
        if (
          pE.selectedObject != null &&
          !$.isEmptyObject(pE.selectedObject) &&
          pE.playlist.widgets[pE.selectedObject.id]
        ) {
          pE.playlist.widgets[pE.selectedObject.id].selected = true;
          pE.selectedObject = pE.playlist.widgets[pE.selectedObject.id];
        }

        // folder Id
        pE.folderId = pE.playlist.folderId;
        (reloadEditor) && pE.refreshEditor({
          reloadToolbar: reloadToolbar,
          reloadPropertiesPanel: reloadPropertiesPanel,
        });
      } else {
        if (res.login) {
          window.location.href = window.location.href;
          location.reload();
        } else {
          pE.showErrorMessage();
        }
      }

      // Reload the form helper connection
      formHelpers.setup(pE, pE.playlist);
    }).fail(function(jqXHR, textStatus, errorThrown) {
      pE.common.hideLoadingScreen();

      // Output error to console
      console.error(jqXHR, textStatus, errorThrown);

      pE.showErrorMessage();
    });
};

/**
 * Layout loading error message
 */
pE.showErrorMessage = function() {
  // Output error on screen
  const htmlError = messageTemplate({
    messageType: 'danger',
    messageTitle: errorMessagesTrans.error,
    messageDescription: errorMessagesTrans.loadingPlaylist,
  });

  pE.editorContainer.html(htmlError);
};

/**
 * Save playlist order
 */
pE.saveOrder = function() {
  const self = this;

  pE.common.showLoadingScreen('saveOrder');

  this.playlist.saveOrder(
    this.editorContainer.find('#timeline-container').find('.playlist-widget'),
  ).then((res) => { // Success
    pE.common.hideLoadingScreen('saveOrder');

    self.reloadData({
      reloadToolbar: false,
      reloadPropertiesPanel: false,
    });
  }).catch((error) => { // Fail/error
    pE.common.hideLoadingScreen('saveOrder');

    // Show error returned or custom message to the user
    let errorMessage = '';

    if (typeof error == 'string') {
      errorMessage = error;
    } else {
      errorMessage = error.errorThrown;
    }

    toastr.error(errorMessagesTrans.saveOrderFailed
      .replace('%error%', errorMessage));
  });
};

/**
 * Close playlist editor
 */
pE.close = function() {
  /**
     * Clear all object own properties
     * @param {object} objectToClean
     */
  const deleteObjectProperties = function(objectToClean) {
    for (const x in objectToClean) {
      if (objectToClean.hasOwnProperty(x)) {
        delete objectToClean[x];
      }
    }
  };

  // Clear loaded vars
  this.mainObjectId = '';
  deleteObjectProperties(this.playlist);
  deleteObjectProperties(this.editorContainer);
  deleteObjectProperties(this.timeline);
  deleteObjectProperties(this.propertiesPanel);
  deleteObjectProperties(this.selectedObject);
  deleteObjectProperties(this.toolbar);

  // Remove resize event listener related to the toolbar
  $(window).off('.toolbar-' + this.mainObjectType);

  // Make sure all remaining objects are pure empty JS objects
  this.playlist = this.editorContainer = this.timeline =
    this.propertiesPanel = this.historyManager =
    this.selectedObject = this.toolbar = {};

  // Restore toastr positioning
  toastr.options.positionClass = this.toastrPosition;

  $('#editor-container').empty();

  // Remove editing class from body
  (!this.inline) && $('body').removeClass('editor-opened');
};

/**
 * Show loading screen
 */
pE.showLocalLoadingScreen = function() {
  pE.editorContainer.find('#playlist-timeline').html(loadingTemplate());
};

/**
 * Clear Temporary Data ( Cleaning cached variables )
 * @param {Boolean} destroyRichTextEditor Destroy existing Rich text editors
 */
pE.clearTemporaryData = function(destroyRichTextEditor = false) {
  // Fix for remaining ckeditor elements or colorpickers
  destroyColorPicker(pE.editorContainer.find('.colorpicker-element'));

  // Hide open tooltips
  pE.editorContainer.find('.tooltip').remove();

  // Remove text callback editor structure variables
  (destroyRichTextEditor) && formHelpers.destroyCKEditor();
};

/**
 * Get object from the playlist
 * @param {string} type - type of the object to get
 * @param {number} id - id of the object to get
 * @return {object} object
 */
pE.getObjectByTypeAndId = function(type, id) {
  let object = {};

  if (type === 'playlist') {
    object = pE.playlist;
  } else if (type === 'widget') {
    object = pE.playlist.widgets[id];
  }

  return object;
};

/**
 * Get the class name for the upload dialog, used by form-helpers.
 * @return {null}
 */
pE.getUploadDialogClassName = function() {
  return 'second-dialog';
};

/**
 * Open object context menu
 * @param {object} obj - Target object
 * @param {object=} position - Page menu position
 */
pE.openContextMenu = function(obj, position = {x: 0, y: 0}) {
  const objId = $(obj).attr('id');
  const objType = $(obj).data('type');

  // Don't open context menu in read only mode
  if (typeof(lD) != 'undefined' && lD?.readOnlyMode === true) {
    return;
  }

  // Get object
  const playlistObject = pE.getObjectByTypeAndId(objType, objId);

  // Create menu and append to the designer div
  // ( using the object extended with translations )
  pE.editorContainer.append(
    contextMenuTemplate(
      Object.assign(playlistObject, {trans: contextMenuTrans}),
    ),
  );

  // Set menu position ( and fix page limits )
  const contextMenuWidth =
  pE.editorContainer.find('.context-menu').outerWidth();
  const contextMenuHeight =
  pE.editorContainer.find('.context-menu').outerHeight();

  const positionLeft = ((position.x + contextMenuWidth) > $(window).width()) ?
    (position.x - contextMenuWidth) :
    position.x;
  const positionTop = ((position.y + contextMenuHeight) > $(window).height()) ?
    (position.y - contextMenuHeight) :
    position.y;

  pE.editorContainer.find('.context-menu')
    .offset({top: positionTop, left: positionLeft});

  // Click overlay to close menu
  pE.editorContainer.find('.context-menu-overlay').click((ev) => {
    if ($(ev.target).hasClass('context-menu-overlay')) {
      pE.editorContainer.find('.context-menu-overlay').remove();
    }
  });

  // Handle buttons
  pE.editorContainer.find('.context-menu .context-menu-btn').click((ev) => {
    const target = $(ev.currentTarget);

    if (target.data('action') == 'Delete') {
      pE.deleteObject(objType, playlistObject[objType + 'Id']);
    } else {
      playlistObject.editPropertyForm(
        target.data('property'),
        target.data('propertyType'),
      );
    }

    // Remove context menu
    pE.editorContainer.find('.context-menu-overlay').remove();
  });
};

/**
 * Load user preference
 * @param {string} prefToLoad - Preference to load
 * @param {string} defaultValue - Default value if preference is not found
 */
pE.loadAndSavePref = function(prefToLoad, defaultValue = 0) {
  // Load using the API
  const linkToAPI = urlsForApi.user.getPref;

  // Request elements based on filters
  $.ajax({
    url: linkToAPI.url + '?preference=' + prefToLoad,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      if (res.data.option == prefToLoad) {
        pE[prefToLoad] = res.data.value;
      } else {
        pE[prefToLoad] = defaultValue;
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
 * Check history and return last step description
 * @return {object} last step description
 */
pE.checkHistory = function() {
  // Check if there are some changes
  const undoActive = pE.historyManager.changeHistory.length > 0;
  let undoActiveTitle = '';

  // Get last action text for popup
  if (undoActive) {
    const lastAction =
      pE.historyManager
        .changeHistory[pE.historyManager.changeHistory.length - 1];

    if (
      typeof historyManagerTrans != 'undefined' &&
    historyManagerTrans.revert[lastAction.type] != undefined
    ) {
      undoActiveTitle = historyManagerTrans.revert[lastAction.type]
        .replace('%target%', lastAction.target.type);
    } else {
      undoActiveTitle = '[' + lastAction.target.type + '] ' + lastAction.type;
    }
  }

  return {
    undoActive: undoActive,
    undoActiveTitle: undoActiveTitle,
  };
};

/**
 * Toggle panel and refresh view containers
 * @param {Array.<number, object>} items
 *  - list of items to add, either just an id or a provider object
 * @return {Promise} Promise
 */
pE.importFromProvider = function(items) {
  const requestItems = [];
  let itemsResult = items;

  itemsResult.forEach((item) => {
    if (isNaN(item)) {
      requestItems.push(item);
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

    pE.common.showLoadingScreen();

    $.ajax({
      url: requestPath,
      type: linkToAPI.type,
      dataType: 'json',
      data: {
        folderId: pE.playlist.folderId,
        items: requestItems,
      },
    }).done(function(res) {
      if (res.success) {
        pE.common.hideLoadingScreen();

        res.data.forEach((newItem) => {
          let addFlag = true;
          if (newItem.isError) {
            addFlag = false;
            toastr.error(newItem.error, newItem.item.id);
          }

          itemsResult.forEach((oldItem, key) => {
            if (isNaN(oldItem) && newItem.item.id == oldItem.id) {
              itemsResult[key] = (addFlag) ? newItem.media.mediaId : null;
            }
          });
        });

        // Filter null results
        itemsResult = itemsResult.filter((el) => el);

        resolve(itemsResult);
      } else {
        pE.common.hideLoadingScreen();

        // Login Form needed?
        if (data.login) {
          window.location.href = window.location.href;
          location.reload();
        } else {
          // Just an error we dont know about
          if (data.message == undefined) {
            reject(data);
          } else {
            reject(data.message);
          }
        }
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
      pE.common.hideLoadingScreen();

      // Reject promise and return an object with all values
      reject(new Error({jqXHR, textStatus, errorThrown}));
    });
  });
};

/**
 * Handle inputs
 */
pE.handleInputs = function() {
  const $playlistTimeline =
    pE.editorContainer.find('#playlist-timeline');

  // Initialise dropabble containers
  $playlistTimeline.droppable({
    greedy: true,
    tolerance: 'pointer',
    accept: (draggable) => {
      // Check target
      return pE.common.hasTarget(draggable, 'playlist');
    },
    drop: function(event, ui) {
      pE.playlist.addObject(event.target, ui.draggable[0]);
    },
  }).attr('data-type', 'playlist');

  // Handle keyboard keys
  $('body').off('keydown.editor')
    .on('keydown.editor', function(handler) {
      if ($(handler.target).is($('body'))) {
        if (handler.key == 'Delete') {
          pE.deleteSelectedObject();
        }
      }
    });

  // Editor container select ( faking drag and drop )
  // to add a object to the playlist
  $playlistTimeline
    .click(function(e) {
      if (
        !$.isEmptyObject(pE.toolbar.selectedCard) ||
        !$.isEmptyObject(pE.toolbar.selectedQueue)
      ) {
        e.stopPropagation();
        pE.selectObject({target: $(e.currentTarget)});
      }
    });

  // Delete object
  pE.editorContainer.find('.footer-actions [data-action="remove-widget"]')
    .click(function(e) {
      if (!$(e.currentTarget).hasClass('inactive')) {
        pE.deleteSelectedObject();
      }
    });

  // Revert last action
  pE.editorContainer.find('.footer-actions [data-action="undo"]')
    .click(function(e) {
      if (!$(e.currentTarget).hasClass('inactive')) {
        pE.undoLastAction();
      }
    });

  // Enable multi select mode
  pE.editorContainer.find('.footer-actions [data-action="multi-select"]')
    .click(function() {
      pE.toggleMultiselectMode();
    });

  // Timeline zoom controls
  pE.editorContainer.find('.footer-controls .btn[data-action="zoom-in"]')
    .click(function() {
      pE.timeline.changeZoomLevel(1);
    });

  pE.editorContainer.find('.footer-controls .btn[data-action="zoom-out"]')
    .click(function() {
      pE.timeline.changeZoomLevel(-1);
    });

  pE.editorContainer.find('.footer-controls .btn[data-action="zoom-reset"]')
    .click(function() {
      pE.timeline.changeZoomLevel(0);
    });

  pE.editorContainer
    .find('.footer-controls .btn[data-action="toggle-scale-mode"]')
    .click(function() {
      pE.timeline.switchScaleMode();
    });
};

/**
 * Update objects in the playlist editor
 */
pE.updateObjects = function() {
  // Update undo button with changes history
  const checkHistory = this.checkHistory();
  const undoActiveTitle =
    (checkHistory) ? checkHistory.undoActiveTitle : '';

  const $undoButton =
    pE.editorContainer.find('.footer-actions [data-action="undo"]');

  // Toggle active
  $undoButton.toggleClass('inactive', !checkHistory.undoActive);

  // Update title
  $undoButton.attr('title', undoActiveTitle);

  // Delete object - Widget button
  pE.editorContainer.find('#playlist-timeline .playlist-widget .widgetDelete')
    .click(function(e) {
      e.stopPropagation();
      pE.deleteObject('widget', $(e.currentTarget).parent().data('widgetId'));
    });
};

/**
 * Toggle multiple select mode
 * @param {boolean} forceSelect - Force select mode
 */
pE.toggleMultiselectMode = function(forceSelect = null) {
  const self = this;
  const timeline = this.timeline;
  const $editorContainer = this.editorContainer;
  const $mainSideToolbar =
    $editorContainer.parents('.container-designer').find('.editor-side-bar');

  const updateTrashContainer = function() {
    // Update trash container status
    $editorContainer.find('.footer-actions [data-action="remove-widget"]')
      .toggleClass(
        'inactive',
        (
          timeline.DOMObject.find('.playlist-widget.multi-selected').length == 0
        ));
  };

  const closeMultiselectMode = function() {
    // Re-enable sort
    timeline.DOMObject.find('#timeline-container').sortable('enable');

    // Clean all temporary objects
    self.toolbar.deselectCardsAndDropZones();

    // Restore toolbar to normal mode
    $mainSideToolbar.css('z-index', 'auto');
  };

  // Check if needs to be selected or unselected
  const multiSelectFlag =
    (forceSelect != null) ?
      forceSelect :
      !$editorContainer.hasClass('multi-select');

  // Toggle multi select class on container
  $editorContainer.toggleClass('multi-select', multiSelectFlag);

  // Toggle class on button
  $editorContainer.find('.footer-actions [data-action="multi-select"]')
    .toggleClass('multiselect-active', multiSelectFlag);

  if (multiSelectFlag) {
    // Show overlay
    $editorContainer.find('.custom-overlay').show().off('click')
      .on('click', () => {
        // Close multi select mode
        closeMultiselectMode();
      });

    // Disable timeline sort
    timeline.DOMObject.find('#timeline-container').sortable('disable');

    // Move toolbar under the overlay
    $mainSideToolbar.css('z-index', 0);

    // Enable select for each widget
    timeline.DOMObject.find('.playlist-widget.deletable')
      .removeClass('selected').off('click').on('click', function(e) {
        e.stopPropagation();
        $(e.currentTarget).toggleClass('multi-selected');

        updateTrashContainer();
      });

    updateTrashContainer();
  } else {
    // Close multi select mode
    closeMultiselectMode();

    // Reload timeline
    timeline.render();
  }
};

