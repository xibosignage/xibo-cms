/**
 * The goal is to have a gulp task for:
 *  - building a complete production release archive
 *  - building a development environment
 *
 * Production Environment
 * ----------------------
 * Run composer install --ignore-platform-reqs
 * Tidy up the vendor/ folder such that we remove tests/ docs/ etc
 * Copy, minify and combine bower_components into theme/default/vendor.js & theme/default/vendor.css
 * Copy, minify and combine Xibo specific front-end theme files into theme/default/app.js&css
 * Copy module vendor files into modules/vendor/
 * Copy base_production.twig file to base.twig
 *
 * --------------------------------------------------------------------------------------------------------------------
 *
 * Development Environment
 * -----------------------
 * Run composer install --no-dev --ignore-platform-reqs
 * Copy bower_components into theme/default
 * Copy base_dev.twig file to base.twig
 *
 * Setup a watcher for changes to bower.js / Xibo specific files to run the dev task
 */

// Include gulp
var gulp = require("gulp");
