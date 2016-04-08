var gulp = require('gulp');
var uglify = require('gulp-uglify');
var rename = require('gulp-rename');
var sass = require('gulp-sass');
var nano = require('gulp-cssnano');

var run_js = function () {
	['src/script.js', 'src/script_admin.js'].forEach(function ( file ) {
		return gulp.src(file)
			.pipe(uglify({
				preserveComments: 'some'
			}))
			.pipe(rename({
				suffix: '.min'
			} ))
			.pipe(gulp.dest( 'dst' ));
	});
};
var run_css = function () {
	['src/style.scss', 'src/style_admin.scss'].forEach(function ( file ) {
		gulp.src(file)
			.pipe(sass())
			.pipe(nano({ autoprefixer: { browsers: [ '> 5%', 'last 2 versions' ], add: true } }))
			.pipe(rename({
				suffix: '.min',
				extension: '.css'
			}))
			.pipe(gulp.dest( 'dst' ));
	});
};

gulp.task('default', function () {
	run_js();
	run_css();
	gulp.watch('src/*.js', run_js);
	gulp.watch('src/*.scss', run_css);
});
