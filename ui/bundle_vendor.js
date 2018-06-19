//--- Add NPM Packages - JS ----

// jquery-ui
window.jQuery = window.$ = require('jquery');

// bootstrap
require('bootstrap');

// babel-polyfill
require('babel-polyfill');

// bootbox
window.bootbox = require('bootbox');

// jquery-ui draggable, resizable & sortable
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

// moment-timezone
require('moment-timezone/moment-timezone.js');

// moment-jalaali
try {
    // Conditional import for the locale variable
    if( calendar_type && calendar_type == "Jalali") {
        window.moment = require('moment-jalaali');
    }
} catch (e) { //Handle variable not set error
    console.log('[Warning] loading moment-jalaali: Calendar Type not defined!');
    console.log(e);
}

// bootstrap-select
require('bootstrap-select');
try {
    // Conditional import for the locale variable
    if( jsLocale && jsLocale != "en-GB" ) {
        require('bootstrap-select/js/i18n/defaults-' + boostrapSelectLanguage + '.js');
    }
} catch (e) { //Handle variable not set error
    console.log('[Warning] loading bootstrap-select: Locale not defined!');
}

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

// timepicker
require('timepicker');

// form-serializer
require('form-serializer');

// datatables.net
var dt_extras = [
         require("datatables.net"),
         require("datatables.net-bs"),
         require("datatables.net-buttons"),
         require("datatables.net-buttons/js/buttons.colVis.min.js"),
         require("datatables.net-buttons/js/buttons.html5.min.js"),
         require("datatables.net-buttons/js/buttons.print.min.js")
     ];
dt_extras.forEach(function(e) {e(window, window.$);});

// throttle-debounce
window.$.debounce = require('throttle-debounce/debounce.js');

// bootstrap-tour
require('bootstrap-tour/build/js/bootstrap-tour.min.js');

//--- Add Local JS files ---
// jquery-message-queuing
require('./src/vendor/jquery-message-queuing/jquery.ba-jqmq.min.js');
