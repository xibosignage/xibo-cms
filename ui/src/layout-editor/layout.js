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

// LAYOUT Module
const Region = require('../layout-editor/region.js');
const Canvas = require('../layout-editor/canvas.js');
const Widget = require('../editor-core/widget.js');
const Element = require('../editor-core/element.js');
const ElementGroup = require('../editor-core/element-group.js');

/**
 * Layout contructor
 * @param  {number} id - layout id
 * @param  {object} data - data from the API request
 */
const Layout = function(id, data) {
  // Is it editable? ( checkif status is draft )
  this.editable = (data.publishedStatusId == 2);

  // Does user have permission to schedule now page?
  this.scheduleNowPermission = data.scheduleNowPermission;

  // Does user have permission to delete layout
  this.deletePermission = data.deletePermission;

  // Parent Id
  this.parentLayoutId = data.parentId;

  // Layout name
  this.name = data.layout;

  // Layout properties
  this.id = 'layout_' + id;
  this.layoutId = id;

  this.folderId = data.folderId;

  // Campaing ID
  this.campaignId = data.campaignId;

  this.regions = {};
  this.duration = null;

  this.drawer = {};

  this.canvas = {};

  this.width = data.width;
  this.height = data.height;

  this.backgroundImage = data.backgroundImageId;
  this.backgroundColor = data.backgroundColor;
  this.backgroundzIndex = data.backgroundzIndex;
  this.resolutionId = null;

  this.code = data.code;
  this.folderId = data.folderId;

  // Interactive actions
  this.actions = data.actions;

  // Get background image if exists, if not, get the background color
  this.backgroundCss = function(width = this.width, height = this.height) {
    if (this.backgroundImage === null) {
      return this.backgroundColor;
    } else {
      // Get API link
      let linkToAPI = urlsForApi['layout']['downloadBackground'].url;
      // Replace ID in the link
      linkToAPI = linkToAPI.replace(':id', this.layoutId);

      return 'url(\'' + linkToAPI +
        '?preview=1&width=' + width + '&height=' + height +
        '&proportional=0&layoutBackgroundId=' + this.backgroundImage +
        '\') top center no-repeat; background-color: ' + this.backgroundColor;
    }
  };

  // Create data structure based on the BE response ( data )
  this.createDataStructure(data);

  // Calculate duration, looping, and all properties related to time
  this.calculateTimeValues();
};

/**
 * Create data structure
 * @param  {object} data - data from the API request
 */
