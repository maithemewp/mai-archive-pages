<?php

class Mai_Archive_Pages {

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	public $post_type;

	/**
	 * Slug prefix.
	 *
	 * @var string
	 */
	public $prefix;

	/**
	 * If archive is a post type.
	 *
	 * @var bool
	 */
	public $is_post_type;

	/**
	 * If archive is a taxonomy.
	 *
	 * @var bool
	 */
	public $is_taxonomy;

	/**
	 * Class constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! function_exists( 'genesis' ) ) {
			return;
		}

		$this->post_type = 'mai_archive_page';
		$this->prefix    = 'maiap';

		add_action( 'template_redirect',    [ $this, 'new_archive_page' ] );
		add_action( 'admin_bar_menu',       [ $this, 'admin_bar_link_front' ], 90 );
		add_action( 'admin_bar_menu',       [ $this, 'admin_bar_link_back' ], 90 );
		add_action( 'genesis_before_while', [ $this, 'do_content' ] );
	}

	function new_archive_page() {
		if ( ! $this->has_access() ) {
			return;
		}

		if ( 'new' !== filter_input( INPUT_GET, 'maiap', FILTER_SANITIZE_STRING ) ) {
			return;
		}

		$title = filter_input( INPUT_GET, 'maiap_title', FILTER_SANITIZE_STRING );
		$slug  = filter_input( INPUT_GET, 'maiap_slug', FILTER_SANITIZE_STRING );

		if ( ! ( $title && $slug ) ) {
			return;
		}

		$existing = get_page_by_path( $slug, OBJECT, $this->post_type );

		if ( $existing ) {
			wp_redirect( get_edit_post_link( $existing ) );
			exit();
		}

		$new_id = wp_insert_post( [
			'post_type'      => $this->post_type,
			'post_title'     => urldecode( $title ),
			'post_name'      => $slug,
			'post_status'    => 'draft',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		] );

		if ( $new_id ) {
			wp_redirect( get_edit_post_link( $new_id, false ) );
			exit();
		}

		$clean_url = home_url( add_query_arg( [] ) );

		wp_redirect( $clean_url );
		exit();
	}

	function admin_bar_link_front( $wp_admin_bar ) {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->has_access() ) {
			return;
		}

		$page_id = $this->get_page_id();

		if ( $page_id ) {

			$wp_admin_bar->add_node( [
				'id'    => 'mai-archive-page',
				'title' => '<span class="ab-icon dashicons dashicons-edit" style="margin-top: 4px;"></span><span class="ab-label">' . __( 'Edit Archive Content', 'mai-archive-pages' ) . '</span>',
				'href'  => get_edit_post_link( $page_id ),
			] );

			return;
		}

		$title = $slug = '';

		if ( $this->is_taxonomy() ) {

			$term_id = get_queried_object_id();

			if ( ! $term_id ) {
				return;
			}

			$title = $this->get_term_title( $term_id );
			$slug  = $this->get_term_slug( $term_id );

		} elseif ( $this->is_post_type() ) {

			$post_type = get_post_type();

			if ( ! $post_type ) {
				return;
			}

			$title = $this->get_post_type_title( $post_type );
			$slug  = $this->get_post_type_slug( $post_type );
		}

		$url = home_url( add_query_arg( [
			'maiap'       => 'new',
			'maiap_title' => urlencode( $this->get_post_type_title( $post_type ) ),
			'maiap_slug'  => $this->get_post_type_slug( $post_type ),
		] ) );

		if ( ! $url ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'mai-archive-page',
			'title' => '<span class="ab-icon dashicons dashicons-plus" style="margin-top: 4px;"></span><span class="ab-label">' . __( 'Add Archive Content', 'mai-archive-pages' ) . '</span>',
			'href'  => $url,
		] );
	}

	function admin_bar_link_back( $wp_admin_bar ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! $this->has_access() ) {
			return;
		}

		$screen = get_current_screen();

		if ( empty( $screen->id ) || $this->post_type !== $screen->id ) {
			return;
		}

		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$slug    = get_post_field( 'post_name', $post_id );
		$parts   = explode( '_', $slug );
		$prefix  = isset( $parts[0] ) ? $parts[0] : '';
		$name    = isset( $parts[1] ) ? $parts[1] : '';
		$id      = isset( $parts[2] ) ? $parts[2] : '';
		$link    = false;

		if ( $name && $id && taxonomy_exists( $name ) ) {
			$link = get_term_link( (int) $id, $name );
		}
		elseif ( $name && post_type_exists( $name ) ) {
			$link = get_post_type_archive_link( $name );
		}

		if ( ! $link ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'mai-archive-page',
			'title' => '<span class="ab-icon dashicons dashicons-admin-links"></span><span class="ab-label">' . __( 'View Archive Page', 'mai-archive-pages' ) . '</span>',
			'href'  => $link,
		] );
	}

	function do_content() {
		$slug = '';

		if ( $this->is_taxonomy() && ! is_paged() ) {

			$term = get_queried_object();
			$slug = $term ? $this->get_term_slug( $term->term_id ) : '';

		} elseif ( $this->is_post_type() && ! is_paged() ) {

			$slug = $this->get_post_type_slug( get_post_type() );
		}

		if ( ! $slug ) {
			return;
		}

		$posts = get_posts(
			[
				'post_type'              => $this->post_type,
				'post_status'            => 'any',
				'name'                   => $slug,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		if ( ! $posts ) {
			return;
		}

		$post    = reset( $posts );
		$content = $this->get_processed_content( $post->post_content );

		if ( ! $content ) {
			return;
		}

		genesis_markup(
			[
				'open'    => '<div %s>',
				'close'   => '</div>',
				'content' => $content,
				'context' => 'archive-page-content',
				'echo'    => true,
			]
		);
	}

	function get_processed_content( $content ) {
		if ( function_exists( 'mai_get_processed_content' ) ) {
			return mai_get_processed_content( $content );
		}

		/**
		 * Embed.
		 *
		 * @var WP_Embed $wp_embed Embed object.
		 */
		global $wp_embed;

