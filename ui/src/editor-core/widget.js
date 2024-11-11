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

/* eslint-disable new-cap */
// WIDGET Module
const EXPIRE_STATUS_MSG_MAP = [
  '',
  widgetStatusTrans.setToStart,
  widgetStatusTrans.setToExpire,
  widgetStatusTrans.expired,
  widgetStatusTrans.deleteOnExpire,
];

const EXPIRE_STATUS_ICON_MAP = [
  '',
  'fa-calendar-plus-o',
  'fa-calendar-o',
  'fa-calendar-check-o',
  'fa-calendar-times-o',
];

const Element = require('../editor-core/element.js');
const ElementGroup = require('../editor-core/element-group.js');

/**
 * Widget contructor
 * @param {number} id - widget id
 * @param {object} data - data from the API request
 * @param {number} regionId - region where the widget belongs ( if exists )
 * @param {object} layoutObject - layout object
 */
const Widget = function(id, data, regionId = null, layoutObject = null) {
  this.widgetId = id;

  if (regionId != null) {
    this.id = 'widget_' + regionId + '_' + id; // widget_regionID_widgetID
    this.regionId = 'region_' + regionId;
  } else {
    this.id = 'widget_' + id; // widget_widgetID
  }

  // Widget elements and groups
  this.elements = {};
  this.elementGroups = {};
  this.elementTypeMap = {};

  // Elements previous state
  this.elementsLastState = '';

  this.widgetName = data.name;

  this.layoutObject = layoutObject;

  this.isValid = data.isValid;

  // widget type
  this.type = 'widget';
  this.subType = data.type;
  this.moduleName = data.moduleName;
  this.moduleDataType = data.moduleDataType;

  // Permissions
  this.isEditable = data.isEditable;
  this.isDeletable = data.isDeletable;
  this.isViewable = data.isViewable;
  this.isPermissionsModifiable = data.isPermissionsModifiable;

  // widget tags
  this.tags = data.tags ? data.tags : [];

  // widget media
  this.mediaIds = data.mediaIds;

  // playlist id
  this.playlistId = data.playlistId;

  // check if audio can be attached to it
  const typesThatCantHaveAudio = ['subplaylist'];
  this.canAttachAudio = !typesThatCantHaveAudio.includes(this.subType);

  // Widget colouring
  if (
    playlistRegionColouring === 'Permissions Colouring' ||
    playlistRegionColouring === 'Sharing Colouring'
  ) {
    this.widgetColouring =
      (data.isEditable) ?
        'timelineMediaItemColouring_enabled' :
        'timelineMediaItemColouring_disabled';
  } else {
    this.widgetColouring = '';

    for (let index = 0; index < this.tags.length; index++) {
      this.widgetColouring += this.tags[index].tag + ' ';
    }
  }

  this.selected = false;

  this.singleWidget = false;
  this.loop = false;
  this.extend = false;

  // by default, if not set, duration is null ( to be calculated )
  this.duration = (data.useDuration == 1) ? data.duration : null;

  this.widgetDurationNotSet = false;
  // in the case of the duration has not being calculated
  this.widgetDefaultDuration = 10;

  this.widgetOptions = data.widgetOptions;
  this.calculatedDuration = data.calculatedDuration;

  this.audio = data.audio;

  this.fromDt = data.fromDt;
  this.toDt = data.toDt;

  // Date limits constants
  this.DATE_MIN = 0;
  this.DATE_MAX = 2147483647;

  // Widget expire status
  // 0: Not set, 1: Due to expire, 2: Expired, 3: Delete on expire
  this.expireStatus = 0;
  this.expireStatusTitle = '';
  this.expireStatusIcon = '';

  // Auto transitions
  this.transitionIn = data.transitionIn;
  this.transitionOut = data.transitionOut;
  this.transitionDurationIn = data.transitionDurationIn;
  this.transitionDurationOut = data.transitionDurationOut;
  // Interactive actions
  this.actions = data.actions;

  // Cached data
  this.cachedData = {};
  this.forceRecalculateData = false;

  this.validateData = {};

  // Widget active target for adding elements
  this.activeTarget = false;

  this.validateRequiredElements = function() {
    const moduleType = this.subType;
    // Check if element is required
    const elementModule = lD.common.getModuleByType(moduleType);

    // Check if there are required elements for this widget
    const originalRequiredElements = (
      elementModule &&
      Array.isArray(elementModule.requiredElements)
    ) ? elementModule.requiredElements : [];
    const requiredElementsAux = originalRequiredElements.slice();

    // If array is empty, we don't need to validate elements
    let requiredElementsValid = (requiredElementsAux.length === 0);

    // Loop through elements
    Object.values(this.elements)
      .forEach((element) => {
        const find = requiredElementsAux.indexOf(element.id);
        if (find != -1) {
          // Remove elements from aux array
          requiredElementsAux.splice(find, 1);

          // Check if valid now
          requiredElementsValid = (requiredElementsAux.length === 0);
        }
      });

    // Save and return required structure
    this.requiredElements = {
      required: originalRequiredElements,
      missing: requiredElementsAux,
      valid: requiredElementsValid,
    };
    return this.requiredElements;
  };

  /**
   * Get transitions from options
   * @return {object} transitions
   */
  this.transitions = function() {
    const trans = {};
    const widgetDurationInMs = this.getDuration() * 1000;

    if (this.transitionIn != null &&
      this.transitionIn != '' &&
      this.transitionIn != undefined
    ) {
      trans.in = {
        name: 'transitionIn',
        type: this.transitionIn,
        duration: this.transitionDurationIn,
        percDuration:
          (this.transitionDurationIn != undefined) ?
            (parseFloat(this.transitionDurationIn) / widgetDurationInMs * 100) :
            0,
        direction: this.getOptions().transInDirection,
      };
    }

    if (this.transitionOut != null &&
      this.transitionOut != '' &&
      this.transitionOut != undefined
    ) {
      trans.out = {
        name: 'transitionOut',
        type: this.transitionOut,
        duration: this.transitionDurationOut,
        percDuration:
          (this.transitionDurationOut != undefined) ?
            (
              parseFloat(this.transitionDurationOut) /
              widgetDurationInMs * 100
            ) :
            0,
        direction: this.getOptions().transOutDirection,
      };
    }

    return trans;
  };

  /**
     * Get an object containing options returned from the back end
     * @return {object} - Options object
     */
  this.getOptions = function() {
    const options = {};

    for (const option in this.widgetOptions) {
      if (this.widgetOptions.hasOwnProperty(option)) {
        const currOption = this.widgetOptions[option];

        if (currOption.type === 'attrib') {
          options[currOption.option] = currOption.value;
        } else if (currOption.type === 'raw') {
          try {
            options[currOption.option] = JSON.parse(currOption.value);
          } catch (e) {
            // If we can't parse the JSON, just set the value as a string
            options[currOption.option] = currOption.value;
          }
        } else if (currOption.type === 'cdata') {
          options[currOption.option] = currOption.value;
        }
      }
    }

    return options;
  };

  /**
   * Get widget calculated duration ( could be different for some widgets )
   * @return {number} - Widget duration in seconds
   */
  this.getDuration = function() {
    return parseFloat(this.calculatedDuration);
  };

  /**
   * Get widget calculated duration with the transition out value if exists
   * @return {number} - Widget duration in seconds
   */
  this.getTotalDuration = function() {
    let totalDuration = this.getDuration();

    // Extend with transition out duration if exists
    if (this.transitionDurationOut != undefined) {
      totalDuration += parseFloat(this.transitionDurationOut) / 1000;
    }

    return totalDuration;
  };

  /**
     * Get widget status based on expire dates
     * @return {number} - Widget expire state
     * 0: Not set, 1: Due to expire, 2: Expired, 3: Delete on expire
     */
  this.calculateExpireStatus = function() {
    let status = 0;
    const currentTime = Math.round(new Date().getTime() / 1000);

    if (this.fromDt > this.DATE_MIN || this.toDt < this.DATE_MAX) {
      if (currentTime < this.fromDt) {
        // Set to start
        status = 1;
      } else if (currentTime > this.toDt) {
        // Expired
        status = 3;
      } else if (
        this.getOptions().deleteOnExpiry == 1 &&
        currentTime < this.toDt &&
        this.toDt < this.DATE_MAX
      ) {
        // Delete on expire ( delete on expiry flag
        // and toDt set and after current time)
        status = 4;
      } else if (currentTime < this.toDt && this.toDt < this.DATE_MAX) {
        // Set to expire ( current time before toDt and toDt is set)
        status = 2;
      }
    }

    // save status to the widget property
    this.expireStatus = status;

    // save status message
    this.expireStatusTitle = '<p class="font-weight-bold mb-0 text-left">' +
      EXPIRE_STATUS_MSG_MAP[status] + '</p>';

    if (this.fromDt > this.DATE_MIN) {
      this.expireStatusTitle +=
      '<p class="mb-0">' +
      widgetStatusTrans.startTime +
      ': ' +
      moment.unix(this.fromDt).tz(timezone).format(jsDateFormat) +
      '</p>';
    }

    if (this.toDt < this.DATE_MAX) {
      this.expireStatusTitle +=
      '<p class="mb-0">' +
      widgetStatusTrans.endTime +
      ': ' +
      moment.unix(this.toDt).tz(timezone).format(jsDateFormat) +
      '</p>';
    }

    // save status icon
    this.expireStatusIcon = EXPIRE_STATUS_ICON_MAP[status];

    // return status
    return status;
  };

  /**
     * Check the module list for the widget type
     * and get if it's region specific or not
     * @return {boolean}
     */
  this.isRegionSpecific = function() {
    const self = this;
    // Set default as true, so a non existing module
    // would have the same rules as a region specific one
    let regionSpecific = true;

    Object.keys(modulesList).forEach(function(item) {
      if (modulesList[item].type == self.subType) {
        regionSpecific = (modulesList[item].regionSpecific == 1);
      }
    });

    return regionSpecific;
  };

  /**
     * Get properties that are to be sent to the widget elements
     * @return {boolean}
     */
  this.getSendToElementProperties = function() {
    const self = this;
    const sendToElement = {};

    Object.keys(modulesList).forEach(function(item) {
      if (modulesList[item].type == self.subType) {
        modulesList[item].properties.forEach((ppt) => {
          if (ppt.sendToElements === true) {
            sendToElement[ppt.id] = self.getOptions()[ppt.id];
          }
        });
      }
    });

    return sendToElement;
  };

  /**
   * Get icon from module
   * @return {string}
   */
  this.getIcon = function() {
    const self = this;
    let moduleIcon = '';
    Object.keys(modulesList).forEach(function(item) {
      if (modulesList[item].type == self.subType) {
        moduleIcon = modulesList[item].icon;
      }
    });

    return moduleIcon;
  };

  /**
     * Check if the current module is enabled
     */
  this.checkIfEnabled = function() {
    // Check if module is enabled
    const module = this.editorObject.common.getModuleByType(this.subType);
    this.enabled = !$.isEmptyObject(module);

    // Override properties if not enabled
    if (!this.enabled) {
      this.isEditable = false;
      this.isPermissionsModifiable = false;
      this.isDeletable = data.isDeletable;
    }
  };

  this.checkShowFallbackData = function() {
    // Check if module has fallbackl data enabled
    const module = this.editorObject.common.getModuleByType(this.subType);

    this.fallbackDataActive = (module.fallbackData === 1);

    return this.fallbackDataActive;
  };

  /**
   * Get widget full id
   * @return {string}
   */
  this.getFullId = function() {
    return 'widget_' + this.regionId.split('region_')[1] + '_' + this.widgetId;
  };

  /**
   * Save current element state to be used for history manager
   */
  this.updateElementPreviousState = function() {
    this.elementsLastState =
      JSON.stringify([
        {
          elements: this.processElementsToSave(this.elements),
        },
      ]);
  };

  /**
   * Process elements to be saved
   * @param {object} elements
   * @return {object} elements to save
   */
  this.processElementsToSave = function(elements) {
    const elementsToParse = (elements) ? elements : this.elements;

    return Object.values(elementsToParse).map((element) => {
      // Save only id and value for element properties if they are not empty
      if (element.properties != undefined) {
        element.properties =
          Object.values(element.properties).map((property) => {
            return {
              id: property.id,
              value: property.value,
            };
          });
      }

      const elementObject = {
        id: element.id,
        elementName: element.elementName,
        elementId: element.elementId,
        type: element.elementType,
        left: element.left,
        top: element.top,
        width: element.width,
        height: element.height,
        layer: element.layer,
        rotation: element.rotation,
        properties: element.properties,
        isVisible: element.isVisible,
      };

      // If we have group, add group properties
      if (element.group) {
        elementObject.groupId = element.group.id;
        elementObject.groupProperties = {
          elementGroupName: element.group.elementGroupName,
          top: element.group.top,
          left: element.group.left,
          width: element.group.width,
          height: element.group.height,
          effect: element.group.effect,
          layer: element.group.layer,
          pinSlot: element.group.pinSlot,
        };

        // Save group scale type if exists
        if (element.groupScale) {
          elementObject.groupScale = 1;
        } else if (element.groupScaleType) {
          elementObject.groupScale = 0;
          elementObject.groupScaleType = element.groupScaleType;
        }
      } else {
        // Save effect if exists
        if (element.effect !== undefined) {
          elementObject.effect = element.effect;
        }
      }

      // Save media id and name if exists
      if (element.mediaId !== undefined) {
        elementObject.mediaId = element.mediaId;
      }

      if (element.mediaName !== undefined) {
        elementObject.mediaName = element.mediaName;
      }

      // Save slot if exists
      if (element.slot != undefined) {
        elementObject.slot = Number(element.slot);

        // Save pin slot option
        (element.pinSlot != undefined) &&
          (elementObject.pinSlot = element.pinSlot);
      }

      return elementObject;
    });
  };
};