Layout.prototype.createDataStructure = function(data) {
  // layout duration calculated based on the longest region duration
  let layoutDuration = 0;

  this.numRegions = data.regions.length;

  // Create regions and add them to the layout
  for (const region in data.regions) {
    if (Object.prototype.hasOwnProperty.call(data.regions, region)) {
      let regionDuration = 0;
      const isCanvas = (data.regions[region].type === 'canvas');

      const newRegion = !isCanvas ?
        new Region(
          data.regions[region].regionId,
          data.regions[region],
        ) :
        new Canvas(
          data.regions[region].regionId,
          data.regions[region],
          {
            width: this.width,
            height: this.height,
          },
        );

      // Save index
      newRegion.index = parseInt(region) + 1;

      // Widgets
      const widgets = newRegion.playlists.widgets;

      // Set number of widgets
      newRegion.numWidgets = widgets.length;

      // Create widgets for this region
      for (const widget in widgets) {
        if (Object.prototype.hasOwnProperty.call(widgets, widget)) {
          const newWidget = new Widget(
            widgets[widget].widgetId,
            widgets[widget],
            data.regions[region].regionId,
            this,
          );

          // Save index
          newWidget.index = parseInt(widget) + 1;

          // Mark the widget as sortable if region can be sorted/edited
          newWidget.isSortable = newRegion.isEditable;

          newWidget.editorObject = lD;

          newWidget.parent = newRegion;

          // calculate expire status
          newWidget.calculateExpireStatus();

          // Check if widget is enabled
          newWidget.checkIfEnabled();

          // If region is a canvas, check if widget has elements
          if (isCanvas) {
            const widgetOptions = newWidget.getOptions();
            const widgetElements =
              (widgetOptions && widgetOptions.elements) ?
                newWidget.getOptions().elements[0].elements :
                [];

            for (let index = 0; index < widgetElements.length; index++) {
              const element = widgetElements[index];

              // Generate temporary ID if it doesn't exist
              if (!element.elementId) {
                element.elementId =
                  'element_' + Math.floor(Math.random() * 1000000);
              }

              const newElement = newWidget.elements[element.elementId] =
                new Element(
                  element,
                  newWidget.widgetId,
                  data.regions[region].regionId,
                  newWidget,
                );

              // Update elements map for the widget
              newWidget.updateElementMap(newElement);

              // If we have a groupId, add or assign it to the group
              if (newElement.groupId != undefined) {
                if (newWidget.elementGroups[newElement.groupId] == undefined) {
                  newWidget.elementGroups[newElement.groupId] =
                    new ElementGroup(
                      Object.assign(
                        newElement.groupProperties,
                        {
                          id: newElement.groupId,
                        },
                      ),
                      newWidget.widgetId,
                      data.regions[region].regionId,
                      newWidget,
                    );
                }

                // If group has no layer, give the element layer to it
                if (
                  newWidget.elementGroups[newElement.groupId].layer == null ||
                  newWidget.elementGroups[newElement.groupId].layer == undefined
                ) {
                  newWidget.elementGroups[newElement.groupId].layer =
                    newElement.layer;
                }

                // Add group reference to element
                newElement.group = newWidget.elementGroups[newElement.groupId];

                // Remove temporary group properties from element
                delete newElement.groupProperties;

                // Add element to group
                newWidget
                  .elementGroups[newElement.groupId]
                  .elements[newElement.elementId] =
                    newElement;

                // Update slot on group
                newWidget.elementGroups[newElement.groupId].updateSlot(
                  newElement.slot,
                );
              }
            }

            // Update elements previous state
            newWidget.updateElementPreviousState();

            // Check required elements
            newWidget.validateRequiredElements();
          }

          // Add newWidget to the Region widget object
          newRegion.widgets[newWidget.id] = newWidget;

          // Mark the region as not empty
          newRegion.isEmpty = false;

          // If it's a canvas region, set the duration as the longest widget
          if (isCanvas) {
            const widgetDuration = newWidget.getTotalDuration();
            if (
              !regionDuration ||
              widgetDuration > regionDuration
            ) {
              regionDuration = widgetDuration;
            }
          } else {
            // Increase region Duration with widget base duration
            regionDuration += newWidget.getTotalDuration();
          }
        }
      }

      // Set region duration
      newRegion.duration = regionDuration;

      // If it's a canvas, save region as a canvas
      if (isCanvas) {
        this.canvas = newRegion;
      } else {
        // Push Region to the Layout region array
        this.regions[newRegion.id] = newRegion;
      }

      // update layoutDuration if the current regions is the longest one
      if (regionDuration > layoutDuration) {
        layoutDuration = regionDuration;
      }
    }
  }

  // Create drawer object if exists
  for (const drawer in data.drawers) {
    if (Object.prototype.hasOwnProperty.call(data.drawers, drawer)) {
      this.createDrawer(data.drawers[drawer]);
    }
  }

  // Set layout duration
  this.duration = layoutDuration;
  this.durationFormatted = lD.common.timeFormat(layoutDuration);
};

/**
 * Remove object from data structure
 * @param {string} objectType - Object type
 * @param {string} objectId - Object ID
 * @param {string} auxId - Object sub type or parent region (if widget)
 */