		$content = $wp_embed->autoembed( $content );     // WP runs priority 8.
		$content = $wp_embed->run_shortcode( $content ); // WP runs priority 8.
		$content = do_blocks( $content );                // WP runs priority 9.
		$content = wptexturize( $content );              // WP runs priority 10.
		$content = wpautop( $content );                  // WP runs priority 10.
		$content = shortcode_unautop( $content );        // WP runs priority 10.
		$content = function_exists( 'wp_filter_content_tags' ) ? wp_filter_content_tags( $content ) : wp_make_content_images_responsive( $content ); // WP runs priority 10. WP 5.5 with fallback.
		$content = do_shortcode( $content );             // WP runs priority 11.
		$content = convert_smilies( $content );          // WP runs priority 20.

		return $content;
	}

	function get_page_id() {
		$slug = false;
		if ( $this->is_taxonomy() ) {
			$slug = $this->get_term_slug( get_queried_object_id() );
		} elseif ( $this->is_post_type() ) {
			$slug = $this->get_post_type_slug( get_post_type() );
		}
		return $slug ? get_page_by_path( $slug, OBJECT, $this->post_type ) : 0;
	}

	function get_post_type_title( $post_type ) {
		$post_type = get_post_type_object( $post_type );
		return $this->get_title( $post_type->label );
	}

	function get_post_type_slug( $post_type) {
		return $this->get_slug( $post_type );
	}

	function get_term_title( $term_id ) {
		$term     = get_term( $term_id );
		$taxonomy = get_taxonomy( $term->taxonomy );
		return $this->get_title( $term->name, $taxonomy->labels->singular_name );
	}

	function get_term_slug( $term_id ) {
		$term = get_term( $term_id );
		return $this->get_slug( $term->taxonomy, $term_id );
	}

	function get_title( $title, $name = '' ) {
		$name = $name ?: __( 'Archive', 'mai-archive-pages' );
		return sprintf( '%s [%s]', $title, $name );
	}

	function get_slug( $type, $name = '' ) {
		return $name ? sprintf( '%s_%s_%s', $this->prefix, $type, $name ) : sprintf( '%s_%s', $this->prefix, $type );
	}

	function is_post_type() {
		return is_home() || is_post_type_archive();
	}

	function is_taxonomy() {
		return is_category() || is_tag() || is_tax();
	}

	function has_access() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}
}
