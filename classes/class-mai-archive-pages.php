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
	 * @since 0.1.0
	 */
	public function __construct() {
		if ( ! function_exists( 'genesis' ) ) {
			return;
		}

		$this->post_type = 'mai_archive_page';
		$this->prefix    = 'mai';

		add_action( 'template_redirect',    [ $this, 'new_archive_page' ] );
		add_action( 'admin_bar_menu',       [ $this, 'admin_bar_link_front' ], 90 );
		add_action( 'admin_bar_menu',       [ $this, 'admin_bar_link_back' ], 90 );
		add_action( 'genesis_before_while', [ $this, 'do_content' ] );
		add_action( 'delete_term',          [ $this, 'delete_archive_page' ] );
	}

	/**
	 * Creates a new archive page.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
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

	/**
	 * Output the admin toolbar link on the front end.
	 * Links to the archive page content editor.
	 *
	 * @since 0.1.0
	 *
	 * @param object $wp_admin_bar
	 *
	 * @return void
	 */
	function admin_bar_link_front( $wp_admin_bar ) {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->has_access() ) {
			return;
		}

		if ( ! ( $this->is_taxonomy() || $this->is_post_type() ) ) {
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

		$title = $slug = $id = '';

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

		if ( ! ( $title && $slug ) ) {
			return;
		}

		$url = home_url( add_query_arg( [
			'maiap'       => 'new',
			'maiap_title' => urlencode( $title ),
			'maiap_slug'  => $slug,
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

	/**
	 * Output the admin toolbar link in the backend.
	 * Links to the actual archive.
	 *
	 * @since 0.1.0
	 *
	 * @param object $wp_admin_bar
	 *
	 * @return void
	 */
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

		$post_id        = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$slug           = get_post_field( 'post_name', $post_id );
		$taxonomy_slug  = sprintf( '%s_taxonomy_', $this->prefix );
		$post_type_slug = sprintf( '%s_post_type_', $this->prefix );
		$type           = false;
		$is_valid       = false;

		if ( $this->has_string( $taxonomy_slug, $slug ) ) {
			$type     = 'taxonomy';
			$slug     = str_replace( $taxonomy_slug, '', $slug );
			$is_valid = true;
		} elseif ( $this->has_string( $post_type_slug, $slug ) ) {
			$type     = 'post_type';
			$slug     = str_replace( $post_type_slug, '', $slug );
			$is_valid = true;
		}

		if ( ! $is_valid ) {
			return;
		}

		$parts = explode( '_', $slug );
		$id    = isset( $parts[0] ) ? $parts[0] : '';
		$link  = false;

		if ( ! ( $type && $id ) ) {
			return;
		}

		if ( 'taxonomy' === $type ) {
			$term = get_term( $id );
			if ( ! $term ) {
				return;
			}
			$link = get_term_link( $term );
		} elseif ( 'post_type' === $type ) {
			if ( ! post_type_exists( $id ) ) {
				return;
			}
			$link = get_post_type_archive_link( $id );
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

	/**
	 * Output the archive page content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
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
				'post_status'            => [ 'publish', 'private' ],
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

		$post   = reset( $posts );
		$status = $post->post_status;

		if ( 'publish' === $status || ( 'private' === $status && current_user_can( 'edit_posts' ) ) ) {
			$content = $this->get_processed_content( $post->post_content );
		}

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

	/**
	 * Deletes the archive page when a term is deleted.
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return void
	 */
	function delete_archive_page( $term_id ) {
		$slug = $this->get_term_slug( $term_id );
		$page = get_page_by_path( $slug, OBJECT, $this->post_type );
		if ( ! $page ) {
			return;
		}
		wp_delete_post( $page->ID );
	}

	/**
	 * Gets processed content, ready for display.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The raw content.
	 *
	 * @return string
	 */
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

	/**
	 * Gets an archive page ID.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	function get_page_id() {
		$slug = false;
		if ( $this->is_taxonomy() ) {
			$slug = $this->get_term_slug( get_queried_object_id() );
		} elseif ( $this->is_post_type() ) {
			$slug = $this->get_post_type_slug( get_post_type() );
		}
		return $slug ? get_page_by_path( $slug, OBJECT, $this->post_type ) : 0;
	}

	/**
	 * Builds a post type archive title from the post type name.
	 *
	 * @since 0.1.0
	 *
	 * @param string The post type name.
	 *
	 * @return string
	 */
	function get_post_type_title( $post_type ) {
		$post_type = get_post_type_object( $post_type );
		return $this->get_title( $post_type->label );
	}

	/**
	 * Builds a post type archive slug from the post type name.
	 *
	 * @since 0.1.0
	 *
	 * @param string The post type name.
	 *
	 * @return string
	 */
	function get_post_type_slug( $post_type) {
		return $this->get_slug( 'post_type', $post_type );
	}

	/**
	 * Builds a term title from the term ID.
	 *
	 * @since 0.1.0
	 *
	 * @param int The term ID.
	 *
	 * @return string
	 */
	function get_term_title( $term_id ) {
		$term     = get_term( $term_id );
		$taxonomy = get_taxonomy( $term->taxonomy );
		return $this->get_title( $term->name, $taxonomy->labels->singular_name );
	}

	/**
	 * Builds a term slug from the term ID.
	 *
	 * @since 0.1.0
	 *
	 * @param int The term ID.
	 *
	 * @return string
	 */
	function get_term_slug( $term_id ) {
		return $this->get_slug( 'taxonomy', $term_id );
	}

	/**
	 * Builds a title from values.
	 *
	 * {Post type label} [Archive]
	 * {Term name} [{Taxonomy label}]
	 *
	 * @since 0.1.0
	 *
	 * @param $name  The content name.
	 * @param $label The content label.
	 *
	 * @return string
	 */
	function get_title( $name, $label = '' ) {
		$label = $label ?: __( 'Archive', 'mai-archive-pages' );
		return sprintf( '%s [%s]', $name, $label );
	}

	/**
	 * Builds a slug name from the content type and content name.
	 *
	 * mai_post_type_{post_type_name}
	 * mai_taxonomy_{term_id}
	 *
	 * @since 0.1.0
	 *
	 * @param string     $content_type The content type, 'post_type' or 'taxonomy'.
	 * @param string|int $id           The content name or id, either post type name or term id.
	 *
	 * @return string
	 */
	function get_slug( $content_type, $id ) {
		return sprintf( '%s_%s_%s', $this->prefix, $content_type, $id );
	}

	/**
	 * Checks if current page is a post type archive.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	function is_post_type() {
		return is_home() || is_post_type_archive();
	}

	/**
	 * Checks if current page is a taxonomy archive.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	function is_taxonomy() {
		return is_category() || is_tag() || is_tax();
	}

	/**
	 * Checks if the user has access to create or edit archives.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	function has_access() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Checks if a string contains at least one specified string.
	 * Taken from mai_has_string() in Mai Engine plugin.
	 *
	 * @since 0.1.0
	 *
	 * @param string|array $needle   String or array of strings to check for.
	 * @param string       $haystack String to check in.
	 *
	 * @return string
	 */
	function has_string( $needle, $haystack ) {
		if ( is_array( $needle ) ) {
			foreach ( $needle as $string ) {
				if ( false !== strpos( $haystack, $string ) ) {
					return true;
				}
			}

			return false;
		}

		return false !== strpos( $haystack, $needle );
	}
}
