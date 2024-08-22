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

// PLAYLIST Module
const Widget = require('../editor-core/widget.js');

/**
 * Playlist contructor
 * @param  {number} id - Playlist id
 * @param  {object} data - data to build the playlist object
 */
const Playlist = function(id, data) {
  // Playlist name
  this.name = data.name;

  //  properties
  this.playlistId = id;
  this.folderId = data.folderId;
  this.isEmpty = true;

  this.regionId = data.regionId;
  this.isTopLevel = (data.regionId != 0);
  this.widgets = {};
  this.duration = null;
  this.folderId = data.folderId;

  // Create data structure based on the API data
  this.createDataStructure(data);

  // Calculate duration, looping, and all properties related to time
  this.calculateTimeValues();
};

/**
 * Create data structure
 * @param {object} data - data to build the playlist object
 */
Playlist.prototype.createDataStructure = function(data) {
  // Playlist duration calculated based on the longest region duration
  let playlistDuration = 0;

  // Widget's data
  const widgets = data.widgets;

  // Create widgets for this region
  for (const widget in widgets) {
    if (Object.prototype.hasOwnProperty.call(widgets, widget)) {
      const newWidget = new Widget(
        widgets[widget].widgetId,
        widgets[widget],
      );

      if (newWidget.subType == 'image' || newWidget.subType == 'video') {
        newWidget.previewSrc =
          imageDownloadUrl.replace(':id', widgets[widget].mediaIds[0]);
      }

      // Save designer object for later use
      newWidget.editorObject = pE;

      // Save parent region
      newWidget.parent = this;

      // calculate expire status
      newWidget.calculateExpireStatus();

      // Check if widget is enabled
      newWidget.checkIfEnabled();

      // Format duration
      newWidget.calculatedDurationFormatted =
        pE.common.timeFormat(newWidget.calculatedDuration);

      // Add newWidget to the playlist widget object
      this.widgets[newWidget.id] = newWidget;

      // Mark the playlist as not empty
      this.isEmpty = false;

      // Increase playlist Duration
      playlistDuration += newWidget.getTotalDuration();
    }
  }

  // Set playlist duration
  this.duration = playlistDuration;
};

/**
 * Calculate timeline values ( duration, loops )
 * based on widget and region duration
 */
Playlist.prototype.calculateTimeValues = function() {
  // Widgets
  const widgets = this.widgets;
  let loopSingleWidget = false;
  let singleWidget = false;

  // If there is only one widget in the playlist
  // check the loop option for that region
  if (widgets.length === 1) {
    singleWidget = true;
    // Check the loop option
    for (const option in this.options) {
      if (
        this.options[option].option === 'loop' &&
        this.options[option].value === '1'
      ) {
        this.loop = true;
        loopSingleWidget = true;
        break;
      }
    }
  } else if (parseFloat(this.duration) < parseFloat(this.duration)) {
    // if the region duration is less than the layout duration enable loop
    this.loop = true;
  }

  for (const widget in widgets) {
    if (Object.prototype.hasOwnProperty.call(widgets, widget)) {
      const currWidget = widgets[widget];

      // If the widget needs to be extended
      currWidget.singleWidget = singleWidget;
      currWidget.loop = loopSingleWidget;
    }
  }
};

/**
 * Add action to take after dropping a draggable item
 * @param {object} _droppable - Target drop object
 * @param {object} draggable - Dragged object
 * @param {number=} addToPosition - Add to specific position in the widget list
 */