Layout.prototype.removeFromStructure = function(objectType, objectId, auxId) {
  const self = this;

  if (objectType === 'region' && auxId === 'canvas') {
    // Set canvas as empty object
    self.canvas = {};
  } else if (objectType === 'region') {
    delete self.regions['region_' + objectId];
  } else if (objectType === 'widget' && auxId === 'canvas') {
    delete self.canvas.widgets[
      'widget' + '_' + self.canvas.regionId + '_' + objectId
    ];
  } else if (objectType === 'widget') {
    delete self.regions[auxId].widgets[
      'widget' + '_' + auxId + '_' + objectId
    ];
  }
};

/**
 * Calculate timeline values ( duration, loops )
 * based on widget and region duration
 */
Layout.prototype.calculateTimeValues = function() {
  for (const region in this.regions) {
    if (Object.prototype.hasOwnProperty.call(this.regions, region)) {
      const currRegion = this.regions[region];

      // Widgets
      const widgets = currRegion.widgets;
      let loopSingleWidget = false;
      let singleWidget = false;

      // If there is only one widget in the playlist
      // check the loop option for that region
      if (Object.keys(widgets).length === 1) {
        singleWidget = true;
        // Check the loop option
        for (const option in currRegion.options) {
          if (
            currRegion.options[option].option === 'loop' &&
            currRegion.options[option].value === '1'
          ) {
            currRegion.loop = true;
            loopSingleWidget = true;
            break;
          }
        }
      } else if (parseFloat(currRegion.duration) < parseFloat(this.duration)) {
      // if the region duration is less than the layout duration enable loop
        currRegion.loop = true;
      }

      for (const widget in widgets) {
        if (Object.prototype.hasOwnProperty.call(widgets, widget)) {
          const currWidget = widgets[widget];

          // If the widget needs to be extended
          currWidget.singleWidget = singleWidget;
          currWidget.loop = loopSingleWidget;
        }
      }
    }
  }
};

/**
 * Checkout layout
 */
Layout.prototype.checkout = function() {
  const linkToAPI = urlsForApi.layout.checkout;
  let requestPath = linkToAPI.url;

  lD.common.showLoadingScreen();

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', lD.layout.layoutId);

  // Deselect previous selected object
  lD.selectObject();

  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      bootbox.hideAll();

      toastr.success(res.message);

      // Turn off read only mode
      lD.readOnlyMode = false;

      // Hide read only message
      lD.editorContainer.removeClass('view-mode');
      lD.editorContainer.find('#read-only-message').remove();

      // If we're in interactive mode
      // move back to edit mode
      if (lD.interactiveMode) {
        lD.toggleInteractiveMode(false, false);
      }

      // Reload layout
      lD.reloadData(res.data,
        {
          refreshEditor: true,
          refreshViewer: true,
          reloadPropertiesPanel: true,
        });

      // Refresh toolbar
      lD.toolbar.render();

      lD.common.hideLoadingScreen();
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
 * Publish layout
 */
Layout.prototype.publish = function() {
  const linkToAPI = urlsForApi.layout.publish;
  let requestPath = linkToAPI.url;

  lD.common.showLoadingScreen();

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', this.parentLayoutId);

  const serializedData = $('#layoutPublishForm').serialize();

  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
    data: serializedData,
  }).done(function(res) {
    if (res.success) {
      bootbox.hideAll();

      toastr.success(res.message);

      // Update the thumbnail
      lD.uploadThumbnail().finally(() => {
        // Redirect to the new published layout ( read only mode )
        window.location.href =
          urlsForApi.layout.designer.url.replace(
            ':id', res.data.layoutId) + '?vM=1' +
            (lD.templateEditMode ? '&isTemplateEditor=1' : '');
      });
    } else {
      lD.common.hideLoadingScreen();

      // Login Form needed?
      if (res.login) {
        window.location.reload();
      } else {
        toastr.error(res.message);

        // Remove loading icon from publish dialog
        $(
          '[data-test="publishFormLayoutForm"] ' +
          '.btn-bb-Publish i.fa-cog',
        ).remove();
      }
    }
  }).fail(function(jqXHR, textStatus, errorThrown) {
    lD.common.hideLoadingScreen();

    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);
  });
};

