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

// jquery-ui
window.jQuery = window.$ = require('jquery');

// bootstrap
require('bootstrap');

// babel-polyfill
require('babel-polyfill');

// bootbox
window.bootbox = require('bootbox');

// jqueryui resizable, droppable, draggable & sortable
require('jquery-ui/ui/widgets/resizable');
require('jquery-ui/ui/widgets/draggable');
require('jquery-ui/ui/widgets/droppable');
require('jquery-ui/ui/widgets/sortable');

// jquery-validation
require('jquery-validation');

// bootstrap-colorpicker
require('bootstrap-colorpicker');

// momentjs
window.moment = require('moment');
require('moment/min/locales');

// moment-timezone
require('moment-timezone');

try {
  // Conditional import for the locale variable
  if (CALENDAR_TYPE && CALENDAR_TYPE == 'Jalali') {
    // moment-jalaali
    window.moment = require('moment-jalaali');

    // Persian date time picker
    window.persianDate = require('persian-date/dist/persian-date.min.js');
    require('persian-datepicker/dist/js/persian-datepicker.min.js');
  } else {
    // Time/Date picker
    require('flatpickr');
    window.flatpickrMonthSelectPlugin =
      require('flatpickr/dist/plugins/monthSelect/index.js');

    try {
      // Conditional import for the locale variable
      if (jsShortLocale && jsShortLocale != 'en-GB') {
        require('flatpickr/dist/l10n/' + jsShortLocale + '.js');
      }
    } catch (e) { // Handle variable not set error
      console.warn(e);
      console.warn('[Warning] loading flatpickr: Locale not defined!');
    }
  }
} catch (e) { // Handle variable not set error
  console.warn(e);
  console.warn('[Warning] loading moment-jalaali: Calendar Type not defined!');
}

// select2
require('select2');

try {
  // Conditional import for the locale variable
  if (jsShortLocale && jsShortLocale != 'en-GB' ) {
    require('select2/dist/js/i18n/' + jsLocale + '.js');
  }
} catch (e) { // Handle variable not set error
  console.warn(e);
  console.warn('[Warning] loading select2: Locale not defined!');
}

// Default theme for select2
$.fn.select2.defaults.set('theme', 'bootstrap');

// ekko-lightbox
require('ekko-lightbox');

// underscore
window._ = require('underscore/underscore-min.js');

// toastr
window.toastr = require('toastr');

// bootstrap-switch
require('bootstrap-switch');

// bootstrap-slider
require('bootstrap-slider');

// bootstrap-tagsinput
require('bootstrap-tagsinput');

// handlebars
window.Handlebars = require('handlebars/dist/handlebars.min.js');

// colors.js
require('colors.js');

// chart.js
require('chart.js');
window.ChartDataLabels = require('chartjs-plugin-datalabels');

// form-serializer
require('form-serializer');

// --- Add Local JS files ---
// jquery-message-queuing
require('./src/vendor/jquery-message-queuing/jquery.ba-jqmq.min.js');

// typeahead
window.Bloodhound = require('corejs-typeahead/dist/bloodhound.min.js');
require('corejs-typeahead/dist/typeahead.jquery.min.js');

// jsTree
require('jstree/dist/jstree.min.js');
require('jstree/dist/themes/default/style.min.css');

