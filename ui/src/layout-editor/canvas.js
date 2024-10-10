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

// CANVAS Module

/**
 * Canvas contructor
 * @param {number} id - region id
 * @param {object} data - data from the API request
 * @param {object} layoutDimensions - layout dimensions
 */
const Canvas = function(id, data, layoutDimensions) {
  this.id = 'region_' + id;
  this.regionId = id;

  this.type = 'region';
  this.subType = 'canvas';

  this.name = data.name;
  this.playlists = data.regionPlaylist;
  this.loop = false; // Loop region widgets

  // Widgets
  this.widgets = {};

  this.options = data.regionOptions;

  // Permissions
  this.isEditable = data.isEditable;
  this.isDeletable = data.isDeletable;
  this.isViewable = data.isViewable;
  this.isPermissionsModifiable = data.isPermissionsModifiable;

  // Interactive actions
  this.actions = data.actions;

  // set dimentions
  this.dimensions = {
    width: layoutDimensions.width,
    height: layoutDimensions.height,
    top: 0,
    left: 0,
  };

  this.zIndex = data.zIndex;
};

/**
 * Change canvas layer
 * @param {number} [newLayer] - New left position (for move tranformation)
 * @param {bool=} saveToHistory - Flag to save or not to the change history
 */
Canvas.prototype.changeLayer = function(newLayer, saveToHistory = true) {
  // add transform change to history manager
  if (saveToHistory) {
    // save old/previous values
    const oldValues = [{
      width: this.dimensions.width,
      height: this.dimensions.height,
      top: this.dimensions.top,
      left: this.dimensions.left,
      zIndex: this.zIndex,
      regionid: this.regionId,
    }];

    // Update new values if they are provided
    const newValues = [{
      width: this.dimensions.width,
      height: this.dimensions.height,
      top: this.dimensions.top,
      left: this.dimensions.left,
      zIndex: (newLayer != undefined) ?
        newLayer : this.zIndex,
      regionid: this.regionId,
    }];

    // Add a tranform change to the history array
    lD.historyManager.addChange(
      'transform',
      'region',
      this.regionId,
      {
        regions: JSON.stringify(oldValues),
      },
      {
        regions: JSON.stringify(newValues),
      },
      {
        upload: true, // options.upload
        targetSubType: 'canvas',
      },
    ).catch((error) => {
      toastr.error(errorMessagesTrans.transformRegionFailed);
      console.error(error);
    });
  }

  // Apply changes to the canvas ( updating values )
  this.zIndex = (newLayer != undefined) ?
    newLayer : this.zIndex;
};

/**
 * Get widgets by type
 * @param {string} type - Type of widget
 * @param {boolean} getEditableOnly - Get only widgets that can be edited
 * @return {object} Found widgets
 */
Canvas.prototype.getWidgetsOfType = function(type, getEditableOnly = true) {
  const widgets = {};
  const self = this;

  Object.values(self.widgets).forEach((widget) => {
    if (
      (
        (
          getEditableOnly &&
          widget.isEditable
        ) ||
        !getEditableOnly
      ) &&
      widget.subType === type
    ) {
      widgets[widget.widgetId] = widget;
    }
  });

  return widgets;
};

/**
 * Get widgets by type
 * @param {string} type - Type of widget
 * @param {boolean} getEditableOnly - Get only widgets that can be edited
 * @param {boolean} getFirstIfNotActive
 *  - Return first valid widget if we don't have an active
 * @return {object} Active or first widget
 */
Canvas.prototype.getActiveWidgetOfType = function(
  type,
  getEditableOnly = true,
  getFirstIfNotActive = true,
) {
  const self = this;
  let targetWidget = {};
  let firstWidgetAdded = false;

  Object.values(self.widgets).every((widget) => {
    const isValid = (
      (
        getEditableOnly &&
        widget.isEditable
      ) ||
      !getEditableOnly
    ) &&
    widget.subType === type;

    // Get first widget or active valid widget
    if (isValid) {
      if (
        !widget.activeTarget &&
        !firstWidgetAdded &&
        getFirstIfNotActive
      ) {
        // Save targetWidget to be sent
        // if we don't find an active one
        firstWidgetAdded = true;
        targetWidget = widget;
      } else if (widget.activeTarget) {
        // Active widget, return right away
        targetWidget = widget;
        return false;
      }
    }

    return true;
  });

  // Return first found widget if we didn't have any active
  return targetWidget;
};

/**
 * Move elements between widgets
 * @param {object} sourceWidgetId - Old widget id
 * @param {object} targetWidgetId - Target widget id
 * @param {object[]} elements - Elements to be moved
 * @param {object[]} groups - Groups to be moved
 */
