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

// --- Add NPM Packages - JS ----
import './public_path';

// leaflet
require('leaflet');
require('leaflet-draw');
require('leaflet-search');

window.L = require('leaflet');
window.leafletPip = require('@mapbox/leaflet-pip');

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: '/dist/assets/marker-icon-2x.png',
  iconUrl: '/dist//assets/marker-icon.png',
  shadowUrl: '/dist/assets/marker-shadow.png',
});

require('leaflet.markercluster');
require('leaflet-easyprint');
require('leaflet-fullscreen');

// Style
require('leaflet/dist/leaflet.css');
require('leaflet-draw/dist/leaflet.draw-src.css');
require('leaflet-search/dist/leaflet-search.src.css');
require('leaflet.markercluster/dist/MarkerCluster.css');
require('leaflet.markercluster/dist/MarkerCluster.Default.css');
require('leaflet-fullscreen/dist/leaflet.fullscreen.css');
