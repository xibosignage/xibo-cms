/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

// CHANGE Module
/**
 * A change stores a operation state
*/

/**
 * Change object
 * @param  {number} id -Change id
 * @param  {string} type -Type of change ( tranform, properties,... )
 * @param  {string} targetType
 *  - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetSubType
 *  - Target object Sub Type ( canvas, playlist, ... )
 * @param  {string} targetID - Target object ID
 *  - Target object ( widget, region, layout, ...
 * @param {object} oldState - Previous change properties
 * @param {object} newState - Change properties, to be saved
 * @param {object} auxTarget - Target to use as comparison as well, (id, type)
*/
const Change = function(
  id, type, targetType, targetSubType, targetID, oldState, newState, auxTarget,
) {
  this.id = id;
  this.type = type;
  this.target = {
    id: targetID,
    type: targetType,
    subType: targetSubType,
  };
  this.oldState = oldState;
  this.newState = newState;
  this.timeStamp = Math.round((new Date()).getTime() / 1000);

  // Flag to check if the change was successfully uploaded
  this.uploaded = false;

  // Flag to check if the change was already marked for upload
  this.uploading = false;

  // Skip upload
  this.skipUpload = false;

  // Aux target - to be used to delete change
  // using more than just the Change.target
  this.auxTarget = auxTarget;
};

module.exports = Change;
