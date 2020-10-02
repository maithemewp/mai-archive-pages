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

		add_action( 'admin_bar_menu',               [ $this, 'admin_bar_link_front' ], 90 );
		add_action( 'admin_bar_menu',               [ $this, 'admin_bar_link_back' ], 90 );
		add_action( 'load-edit.php',                [ $this, 'load_archive_pages' ] );
		add_action( 'load-term.php',                [ $this, 'load_term_edit' ] );
		add_action( 'genesis_before_while',         [ $this, 'do_content' ] );
		add_action( 'woocommerce_before_shop_loop', [ $this, 'do_content' ], 12 );
		add_action( 'delete_term',                  [ $this, 'delete_archive_page' ] );
		add_filter( 'post_type_link',               [ $this, 'permalink' ], 10, 2 );
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

		$link_title = '<span class="ab-icon dashicons dashicons-edit" style="margin-top:2px;"></span><span class="ab-label">' . __( 'Edit Archive Content', 'mai-archive-pages' ) . '</span>';
		$page_id    = $this->get_archive_page_id();

		if ( $page_id ) {

			$wp_admin_bar->add_node( [
				'id'    => 'mai-archive-page',
				'title' => $link_title,
				'href'  => get_edit_post_link( $page_id, false ),
			] );

			return;
		}

		$type = $id = '';

		if ( $this->is_taxonomy() ) {
			$type = 'taxonomy';
			$id   = get_queried_object_id();

			if ( ! $id ) {
				return;
			}

		} elseif ( $this->is_post_type() ) {
			$type = 'post_type';
			$id   = get_post_type();

			if ( ! $id ) {
				return;
			}
		}

		if ( ! ( $type && $id ) ) {
			return;
		}

		$url = $this->get_create_new_archive_page_url( $id, $type );

		if ( ! $url ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'mai-archive-page',
			'title' => $link_title,
			'href'  => $url,
		] );
	}

	/**
	 * Gets the url used to create a new archive page.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $id The content ID.
	 * @param string $type   The content type.
	 *
	 * @return void
	 */
	function get_create_new_archive_page_url( $id, $type ) {
		if ( ! ( $id && $type ) ) {
			return;
		}

		switch ( $type ) {
			case 'taxonomy':
				$title = $this->get_term_title( $id );
				$slug  = $this->get_archive_term_slug( $id );
			break;
			case 'post_type':
				$title = $this->get_post_type_title( $id );
				$slug  = $this->get_archive_post_type_slug( $id );
			break;
			default:
				$title = '';
				$slug  = '';
		}

		if ( ! ( $title && $slug ) ) {
			return;
		}

		return add_query_arg(
			[
				'maiap'       => 'new',
				'maiap_title' => urlencode( $title ),
				'maiap_slug'  => $slug,
			],
			admin_url( 'edit.php?post_type=' . $this->post_type ),
		);
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

		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$slug    = get_post_field( 'post_name', $post_id );
		$type    = $this->get_type_from_slug( $slug );
		$id      = $this->get_id_from_slug( $slug );

		switch ( $type ) {
			case 'taxonomy':
				$link = ( $term = get_term( $id ) ) ? get_term_link( $term ) : false;
			break;
			case 'post_type':
				$link = post_type_exists( $id ) ? get_post_type_archive_link( $id ) : false;
			break;
			default:
				$link = false;
		}

		if ( ! $link ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'mai-archive-page',
			'title' => '<span class="ab-icon dashicons dashicons-admin-links" style="font-size:18px;margin-top:3px;"></span><span class="ab-label">' . __( 'View Archive Page', 'mai-archive-pages' ) . '</span>',
			'href'  => $link,
		] );
	}

	/**
	 * Runs new function on dynamic term edit forms.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function load_term_edit() {
		$current_screen = get_current_screen();
		$taxonomy       = $current_screen->taxonomy;

		add_action( "{$taxonomy}_edit_form", [ $this, 'add_edit_archive_button' ], 2, 2 );
	}

	/**
	 * Adds edit archive button to term edit page.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Term $term    The term you are editing.
	 * @param string $taxonomy The taxonomy the term belongs to.
	 *
	 * @return void
	 */
	function add_edit_archive_button( $term, $taxonomy ) {
		$slug     = $this->get_archive_term_slug( $term->term_id );
		$existing = get_page_by_path( $slug, OBJECT, $this->post_type );

		if ( $existing ) {
			$link = get_edit_post_link( $existing, false );
		} else {
			$link = $this->get_create_new_archive_page_url( $term->term_id, 'taxonomy' );
		}

		if ( ! $link ) {
			return;
		}

		echo '<table class="form-table" role="presentation">';
			echo '<tbody>';
				echo '<tr class="form-field term-content-wrap">';
					printf( '<th scope="row">%s</th>', __( 'Content', 'mai-archive-pages' ) );
					printf( '<td><a href="%s" class="button button-secondary"><span class="dashicons dashicons-edit" style="margin-top:4px;margin-left:-4px;"></span> %s</a></td>', $link, __( 'Edit Archive Page', 'mai-archive-pages' ) );
				echo '</tr>';
			echo '</body>';
		echo '</table>';
	}

	/**
	 * Creates a new archive page.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function load_archive_pages() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! $this->has_access() ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( $this->post_type !== $current_screen->post_type ) {
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
			wp_redirect( get_edit_post_link( $existing, false ) );
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
			$slug = $term ? $this->get_archive_term_slug( $term->term_id ) : '';

		} elseif ( $this->is_post_type() && ! is_paged() ) {

			$slug = $this->get_archive_post_type_slug( get_post_type() );
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
				'suppress_filters'       => false, // https://github.com/10up/Engineering-Best-Practices/issues/116
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
	 * @since 0.1.0
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return void
	 */
	function delete_archive_page( $term_id ) {
		$slug = $this->get_archive_term_slug( $term_id );
		$page = get_page_by_path( $slug, OBJECT, $this->post_type );
		if ( ! $page ) {
			return;
		}
		wp_delete_post( $page->ID );
	}

	/**
	 * Use the 'url' custom field value for the permalink URL of all favorite posts.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url The existing URL.
	 *
	 * @return string The modified URL.
	 */
	function permalink( $url, $post ) {
		if ( $this->post_type !== $post->post_type ) {
			return $url;
		}

		return $this->get_archive_url_from_slug( $post->post_name ) ?: $url;
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
	 * Gets an archive url from post slug.
	 *
	 * @since 0.1.0
	 *
	 * @var string $slug The post slug.
	 *
	 * @return string
	 */
	function get_archive_url_from_slug( $slug ) {
		$id = $this->get_id_from_slug( $slug );

		switch ( $this->get_type_from_slug( $slug ) ) {
			case 'taxonomy':
				$link = ( $term = get_term( $id ) ) ? get_term_link( $term ) : false;
			break;
			case 'post_type':
				$link = post_type_exists( $id ) ? get_post_type_archive_link( $id ) : false;
			break;
			default:
				$link = '';
		}

		return $link;
	}

	/**
	 * Gets the id from a post slug.
	 * For post archive it's the post_type name.
	 * For taxonomy archive it's the term ID.
	 *
	 * @since 0.1.0
	 *
	 * @var string $slug The post slug.
	 *
	 * @return string|int
	 */
	function get_id_from_slug( $slug ) {
		return str_replace( sprintf( '%s_%s_', $this->prefix, $this->get_type_from_slug( $slug ) ), '', $slug );
	}

	/**
	 * Gets the type from a post slug.
	 *
	 * @since 0.1.0
	 *
	 * @var string $slug The post slug.
	 *
	 * @return string
	 */
	function get_type_from_slug( $slug ) {
		$type = '';

		if ( $this->has_string( sprintf( '%s_taxonomy_', $this->prefix ), $slug ) ) {
			$type = 'taxonomy';
		} elseif ( $this->has_string( sprintf( '%s_post_type_', $this->prefix ), $slug ) ) {
			$type = 'post_type';
		}

		return $type;
	}

	/**
	 * Gets an archive page ID.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	function get_archive_page_id() {
		$slug = $this->get_archive_page_slug();
		return $slug ? get_page_by_path( $slug, OBJECT, $this->post_type ) : 0;
	}

	/**
	 * Gets an archive page slug.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	function get_archive_page_slug() {
		$slug = '';
		if ( $this->is_taxonomy() ) {
			$slug = $this->get_archive_term_slug( get_queried_object_id() );
		} elseif ( $this->is_post_type() ) {
			$slug = $this->get_archive_post_type_slug( get_post_type() );
		}
		return $slug;
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
	function get_archive_post_type_slug( $post_type) {
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
		return $this->get_title( $term->name, $taxonomy->label );
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
	function get_archive_term_slug( $term_id ) {
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
	 * @param string     $type The content type, 'post_type' or 'taxonomy'.
	 * @param string|int $id           The content name or id, either post type name or term id.
	 *
	 * @return string
	 */
	function get_slug( $type, $id ) {
		return sprintf( '%s_%s_%s', $this->prefix, $type, $id );
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
