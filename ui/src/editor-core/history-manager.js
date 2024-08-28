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
/**
 * History manager, that stores all the changes and
 * operations that can be applied to them (upload/revert)
 */

const Change = require('../editor-core/change.js');
const managerTemplate = require('../templates/history-manager.hbs');

// Map from a operation to its inverse, and
// detail if the operation is done on the object or the layout
const inverseChangeMap = {
  transform: {
    inverse: 'transform',
    parseData: true,
  },
  create: {
    inverse: 'delete',
  },
  saveForm: {
    inverse: 'saveForm',
  },
  addMedia: {
    inverse: 'delete',
  },
  addWidget: {
    inverse: 'delete',
  },
  order: {
    inverse: 'order',
  },
  saveElements: {
    inverse: 'saveElements',
  },
};

/**
 * History Manager
 * @param {object} parent - Parent object
 * @param {object} container - Container to append the manager to
 * @param {bool} visible - Show the manager
 */
const HistoryManager = function(parent, container, visible) {
  this.parent = parent;

  this.DOMObject = container;

  this.extended = true;

  this.visible = visible;

  this.changeUniqueId = 0;

  this.changeHistory = []; // Array of changes

  this.toggleExtended = function() {
    this.extended = !this.extended;

    // Render template container
    this.render();
  };

  // Return true if there are some not uploaded changes
  this.changesToUpload = function() {
    for (let index = this.changeHistory.length - 1; index >= 0; index--) {
      if (!this.changeHistory[index].uploaded) {
        return true;
      }
    }

    return false;
  };
};

/**
 * Save a editor change to the history array
 * @param {string} changeType -Type of change ( resize, move )
 * @param {string} targetType - Target object Type
 * ( widget, region, layout, ... )
 * @param {string} targetId - Target object ID
 * @param {object=} [oldValues] - Previous object change
 * @param {object=} [newValues] - New object change
 * @param {object =} [options] - Manager options
 * @param {bool=} [options.upload = true] - Upload change in real time
 * @param {bool=} [options.addToHistory = true]
 *  - Add change to the history array
 * @param {bool=} [options.updateTargetId = false]
 *  - Update change target id with the one returned from the API on upload
 * @param {string=} [options.updateTargetType = null]
 *  - Update change target type after upload
 * with the value passed on this variable
 * @param {object=} [options.customRequestPath = null]
*   - Custom Request Path ( url and type )
 * @param {object=} [options.customRequestReplace = null]
 *  - Custom Request replace ( tag and replace )
 * @return {Promise} - Promise that resolves when the change is uploaded
*/
HistoryManager.prototype.addChange = function(
  changeType, targetType, targetId, oldValues, newValues,
  {
    upload = true,
    addToHistory = true,
    updateTargetId = false,
    updateTargetType = null,
    customRequestPath = null,
    customRequestReplace = null,
    targetSubType = null,
    skipUpload = false,
    auxTarget = {},
  } = {},
) {
  const changeId = this.changeUniqueId++;

  // create new change and add it to the array
  const newChange = new Change(
    changeId,
    changeType,
    targetType,
    targetSubType,
    targetId,
    oldValues,
    newValues,
    auxTarget,
  );

  // If we want to skip upload and only use it for revert locally
  newChange.skipUpload = skipUpload;

  // If we skip upload, mark it as uploaded
  if (skipUpload) {
    newChange.uploaded = true;
  }

  // Add change to the history array
  if (addToHistory) {
    this.changeHistory.push(newChange);

    // Render template container
    this.render();
  }

  // Upload change
  if (upload) {
    return this.uploadChange(
      newChange,
      updateTargetId,
      updateTargetType,
      customRequestPath,
      customRequestReplace,
    );
  } else {
    return Promise.resolve('Change added!');
  }
};

/**
 * Upload first change in the history array
 * @param {object} change
 * @param {bool=} updateId
 * @param {string=} updateType
 * @param {object=} customRequestPath
 * @param {object=} customRequestReplace
 * @return {Promise} - Promise that resolves when the change is uploaded
 */