Canvas.prototype.moveElementsBetweenWidgets = function(
  sourceWidgetId,
  targetWidgetId,
  elements,
  groups,
) {
  const self = this;

  const sourceWidget = lD.layout.canvas.widgets[sourceWidgetId];
  const targetWidget = lD.layout.canvas.widgets[targetWidgetId];
  let reloadPropertiesPanel = false;

  const updateInViewer = function(
    id,
    target,
  ) {
    const $target = lD.viewer.DOMObject.find('#' + id);
    $target.data('widgetId', target.widgetId);
    $target.attr('data-widget-id', target.widgetId);
    $target.data('regionId', target.regionId);
    $target.attr('data-region-id', target.widgetId);
  };

  // Move elements
  elements.forEach((element) => {
    // Change region and widget ids
    element.widgetId = targetWidget.widgetId;
    element.regionId = targetWidget.regionId.split('_')[1];

    if (lD.selectedObject.elementId === element.elementId) {
      reloadPropertiesPanel = true;
      element.selected = true;
      lD.selectedObject = element;
    }

    // Update in viewer
    updateInViewer(element.elementId, element);
    lD.viewer.renderElementContent(element);

    // Add to new widget
    targetWidget.elements[element.elementId] = element;

    // Remove from old widget
    self.removeFromCanvasWidget(
      sourceWidget.id,
      element.elementId,
      'element',
    );
  });

  // Move groups
  groups.forEach((group) => {
    const widgetId = targetWidget.widgetId;
    const regionId = targetWidget.regionId.split('_')[1];
    // Change region and widget ids
    group.widgetId = widgetId;
    group.regionId = regionId;

    if (lD.selectedObject.id === group.id) {
      reloadPropertiesPanel = true;
      group.selected = true;
      lD.selectedObject = group;
    }

    // Update in viewer
    updateInViewer(group.id, group);
    Object.values(group.elements).forEach((el) => {
      // Change region and widget ids
      el.widgetId = widgetId;
      el.regionId = regionId;

      if (lD.selectedObject.elementId === el.elementId) {
        reloadPropertiesPanel = true;
        el.selected = true;
        lD.selectedObject = el;
      }

      updateInViewer(el.elementId, el);
      lD.viewer.renderElementContent(el);

      // Add to new widget
      targetWidget.elements[el.elementId] = el;

      // Remove from old widget
      self.removeFromCanvasWidget(
        sourceWidget.id,
        el.elementId,
        'element',
      );
    });

    // Add to new widget
    targetWidget.elementGroups[group.id] = group;

    // Remove from old widget
    self.removeFromCanvasWidget(
      sourceWidget.id,
      group.id,
      'group',
    );
  });

  // Save both widgets
  Promise.all([
    sourceWidget.saveElements(),
    targetWidget.saveElements(),
  ]).then((_res) => {
    // Reload properties panel
    (reloadPropertiesPanel) &&
      lD.propertiesPanel.render(lD.selectedObject);
  });
};

/**
 * Remove elements or group from canvas widget
 * @param {object} widgetId - Old widget
 * @param {string} objectToRemoveId - Id of the Group or element to be removed
 * @param {string} type - group or element

 */
Canvas.prototype.removeFromCanvasWidget = function(
  widgetId,
  objectToRemoveId,
  type,
) {
  if (type === 'group') {
    delete lD.layout.canvas.widgets[widgetId].elementGroups[objectToRemoveId];
  } else {
    delete lD.layout.canvas.widgets[widgetId].elements[objectToRemoveId];
  }
};

/**
 * Edit property by type
 * @param {string} property - property to edit
 */
Canvas.prototype.editPropertyForm = function(property) {
  const self = this;
  const app = lD;

  // Load form the API
  const linkToAPI = urlsForApi.region['get' + property];

  let requestPath = linkToAPI.url;

  // Replace widget id
  requestPath = requestPath.replace(':id', this.regionId);

  // Create dialog
  const calculatedId = new Date().getTime();

  // Create dialog
  const dialog = bootbox.dialog({
    className: 'second-dialog',
    title: editorsTrans.loadPropertyForObject
      .replace('%prop%', property)
      .replace('%obj%', 'region'),
    message:
        '<p><i class="fa fa-spin fa-spinner"></i>' +
        editorsTrans.loading +
        '...</p>',
    size: 'large',
    buttons: {
      cancel: {
        label: translations.cancel,
        className: 'btn-white btn-bb-cancel',
      },
      done: {
        label: translations.done,
        className: 'btn-primary test btn-bb-done',
        callback: function(res) {
          app.common.showLoadingScreen();

          let dataToSave = '';
          const options = {
            addToHistory: false, // options.addToHistory
          };

          // Get data to save
          if (property === 'Permissions') {
            dataToSave = formHelpers.permissionsFormBeforeSubmit(dialog);
            options.customRequestPath = {
              url: dialog.find('.permissionsGrid').data('url'),
              type: 'POST',
            };
          } else {
            dataToSave = form.serialize();
          }

          app.historyManager.addChange(
            'save' + property,
            'widget', // targetType
            self.regionId, // targetId
            null, // oldValues
            dataToSave, // newValues
            options,
          ).then((res) => { // Success
            app.common.hideLoadingScreen();

            dialog.modal('hide');

            app.reloadData(app.layout);
          }).catch((error) => { // Fail/error
            app.common.hideLoadingScreen();

            // Show error returned or custom message to the user
            let errorMessage = '';

            if (typeof error == 'string') {
              errorMessage += error;
            } else {
              errorMessage += error.errorThrown;
            }

            // Display message in form
            formHelpers.displayErrorMessage(
              dialog.find('form'),
              errorMessage,
              'danger',
            );

            // Show toast message
            toastr.error(errorMessage);
          });
        },

      },
    },
  }).attr('id', calculatedId).attr('data-test', 'region' + property + 'Form');

  // Request and load property form
  $.ajax({
    url: requestPath,
    type: linkToAPI.type,
  }).done(function(res) {
    if (res.success) {
      // Add title
      dialog.find('.modal-title').html(res.dialogTitle);

      // Add body main content
      dialog.find('.bootbox-body').html(res.html);

      dialog.data('extra', res.extra);

      if (property == 'Permissions') {
        formHelpers.permissionsFormAfterOpen(dialog);
      }

      // Call Xibo Init for this form
      XiboInitialise('#' + dialog.attr('id'));
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

        dialog.modal('hide');
      }
    }
  }).catch(function(jqXHR, textStatus, errorThrown) {
    console.error(jqXHR, textStatus, errorThrown);
    toastr.error(errorMessagesTrans.formLoadFailed);

    dialog.modal('hide');
  });
};

module.exports = Canvas;
