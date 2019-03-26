'use strict';

/**
 * Define configurations
 */

const info = require( './package.json' );

const config = {
	init: './' + info.name + '.php',
	src: {
		scss: [ './assets/scss/**/*.scss' ],
		css: [ './assets/css/**/*.css', '!./assets/css/vendors/*' ],
		js: [ './assets/js/**/*.js', '!./assets/js/vendors/*' ],
		pot: [ './**/*.php', '!./__build/**/*.php', '!./__bak/**/*.php' ],
		build: [
			'./*',
			'./assets/css/**/*',
			'./assets/icons/**/*',
			'./assets/images/**/*',
			'./assets/js/**/*',
			'./inc/**/*',
			'./languages/**/*',
			'./page-templates/**/*',
			'./template-parts/**/*',

			// exclude files and folders
			'!**/Thumbs.db',
			'!**/.DS_Store',
			'!./.gitignore',
			'!./package*.json',
			'!./gulpfile.js',
			'!./node_modules',
			'!./README.md',
			'!./LICENSE.md',
			'!./__build',
			'!./__bak',
		],
	},
	dest: {
		scss: './assets/scss',
		css: './assets/css',
		js: './assets/js',
		icons: './assets/icons',
		pot: './languages',
		build: './__build/' + info.name,
		zip: './__build/zip',
	},
};

/**
 * Init Gulp and plugins
 */

const gulp          = require( 'gulp' );

// CSS
const sass          = require( 'gulp-sass' );
const autoprefixer  = require( 'gulp-autoprefixer' );
const uglifycss     = require( 'gulp-uglifycss' );
const mmq           = require( 'gulp-merge-media-queries' );
const rtlcss        = require( 'gulp-rtlcss' );

// JS
const uglify        = require( 'gulp-uglify' );

// Translation
const wpPot         = require( 'gulp-wp-pot' );

// Others
const fs            = require( 'fs' );
const del           = require( 'del' );
const rename        = require( 'gulp-rename' );
const replace       = require( 'gulp-replace' );
const watch         = require( 'gulp-watch' );
const zip           = require( 'gulp-zip' );

/**
 * Task: Change theme info (style.css file header) based on package.json values.
 */
gulp.task( 'plugin_info', function() {
	var info = JSON.parse( fs.readFileSync( './package.json' ) );

	// Change plugin / theme info
	return gulp.src( [ config.init ] )
		.pipe( replace( new RegExp( '((?:Plugin|Theme) Name: ).*' ), '$1' + info.title ) )
		.pipe( replace( new RegExp( '((?:Plugin|Theme) URI: ).*' ), '$1' + info.uri ) )
		.pipe( replace( new RegExp( '(Description: ).*' ), '$1' + info.description ) )
		.pipe( replace( new RegExp( '(Version: ).*' ), '$1' + info.version ) )
		.pipe( replace( new RegExp( '(Author: ).*' ), '$1' + info.author.name ) )
		.pipe( replace( new RegExp( '(Author URI: ).*' ), '$1' + info.author.url ) )
		.pipe( replace( new RegExp( '(Text Domain: ).*' ), '$1' + info.name ) )
		.pipe( replace( new RegExp( '(Tags: ).*' ), '$1' + info.keywords.join( ', ' ) ) )

		.pipe( replace( new RegExp( '(\'' + info.name.replace( '-', '_' ).toUpperCase() + '_VERSION\', \').*?(\'.*)' ), '$1' + info.version + '$2' ) )

		.pipe( gulp.dest( './' ) );
} );

/**
 * Task: Change info on readme.txt based on package.json values.
 */
gulp.task( 'readme_txt', function() {
	var info = JSON.parse( fs.readFileSync( './package.json' ) );

	var contributors = info.contributors.map(function( contributor ) {
		return contributor.name;
	});

	// Change theme version on eadme.txt
	return gulp.src( [ './readme.txt' ] )
		.pipe( replace( /(===).*(===)/, '$1 ' + info.title + ' $2' ) )
		.pipe( replace( /(Contributors: ).*/, '$1' + contributors.join( ', ' ) ) )
		.pipe( replace( /(Tags: ).*/, '$1' + info.keywords.join( ', ' ) ) )
		.pipe( replace( /(Stable tag: ).*/, '$1' + info.version ) )

		.pipe( replace( /(\s\s).*(\s\s== Description ==)/, '$1' + info.description + '$2' ) )

		// .pipe( replace( /(== Description ==\s\s).*(\s\s)/, '$1' + info.description + '$2' ) )

		.pipe( gulp.dest( './' ) );
} );