HistoryManager.prototype.uploadChange = function(
  change,
  updateId,
  updateType,
  customRequestPath,
  customRequestReplace,
) {
  const self = this;
  const app = this.parent;

  // Test for empty history array
  if (!change || change.uploaded) {
    return Promise.reject('Change already uploaded!');
  }

  change.uploading = true;

  const linkToAPI =
    (customRequestPath != null) ?
      customRequestPath :
      urlsForApi[change.target.type][change.type];

  let requestPath = linkToAPI.url;

  // Custom replace tag
  if (customRequestReplace) {
    requestPath =
      requestPath.replace(
        customRequestReplace.tag,
        customRequestReplace.replace,
      );
  }

  // replace id if necessary/exists
  if (change.target) {
    let replaceId = change.target.id;
    const replaceType = change.target.type;

    // If the replaceId is not set or the change needs the main object Id
    if (replaceId == null || linkToAPI.useMainObjectId) {
      replaceId = app.mainObjectId;
    }

    requestPath = requestPath.replace(':id', replaceId);
    requestPath = requestPath.replace(':type', replaceType);
  }

  // Run ajax request and save promise
  return new Promise(function(resolve, reject) {
    $.ajax({
      url: requestPath,
      type: linkToAPI.type,
      data: change.newState,
    }).done(function(data) {
      if (data.success) {
        change.uploaded = true;
        change.uploading = false;

        // Update the Id of the change with the new object
        if (updateId) {
          if (change.type === 'create' || change.type === 'addWidget') {
            change.target.id = data.id;
          } else if (change.type === 'addMedia') {
            change.target.id = [];

            for (let index = 0; index < data.data.newWidgets.length; index++) {
              change.target.id.push(data.data.newWidgets[index].widgetId);
            }
          }
        }

        // If set: Update the type of change to enable the revert
        if (updateType != null) {
          change.target.type = updateType;
        }

        // Resolve promise
        resolve(data);
      } else {
        // Login Form needed?
        if (data.login) {
          window.location.reload();
        } else {
          // Just an error we dont know about
          if (data.message == undefined) {
            reject(data);
          } else {
            reject(data.message);
          }
        }
      }

      // Render/Update Manager
      self.render();
    }).fail(function(jqXHR, textStatus, errorThrown) {
      // Output error to console
      console.error(jqXHR, textStatus, errorThrown);

      // Reject promise and return an object with all values
      reject({jqXHR, textStatus, errorThrown});
    });
  });
};

