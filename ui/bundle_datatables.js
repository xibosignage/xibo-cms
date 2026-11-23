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

// --- NPM packages style ---
// import './public_path';

// JS
const DT_EXTRAS = [
  require('datatables.net'),
  require('datatables.net-bs4'),
  require('datatables.net-buttons'),
  require('datatables.net-buttons/js/buttons.colVis.min.js'),
  require('datatables.net-buttons/js/buttons.html5.min.js'),
  require('datatables.net-buttons/js/buttons.print.min.js'),
  require('datatables.net-buttons-bs4'),
  require('datatables.net-responsive'),
];
DT_EXTRAS.forEach(function(e) {
  if (typeof e === 'function') {
    e(window, window.$);
  }
});

// Style
require('datatables.net-bs4/css/dataTables.bootstrap4.min.css');
require('datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css');
require('datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css');

