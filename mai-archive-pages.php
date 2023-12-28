<?php

/**
 * Plugin Name:     Mai Archive Pages
 * Plugin URI:      https://bizbudding.com/mai-design-pack/
 * Description:     Build robust and SEO-friendly archive pages with blocks.
 * Version:         1.4.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Must be at the top of the file.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Main Mai_Archive_Pages_Plugin Class.
 *
 * @since 0.1.0
 */
final class Mai_Archive_Pages_Plugin {

	/**
	 * @var   Mai_Archive_Pages_Plugin The one true Mai_Archive_Pages_Plugin
	 * @since 0.1.0
	 */
	private static $instance;

	/**
	 * Main Mai_Archive_Pages_Plugin Instance.
	 *
	 * Insures that only one instance of Mai_Archive_Pages_Plugin exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   0.1.0
	 * @static  var array $instance
	 * @uses    Mai_Archive_Pages_Plugin::setup_constants() Setup the constants needed.
	 * @uses    Mai_Archive_Pages_Plugin::includes() Include the required files.
	 * @uses    Mai_Archive_Pages_Plugin::hooks() Activate, deactivate, etc.
	 * @see     Mai_Archive_Pages_Plugin()
	 * @return  object | Mai_Archive_Pages_Plugin The one true Mai_Archive_Pages_Plugin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup.
			self::$instance = new Mai_Archive_Pages_Plugin;
			// Methods.
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-archive-pages' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-archive-pages' ), '1.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function setup_constants() {
		// Plugin version.
		if ( ! defined( 'MAI_ARCHIVE_PAGES_PLUGIN_VERSION' ) ) {
			define( 'MAI_ARCHIVE_PAGES_PLUGIN_VERSION', '1.4.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'MAI_ARCHIVE_PAGES_PLUGIN_DIR' ) ) {
			define( 'MAI_ARCHIVE_PAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Includes Path.
		// if ( ! defined( 'MAI_ARCHIVE_PAGES_PLUGIN_CLASSES_DIR' ) ) {
		// 	define( 'MAI_ARCHIVE_PAGES_PLUGIN_CLASSES_DIR', MAI_ARCHIVE_PAGES_PLUGIN_PLUGIN_DIR . 'classes/' );
		// }

		// Plugin Includes Path.
		// if ( ! defined( 'MAI_ARCHIVE_PAGES_PLUGIN_INCLUDES_DIR' ) ) {
		// 	define( 'MAI_ARCHIVE_PAGES_PLUGIN_INCLUDES_DIR', MAI_ARCHIVE_PAGES_PLUGIN_PLUGIN_DIR . 'includes/' );
		// }

		// Plugin Folder URL.
		if ( ! defined( 'MAI_ARCHIVE_PAGES_PLUGIN_URL' ) ) {
			define( 'MAI_ARCHIVE_PAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'MAI_ARCHIVE_PAGES_PLUGIN_FILE' ) ) {
			define( 'MAI_ARCHIVE_PAGES_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name
		if ( ! defined( 'MAI_ARCHIVE_PAGES_PLUGIN_BASENAME' ) ) {
			define( 'MAI_ARCHIVE_PAGES_PLUGIN_BASENAME', dirname( plugin_basename( __FILE__ ) ) );
		}
	}

	/**
	 * Include required files.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function includes() {
		// Include vendor libraries.
		require_once __DIR__ . '/vendor/autoload.php';
		// Includes.
		foreach ( glob( MAI_ARCHIVE_PAGES_PLUGIN_DIR . 'includes/*.php' ) as $file ) { include $file; }
		// Classes.
		foreach ( glob( MAI_ARCHIVE_PAGES_PLUGIN_DIR . 'classes/*.php' ) as $file ) { include $file; }
	}

	/**
	 * Run the hooks.
	 *
	 * @since   0.1.0
	 * @return  void
	 */
	public function hooks() {
		add_action( 'plugins_loaded',                    [ $this, 'updater' ], 12 );
		add_action( 'init',                              [ $this, 'register_content_types' ] );
		add_action( 'acf/init',                          [ $this, 'register_field_group' ] );
		add_filter( 'acf/load_field/key=maiap_location', [ $this, 'load_location_choices' ] );
		add_action( 'init',                              [ $this, 'init' ] );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	}

	/**
	 * Setup the updater.
	 *
	 * composer require yahnis-elsts/plugin-update-checker
	 *
	 * @uses    https://github.com/YahnisElsts/plugin-update-checker/
	 *
	 * @return  void
	 */
	public function updater() {
		// Bail if plugin updater is not loaded.
		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		// Setup the updater.
		$updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/mai-archive-pages/', __FILE__, 'mai-archive-pages' );

		// Maybe set github api token.
		if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
			$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
		}