/**
 * Discard layout
 */
Layout.prototype.discard = function() {
  const linkToAPI = urlsForApi.layout.discard;
  let requestPath = linkToAPI.url;

  lD.common.showLoadingScreen();

  // Deselect previous selected object
  lD.selectObject();

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', this.parentLayoutId);

  const serializedData = $('#layoutDiscardForm').serialize();

  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
    data: serializedData,
  }).done(function(res) {
    if (res.success) {
      bootbox.hideAll();

      toastr.success(res.message);

      // Redirect to the layout grid
      window.location.href = lD.exitURL;
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
      } else {
        toastr.error(res.message);

        // Remove loading icon from publish dialog
        $(
          '[data-test="discardFormLayoutForm"] ' +
          '.btn-bb-Discard i.fa-cog',
        ).remove();
      }
    }

    lD.common.hideLoadingScreen();
  }).fail(function(jqXHR, textStatus, errorThrown) {
    lD.common.hideLoadingScreen();

    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);
  });
};

/**
 * Delete layout
 */
Layout.prototype.delete = function() {
  const linkToAPI = urlsForApi.layout.delete;
  let requestPath = linkToAPI.url;

  lD.common.showLoadingScreen();

  // Deselect previous selected object
  lD.selectObject();

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', this.layoutId);

  const serializedData = $('#layoutDeleteForm').serialize();

  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
    data: serializedData,
  }).done(function(res) {
    if (res.success) {
      bootbox.hideAll();

      toastr.success(res.message);

      // Redirect to the layout grid
      window.location.href = lD.exitURL;
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
      } else {
        toastr.error(res.message);

        // Remove loading icon from publish dialog
        $(
          '[data-test="deleteFormLayoutForm"] ' +
          '.btn-bb-Yes i.fa-cog',
        ).remove();
      }
    }

    lD.common.hideLoadingScreen();
  }).fail(function(jqXHR, textStatus, errorThrown) {
    lD.common.hideLoadingScreen();

    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);
  });
};


/**
 * Update layout data
 */
Layout.prototype.updateData = function(data) {
  // Is it editable? ( check if status is draft )
  this.editable = (data.publishedStatusId == 2);

  // Does user have permission to schedule now page?
  this.scheduleNowPermission = data.scheduleNowPermission;

  // Does user have permission to delete layout
  this.deletePermission = data.deletePermission;

  this.width = data.width;
  this.height = data.height;

  this.backgroundImage = data.backgroundImageId;
  this.backgroundColor = data.backgroundColor;
  this.backgroundzIndex = data.backgroundzIndex;
  this.resolutionId = null;
};

/**
 * Clear layout
 */
Layout.prototype.clear = function() {
  const linkToAPI = urlsForApi.layout.clear;
  let requestPath = linkToAPI.url;

  lD.common.showLoadingScreen();

  // Deselect previous selected object
  lD.selectObject();

  // replace id if necessary/exists
  requestPath = requestPath.replace(':id', this.layoutId);

  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      bootbox.hideAll();

      toastr.success(res.message);

      lD.reloadData(res.id, {refreshEditor: true});
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
      } else {
        toastr.error(res.message);

        // Remove loading icon from publish dialog
        $(
          '[data-test="clearFormLayoutForm"] ' +
          '.btn-bb-Yes i.fa-cog',
        ).remove();
      }
    }

    lD.common.hideLoadingScreen();
  }).fail(function(jqXHR, textStatus, errorThrown) {
    lD.common.hideLoadingScreen();

    // Output error to console
    console.error(jqXHR, textStatus, errorThrown);
  });
};

