<?php
/*
Plugin Name: Coexist with Gutenberg
Plugin URI:
Description: Add UI to coexist with Gutenberg happily
Version: 1.0
Author: PRESSMAN
Author URI: https://www.pressman.ne.jp/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class CoexistsWithGuntenberg{

	public static $gunterbergable_post_types = [];
	public static $instance;

	function __construct(){
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
		if ( isset( $_REQUEST['rest_route'] ) ) { return; }
		add_action( 'init', [ $this, 'get_gunterbergable_post_types' ], 999 );
		add_action( 'init', [ $this, 'replace_default_add_new_button' ], 1000 );
		add_action( 'admin_menu', [ $this, 'add_sub_post_new_classic_menu_and_button' ], 999 );
		add_filter( 'submenu_file', [ $this, 'set_up_current_menu' ], 999 );
		add_action( 'admin_bar_menu', [ $this, 'add_switch_editor_button' ], 9999 );
	}

	/**
	 * Get instance
	 *
	 * @return $instance
	 */
	public static function get_instance() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Check if Gutenberg is active
	 *
	 * @return bool
	 */
	function is_gutenberg_active(){
		return ( defined( 'GUTENBERG_VERSION' ) ) ? true : false;
	}

	/**
	 * Get a list of Gutenbergable post_types
	 *
	 * @return none
	 */
	function get_gunterbergable_post_types(){
		// Gutenbergable post_type needs 'show_in_rest'
		$post_types = get_post_types( [
				'show_in_rest' => true
			]
		);
		// Gutenbergable post_type needs 'editor' support
		foreach ( $post_types as $key => $post_type ) {
			if ( ! post_type_supports( $post_type, 'editor' ) ) {
				unset( $post_types[ $key ] );
			}
		}
		self::$gunterbergable_post_types = array_keys( $post_types );
	}

	/**
	 * Add 'Classic Editor' option under 'Add New' button in post.php screen.
	 *
	 * @return none
	 */
	function replace_default_add_new_button(){
		if( ! $this->is_gutenberg_active() ){
			return;
		}

		if( function_exists( 'gutenberg_replace_default_add_new_button' ) ){
			add_action( 'admin_print_scripts-post.php', 'gutenberg_replace_default_add_new_button' );
		}
	}

	/**
	 * Add 'Add new (classic)' link to admin menu.
	 *
	 * @return none
	 */
	function add_sub_post_new_classic_menu_and_button(){
		if( ! $this->is_gutenberg_active() ){
			return;
		}

		global $submenu;
		$post_types = self::$gunterbergable_post_types;
		foreach ( $post_types as $post_type ) {
			$slug = ( 'post' === $post_type ) ? 'edit.php' : 'edit.php?post_type=' . $post_type;
			if ( isset( $submenu[ $slug ] ) ) {
				$submenu[ $slug ] = $this->insert_classic_menu( $slug );
			}
		}
	}

	/**
	 * Insert 'Add new (classic)' link to selected submenu.
	 *
	 * @param string $slug
	 * @return array
	 */
	function insert_classic_menu( $slug ){
		global $submenu;
		$array = $submenu[ $slug ];
		$post_new_slug = str_replace( 'edit.php', 'post-new.php', $slug );

		// Where is post-new.php?
		$where_is_post_new = -1;
		foreach ( $array as $k => $v ) {
			if ( is_array( $v ) && $post_new_slug === $v['2'] ) {
				$where_is_post_new = (int) $k;
				break;
			}
		}

		// Insert classic menu next to normal link
		$key_big_to_small = array_reverse( array_keys( $array ) );
		foreach ( $key_big_to_small as $key ) {
			if ( ! isset( $array[ $key ] ) ){
				continue;
			}
			if ( $key > $where_is_post_new ) {
				$array[ $key + 1 ] = $array[ $key ];
				unset( $array[ $key ] );
			} else if( $key === $where_is_post_new ){
				$array[ $key + 1 ] = $array[ $key ];
				$array[ $key + 1 ][0] = apply_filters( 'cwg_post_new_classic_menu_text', $array[ $key ][0] . ' (classic)', $slug );
				$array[ $key + 1 ][2] = ( false === strpos( $array[ $key ][2], '?' ) ) ? $array[ $key ][2] . '?classic-editor' : $array[ $key ][2] . '&classic-editor';
				break;
			}
		}
		ksort( $array );

		return $array;
	}

	/**
	 * Set 'current' class to post-new.php with classic-editor param
	 *
	 * @param string $submenu_file
	 * @return string
	 */
	function set_up_current_menu( $submenu_file ){
		if( ! $this->is_gutenberg_active() ){
			return;
		}

		if ( isset( $_REQUEST['classic-editor'] ) && '' !== $submenu_file && 0 === strpos( $submenu_file, 'post-new.php') ) {
			$submenu_file = ( false === strpos( $submenu_file, '?' ) )
				 ? $submenu_file . '?classic-editor'
				 : $submenu_file . '&classic-editor';
		}
		return $submenu_file;
	}

	/**
	 * Add 'Switch to (Classic|Gunteberg) editor' button to admin bar.
	 *
	 * @param object $wp_admin_bar
	 * @return none
	 */
	function add_switch_editor_button( $wp_admin_bar ) {
		if( ! $this->is_gutenberg_active() ){
			return;
		}

		if ( is_admin() ){

			global $pagenow, $post_type;
			$gunterbergable_post_types = self::$gunterbergable_post_types;

			if ( ! in_array( $post_type, $gunterbergable_post_types ) ) {
				return;
			}

			$uri = ( empty( $_SERVER['HTTPS'] ) ? 'http://' : 'https://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$switch_to = 'Gunteberg';
			if ( ! isset( $_REQUEST['classic-editor'] ) ) {
				$switch_to = 'Classic';
				$uri = ( false === strpos( $uri, '?' ) ) ? $uri . '?classic-editor' : $uri . '&classic-editor';
			} else {
				$uri = str_replace( ['?classic-editor', '&classic-editor'], '', $uri );
			}

			$wp_admin_bar->add_node( [
				'id' => 'switch_editor_mode',
				'title' => 'Switch to ' . $switch_to . ' editor',
				'href' => $uri
			]);

		}
	}

}

CoexistsWithGuntenberg::get_instance();
