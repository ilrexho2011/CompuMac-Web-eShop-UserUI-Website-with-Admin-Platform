const { src, dest } = require('gulp');
const gulp = require('gulp');
const concat = require('gulp-concat');
const terser = require('gulp-terser');
const sourcemaps = require('gulp-sourcemaps');
const postcss = require('gulp-postcss');
const cssnano = require('cssnano');
const autoprefixer = require('autoprefixer');
const browserSync = require("browser-sync").create();

const minify = require('gulp-clean-css');
const minifyJs = require('gulp-uglify');

const cssPath = 'assets/front_end/modern/css/*.css';

function cssBundle() {
    return src([
        'assets/front_end/modern/css/Lexend-Deca-fonts.css',
        'assets/front_end/modern/css/Open-Sans-fonts.css',
        'assets/front_end/modern/bootstrap-5.3.0-dist/css/bootstrap.min.css',
        'assets/front_end/modern/fontawesome-free-6.4.0-web/css/all.min.css',
        'assets/front_end/modern/css/intlTelInput.css',
        // 'assets/front_end/modern/css/iziModal.min.css',
        // 'assets/front_end/modern/css/all.min.css',
        // 'assets/front_end/modern/css/swiper-bundle.min.css',
        // 'assets/front_end/modern/css/bootstrap-tabs-x.min.css',
        'assets/front_end/modern/css/swiper-js.css',
        'assets/front_end/modern/css/sweetalert2.min.css',
        'assets/front_end/modern/css/select2.css',
        'assets/front_end/modern/css/select2.min.css',
        'assets/front_end/modern/css/jssocials.css',
        'assets/front_end/modern/css/components.css',
        'assets/front_end/modern/css/dropzone.css',
        'assets/front_end/modern/css/star-rating.css',
        'assets/front_end/modern/css/star-rating.min.css',
        'assets/front_end/modern/css/theme.min.css',
        'assets/front_end/modern/css/theme.css',
        'assets/front_end/modern/xZoom-master/dist/xzoom.css',
        'assets/front_end/modern/css/daterangepicker.css',
        'assets/front_end/modern/css/bootstrap-table.min.css',
        'assets/front_end/modern/css/lightbox.css',
    ])
        .pipe(sourcemaps.init())
        .pipe(concat('eshop-bundle.css'))
        .pipe(postcss([autoprefixer(), cssnano()])) //not all plugins work with postcss only the ones mentioned in their documentation
        .pipe(sourcemaps.write('.'))
        .pipe(dest('assets/front_end/modern/css'));
}
exports.cssBundle = cssBundle;

function cssBundleMain() {
    return src([
        'assets/front_end/modern/bootstrap-5.3.0-dist/css/bootstrap.min.css',
        'assets/front_end/modern/css/custom.css',
        // 'assets/front_end/modern/css/products.css',
    ])
        .pipe(sourcemaps.init())
        .pipe(concat('eshop-bundle-main.css'))
        .pipe(postcss([autoprefixer(), cssnano()])) //not all plugins work with postcss only the ones mentioned in their documentation
        .pipe(sourcemaps.write('.'))
        .pipe(dest('assets/front_end/modern/css'));
}
exports.cssBundleMain = cssBundleMain;

// function cssBundleMainRTL() {
//     return src([
//         'assets/front_end/modern/css/rtl/bootstrap.min.css',
//         'assets/front_end/modern/css/rtl/style.css',
//         'assets/front_end/modern/css/rtl/products.css',
//     ])
//         .pipe(sourcemaps.init())
//         .pipe(concat('eshop-bundle-main.css'))
//         .pipe(postcss([autoprefixer(), cssnano()])) //not all plugins work with postcss only the ones mentioned in their documentation
//         .pipe(sourcemaps.write('.'))
//         .pipe(dest('assets/front_end/modern/css/rtl'));
// }
// exports.cssBundleMainRTL = cssBundleMainRTL;


// minifying js
const jsBundle = () =>
    src([
        'assets/front_end/modern/js/jquery.min.js',
        'assets/front_end/modern/bootstrap-5.3.0-dist/js/bootstrap.bundle.min.js',
        'assets/front_end/modern/fontawesome-free-6.4.0-web/js/all.min.js',
        'assets/front_end/modern/js/jssocials.min.js',
        'assets/front_end/modern/js/select2.full.min.js',
        'assets/front_end/modern/js/sweetalert2.all.min.js',
        'assets/admin_old/js/tagify.min.js',
        'assets/front_end/modern/js/swiper-js-bundle.js',
        'assets/front_end/modern/js/lazyload.min.js',
        // 'assets/front_end/modern/js/star-rating.js',
        // 'assets/front_end/modern/js/star-rating.min.js',
        'assets/front_end/modern/js/bootstrap-table.min.js',
        'assets/front_end/modern/js/bootstrap-table.min.js',
        'assets/front_end/modern/js/elevatezoom.min.js',
        'assets/front_end/modern/js/deeplink.js',
        'assets/front_end/modern/js/lightbox.js',
        'assets/front_end/modern/js/dropzone.js',
        'assets/front_end/modern/js/jquery.validate.min.js',
        'assets/front_end/modern/js/moment.min.js',
        'assets/front_end/modern/js/daterangepicker.js',
    ])
        .pipe(concat('eshop-bundle-js.js'))
        .pipe(minifyJs())
        .pipe(dest('assets/front_end/modern/js/'))
        .pipe(browserSync.stream());

exports.jsBundle = jsBundle;

const topJsBundle = () =>
    src([
        // 'assets/front_end/modern/js/moment.min.js',
        // 'assets/front_end/modern/js/daterangepicker.js',
        'assets/front_end/modern/js/custom.js',
        // 'assets/front_end/modern/js/theme.min.js',
    ])
        .pipe(concat('eshop-bundle-top-js.js'))
        .pipe(minifyJs())
        .pipe(dest('assets/front_end/modern/js/'));

exports.topJsBundle = topJsBundle;
