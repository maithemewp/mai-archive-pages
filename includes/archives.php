<?php

class BE_Landing_Page {

	/**
	 * Supported taxonomies
	 * @var array
	 */
	public $supported_taxonomies;

	/**
	 * Supported CPT Archives
	 * @var array
	 */
	public $supported_cpt_archives;

	/**
	 * Post type
	 * @var string
	 */
	public $post_type;

	/**
	 * Class constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'init', [ $this, 'supported_taxonomies' ], 4 );
		add_action( 'init', [ $this, 'supported_cpt_archives' ], 4 );
		add_action( 'init', [ $this, 'post_type' ], 4 );
		add_action( 'init', [ $this, 'register_cpt' ], 12 );
		add_action('acf/init', [ $this, 'register_metabox' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_link_front' ], 90 );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_link_back' ], 90 );

		// Theme locations
		$locations = apply_filters(
			'cultivate_pro/landing/theme_locations',
			[
				'genesis_before_while' => 20,
				'tha_content_while_before' => 20,
			]
		);
		foreach( $locations as $hook => $priority ) {
			add_action( $hook, [ $this, 'show' ], $priority );
		}

		// Add 'the_content' filter
		add_filter( 'cultivate_pro/landing/the_content', 'the_content' );
	}

	/**
	 * Supported Taxonomies
	 *
	 */
	function supported_taxonomies() {
		$this->supported_taxonomies = apply_filters( 'cultivate_pro/landing/taxonomies', [ 'category' ] );
	}

	/**
	 * Supported Post Type Archives
	 *
	 */
	function supported_cpt_archives() {
		$this->supported_cpt_archives = apply_filters( 'cultivate_pro/landing/cpt_archives', [] );
	}

	/**
	 * Post Type
	 *
	 */
	function post_type() {
		$this->post_type = 'cultivate_landing';
	}

	/**
	 * Register the custom post type
	 *
	 */
	function register_cpt() {

		$labels = [
			'name'               => __( 'Landing Pages', 'cultivate-pro' ),
			'singular_name'      => __( 'Landing Page', 'cultivate-pro' ),
			'add_new'            => __( 'Add New', 'cultivate-pro' ),
			'add_new_item'       => __( 'Add New Landing Page', 'cultivate-pro' ),
			'edit_item'          => __( 'Edit Landing Page', 'cultivate-pro' ),
			'new_item'           => __( 'New Landing Page', 'cultivate-pro' ),
			'view_item'          => __( 'View Landing Page', 'cultivate-pro' ),
			'search_items'       => __( 'Search Landing Pages', 'cultivate-pro' ),
			'not_found'          => __( 'No Landing Pages found', 'cultivate-pro' ),
			'not_found_in_trash' => __( 'No Landing Pages found in Trash', 'cultivate-pro' ),
			'parent_item_colon'  => __( 'Parent Landing Page:', 'cultivate-pro' ),
			'menu_name'          => __( 'Landing Pages', 'cultivate-pro' ),
		];

		$args = [
			'labels'              => $labels,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'editor', 'revisions' ],
			'public'              => false,
			'show_ui'             => true,
			'show_in_rest'	      => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite'             => false,
			'menu_icon'           => cultivate_pro()->menu_icon(),
			'show_in_menu'		=> false,
		];

