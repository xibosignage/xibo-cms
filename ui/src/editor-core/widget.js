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

  this.widgetName = data.name;

  this.layoutObject = layoutObject;

  this.isValid = data.isValid;

  // widget type
  this.type = 'widget';
  this.subType = data.type;
  this.moduleName = data.moduleName;

  // Permissions
  this.isEditable = data.isEditable;
  this.isDeletable = data.isDeletable;
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
    this.expireStatusTitle = '<p>' + EXPIRE_STATUS_MSG_MAP[status] + '</p>';

    if (this.fromDt > this.DATE_MIN) {
      this.expireStatusTitle +=
      '<p>' +
      widgetStatusTrans.startTime +
      ': ' +
      moment.unix(this.fromDt).format(jsDateFormat) +
      '</p>';
    }

    if (this.toDt < this.DATE_MAX) {
      this.expireStatusTitle +=
      '<p>' +
      widgetStatusTrans.endTime +
      ': ' +
      moment.unix(this.toDt).format(jsDateFormat) +
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
     * Check if the current module is enabled
     */
  this.checkIfEnabled = function() {
    // Check if module is enabled
    const module = this.designerObject.common.getModuleByType(this.subType);
    this.enabled = !$.isEmptyObject(module);

    // Override properties if not enabled
    if (!this.enabled) {
      this.isEditable = false;
      this.isPermissionsModifiable = false;
      this.isDeletable = data.isDeletable;
    }
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

  const app = this.designerObject;

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

          app.manager.addChange(
            'save' + property,
            'widget', // targetType
            self.widgetId, // targetId
            null, // oldValues
            dataToSave, // newValues
            options,
          ).then((res) => { // Success
            app.common.hideLoadingScreen();

            // Behavior if successful
            toastr.success(res.message);

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
  const app = this.designerObject;

  // Get region widgets
  const region = app.getElementByTypeAndId('region', this.regionId);
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

module.exports = Widget;