/**
 * Create clone from widget
 * @return {object} - Widget clone
 */
Widget.prototype.createClone = function() {
  const self = this;

  const widgetClone = {
    id: 'ghost_' + this.id,
    widgetName: this.widgetName,
    moduleName: this.moduleName,
    subType: this.subType,
    duration: this.getTotalDuration(),
    regionId: this.regionId,
    // so that can be calculated on template rendering time
    durationPercentage: function() {
      return (this.duration / self.layoutObject.duration) * 100;
    },
  };

  return widgetClone;
};

/**
 * Edit property form
 *
 * @param {string} property - property to edit
 * @param {object} type - type of the property
 */
Widget.prototype.editPropertyForm = function(property, type) {
  const self = this;

  const app = this.editorObject;

  // Load form the API
  const linkToAPI = urlsForApi.widget['get' + property];

  let requestPath = linkToAPI.url;

  // Replace type
  requestPath = requestPath.replace(':type', type);

  // Replace widget id
  requestPath = requestPath.replace(':id', this.widgetId);

  // Create dialog
  const calculatedId = new Date().getTime();

  // Create dialog
  const dialog = bootbox.dialog({
    className: 'second-dialog',
    title: editorsTrans.loadPropertyForObject
      .replace('%prop%', property).replace('%obj%', 'widget'),
    message:
      '<p><i class="fa fa-spin fa-spinner"></i> ' +
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
          const form = dialog.find('form');

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

          // If there is a type to replace
          if (type !== undefined) {
            options.customRequestReplace = {
              tag: ':type',
              replace: type,
            };
          }

          app.historyManager.addChange(
            'save' + property,
            'widget', // targetType
            self.widgetId, // targetId
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
              dialog.find('form'), errorMessage,
              'danger',
            );

            // Show toast message
            toastr.error(errorMessage);
          });
        },

      },
    },
  }).attr('id', calculatedId).attr('data-test', 'widgetPropertiesForm');

  // Request and load element form
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

      if (property === 'Permissions') {
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

/**
 * Edit attached audio
 */
Widget.prototype.editAttachedAudio = function() {
  this.editPropertyForm('Audio');
};

/**
 * Edit expiry dates
 */
Widget.prototype.editExpiry = function() {
  this.editPropertyForm('Expiry');
};

/**
 * Edit transitions dates
 * @param {string} type - transition type, in or out
 */
Widget.prototype.editTransition = function(type) {
  this.editPropertyForm('Transition', type);
};

/**
 * Edit permissions
 */
Widget.prototype.editPermissions = function() {
  this.editPropertyForm('Permissions');
};

/**
 * Get next widget in line
 * @param {boolean} reverse - if true, get previous widget
 * @return {object} - next widget
 */
Widget.prototype.getNextWidget = function(reverse = false) {
  // Get main app
  const app = this.editorObject;

  // Get region widgets
  const region = app.getObjectByTypeAndId('region', this.regionId);
  const widgets = region.widgets;

  // Calculate new index
  const index = this.index + ((reverse) ? -1 : 1);

  // Select first widget
  for (const widget in widgets) {
    if (widgets.hasOwnProperty(widget)) {
      if (widgets[widget].index == index) {
        return widgets[widget];
      }
    }
  }

  return false;
};

/**
 * Save elements to widget
 * @param {object} elements - elements to save
 * @param {boolean} updateEditor - update editor
 * @param {boolean} reloadData - reload data
 * @param {boolean} forceRequest
 *  - always make request even another one is happening
 * @param {boolean} addToHistory
 *  - save state to history so it can be reverted
 * @return {Promise} - Promise
 */
Widget.prototype.saveElements = function(
  {
    elements = null,
    updateEditor = false,
    reloadData = true,
    forceRequest = false,
    addToHistory = true,
  } = {},
) {
  // If widget isn't editable, throw an error
  if (this.isEditable === false) {
    toastr.error(errorMessagesTrans.canvasWidgetNotShared);
    return;
  }

  const self = this;
  const app = this.editorObject;
  const widgetId = this.widgetId;
  const linkToAPI = urlsForApi.widget.saveElements;
  const requestPath = linkToAPI.url.replace(':id', widgetId);

  let elementsToSave = (elements) ? elements : this.elements;

  let savePending;

  const reloadLayout = function() {
    if (!reloadData) {
      return;
    }

    return app.reloadData(app.layout,
      {
        refreshEditor: updateEditor,
      });
  };

  // If there's no more elements in widget, remove it
  if (Object.keys(elementsToSave).length == 0) {
    const isGlobalWidget = (this.subType === 'global');
    let removeCanvasRegion = false;
    let removeCurrentWidget = false;
    let unsetCanvasDuration = false;
    const canvas = app.layout.canvas;

    // If we deleted the last global element
    if (isGlobalWidget) {
      // Check if we have other widgets on the canvas region
      if (Object.keys(canvas.widgets).length > 1) {
        removeCanvasRegion = false;
        removeCurrentWidget = false;
        unsetCanvasDuration = true;
      } else {
        // Otherwise, just remove the region
        removeCanvasRegion = true;
      }
    } else if (Object.keys(canvas.widgets).length === 2) {
      // If it's not a global element, and
      // we only have this widget and the global

      // Check if the global widget has elements
      let globalHasElements = true;
      $.each(canvas.widgets, function(i, e) {
        if (e.subType === 'global' && Object.keys(e.elements).length === 0) {
          globalHasElements = false;
        }
      });

      // If global has no elements, we delete region altogether
      if (!globalHasElements) {
        removeCanvasRegion = true;
      } else {
        removeCurrentWidget = true;
      }
    } else {
      // If it's not global, and we have more than 2 widgets, remove this one
      removeCurrentWidget = true;
    }

    // If we removed all global elements
    // but we still have other widgets in canvas
    // Set canvas region duration to null to reset it
    if (unsetCanvasDuration) {
      const linkToAPI = urlsForApi.widget.saveForm;
      const requestPath = linkToAPI.url.replace(':id', self.widgetId);

      // Data to be saved
      const dataToSave = {
        name: self.widgetName,
        useDuration: false,
        duration: null,
      };

      // Set widget duration to 0
      savePending = $.ajax({
        url: requestPath,
        type: linkToAPI.type,
        data: dataToSave,
      }).done(function(res) {
        if (!res.success) {
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
    } else if (
      removeCanvasRegion
    ) {
      // Remove region
      app.layout.deleteObject('region', canvas.regionId)
        .then(() => {
          // Remove object from structure
          app.layout.removeFromStructure(
            'region',
            canvas.regionId,
            'canvas',
          );

          // If selected object is an element or group with the canvas region id
          // unselect it
          if (
            app.selectedObject.regionId === canvas.regionId
          ) {
            app.selectedObject = app.layout;
            app.selectedObject.type = 'layout';
            app.viewer.selectObject();
          }

          // Refresh layer manager
          app.viewer.layerManager.render();

          // Remove canvas from viewer
          app.viewer.DOMObject.find('.designer-region-canvas').remove();
        });
    } else if (removeCurrentWidget) {
      // Remove widget
      app.layout.deleteObject('widget', this.widgetId)
        .then(() => {
          // Remove object from structure
          app.layout.removeFromStructure(
            'widget',
            this.widgetId,
            'canvas',
          );

          // If selected object is an element or group with the widget id
          // unselect it
          if (
            app.selectedObject.widgetId === this.widgetId
          ) {
            app.selectedObject = app.layout;
            app.selectedObject.type = 'layout';
            app.viewer.selectObject();
          }

          // Refresh layer manager
          app.viewer.layerManager.render();
        });
    }

    // If we removed widget or region, we
    // skip saving elements
    if (
      removeCanvasRegion ||
      removeCurrentWidget
    ) {
      return Promise.resolve();
    }
  }

  // Update element map
  this.updateElementMap();

  // Process elements to save
  elementsToSave = Array.isArray(elementsToSave) ?
    elementsToSave :
    this.processElementsToSave(elementsToSave);

  // Check if it's valid JSON
  try {
    JSON.parse(JSON.stringify(elementsToSave));
  } catch (e) {
    console.error('saveElementsToWidget', e);
    return;
  }

  // Check previously saved elements
  let previousElements = '';
  if (this.elementsLastState != '') {
    previousElements = this.elementsLastState;
  }

  // Save current as previous to be used for undo next time
  this.elementsLastState = JSON.stringify([
    {
      elements: elementsToSave,
    },
  ]);

  // Save to history if it has previous state to be reverted to
  if (addToHistory) {
    lD.historyManager.addChange(
      'saveElements',
      'widget',
      this.widgetId,
      previousElements,
      JSON.stringify([
        {
          elements: elementsToSave,
        },
      ]),
      {
        upload: false, // options.upload
        skipUpload: true,
        uploaded: true,
        auxTarget: {
          type: 'region',
          id: lD.layout.canvas.regionId,
        },
      },
    );
  }

  lD.common.showLoadingScreen();

  const saveElementsRequest = function() {
    if (
      self.saveElementsRequest == 'force' &&
      forceRequest === false
    ) {
      lD.common.hideLoadingScreen();

      // If the previous request was forced, cancel current
      return Promise.resolve('Cancelled due to previous force request!');
    } else if (
      self.saveElementsRequest != undefined &&
      forceRequest === false
    ) {
      // If there was still a render request, abort it
      self.saveElementsRequest.abort('requestAborted');
    }

    const saveRequest = $.ajax({
      url: requestPath,
      type: linkToAPI.type,
      dataType: 'json',
      data: JSON.stringify([
        {
          elements: elementsToSave,
        },
      ]),
    }).fail(function(jqXHR, textStatus, errorThrown) {
      // Clear request var after response
      self.saveElementsRequest = undefined;

      if (textStatus != 'requestAborted') {
        console.error('saveElementsToWidget', jqXHR, textStatus, errorThrown);
      }
    }).always(function(res) {
      // Clear request var after response
      self.saveElementsRequest = undefined;

      lD.common.hideLoadingScreen();

      if (res.success) {
        return reloadLayout();
      } else {
        // Login Form needed?
        if (res.login) {
          window.location.reload();
        } else {
          if (res.statusText != 'requestAborted') {
            // Just an error we dont know about
            if (res.message == undefined) {
              console.error(res);
            } else {
              console.error(res.message);
            }
          }
        }
      }
    });

    // If this request is forced, save flag and return request
    if (forceRequest === true) {
      self.saveElementsRequest = 'force';
    } else {
      // Save request so it can be cancelled if we repeat the request
      self.saveElementsRequest = saveRequest;
    }

    // Return save request
    return saveRequest;
  };

  if (savePending) {
    return savePending.then(saveElementsRequest);
  } else {
    return saveElementsRequest();
  }
};

/**
 * Add element to widget
 * @param {object} element - element to add
 * @param {boolean} save - if true, save changes to widget
 * @return {Promise} - Promise
 */
Widget.prototype.addElement = function(
  element,
  save = true,
) {
  // Region id parse
  const regionId = (this.regionId.split('region_') > 1) ?
    this.regionId : this.regionId.split('region_')[1];
  // Add element to object
  const newElement = this.elements[element.elementId] = new Element(
    element,
    this.widgetId,
    regionId,
    this,
  );

  // Update elements map for the widget
  this.elements[element.elementId].slot = this.updateElementMap(newElement);

  // If we have a groupId, add or assign it to the group
  if (
    element.groupId != undefined &&
    element.groupId != ''
  ) {
    if (this.elementGroups[element.groupId] == undefined) {
      this.elementGroups[element.groupId] = new ElementGroup(
        Object.assign(
          (element.group) ? element.group : element.groupProperties,
          {
            id: element.groupId,
          },
        ),
        this.widgetId,
        regionId,
        this,
      );
    }

    // Add group reference to element
    newElement.group = this.elementGroups[element.groupId];

    // Remove temporary group properties from element
    delete newElement.groupProperties;

    // Add element to group
    this.elementGroups[element.groupId]
      .elements[element.elementId] =
        this.elements[element.elementId];

    // Update slot on group ( if not defined )
    if (this.elementGroups[element.groupId].slot != undefined) {
      this.elementGroups[element.groupId].updateSlot(
        this.elementGroups[element.groupId].slot,
        true,
      );
    } else {
      this.elementGroups[element.groupId].updateSlot(
        newElement.slot,
      );
    }
  }

  // Get element properties
  return newElement.getProperties().then(() => {
    // Save changes to widget and return promise
    if (save) {
      return this.saveElements();
    } else {
      return Promise.resolve();
    }
  });
};

/**
 * Remove element
 * @param {string} elementId - id of the element to remove
 * @param {boolean} save - if true, save changes to widget
 * @param {boolean} removeFromViewer - if true, remove from viewer
 * @param {boolean} reload - if true, reload after removing
 */
Widget.prototype.removeElement = function(
  elementId,
  {
    save = true,
    removeFromViewer = true,
    reloadLayerManager = true,
    reload = true,
  } = {},
) {
  const elementGroupId = this.elements[elementId].groupId;

  // Remove element from DOM
  (removeFromViewer) && $(`#${elementId}`).remove();

  // Remove element from object
  delete this.elements[elementId];

  // Remove element from a group
  let savedAlready = false;
  if (
    elementGroupId &&
    this.elementGroups[elementGroupId]
  ) {
    delete this.elementGroups[elementGroupId].elements[elementId];

    // If group is empty, remove it
    if (
      Object.values(this.elementGroups[elementGroupId].elements).length == 0
    ) {
      // Remove group from viewer
      $(`#${elementGroupId}`).remove();

      delete this.elementGroups[elementGroupId];
    } else {
      // Recalculate group dimensions and save
      if (save) {
        lD.viewer.saveElementGroupProperties(
          lD.viewer.DOMObject.find('#' + elementGroupId),
          true,
        );

        savedAlready = true;
      }
    }
  }

  // Recalculate required elements
  this.validateRequiredElements();

  // Validate other widget elements on the viewer
  Object.values(this.elements).forEach((el) => {
    lD.viewer.validateElement(el);
  });

  // Only save if we're not removing the widget
  // Save changes to widget
  (save && !savedAlready) && this.saveElements({
    updateEditor: reload,
  });

  // If object is selected, remove it from selection
  if (lD.selectedObject.elementId == elementId) {
    lD.selectObject({
      reloadViewer: false,
    });
    lD.viewer.selectObject(null, false, false);
  } else if (lD.selectedObject.type != 'layout' && save) {
    // If we have a selected object other than layout, reload properties panel
    lD.propertiesPanel.render(lD.selectedObject);
  }

  // Reload viewer to update widget valid status
  (reload) && lD.viewer.render();

  // If we're not removing widget, we need ot update element map
  (save) && this.updateElementMap();

  // Update layer manager
  (reloadLayerManager) &&
    lD.viewer.layerManager.render();
};

/**
 * Remove element group
 * @param {string} groupId - id of the group to remove
 * @param {boolean} save - save at the last element
 */
Widget.prototype.removeElementGroup = function(
  groupId,
  {
    save = true,
    reload = true,
  } = {},
) {
  const self = this;
  // Get element group
  const elementGroup = this.elementGroups[groupId];

  // Loop through elements in group to remove them
  // and only save on the last element
  Object.values(elementGroup.elements)
    .forEach((element) => {
      // Check if it's the last element
      const lastElement = (
        Object.keys(elementGroup.elements).length == 1
      );

      // Remove element from group
      delete elementGroup.elements[element.elementId];

      // Delete element from widget
      self.removeElement(
        element.elementId,
        {
          save: (lastElement && save),
          reload: reload,
        },
      );
    });

  // Delete element group from widget
  this.elementGroups[elementGroup.id] = null;
  delete this.elementGroups[elementGroup.id];

  // If object is selected, remove it from selection
  if (this.editorObject.selectedObject.id == groupId) {
    this.editorObject.selectObject({
      reloadViewer: false,
    });

    // Update viewer moveable
    (this.editorObject.viewer) &&
      this.editorObject.viewer.updateMoveable();
  }

  // Remove element from the DOM
  $(`#${groupId}`).remove();
};

/**
 * Get widget data
  * @return {Promise} - Promise with widget data
 */
Widget.prototype.getData = function() {
  const self = this;
  const linkToAPI = urlsForApi.module.getData;
  const requestPath =
    linkToAPI.url
      .replace(':id', this.widgetId)
      .replace(':regionId', this.regionId.split('region_')[1]);

  // If data request is already in progress, return cached promise
  if (self.cachedDataPromise) {
    return self.cachedDataPromise;
  }

  if (self.forceRecalculateData) {
    // Clear cached data
    self.cachedData = {};

    // Set force back to false (since we're going to get new data)
    self.forceRecalculateData = false;
  }

  // Recalculate required elements
  this.validateRequiredElements();

  // If widget already has data for that index, use cached data
  if (
    !$.isEmptyObject(self.cachedData)
  ) {
    // Clear cached promise
    self.cachedDataPromise = null;

    // Resolve the promise with the data
    return Promise.resolve(self.cachedData);
  }

  self.cachedDataPromise = new Promise(function(resolve, reject) {
    $.ajax({
      url: requestPath,
      type: linkToAPI.type,
      dataType: 'json',
    }).done((data) => {
      // If we don't have data, show sample data
      if (!data.data) {
        // Show sample data
        for (let i = 0; i < modulesList.length; i++) {
          if (modulesList[i].type === self.subType) {
            // Clear cached promise
            self.cachedDataPromise = null;

            const assetURL = urlsForApi.module.assetDownload.url;
            const assetRegex = /\[\[assetId=[\w&\-]+\]\]/gi;

            const sampleData = modulesList[i].sampleData || [];
            $.each(sampleData, function(index, item) {
              $.each(item, function(key, value) {
                value && value.match(assetRegex)?.forEach((match) => {
                  const assetId = match.split('[[assetId=')[1].split(']]')[0];
                  const assetUrl = assetURL.replace(':assetId', assetId);

                  // Replace asset id with asset url
                  item[key] = value.replace(match, assetUrl);
                });
              });
            });

            // Resolve the promise with the data
            self.cachedData = {
              data: sampleData,
              meta: data?.meta || {},
            };

            // Save error to widget
            self.validateData = {
              sampleDataMessage: layoutEditorTrans.showingSampleData,
            };

            // If we have an error, add it to the validate data
            if (data.success === false) {
              self.validateData.errorMessage = data.message;
            }

            resolve(self.cachedData);
          }
        }
      } else { // If we have data, show even if widget is invalid
        if (self.isValid) {
          // Valid, so reset messages
          self.validateData = {};
        } else {
          // Invalid widget
          self.validateData = {
            showError: true,
          };
        }

        // Run onDataLoad/onParseData
        Object.keys(modulesList).forEach(function(item) {
          if (modulesList[item].type === self.subType
          ) {
            const properties = {};
            const options = self.getOptions();
            $.each(modulesList[item].properties, function(i, property) {
              if (options[property.id] !== undefined) {
                const propertyValue = (property.type === 'number') ?
                  Number(options[property.id]) :
                  options[property.id];
                properties[property.id] = propertyValue;
              } else {
                properties[property.id] = property.default || null;
              }
            });

            if (modulesList[item].onDataLoad) {
              const onDataLoad = new Function(
                'return function(items, meta, properties) {' +
                modulesList[item].onDataLoad + '}',
              )();

              data.data = onDataLoad(data.data, data.meta, properties);

              // Check for dataItems on onDataLoad response
              if (data.data && data.data.hasOwnProperty('dataItems')) {
                data.data = data.data.dataItems;
              }
            }

            if (modulesList[item].onParseData) {
              const onParseData = new Function(
                'return function(item, properties) {' +
                modulesList[item].onParseData + '}',
              )();

              // Apply to each data item
              $.each(data.data, function(i, data) {
                data = onParseData(data, properties);
              });
            }
          }
        });

        // Return the item
        self.cachedData = {data: data.data, meta: data?.meta || {}};
      }

      // Clear cached promise
      self.cachedDataPromise = null;

      // Resolve the promise with the data
      resolve(self.cachedData);
    }).fail(function(jqXHR, textStatus, errorThrown) {
      console.error('getData', jqXHR, textStatus, errorThrown);
    });
  });

  // Return promise
  return self.cachedDataPromise;
};

/**
 * Update element map for this widget
 * @return {string} error message
 */
Widget.prototype.checkRequiredElements = function() {
  let errorMessage = '';
  const self = this;

  // Check if we need to show the required elements error message
  if (self.requiredElements && self.requiredElements.valid == false) {
    const dataType = lD.common.getModuleByType(self.subType).dataType;

    // Get element names for the missing elements
    const requiredMissingElements =
      self.requiredElements.missing.map((el) => {
        const elTitle = lD.templateManager.templates[dataType][el].title;
        return (elTitle != undefined) ? elTitle : el;
      });

    errorMessage =
      propertiesPanelTrans.requiredElementsMessage
        .replace('%elements%', requiredMissingElements.join(', '));
  }

  return errorMessage;
};

/**
 * Update element map for this widget
 * @param {object} [element]
 * @return {number} number of elements of the added type
 */
Widget.prototype.updateElementMap = function(element) {
  const self = this;
  const addElementToMap = function(el) {
    // element.elementType, element.id, element.elementId
    const elementType = el.elementType;
    const elementSubType = el.id;
    const elementSlot = el.slot;
    const elementId = el.elementId;
    const elementGroupId = el.groupId;

    // if we don't have the type level, create it
    if (!self.elementTypeMap[elementType]) {
      self.elementTypeMap[elementType] = {};
    }

    // if we don't have the sub type level, create it
    if (!self.elementTypeMap[elementType][elementSubType]) {
      self.elementTypeMap[elementType][elementSubType] = [];
    }

    const subTypeArray = self.elementTypeMap[elementType][elementSubType];

    // Check first available slot
    const numberOfSlots = subTypeArray.length;
    const firstSlot =
      subTypeArray.findIndex(function(el) {
        // If slot is empty, or we have an element of the same group
        return (
          el == [] ||
          el == undefined ||
          (
            elementGroupId != undefined &&
            el[0].elGroup == elementGroupId
          ));
      });

    let newSlot = 0;

    const elementObjectToAdd = {
      elId: elementId,
      elGroup: elementGroupId,
    };

    if (
      elementSlot != undefined &&
      elementSlot != null
    ) {
      // Slot set, add to position
      if (!subTypeArray[elementSlot]) {
        subTypeArray[elementSlot] = [];
        subTypeArray[elementSlot].push(elementObjectToAdd);
      } else {
        // Add to array if it's not added already
        const findElement = subTypeArray[elementSlot].findIndex(function(el) {
          return (el.elId == elementId);
        });

        if (findElement === -1) {
          subTypeArray[elementSlot].push(elementObjectToAdd);
        }
      }
      newSlot = elementSlot;
      subTypeArray[elementSlot];
    } else if (firstSlot == -1 && numberOfSlots == 0) {
      // If we have no slots, add to the first one
      newSlot = 0;
      subTypeArray[0] = [elementObjectToAdd];
    } else if (firstSlot == -1) {
      // If we have slots, but don't have an empty space, add to the next slot
      newSlot = subTypeArray.length;
      subTypeArray.push([elementObjectToAdd]);
    } else {
      // If we have slots and an empty slot ( or of the same group ), add to it
      newSlot = firstSlot;
      if (!subTypeArray[firstSlot]) {
        subTypeArray[firstSlot] = [];
      }

      subTypeArray[firstSlot].push(elementObjectToAdd);
    }

    // Return new element slot
    return newSlot;
  };

  // if element type is global (not data based)
  // do nothing
  if (element && element.elementType == 'global') {
    return;
  }

  // If we don't pass the element
  // just update the type map based on existing elements
  if (
    !element
  ) {
    // Reset map
    self.elementTypeMap = {};

    // Go through all elements and add the records to the map
    Object.values(this.elements).map((element) => {
      addElementToMap(element);
    });
  } else {
    const newElSlot = addElementToMap(element, true);

    // Return element new position
    return newElSlot;
  }
};

/**
 * Get data type structure from widget
 * @return {Promise} - Promise
 */
Widget.prototype.getDataType = function() {
  // Get request path
  const requestPath =
    urlsForApi.widget.getDataType.url.replace(':id', this.widgetId);

  return $.ajax({
    method: 'GET',
    url: requestPath,
    success: function(response) {
      return Promise.resolve(response);
    },
    error: function() {
      $select.parent().append(
        '{% trans "An unknown error has occurred. Please refresh" %}',
      );
    },
  });
};

/**
 * Get fallback data
 * @return {object} fallback data
 */
Widget.prototype.getFallbackData = function() {
  // Get request path
  const requestPath =
    urlsForApi.widget.data.get.url.replace(':id', this.widgetId);

  return $.ajax({
    method: 'GET',
    url: requestPath,
    success: function(response) {
      return Promise.resolve(response);
    },
    error: function() {
      toastr.error('An unknown error has occurred');
    },
  });
};

/**
 * Add fallback data
 * @param {object} data
 * @param {number} displayOrder
 * @return {Promise} - Promise
 */
Widget.prototype.addFallbackData = function(data, displayOrder) {
  // Get request path
  const linkToAPI = urlsForApi.widget.data.add;
  const requestPath =
    linkToAPI.url.replace(':id', this.widgetId);


  return $.ajax({
    method: linkToAPI.type,
    url: requestPath,
    data: {
      data,
      displayOrder,
    },
    success: function(response) {
      if (!response.success) {
        toastr.error(response.message);
      }

      return Promise.resolve(response);
    },
    error: function() {
      toastr.error('An unknown error has occurred');
    },
  });
};

/**
 * Edit fallback data record
 * @param {string} recordId
 * @param {object} data
 * @param {number} displayOrder
 * @return {Promise} - Promise
 */
Widget.prototype.editFallbackDataRecord = function(
  recordId,
  data,
  displayOrder,
) {
  // Get request path
  const linkToAPI = urlsForApi.widget.data.edit;
  const requestPath =
    linkToAPI.url.replace(':id', this.widgetId)
      .replace(':dataId', recordId);

  return $.ajax({
    method: linkToAPI.type,
    url: requestPath,
    data: {
      data,
      displayOrder,
    },
    success: function(response) {
      if (!response.success) {
        toastr.error(response.message);
      }

      return Promise.resolve(response);
    },
    error: function() {
      toastr.error('An unknown error has occurred');
    },
  });
};

/**
 * Delete fallback data record
 * @param {string} recordId
 * @return {Promise} - Promise
 */
Widget.prototype.deleteFallbackDataRecord = function(recordId) {
  // Get request path
  const linkToAPI = urlsForApi.widget.data.delete;
  const requestPath =
    linkToAPI.url.replace(':id', this.widgetId)
      .replace(':dataId', recordId);

  return $.ajax({
    method: linkToAPI.type,
    url: requestPath,
    success: function(response) {
      if (!response.success) {
        toastr.error(response.message);
      }

      return Promise.resolve(response);
    },
    error: function() {
      toastr.error('An unknown error has occurred');
    },
  });
};

/**
 * Save fallback data order
 * @param {object[]} records - Records array in order
 * @return {Promise} - Promise
 */
Widget.prototype.saveFallbackDataOrder = function(records) {
  // Get request path
  const linkToAPI = urlsForApi.widget.data.setOrder;
  const requestPath =
    linkToAPI.url.replace(':id', this.widgetId);

  return $.ajax({
    method: linkToAPI.type,
    url: requestPath,
    data: {
      order: records,
    },
    success: function(response) {
      if (!response.success) {
        toastr.error(response.message);
      }

      return Promise.resolve(response);
    },
    error: function() {
      toastr.error('An unknown error has occurred');
    },
  });
};

module.exports = Widget;