		register_post_type( $this->post_type, apply_filters( 'cultivate_pro/landing/post_type_args', $args ) );
	}

	/**
	 * Register metabox
	 *
	 */
	function register_metabox() {

		$taxonomies = $tax_fields = [];
		$default_term = !empty( $_GET['cultivate_term'] ) ? intval( $_GET['cultivate_term'] ) : false;
		$default_tax = !empty( $_GET['cultivate_tax'] ) ? esc_attr( $_GET['cultivate_tax'] ) : false;
		$tax = false;
		foreach( $this->supported_taxonomies as $i => $tax_slug ) {
			$tax = get_taxonomy( $tax_slug );
			$taxonomies[ $tax_slug ] = $tax->labels->singular_name;
			$default = $tax_slug === $default_tax ? $default_term : false;

			$tax_fields[] = [
				'key'					=> 'field_10' . $i,
				'label'					=> $tax->labels->name,
				'name'					=> 'be_connected_' . $tax_slug,
				'type'					=> 'taxonomy',
				'default_value'			=> $default,
				'taxonomy'				=> $tax_slug,
				'field_type'			=> 'select',
				'conditional_logic'		=> [
					[
						[
							'field'		=> 'field_5da8747adb0bf',
							'operator'	=> '==',
							'value'		=> $tax_slug,
						]
					]
				]
			];
		}

		$taxonomy_select_field = [[
			'key'		=> 'field_5da8747adb0bf',
			'label'		=> __( 'Taxonomy', 'cultivate-pro' ),
			'name'		=> 'be_connected_taxonomy',
			'type'		=> 'select',
			'choices'	=> $taxonomies,
			'default_value' => $default_tax,
		]];

		$settings = apply_filters( 'cultivate_pro/landing/field_group', [
			'title' => __( 'Appears On', 'cultivate-pro' ),
			'fields' => array_merge( $taxonomy_select_field, $tax_fields ),
			'location' => [
				[
					[
						'param' => 'post_type',
						'operator' => '==',
						'value' => $this->post_type,
					],
				],
			],
			'position' => 'side',
			'active' => true,
		] );

		if( ! empty( $settings ) )
			acf_add_local_field_group( $settings );
	}


	/**
	 * Show landing page
	 *
	 */
	function show( $location = '' ) {
		if( ! $location )
			$location = $this->get_landing_id();

		if( empty( $location ) || get_query_var( 'paged' ) )
			return;

		$args = [ 'post_type' => $this->post_type, 'posts_per_page' => 1, 'post_status' => 'publish' ];
		if( is_int( $location ) )
			$args['p'] = intval( $location );
		else
			$args['name'] = sanitize_key( $location );

		$loop = new \WP_Query( $args );

		if( $loop->have_posts() ): while( $loop->have_posts() ): $loop->the_post();
			echo '<div class="block-area block-area-' . sanitize_key( get_the_title() ) . '">';
				global $post;
				echo apply_filters( 'cultivate_pro/landing/the_content', $post->post_content );
			echo '</div>';
			if( is_archive() ) {
				$title = __( 'Newest', 'cultivate-pro' ) . ' ' . get_the_archive_title();
				$title = apply_filters( 'cultivate_pro/landing/archive_title', $title );
				if( !empty( $title ) )
					echo '<header id="recent" class="archive-recent-header"><h2>' . $title . '</h2></header>';
			}
		endwhile; endif; wp_reset_postdata();
	}

	/**
	 * Get taxonomy
	 *
	 */
	function get_taxonomy() {
		$taxonomy = is_category() ? 'category' : ( is_tag() ? 'post_tag' : get_query_var( 'taxonomy' ) );
		if( !empty( $this->supported_taxonomies ) && in_array( $taxonomy, $this->supported_taxonomies ) )
			return $taxonomy;
		else
			return false;
	}

	/**
	 * Get Landing Page ID
	 *
	 */
	function get_landing_id() {

		if( is_post_type_archive() && in_array( get_post_type(), $this->supported_cpt_archives ) ) {
			$loop = new \WP_Query( [
				'post_type' => $this->post_type,
				'posts_per_page' => 99,
				'fields' => 'ids',
				'no_found_rows' => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'post_name__in' => [ 'cpt-' . get_post_type() ]
			]);

		} else {

			$taxonomy = $this->get_taxonomy();
			if( empty( $taxonomy ) || ! is_archive() )
				return false;

			$meta_key = 'be_connected_' . str_replace( '-', '_', $taxonomy );

			$loop = new \WP_Query( [
				'post_type' => $this->post_type,
				'posts_per_page' => 1,
				'fields' => 'ids',
				'no_found_rows' => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'meta_query' => [
					[
						'key' => $meta_key,
						'value' => get_queried_object_id(),
					]
				]
			] );
		}

		if( empty( $loop->posts ) )
			return false;
		else
			return $loop->posts[0];

	}

	/**
	 * Get term link
	 *
	 */
	function get_term_link( $archive_id = false ) {

		if( empty( $archive_id ) )
			return false;

		$taxonomy = get_post_meta( $archive_id, 'be_connected_taxonomy', true );
		$term = get_post_meta( $archive_id, 'be_connected_' . $taxonomy, true );

		if( empty( $term ) )
			return false;

		$term = get_term_by( 'term_id', $term, $taxonomy );
		return get_term_link( $term, $taxonomy );
	}

	/**
	 * Admin Bar Link, Frontend
	 *
	 */
	 function admin_bar_link_front( $wp_admin_bar ) {
		 $taxonomy = $this->get_taxonomy();
		 if( ! ( $taxonomy || is_post_type_archive( $this->supported_cpt_archives ) ) )
		 	return;

		if( ! ( is_user_logged_in() && current_user_can( 'manage_categories' ) ) )
			return;

		$archive_id = $this->get_landing_id();
		$icon = '<span style="display: block; float: left; margin: 5px 5px 0 0;">' . cultivate_pro()->icon( [ 'icon' => 'cultivatewp-menu', 'size' => 20, ] ) . '</span>';
		if( !empty( $archive_id ) ) {
			$wp_admin_bar->add_node( [
				'id' => 'category_landing_page',
				'title' => $icon . __( 'Edit Landing Page', 'cultivate-pro' ),
				'href'  => get_edit_post_link( $archive_id ),
			] );

		} else {
			$wp_admin_bar->add_node( [
				'id' => 'category_landing_page',
				'title' => $icon . __( 'Add Landing Page', 'cultivate-pro' ),
				'href'  => admin_url( 'post-new.php?post_type=' . $this->post_type . '&cultivate_tax=' . $taxonomy . '&cultivate_term=' . get_queried_object_id() )
			] );
		}
	 }

	/**
	 * Admin Bar Link, Backend
	 *
	 */
	function admin_bar_link_back( $wp_admin_bar ) {
		if( ! is_admin() )
			return;

		$screen = get_current_screen();
		if( empty( $screen->id ) || $this->post_type !== $screen->id )
			return;

		$archive_id = !empty( $_GET['post'] ) ? intval( $_GET['post'] ) : false;
		if( ! $archive_id )
			return;

		$term_link = $this->get_term_link( $archive_id );
		if( empty( $term_link ) )
			return;

		$icon = '<span style="display: block; float: left; margin: 5px 5px 0 0;">' . cultivate_pro()->icon( [ 'icon' => 'cultivatewp-menu', 'size' => 20 ] ) . '</span>';
		$wp_admin_bar->add_node( [
			'id'	=> 'category_landing_page',
			'title'	=> $icon . __( 'View Landing Page', 'cultivate-pro' ),
			'href'	=> $term_link,
		] );
	}
}
