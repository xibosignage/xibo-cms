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

const ArrayHelper = function() {
  return {
    /**
     * Function to move item of an array by index
     * @param arr {Array} - Input array
     * @param from {number} - Original array index
     * @param to {number} - New array index
     * @return {Array}
     */
    move: function(arr, from, to) {
      if (arr === undefined) {
        console.warn('Please provide a valid array parameter');
        return [];
      }

      if (arr.length === 0) {
        return arr;
      }
      // Check if indexes: from and to are within the array
      if (from >= arr.length || to >= arr.length) {
        return arr;
      }

      // Store arr[from]
      const temp = arr[from];

      arr.splice(from, 1);
      arr.splice(to, 0, temp);

      return arr;
    },
  };
};

module.exports = new ArrayHelper([]);