/**
 * Add a new empty object to the layout
 * @param {string} objectType - object type (widget, region, ...)
 * @param {object} options - Position to add the object to
 * @param {object} [options.positionToAdd] - Position to add the object to
 * @param {object} [options.objectSubtype] - object subtype
 * @param {object} [options.dimensions] - object dimensions
 * @return {object} - Manager change
 */
Layout.prototype.addObject = function(
  objectType,
  {
    positionToAdd = null,
    objectSubtype = null,
    dimensions = null,
  } = {},
) {
  let newValues = {};

  // / Get position values if they exist
  if (positionToAdd !== null) {
    newValues = positionToAdd;
  }

  // If element is type region, add type flag
  if (objectType === 'region') {
    newValues = Object.assign(newValues, {
      type: objectSubtype,
    });
  }

  // Set dimensions if they exist
  if (dimensions !== null) {
    newValues = Object.assign(newValues, dimensions);
  }

  // Add a create change to the history array, and
  // an option to update the Id on the change to the newly created object
  return lD.historyManager.addChange(
    'create',
    objectType, // targetType
    null, // targetId
    null, // oldValues
    newValues, // newValues
    {
      updateTargetId: true,
      // Don't add to history manager if it's a canvas
      addToHistory: (objectSubtype != 'canvas'),
      targetSubType: (objectSubtype) ? objectSubtype : null,
    },
  );
};

/**
 * Delete an object in the layout, by ID
 * @param {string} objectType - object type (widget, region, ...)
 * @param {number} objectId - object id
 * @param {object =} [options] - Delete submit params/options
 * @param {boolean =} [showLoadingScreen] - Show loading screen for the request
 * @param {boolean =} [deselectObject] - Deselect object before deleting
 * @return {object} - Manager change
 * @return {Promise} - Promise object
 */
Layout.prototype.deleteObject = function(
  objectType,
  objectId,
  options = null,
  showLoadingScreen = true,
  deselectObject = true,
) {
  (showLoadingScreen) &&
    lD.common.showLoadingScreen();

  // Save all changes first
  return lD.historyManager.saveAllChanges().then((res) => {
  // Remove changes from the history array
    return lD.historyManager.removeAllChanges(
      objectType,
      objectId,
    ).then((_res) => {
      // Unselect selected object before deleting
      (
        deselectObject &&
        objectType === lD.selectedObject.type &&
        objectId === lD.selectedObject[lD.selectedObject.type + 'Id']
      ) &&
        lD.selectObject();

      (showLoadingScreen) &&
        lD.common.hideLoadingScreen();

      // Create a delete type change, upload it
      // but don't add it to the history array
      return lD.historyManager.addChange(
        'delete',
        objectType, // targetType
        objectId, // targetId
        null, // oldValues
        options, // newValues
        {
          addToHistory: false, // options.addToHistory
        },
      );
    }).catch(function() {
      (showLoadingScreen) &&
        lD.common.hideLoadingScreen();

      toastr.error(errorMessagesTrans.removeAllChangesFailed);
    });
  }).catch(function() {
    (showLoadingScreen) &&
      lD.common.hideLoadingScreen();

    toastr.error(errorMessagesTrans.saveAllChangesFailed);
  });
};

/**
 * Save playlist order
 * @param {object} playlist - playlist
 * @param {object} widgets - Widgets DOM objects array
 * @return {object} - Manager change
 */