/**
 * Revert change by ID or the last one in the history array
 * @return {Promise} - Promise that resolves when the change is reverted
*/
HistoryManager.prototype.revertChange = function() {
  // Prevent trying to revert if there are no changes in history
  if (this.changeHistory.length <= 0) {
    return Promise.reject('There are no changes in history!');
  }

  const self = this;

  const app = this.parent;

  // Get the last change in the array
  const lastChange = this.changeHistory[this.changeHistory.length - 1];

  const inverseOperation = inverseChangeMap[lastChange.type].inverse;
  const parseData = inverseChangeMap[lastChange.type].parseData;

  return new Promise(function(resolve, reject) {
    // Revert element save
    if (lastChange.type === 'saveElements') {
      const widget =
        lD.getObjectByTypeAndId('widget', lastChange.target.id, 'canvas');

      try {
        // Get elements from previous state
        const elementsToSave = (lastChange.oldState === '') ?
          [] :
          JSON.parse(lastChange.oldState)[0].elements;

        // Save elements to widget ( without saving to history )
        widget.saveElements({
          elements: elementsToSave,
          addToHistory: false,
          updateEditor: true,
        }).then(function() {
          // Remove change from history
          self.removeLastChange();

          resolve({
            localRevert: true,
          });
        });
      } catch (e) {
        console.error('parseElementFromWidget', e);
        return;
      }
    } else if (!lastChange.uploaded) {
      // Revert on the client side ( non uploaded change )
      // Get data to apply
      let data = lastChange.oldState;

      // Get object by type,from the main object
      const object = app.getObjectByTypeAndId(
        lastChange.target.type, // Type
        lastChange.target.type + '_' + lastChange.target.id, // Id
      );

      // If the operation needs data parsing
      if (parseData != undefined && parseData) {
        data = JSON.parse(data.regions)[0];
      }

      // Apply inverse operation to the object
      object[inverseOperation](data, false);

      // Remove change from history
      self.removeLastChange();

      resolve({
        type: inverseOperation,
        target: lastChange.target.type,
        message: 'Change reverted',
        localRevert: true,
      });
    } else { // Revert using the API
      const linkToAPI = urlsForApi[lastChange.target.type][inverseOperation];

      const revertObject = function() {
        let requestPath = linkToAPI.url;

        // replace id if necessary/exists
        if (lastChange.target) {
          let replaceId = '';

          if (Array.isArray(lastChange.target.id)) {
            replaceId = lastChange.target.id[0];
            lastChange.target.id.shift();
          } else {
            replaceId = lastChange.target.id;
          }

          // If the replaceId is not set or the change needs
          // the layoutId, set it to the replace var
          if (replaceId == null || linkToAPI.useMainObjectId) {
            replaceId = app.mainObjectId;
          }

          requestPath = requestPath.replace(':id', replaceId);
        }

        $.ajax({
          url: requestPath,
          type: linkToAPI.type,
          data: lastChange.oldState,
        }).done(function(data) {
          if (data.success) {
            // If the target is a int or if it's an array
            // with no elements, resolve method
            if (
              (
                Array.isArray(lastChange.target.id) &&
                lastChange.target.id.length == 0
              ) ||
              !isNaN(lastChange.target.id)
            ) {
              // Remove change from history
              self.removeLastChange();

              // If the operation is a deletion, unselect object before deleting
              if (inverseOperation === 'delete') {
                // Unselect selected object before deleting
                app.selectObject();
              }

              // Resolve promise
              resolve(data);
            } else {
              // Revert next change
              revertObject();
            }
          } else {
            // Login Form needed?
            if (data.login) {
              window.location.reload();
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
          // Output error to console
          console.error(jqXHR, textStatus, errorThrown);

          // Reject promise and return an object with all values
          reject({jqXHR, textStatus, errorThrown});
        });
      };

      revertObject();
    }
  });
};

/**
 * Save all the changes in the history array
*/
HistoryManager.prototype.saveAllChanges = async function() {
  const self = this;

  // stop method if there are no changes to be saved
  if (!this.changesToUpload()) {
    return Promise.resolve('No changes to upload');
  }

  const promiseArray = [];

  for (let index = 0; index < self.changeHistory.length; index++) {
    const change = self.changeHistory[index];

    // skip already uploaded changes or skipped
    if (change.uploaded || change.uploading || change.skipUpload) {
      continue;
    }

    change.uploading = true;

    promiseArray.push(await self.uploadChange(change));

    // Render manager container to update the change
    self.render();
  }

  return Promise.all(promiseArray);
};

/**
 * Remove all the changes in the history array related to a specific object
 * @param  {string} targetType - Target object Type
 *  ( widget, region, layout, ... )
 * @param  {string} targetId - Target object ID
 * @return {Promise} - Promise that resolves when the changes are removed
*/
HistoryManager.prototype.removeAllChanges = function(targetType, targetId) {
  const self = this;

  return new Promise(function(resolve, reject) {
    for (let index = 0; index < self.changeHistory.length; index++) {
      const change = self.changeHistory[index];

      const isTarget = (
        change.target.type === targetType &&
        (
          change.target.id === targetId ||
          (
            Array.isArray(change.target.id) &&
            change.target.id.includes(targetId)
          )
        )
      );

      const isAuxTarget = (
        change.auxTarget &&
        change.auxTarget.type === targetType &&
        (
          change.auxTarget.id === targetId ||
          (
            Array.isArray(change.auxTarget.id) &&
            change.auxTarget.id.includes(targetId)
          )
        )
      );

      if (isTarget || isAuxTarget) {
        self.changeHistory.splice(index, 1);

        // When change is removed, we need to decrement the index
        index--;
      }
    }

    // Render template container
    self.render();

    resolve('All Changes Removed');
  });
};

/**
 * Remove last change
*/
HistoryManager.prototype.removeLastChange = function() {
  this.changeHistory.pop();
  this.render();
};

/**
 * Render Manager
 * @param {boolean} reloadToolbar - force render toolbar?
 */
HistoryManager.prototype.render = function(
  reloadToolbar = true,
) {
  // Upload bottom bar if exists
  if (this.parent.bottombar && reloadToolbar) {
    this.parent.bottombar.render();
  }

  if (this.visible == false) {
    return;
  }
  // Compile layout template with data
  const html = managerTemplate(this);

  // Append layout html to the main div
  this.DOMObject.html(html);

  // Make the history div draggable
  this.DOMObject.draggable();

  // Add toggle visibility event
  this.DOMObject.find('#layout-manager-header .title')
    .click(this.toggleExtended.bind(this));
};

module.exports = HistoryManager;
