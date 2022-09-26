/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

/* eslint-disable no-unused-vars */
window.jQuery = window.$ = require('jquery');
require('babel-polyfill');
window.moment = require('moment');
require('moment-timezone');
window.Handlebars = require('handlebars/dist/handlebars.min.js');

// Module specific
// most likely this can be done more efficiently.
require('chart.js');
window.Hls = require('hls.js');
const pdflib = require('pdfjs-dist/legacy/build/pdf.min.js');

import '../vendor/xibo-interactive-control.min.js';
import './xibo-calendar-render';
import './xibo-countdown-render';
import './xibo-finance-render';
import './xibo-image-render';
import './xibo-layout-scaler';
import './xibo-menuboard-render';
import './xibo-metro-render';
import './xibo-substitutes-parser';
import './xibo-text-render';
import './xibo-webpage-render';
import './xibo-worldclock-render';
import './editor-render';
import './player';

// Plugins
require('../vendor/flipclock.min.js');
require('../vendor/flipclock.css');
require('../vendor/jquery-cycle-2.1.6.min.js');
require('../vendor/jquery.marquee.min.js');