Layout.prototype.savePlaylistOrder = function(playlist, widgets) {
  // Get playlist's widgets previous order
  const oldOrder = {};
  let orderIndex = 1;
  for (const element in playlist.widgets) {
    if (playlist.widgets.hasOwnProperty(element)) {
      oldOrder[playlist.widgets[element].widgetId] = orderIndex;
      orderIndex++;
    }
  }

  // Get new order
  const newOrder = {};

  for (let index = 0; index < widgets.length; index++) {
    const widget =
      lD.getObjectByTypeAndId(
        'widget',
        $(widgets[index]).attr('id'), 'region_' + playlist.regionId,
      );

    newOrder[widget.widgetId] = index + 1;
  }

  if (JSON.stringify(newOrder) === JSON.stringify(oldOrder)) {
    return Promise.resolve({
      message: errorMessagesTrans.listOrderNotChanged,
    });
  }

  return lD.historyManager.addChange(
    'order',
    'playlist',
    playlist.playlistId,
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

/**
 * Update layout status fields
 * @param {int} status - Status code
 * @param {string} statusFeedback - Status feedback message
 * @param {string[]} statusMessages - Status messages array
 * @param {int} updatedLayoutDuration - Update Layout duration
 */
Layout.prototype.updateStatus = function(
  status, statusFeedback, statusMessages, updatedLayoutDuration,
) {
  // Update status property
  this.status = {
    code: status,
    description: statusFeedback,
    messages: statusMessages,
  };

  // Update layout duration
  if (updatedLayoutDuration) {
    this.duration = Math.round(Number(updatedLayoutDuration) * 100) / 100;
  }

  // Update layout status
  lD.topbar.updateLayoutStatus();
};

/**
 * Calculate layout values for the layout based on the scale of this container
 * @param {object} container - Container DOM object
 * @return {object} Clone Object containing dimensions for the object
 */
Layout.prototype.scale = function(container) {
  const layoutClone = Object.assign({}, this);

  // Get container dimensions
  const containerDimensions = {
    width: container.width(),
    height: container.height(),
  };

  // Calculate ratio
  const elementRatio = layoutClone.width / layoutClone.height;
  const containerRatio = containerDimensions.width / containerDimensions.height;

  // Create container properties object
  layoutClone.scaledDimensions = {};

  // Calculate scale factor
  if (elementRatio > containerRatio) {
    // element is more "landscapish" than the container
    // Scale is calculated using width
    layoutClone.scaledDimensions.scale =
      containerDimensions.width / layoutClone.width;
  } else { // Same ratio or the container is the most "landscapish"
    // Scale is calculated using height
    layoutClone.scaledDimensions.scale =
      containerDimensions.height / layoutClone.height;
  }

  // Calculate new values for the element using the scale factor
  layoutClone.scaledDimensions.width =
    layoutClone.width * layoutClone.scaledDimensions.scale;
  layoutClone.scaledDimensions.height =
    layoutClone.height * layoutClone.scaledDimensions.scale;

  // Calculate top and left values to centre the element in the container
  layoutClone.scaledDimensions.top =
    containerDimensions.height / 2 - layoutClone.scaledDimensions.height / 2;
  layoutClone.scaledDimensions.left =
    containerDimensions.width / 2 - layoutClone.scaledDimensions.width / 2;

  // Get scaled background
  layoutClone.calculatedBackground =
    layoutClone.backgroundCss(
      layoutClone.scaledDimensions.width,
      layoutClone.scaledDimensions.height,
    );

  // Regions Scalling
  for (const region in layoutClone.regions) {
    if (layoutClone.regions.hasOwnProperty(region)) {
      layoutClone.regions[region].scaledDimensions = {};

      // Loop through the container properties
      // and scale them according to the layout scale from the original
      for (const property in layoutClone.regions[region].dimensions) {
        if (layoutClone.regions[region].dimensions.hasOwnProperty(property)) {
          layoutClone.regions[region].scaledDimensions[property] =
            layoutClone.regions[region].dimensions[property] *
            layoutClone.scaledDimensions.scale;
        }
      }
    }
  }

  return layoutClone;
};

/**
 * Create drawer region for actions targets
 * @param {object} drawerData - Drawer data
 */
Layout.prototype.createDrawer = function(drawerData) {
  // Create drawer as a region
  const newDrawer = new Region(
    drawerData.regionId,
    drawerData,
  );

  // Save index
  newDrawer.index = 1;

  // Widgets
  const widgets = newDrawer.playlists.widgets;

  newDrawer.numWidgets = widgets.length;

  // Create widgets for this region
  for (const widget in widgets) {
    if (widgets.hasOwnProperty(widget)) {
      const newWidget = new Widget(
        widgets[widget].widgetId,
        widgets[widget],
        drawerData.regionId,
        this,
      );

      // Save index
      newWidget.index = parseInt(widget) + 1;

      newWidget.editorObject = lD;

      newWidget.parent = newDrawer;

      newWidget.drawerWidget = true;

      // calculate expire status
      newWidget.calculateExpireStatus();

      // Check if widget is enabled
      newWidget.checkIfEnabled();

      // update duration
      newWidget.getDuration();

      // We need to validate if the target region still exists in the layout
      if (this.regions['region_' + newWidget.getOptions().targetRegionId]) {
        newWidget.targetRegionId = newWidget.getOptions().targetRegionId;
      }

      // Add newWidget to the Region widget object
      newDrawer.widgets[newWidget.id] = newWidget;

      // Mark the region as not empty
      newDrawer.isEmpty = false;
    }
  }

  // Dimensions
  newDrawer.dimensions = {
    width: drawerData.width,
    height: drawerData.height,
    top: drawerData.top,
    left: drawerData.left,
  };

  // Set flag as drawer
  newDrawer.isDrawer = true;

  // Push Region to the Layout region array
  this.drawer = newDrawer;
};

/**
 * Create or get canvas region
 * @param {number=} canvasLayer - Canvas layer
 * @return {Promise} Promise with the canvas region
 */
Layout.prototype.getCanvas = function(canvasLayer) {
  const self = this;
  // If we have a canvas already, return it
  if (!$.isEmptyObject(this.canvas)) {
    return Promise.resolve(this.canvas);
  }

  // Create canvas as a region
  // return promise
  return new Promise(function(resolve, reject) {
    // If we have a layer, set it to the dimensions
    const dimensions = {
      width: self.width,
      height: self.height,
    };
    (canvasLayer) && (dimensions.zIndex = canvasLayer);

    // Create canvas as a region
    // always add to the top left
    // and with the same dimensions as the layout
    self.addObject(
      'region',
      {
        objectSubtype: 'canvas',
        positionToAdd: {
          top: 0,
          left: 0,
        },
        dimensions,
      }).then((res) => {
      // Push Region to the Layout region array
      self.canvas = new Canvas(
        res.id,
        res.data,
        {
          width: self.width,
          height: self.height,
        },
      );

      // Add any widgets we have returned with the new canvas.
      $.each(res.data.regionPlaylist?.widgets, function(ix, el) {
        const widget = new Widget(
          el.widgetId,
          el,
          self.canvas.regionId,
          self,
        );
        widget.editorObject = lD;
        self.canvas.widgets[widget.widgetId] = widget;
      });

      // Return the canvas
      resolve(self.canvas);
    });
  });
};

/**
 * Move a widget in a region
 * @param {string} regionId - The target region
 * @param {string} widgetId - The widget to be moved
 * @param {string} moveType - "topLeft"; "left"; "right"; "topRight";
 */
Layout.prototype.moveWidgetInRegion = function(regionId, widgetId, moveType) {
  const getElement = this.DOMObject.find('#' + regionId + ' #' + widgetId);

  switch (moveType) {
    case 'oneRight':
      getElement.insertAfter(
        getElement.next('.designer-widget:not(.designer-widget-ghost)'),
      );
      break;

    case 'oneLeft':
      getElement.insertBefore(
        getElement.prev('.designer-widget:not(.designer-widget-ghost)'),
      );
      break;

    case 'topRight':
      getElement.insertAfter(
        getElement
          .nextAll('.designer-widget:not(.designer-widget-ghost)').last(),
      );
      break;

    case 'topLeft':
      getElement.prependTo(getElement.parent());
      break;

    default:
      console.warn('Change type not known');
      return;
  }

  // Save new order
  lD.common.showLoadingScreen();

  // Get playlist
  const region = this.DOMObject.find('#' + regionId);
  const playlist =
    lD.getObjectByTypeAndId(
      $(region).data('type'),
      $(region).attr('id'),
    ).playlists;

  // Add sort class
  $(region).addClass('to-sort');

  lD.layout.savePlaylistOrder(playlist, $(region)
    .find('.designer-widget:not(.designer-widget-ghost)'))
    .then((res) => { // Success
      lD.common.hideLoadingScreen();

      lD.reloadData(lD.layout);
    }).catch((error) => { // Fail/error
      // Remove sort class
      $(region).removeClass('to-sort');

      lD.common.hideLoadingScreen();

      // Show error returned or custom message to the user
      // Show error returned or custom message to the user
      let errorMessage = '';

      if (typeof error == 'string') {
        errorMessage = error;
      } else {
        errorMessage = error.errorThrown;
      }

      toastr.error(
        errorMessagesTrans.saveOrderFailed.replace('%error%', errorMessage),
      );
    });
};

/**
 * Check if the layout is empty
 * @return {boolean} if the layout has no content
 */
Layout.prototype.isEmpty = function() {
  // Check if there are regions
  if (!$.isEmptyObject(this.regions)) {
    return false;
  }

  // If we have canvas...
  if (!$.isEmptyObject(this.canvas)) {
    // Check if we have more than one widget in canvas
    // ( the canvas widget is there by default )
    if (Object.values(this.canvas.widgets).length > 1) {
      return false;
    }

    // Check if we have any elements in canvas
    if (!$.isEmptyObject(
      Object.values(this.canvas.widgets)[0].elements,
    )) {
      return false;
    }
  }

  return true;
};

/**
 * Save multiple regions at once
 * @param {Object[]} regions Array of regions to be saved
 * @return {Promise} - Promise that resolves when the regions are saved
 */
Layout.prototype.saveMultipleRegions = function(regions) {
  const self = this;
  return new Promise(function(resolve, reject) {
    const requestPath =
      urlsForApi.region.transform.url.replace(':id', self.layoutId);

    const requestData = [];
    regions.forEach((region) => {
      requestData.push({
        width: region.dimensions.width,
        height: region.dimensions.height,
        top: region.dimensions.top,
        left: region.dimensions.left,
        zIndex: region.zIndex,
        regionid: region.regionId,
      });
    });

    $.ajax({
      url: requestPath,
      type: urlsForApi.region.transform.type,
      data: {
        regions: JSON.stringify(requestData),
      },
    }).done(function(data) {
      resolve(data);
    }).fail(function(jqXHR, textStatus, errorThrown) {
      // Reject promise and return an object with all values
      reject(new Error({jqXHR, textStatus, errorThrown}));
    });
  });
};

/**
 * Save layout background layer
 * @param {number} layer New layer
 * @return {Promise} - Promise that resolves when the regions are saved
 */
Layout.prototype.saveBackgroundLayer = function(layer) {
  const self = this;
  return new Promise(function(resolve, reject) {
    const requestPath =
      urlsForApi.layout.saveForm.url.replace(':id', self.layoutId);

    $.ajax({
      url: requestPath,
      type: urlsForApi.layout.saveForm.type,
      data: {
        backgroundColor: self.backgroundColor,
        backgroundImageId: self.backgroundImage,
        resolutionId: self.resolutionId,
        backgroundzIndex: self.backgroundzIndex,
      },
    }).done(function(data) {
      resolve(data);
    }).fail(function(jqXHR, textStatus, errorThrown) {
      // Reject promise and return an object with all values
      reject(new Error({jqXHR, textStatus, errorThrown}));
    });
  });
};

module.exports = Layout;
