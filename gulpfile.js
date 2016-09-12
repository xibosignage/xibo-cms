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
var version = '1.8.0-beta';
var gulp = require("gulp");
var del = require("del");
var composer = require("gulp-composer");
var tar = require('gulp-tar');
var gzip = require('gulp-gzip');
var rename = require('gulp-rename');
var exec = require('child_process').exec;

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

gulp.task('build-php-vendor-clean', ['build-php'], function() {
    return del([
       'vendor/**/.git/**',
       'vendor/**/Test*/**',
       'vendor/**/test*/**',
       'vendor/**/benchmarks/**',
       'vendor/**/smoketests/**',
       'vendor/**/demo*/**',
       'vendor/**/doc*/**',
       'vendor/**/examples/**',
       'vendor/**/phpunit.xml',
       'vendor/**/*.md',
    ]);
});

gulp.task('build-php-archive', function() {
    return gulp.src([
            '**/*',
            '*/.htaccess',
            '!composer.*',
            '!*.json*',
            '!Vagrantfile',
            '!phpunit.xml',
            '!gulpfile.js',
            '!CONTRIBUTING.md',
            '!*.tar.gz',
            '!tests{,/**}',
            '!node_modules{,/**}',
            '!cache/**',
            '!custom/**/!(README.md)',
            '!library/**',
            '!web/settings.php',
            '!web/theme/custom/**/!(README.md)',
            '!web/swagger-ui{,/**}'
        ])
        .pipe(rename(function (path) {
                path.dirname = 'xibo-cms-' + version + '/' + path.dirname;
            })
        )
        .pipe(tar('xibo-cms-' + version + '.tar'))
        .pipe(gzip())
        .pipe(gulp.dest('./'))
});

gulp.task('swagger', function () {
     exec('./vendor/bin/swagger lib -o web/swagger.json', function (err, stdout, stderr) {
         console.log(stdout);
         console.log(stderr);
     });
});

gulp.task('watch', function() {
    gulp.watch('composer.json', ['default'])
});

gulp.task('default', ['default-php']);
gulp.task('build', ['build-php', 'build-php-vendor-clean']);

// Something like this for front-end: https://gist.github.com/ktmud/9384509