		// Add icons for Dashboard > Updates screen.
		if ( function_exists( 'mai_get_updater_icons' ) && $icons = mai_get_updater_icons() ) {
			$updater->addResultFilter(
				function ( $info ) use ( $icons ) {
					$info->icons = $icons;
					return $info;
				}
			);
		}
	}

	/**
	 * Register content types.
	 *
	 * @return  void
	 */
	public function register_content_types() {

		register_post_type( 'mai_archive_page', array(
			'exclude_from_search' => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'labels'              => array(
				'name'               => _x( 'Archive Pages', 'Archive Page general name',      'mai-archive' ),
				'singular_name'      => _x( 'Archive Page', 'Archive Page singular name',      'mai-archive' ),
				'menu_name'          => _x( 'Archive Pages', 'Archive Page admin menu',        'mai-archive' ),
				'name_admin_bar'     => _x( 'Archive', 'Archive Page add new on admin bar',    'mai-archive' ),
				'add_new'            => _x( 'Add New', 'Archive Page',                         'mai-archive' ),
				'add_new_item'       => __( 'Add New Archive Page',                            'mai-archive' ),
				'new_item'           => __( 'New Archive Page',                                'mai-archive' ),
				'edit_item'          => __( 'Edit Archive Page',                               'mai-archive' ),
				'view_item'          => __( 'View Archive Page',                               'mai-archive' ),
				'all_items'          => __( 'All Archive Pages',                               'mai-archive' ),
				'search_items'       => __( 'Search Archive Pages',                            'mai-archive' ),
				'parent_item_colon'  => __( 'Parent Archive Pages:',                           'mai-archive' ),
				'not_found'          => __( 'No Archive Pages found.',                         'mai-archive' ),
				'not_found_in_trash' => __( 'No Archive Pages found in Trash.',                'mai-archive' )
			),
			'menu_icon'          => 'dashicons-admin-page',
			'public'             => false,
			'publicly_queryable' => is_admin(),
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => true,
			'show_tagcloud'      => false,
			'show_ui'            => true,
			'rewrite'            => false,
			'supports'           => array( 'title', 'editor' ),
		) );
	}

	/**
	 * Registers field group.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	function register_field_group() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			[
				'key'      => 'maiap_archive_page_field_group',
				'title'    => __( 'Settings', 'mai-publisher' ),
				'fields'   => [
					[
						'label'   => __( 'Locations Settings', 'mai-publisher' ),
						'key'     => 'maiap_location',
						'name'    => 'maiap_location',
						'type'    => 'select',
						'choices' => [],
					],
				],
				'position' => 'side',
				'location' => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'mai_archive_page',
						],
					],
				],
			]
		);
	}

	/**
	 * Loads location choices.
	 *
	 * @since 1.4.0
	 *
	 * @param array $field The field data.
	 *
	 * @return array
	 */
	function load_location_choices( $field ) {
		if ( ! is_admin() ) {
			return $field;
		}

		global $post;

		$after                  = str_contains( $post->post_name, '_after_' );
		$before                 = ! $after;
		$label                  = $before ? __( 'Before', 'mai-publisher' ) : __( 'After', 'mai-publisher' );
		$field['default_value'] = $before ? 'inside' : 'outside';
		$field['choices']       = [
			'outside' => sprintf( '%s %s', $label, __( 'container (full width)', 'mai-publisher' ) ),
			'inside'  => sprintf( '%s %s', $label, __( 'entries (contained)', 'mai-publisher' ) ),
		];

		return $field;
	}

	/**
	 * Plugin init.
	 *
	 * @return  void
	 */
	public function init() {
		new Mai_Archive_Pages;
	}

	/**
	 * Plugin activation.
	 *
	 * @return  void
	 */
	public function activate() {
		$this->register_content_types();
		flush_rewrite_rules();
	}
}

/**
 * The main function for that returns Mai_Archive_Pages_Plugin
 *
 * The main function responsible for returning the one true Mai_Archive_Pages_Plugin
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $plugin = Mai_Archive_Pages_Plugin(); ?>
 *
 * @since 0.1.0
 *
 * @return object|Mai_Archive_Pages_Plugin The one true Mai_Archive_Pages_Plugin Instance.
 */
function mai_archive_pages_plugin() {
	return Mai_Archive_Pages_Plugin::instance();
}

// Get Mai_Archive_Pages_Plugin Running.
mai_archive_pages_plugin();
