// Include gulp
var version = '1.8.2';
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
       '!vendor/twig/twig/lib/Twig',
       '!vendor/twig/twig/lib/Twig/**',
       'vendor/**/test*/**',
       'vendor/**/benchmarks/**',
       'vendor/**/smoketests/**',
       'vendor/**/demo*/**',
       'vendor/**/doc*/**',
       'vendor/**/examples/**',
       'vendor/**/phpunit.xml',
       'vendor/**/*.md'
    ]);
});

gulp.task('build-php-archive', function() {
    return gulp.src([
            '**/*',
            '*/.htaccess',
            '!composer.*',
            '!docker-compose.yml',
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
