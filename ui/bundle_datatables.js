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