Playlist.prototype.addObject = function(
  _droppable,
  draggable,
  addToPosition = null,
) {
  const draggableType = $(draggable).data('type');
  const draggableSubType = $(draggable).data('subType');
  const draggableData = $(draggable).data();

  // Get playlist Id
  const playlistId = this.playlistId;

  // Add dragged item to region
  if (draggableType == 'media') { // Adding media from search tab to a region
    if ($(draggable).hasClass('from-provider')) {
      pE.importFromProvider([$(draggable).data('providerData')]).then((res) => {
        // If res is empty, it means that the import failed
        if (res.length === 0) {
          console.error(errorMessagesTrans.failedToImportMedia);
        } else {
          this.addMedia(res, addToPosition);
        }
      }).catch(function() {
        toastr.error(errorMessagesTrans.importingMediaFailed);
      });
    } else {
      this.addMedia($(draggable).data('mediaId'), addToPosition);
    }
  } else { // Add widget/module/template
    // Get regionSpecific property
    const regionSpecific = $(draggable).data('regionSpecific');

    // Upload form if not region specific
    if (regionSpecific == 0) {
      const validExt = $(draggable).data('validExt').replace(/,/g, '|');

      openUploadForm({
        url: libraryAddUrl,
        title: uploadTrans.uploadMessage,
        animateDialog: false,
        initialisedBy: 'playlist-editor-upload',
        className: 'second-dialog',
        buttons: {
          main: {
            label: translations.done,
            className: 'btn-primary btn-bb-main',
            callback: function() {
              pE.reloadData();
            },
          },
        },
        templateOptions: {
          trans: uploadTrans,
          upload: {
            maxSize: $(draggable).data().maxSize,
            maxSizeMessage: $(draggable).data().maxSizeMessage,
            validExtensionsMessage: translations.validExtensions
              .replace('%s', $(draggable).data('validExt')),
            validExt: validExt,
          },
          playlistId: playlistId,
          displayOrder: addToPosition,
          currentWorkingFolderId: pE.folderId,
          showWidgetDates: true,
          folderSelector: true,
        },
      }).attr('data-test', 'uploadFormModal');
    } else { // Add widget to a region
      const linkToAPI = urlsForApi.playlist.addWidget;

      let requestPath = linkToAPI.url;

      pE.common.showLoadingScreen();

      // Replace type
      requestPath = requestPath.replace(':type', draggableSubType);

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
      if (draggableData.templateId) {
        addOptions = addOptions || {};
        addOptions.templateId = draggableData.templateId;
      }

      pE.historyManager.addChange(
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
        pE.common.hideLoadingScreen();

        // The new selected object
        pE.selectedObject.id = 'widget_' + res.data.widgetId;
        pE.selectedObject.type = 'widget';

        // If we're adding a specific playlist, we need to
        // update playlist values in the new widget
        const subPlaylistId = $(draggable).data('subPlaylistId');
        if (subPlaylistId) {
          pE.historyManager.addChange(
            'saveForm',
            'widget', // targetType
            res.data.widgetId, // targetId
            null, // oldValues
            {
              subPlaylists: JSON.stringify([
                {
                  rowNo: 1,
                  playlistId: subPlaylistId,
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
            pE.reloadData();
          }).catch((_error) => {
            toastr.error(_error);

            // Delete newly added widget
            pE.deleteObject('widget', res.data.widgetId);
          });
        } else {
          pE.reloadData();
        }
      }).catch((error) => { // Fail/error
        pE.common.hideLoadingScreen();

        // Show error returned or custom message to the user
        let errorMessage = '';

        if (typeof error == 'string') {
          errorMessage += error;
        } else {
          errorMessage += error.errorThrown;
        }

        // Remove added change from the history manager
        pE.historyManager.removeLastChange();

        // Show toast message
        toastr.error(errorMessage);
      });
    }
  }
};

/**
 * Add media to the playlist
 * @param {Array.<number>} media
 * @param {number=} addToPosition
 */
Playlist.prototype.addMedia = function(media, addToPosition = null) {
  // Get playlist Id
  const playlistId = this.playlistId;

  // Get media Id
  let mediaToAdd = {};

  if (Array.isArray(media)) {
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
  if (pE.useLibraryDuration != undefined) {
    mediaToAdd.useDuration = (pE.useLibraryDuration == '1');
  }

  // Show loading screen in the dropzone
  pE.showLocalLoadingScreen();

  pE.common.showLoadingScreen();

  // Set position to add if selected
  if (addToPosition != null) {
    mediaToAdd.displayOrder = addToPosition;
  }

  // Create change to be uploaded
  pE.historyManager.addChange(
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
    pE.common.hideLoadingScreen();

    // The new selected object
    pE.selectedObject.id = 'widget_' + res.data.newWidgets[0].widgetId;
    pE.selectedObject.type = 'widget';

    pE.reloadData();
  }).catch((error) => { // Fail/error
    pE.common.hideLoadingScreen();

    // Show error returned or custom message to the user
    let errorMessage = '';

    if (typeof error == 'string') {
      errorMessage = error;
    } else {
      errorMessage = error.errorThrown;
    }

    // Show toast message
    toastr.error(errorMessagesTrans.addMediaFailed
      .replace('%error%', errorMessage));
  });
};

/**
 * Delete an object in the playlist, by ID
 * @param {string} objectType - object type (widget, region, ...)
 * @param {number} objectId - object id
 * @return {Promise} - Promise object
 */
Playlist.prototype.deleteObject = function(
  objectType,
  objectId,
) {
  pE.common.showLoadingScreen();

  // Remove changes from the history array
  return pE.historyManager.removeAllChanges(
    objectType,
    objectId,
  ).then((_res) => {
    pE.common.hideLoadingScreen();

    // Create a delete type change
    // upload it but don't add it to the history array
    return pE.historyManager.addChange(
      'delete',
      objectType, // targetType
      objectId, // targetId
      null, // oldValues
      null, // newValues
      {
        addToHistory: false, // options.addToHistory
      },
    );
  }).catch(function() {
    pE.common.hideLoadingScreen();

    toastr.error(errorMessagesTrans.removeAllChangesFailed);
  });
};

/**
 * Save playlist order
 * @param {object} widgets - Widgets DOM objects array
 * @return {Promise} - Promise object
 */
Playlist.prototype.saveOrder = function(widgets) {
  if ($.isEmptyObject(pE.playlist.widgets)) {
    return Promise.resolve({
      message: errorMessagesTrans.noWidgetsNeedSaving,
    });
  }

  // Get playlist's widgets previous order
  const oldOrder = {};
  let orderIndex = 1;
  for (const widget in pE.playlist.widgets) {
    if (pE.playlist.widgets.hasOwnProperty(widget)) {
      oldOrder[pE.playlist.widgets[widget].widgetId] = orderIndex;
      orderIndex++;
    }
  }

  // Get new order
  const newOrder = {};

  for (let index = 0; index < widgets.length; index++) {
    const widget = pE.getObjectByTypeAndId(
      'widget',
      $(widgets[index]).attr('id'),
    );

    newOrder[widget.widgetId] = index + 1;
  }

  if (JSON.stringify(newOrder) === JSON.stringify(oldOrder)) {
    return Promise.resolve({
      message: errorMessagesTrans.listOrderNotChanged,
    });
  }

  return pE.historyManager.addChange(
    'order',
    'playlist',
    this.playlistId,
    {
      widgets: oldOrder,
    },
    {
      widgets: newOrder,
    },
  ).catch((error) => {
    toastr.error(errorMessagesTrans.playlistOrderSave);
    console.error(error);
  });
};

module.exports = Playlist;