/**
 * Wrapper Task: Set theme info files.
 */
gulp.task( 'info', gulp.series( 'plugin_info', 'readme_txt' ) );

/**
 * Task: Convert SASS to CSS files.
 */
gulp.task( 'css_sass', function() {
	return gulp.src( config.src.scss )
		.pipe( sass( {
			outputStyle: 'expanded',
			indentType: 'tab',
			indentWidth: 1,
		} )
		.on( 'error', sass.logError ) )
		.pipe( autoprefixer( { cascade: false } ) )
		.pipe( gulp.dest( config.dest.css ) );
} );

/**
 * Task: Minify CSS files.
 */
gulp.task( 'css_min', function() {
	var src = config.src.css.concat( [ '!./assets/css/**/*.min.css', '!./assets/css/**/*-rtl.css' ] );

	return gulp.src( src )
		.pipe( mmq() )
		.pipe( uglifycss() )
		.pipe( rename( { suffix: '.min' } ) )
		.pipe( gulp.dest( config.dest.css ) );
} );

/**
 * Task: Make RTL CSS files.
 */
gulp.task( 'css_rtl', function() {
	var src = config.src.css.concat( [ '!./assets/css/**/*-rtl.css' ] );

	return gulp.src( src )
		.pipe( rtlcss() )
		.pipe( rename( { suffix: '-rtl' } ) )
		.pipe( gulp.dest( config.dest.css ) );
} );

/**
 * Task: Run group of tasks regarding CSS files in sequence.
 */
gulp.task( 'css', gulp.series( 'css_sass', 'css_min', 'css_rtl' ) );

/**
 * Task: Minify JS files.
 */
gulp.task( 'js', function() {
	var src = config.src.js.concat( [ '!./assets/js/**/*.min.js' ] );

	return gulp.src( src )
		.pipe( uglify().on( 'error', function( error ) {
			console.error( error ); 
			this.emit( 'end' ); 
		} ) )
		.pipe( rename( { suffix: '.min' } ) )
		.pipe( gulp.dest( config.dest.js ) );
} );

/**
 * Task: Generate .pot file for translation.
 */
gulp.task( 'pot', function() {
	var info = JSON.parse( fs.readFileSync( './package.json' ) );

	return gulp.src( config.src.pot )
		.pipe( wpPot( {
			domain: info.name,
			package: info.title,
			metadataFile: info.name + '.php',
		} ).on( 'error', function( error ) {
			console.error( error );
			this.emit( 'end' );
		} ) )
		.pipe( gulp.dest( config.dest.pot + '/' + info.name + '.pot' ) );
} );

/**
 * Task: Watch all files and copy to 'build' folder.
 */
gulp.task( 'watch', function() {
	watch( './package.json', function() {
		gulp.task( 'info' )();
	} );

	watch( config.src.pot, function() {
		gulp.task( 'pot' )();
	} );
	
	watch( config.src.scss, function() {
		gulp.task( 'css' )();
	} );

	watch( config.src.js.concat( [ '!./assets/js/**/*.min.js' ] ), function( obj ) {
		gulp.task( 'js' )();
	} );

	watch( config.src.build, { base: './' }, function( obj ) {
		if ( 'unlink' === obj.event ) {
			del( config.dest.build + '/' + obj.relative, { force: true } );
		} else {
			gulp.src( obj.path, { base: './' } )
				.pipe( gulp.dest( config.dest.build ) );
		}
	} );
} );

/**
 * Task: Clean files in "__build" directory.
 */
gulp.task( 'clean', function() {
	return del( config.dest.build + '/*', { force: true } );
} );

/**
 * Task: Copy selected files from sources to "__build" directory.
 */
gulp.task( 'copy', function() {
	return gulp.src( config.src.build, { base: './' } )
		.pipe( gulp.dest( config.dest.build ) );
} );

/**
 * Wrapper Task: Build.
 */
gulp.task( 'build', gulp.series( 'pot', 'css', 'js', 'info', 'clean', 'copy' ) );

/**
 * Wrapper Task: Default task.
 */
gulp.task( 'default', gulp.series( 'build', 'watch' ) );

/**
 * Wrapper Task: Build to zip.
 */
gulp.task( 'zip', function() {
	var info = JSON.parse( fs.readFileSync( './package.json' ) );

	return gulp.src( config.dest.build + '/**/*', { buffer: false, base: config.dest.build + '/../' } )
		.pipe( zip( info.name + '-' + info.version + '.zip' ) )
		.pipe( gulp.dest( config.dest.zip ) );
} );