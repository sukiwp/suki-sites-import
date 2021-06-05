<?php
/**
 * WordPress Importer
 * https://github.com/humanmade/WordPress-Importer
 *
 * Released under the GNU General Public License v2.0
 * https://github.com/humanmade/WordPress-Importer/blob/master/LICENSE
 *
 * @package WordPress Importer
 */

/**
 * WXR Import Info Class
 */
class WXR_Import_Info {

	/**
	 * Home
	 *
	 * @var string
	 */
	public $home;

	/**
	 * Site URL
	 *
	 * @var string
	 */
	public $siteurl;

	/**
	 * Title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Users
	 *
	 * @var array
	 */
	public $users = array();

	/**
	 * Post Count
	 *
	 * @var integer
	 */
	public $post_count = 0;

	/**
	 * Media Count
	 *
	 * @var integer
	 */
	public $media_count = 0;

	/**
	 * Comment Count
	 *
	 * @var string
	 */
	public $comment_count = 0;

	/**
	 * Term Count
	 *
	 * @var string
	 */
	public $term_count = 0;

	/**
	 * Generator
	 *
	 * @var string
	 */
	public $generator = '';

	/**
	 * Version
	 *
	 * @var string
	 */
	public $version;
}
