/**
 * The goal is to have a gulp task for:
 *  - building a complete production release archive
 *  - building a development environment
 *
 * Production Environment
 * ----------------------
 * PHP:
 * Run composer install --ignore-platform-reqs
 * Tidy up the vendor/ folder such that we remove tests/ docs/ etc
 *
 * Front-End:
 * Copy, minify and combine bower_components into theme/default/vendor.js & theme/default/vendor.css
 * Copy, minify and combine Xibo specific front-end theme files into theme/default/app.js&css
 * Copy module vendor files into modules/vendor/
 *
 * Twig:
 * Copy base_production.twig file to base.twig
 *
 * --------------------------------------------------------------------------------------------------------------------
 *
 * Development Environment
 * -----------------------
 * PHP:
 * Run composer install --no-dev --ignore-platform-reqs
 *
 * Front-End:
 * Copy bower_components into theme/default & modules/vendor
 *
 * Twig:
 * Copy base_dev.twig file to base.twig
 *
 * Setup a watcher for changes to bower.js / Xibo specific files to run the dev task
 */

// Include gulp
var gulp = require("gulp");
var composer = require("gulp-composer");

gulp.task('default-php', function() {
    return composer("install", {
        "ignore-platform-reqs": true
    });
});

gulp.task('build-php', function() {
    return composer("install", {
        "ignore-platform-reqs": true,
        "no-dev": true,
        "optimize-autoloader": true
    });
});

gulp.task('watch', function() {
    gulp.watch('composer.json', ['build-php'])
});

gulp.task('default', ['default-php']);
gulp.task('build', ['build-php']);

// Something like this for front-end: https://gist.github.com/ktmud/9384509