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
