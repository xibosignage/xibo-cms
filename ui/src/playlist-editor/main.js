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
const deleteElementModalContentTemplate =
  require('../templates/delete-element-modal-content.hbs');
const deleteMultiElementModalContentTemplate =
  require('../templates/delete-multi-element-modal-content.hbs');

// Include modules
const Playlist = require('../playlist-editor/playlist.js');
const PlaylistTimeline = require('../playlist-editor/playlist-timeline.js');
const Toolbar = require('../editor-core/toolbar.js');
const PropertiesPanel = require('../editor-core/properties-panel.js');
const Manager = require('../editor-core/manager.js');
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

  // Manager
  manager: {},

  // Selected object
  selectedObject: {},

  // Bottom toolbar
  toolbar: {},

  // folderId
  folderId: '',
};

/**
 * Load Playlist and build app structure
 * @param {string} inline - Is this an inline playlist editor?
 */
pE.loadEditor = function(inline = false) {
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
        // Append layout html to the main div
        pE.editorContainer.html(
          inline ?
            playlistEditorTemplate() :
            playlistEditorExternalContainerTemplate(
                {
                  trans: editorsTrans,
                },
            ),
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
          pE.manager = lD.manager;
        } else {
          pE.manager = new Manager(
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
        pE.initElements();

        // Load user preferences
        pE.loadAndSavePref('useLibraryDuration', 0);

        // Reload toolbar
        pE.toolbar.render();

        pE.common.hideLoadingScreen();

        // Handle editor close button
        pE.editorContainer.find('#closePlaylistEditorBtn').on('click', function() {
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
 * @param {object=} obj - Object to be selected
 * @param {bool=} forceUnselect - Clean selected object
 * @param {number=} [positionToAdd = null] - Order position for widget
 */
pE.selectObject = function({
  target = null,
  forceUnselect = false,
  positionToAdd = null,
} = {}) {
  // Clear rogue tooltips
  pE.common.clearTooltips();

  // If there is a selected card, use the
  // drag&drop simulate to add that item to a object
  if (!$.isEmptyObject(this.toolbar.selectedCard)) {
    if (
      target.data('type') == 'playlist'
      // TODO: merge conflict: if([obj.data('type'), 'all']
      // .indexOf($(this.toolbar.selectedCard).attr('drop-to')) !== -1) {
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
    let newSelectedId = {};
    let newSelectedType = {};

    // Unselect the previous selectedObject object if still selected
    if (this.selectedObject.selected) {
      if (this.selectedObject.type == 'widget') {
        if (this.playlist.widgets[this.selectedObject.id]) {
          this.playlist.widgets[this.selectedObject.id].selected = false;
        }
      }
    }

    // If there's no selected object, select a default one
    // ( or nothing if widgets are empty)
    if (target == null || typeof target.data('type') == 'undefined') {
      if ($.isEmptyObject(pE.playlist.widgets) || forceUnselect) {
        this.selectedObject = {};
      } else {
        // Select first widget
        const newId = Object.keys(this.playlist.widgets)[0];

        this.playlist.widgets[newId].selected = true;
        this.selectedObject.type = 'widget';
        this.selectedObject = this.playlist.widgets[newId];
      }
    } else {
      // Get object properties from the DOM ( or set to layout if not defined )
      newSelectedId = target.attr('id');
      newSelectedType = target.data('type');

      // Select new object
      if (newSelectedType === 'widget') {
        this.playlist.widgets[newSelectedId].selected = true;
        this.selectedObject = this.playlist.widgets[newSelectedId];
      }

      this.selectedObject.type = newSelectedType;
    }

    // Refresh the designer containers
    pE.refreshEditor();
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
  this.playlist.addElement(droppable, card, positionToAdd);
};

/**
 * Revert last action
 */
pE.undoLastAction = function() {
  pE.common.showLoadingScreen();

  pE.manager.revertChange().then((res) => { // Success
    pE.common.hideLoadingScreen();

    toastr.success(res.message);

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
  const createDeleteModal = function(objectType,
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

            pE.common.showLoadingScreen('deleteObject');

            // Delete element from the layout
            pE.playlist.deleteElement(objectType, objectId, options)
              .then((res) => { // Success
                pE.common.hideLoadingScreen('deleteObject');

                // Behavior if successful
                toastr.success(res.message);

                // Remove selected object
                pE.selectedObject = {};

                // Reload data
                pE.reloadData();
              }).catch((error) => { // Fail/error
                pE.common.hideLoadingScreen('deleteObject');
                // Behavior if successful
                toastr.success(res.message);
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
          },
        },
      },
    }).attr('data-test', 'deleteObjectModal');
  };

  if (objectType === 'widget') {
    const widgetToDelete =
    pE.getElementByTypeAndId('widget', 'widget_' + objectId);

    if (widgetToDelete.isRegionSpecific()) {
      createDeleteModal(objectType, objectId);
    } else {
      pE.common.showLoadingScreen('checkMediaIsUsed');

      const linkToAPI = urlsForApi.media.isUsed;
      const requestPath =
        linkToAPI.url.replace(':id', widgetToDelete.mediaIds[0]);

      // Request with count as being 2, for the published layout and draft
      $.get(requestPath + '?count=1')
        .done(function(res) {
          if (res.success) {
            createDeleteModal(objectType, objectId, true, !res.data.isUsed);
          } else {
            if (res.login) {
              window.location.href = window.location.href;
              location.reload();
            } else {
              toastr.error(res.message);
            }
          }

          pE.common.hideLoadingScreen('checkMediaIsUsed');
        }).fail(function(jqXHR, textStatus, errorThrown) {
          pE.common.hideLoadingScreen('checkMediaIsUsed');

          // Output error to console
          console.error(jqXHR, textStatus, errorThrown);
        });
    }
  }
};

/**
 * Delete multiple objects
 * @param {string} objectsType - Type of objects to delete
 * @param {string[]} objectIds - Object ids to delete
 */
pE.deleteMultipleObjects = function(objectsType, objectIds) {
  const createMultiDeleteModal = function(objectArray) {
    bootbox.hideAll();

    const htmlContent = deleteMultiElementModalContentTemplate({
      mainMessage: deleteMenuTrans.deleteMultipleObjects,
      objectArray: objectArray,
      trans: deleteMenuTrans,
    });

    // Create buttons object
    const buttons = {
      cancel: {
        label: editorsTrans.no,
        className: 'btn-white btn-bb-cancel',
      },
    };

    // Select all button ( if there are 2 or more checkboxes )
    if ($(htmlContent).find('input[type="checkbox"]').length > 1) {
      buttons.selectAll = {
        label: editorsTrans.selectAll,
        className: 'btn-warning btn-bb-selectall',
        callback: function() {
          $(this).find('input[type="checkbox"]').prop('checked', true);
          return false;
        },
      };
    }

    buttons.confirm = {
      label: editorsTrans.yes,
      className: 'btn-danger btn-bb-confirm',
      callback: function() {
        const $objects = $(this).find('.multi-delete-element');
        let deletedElements = 0;
        let index = 0;

        // Show modal
        pE.common.showLoadingScreen('deleteObjects');

        // Leave multi select mode
        pE.toggleMultiselectMode(false);

        const deleteObject = function() {
          const $element = $($objects[index]);

          // Empty options object
          let options = null;
          const objectId = $element.data('id');
          const objectType = $element.data('type');

          // If delete media is checked, pass that as a param for delete
          if ($element.find('input.deleteMedia').is(':checked')) {
            options = {
              deleteMedia: 1,
            };
          }

          // Delete element from the playlist
          pE.playlist.deleteElement(objectType, objectId, options)
            .then((res) => { // Success
            // Behavior if successful
              toastr.success(res.message);

              deletedElements++;

              if (deletedElements == $objects.length) {
                // Hide loading screen
                pE.common.hideLoadingScreen('deleteObjects');

                // Remove selected object
                pE.selectedObject = {};

                // Reload data
                pE.reloadData();

                // Hide/close modal
                bootbox.hideAll();
              } else {
                index++;
                deleteObject();
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

        deleteObject();

        return false;
      },
    };

    bootbox.dialog({
      title: editorsTrans.deleteMultipleTitle,
      message: htmlContent,
      size: 'large',
      buttons: buttons,
    }).attr('data-test', 'deleteObjectModal');
  };

  if (objectsType === 'widget') {
    pE.common.showLoadingScreen('checkMediaIsUsed');
    const arrayOfWidgets = [];
    let index = 0;

    const getWidgetStatus = function() {
      const widgetId = objectIds[index];
      const widgetToDelete =
      pE.getElementByTypeAndId('widget', 'widget_' + widgetId);
      const linkToAPI = urlsForApi.media.isUsed;
      const requestPath =
      linkToAPI.url.replace(':id', widgetToDelete.mediaIds[0]);

      if (widgetToDelete.isRegionSpecific()) {
        arrayOfWidgets.push({
          objectId: widgetId,
          objectType: 'widget',
          objectName: widgetToDelete.widgetName,
          hasMedia: false,
          dataUsed: false,
        });

        if (arrayOfWidgets.length == objectIds.length) {
          createMultiDeleteModal(arrayOfWidgets);
          pE.common.hideLoadingScreen('checkMediaIsUsed');
        } else {
          index++;
          getWidgetStatus();
        }
      } else {
        // Request with count as being 2, for the published layout and draft
        $.get(requestPath + '?count=1')
          .done(function(res) {
            if (res.success) {
              arrayOfWidgets.push({
                objectId: widgetId,
                objectType: 'widget',
                objectName: widgetToDelete.widgetName,
                hasMedia: true,
                dataUsed: res.data.isUsed,
              });

              if (arrayOfWidgets.length == objectIds.length) {
                createMultiDeleteModal(arrayOfWidgets);
                pE.common.hideLoadingScreen('checkMediaIsUsed');
              } else {
                index++;
                getWidgetStatus();
              }
            } else {
              if (res.login) {
                window.location.href = window.location.href;
                location.reload();
              } else {
                toastr.error(res.message);
              }
            }
          }).fail(function(jqXHR, textStatus, errorThrown) {
            pE.common.hideLoadingScreen('checkMediaIsUsed');

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);
          });
      }
    };

    // Start getting widget status
    getWidgetStatus();
  }
};

/**
 * Refresh designer
 * @param {boolean} [updateToolbar=false] - Update toolbar
 */
pE.refreshEditor = function(updateToolbar = false) {
  // Remove temporary data
  this.clearTemporaryData();

  // Render containers
  (updateToolbar) && this.toolbar.render();
  this.manager.render();

  // Render timeline
  this.timeline.render();

  // Render properties panel
  this.propertiesPanel.render(this.selectedObject);

  // Update elements based on manager changes
  this.updateElements();

  // Show properties panel
  $('.properties-panel-container').addClass('opened');
};

/**
 * Reload API data and replace the playlist structure with the new value
 */
pE.reloadData = function() {
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
        pE.refreshEditor();
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

    // Behavior if successful
    toastr.success(res.message);

    self.reloadData();
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
    this.propertiesPanel = this.manager =
    this.selectedObject = this.toolbar = {};

  // Restore toastr positioning
  toastr.options.positionClass = this.toastrPosition;

  $('#editor-container').empty();
};

/**
 * Show loading screen
 */
pE.showLocalLoadingScreen = function() {
  pE.editorContainer.find('#playlist-timeline').html(loadingTemplate());
};

/**
 * Clear Temporary Data ( Cleaning cached variables )
 */
pE.clearTemporaryData = function() {
  // Fix for remaining ckeditor elements or colorpickers
  destroyColorPicker(pE.editorContainer.find('.colorpicker-element'));

  // Hide open tooltips
  pE.editorContainer.find('.tooltip').remove();

  // Remove text callback editor structure variables
  formHelpers.destroyCKEditor();
};

/**
 * Get element from the main object ( playlist )
 * @param {string} type - type of the element to get
 * @param {number} id - id of the element to get
 * @return {object} element
 */
pE.getElementByTypeAndId = function(type, id) {
  let element = {};

  if (type === 'playlist') {
    element = pE.playlist;
  } else if (type === 'widget') {
    element = pE.playlist.widgets[id];
  }

  return element;
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

  // Get object
  const playlistObject = pE.getElementByTypeAndId(objType, objId);

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
  const undoActive = pE.manager.changeHistory.length > 0;
  let undoActiveTitle = '';

  // Get last action text for popup
  if (undoActive) {
    const lastAction =
      pE.manager.changeHistory[pE.manager.changeHistory.length - 1];

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
pE.initElements = function() {
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
      pE.playlist.addElement(event.target, ui.draggable[0]);
    },
  }).attr('data-type', 'playlist');

  // Handle keyboard keys
  $('body').off('keydown').on('keydown', function(handler) {
    if (!$(handler.target).is($('input'))) {
      if (handler.key == 'Delete') {
        pE.deleteSelectedObject();
      }
    }
  });

  // Editor container select ( faking drag and drop )
  // to add a element to the playlist
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
};

/**
 * Update elements in the playlist editor
 */
pE.updateElements = function() {
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
 * Toggle multiple element select mode
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
    $editorContainer.find('.custom-overlay').show().off().on('click', () => {
      // Close multi select mode
      closeMultiselectMode();
    });

    // Disable timeline sort
    timeline.DOMObject.find('#timeline-container').sortable('disable');

    // Move toolbar under the overlay
    $mainSideToolbar.css('z-index', 0);

    // Enable select for each widget
    timeline.DOMObject.find('.playlist-widget.deletable')
      .removeClass('selected').off().on('click', function(e) {
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

