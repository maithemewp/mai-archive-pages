<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

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

		add_action( 'admin_bar_menu',                      [ $this, 'admin_bar_link_front' ], 99 );
		add_action( 'admin_bar_menu',                      [ $this, 'admin_bar_link_back' ], 99 );
		add_action( 'load-edit.php',                       [ $this, 'load_archive_pages' ] );
		add_action( 'load-term.php',                       [ $this, 'load_term_edit' ] );
		add_action( 'genesis_before_content_sidebar_wrap', [ $this, 'do_content_outside_before' ], 20 );
		add_action( 'genesis_loop',                        [ $this, 'do_content_inside_before' ], 5 );
		add_action( 'genesis_loop',                        [ $this, 'do_content_inside_after' ], 15 );
		add_action( 'genesis_after_content_sidebar_wrap',  [ $this, 'do_content_outside_after' ], 5 );
		add_action( 'woocommerce_archive_description',     [ $this, 'do_shop_content_before' ], 12 );
		add_action( 'woocommerce_after_main_content',      [ $this, 'do_shop_content_after' ], 12 );
		add_action( 'delete_term',                         [ $this, 'delete_archive_page' ] );
		add_filter( 'post_type_link',                      [ $this, 'permalink' ], 10, 2 );
	}

	/**
	 * Output the admin toolbar link on the front end.
	 * Links to the archive page content editor.
	 *
	 * @since 0.1.0
	 *
	 * @param object $wp_admin_bar The admin bar object.
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

		if ( ! ( maiap_is_taxonomy() || maiap_is_post_type() ) ) {
			return;
		}

		$this->add_admin_bar_link_front( $wp_admin_bar, true );
		$this->add_admin_bar_link_front( $wp_admin_bar, false );
	}

	/**
	 * Adds an admin bar link.
	 *
	 * @since TBD
	 *
	 * @param object $wp_admin_bar The admin bar object.
	 * @param bool   $before       If before or after content.
	 *
	 * @return void
	 */
	function add_admin_bar_link_front( $wp_admin_bar, $before ) {
		$page_id = $this->get_archive_page_id( $before );
		$node_id = $before ? 'mai-archive-page-before' : 'mai-archive-page-after';
		$parent  = 'edit';

		if ( $page_id ) {
			$link_title = $before ? __( 'Edit Content Before', 'mai-archive-pages' ) : __( 'Edit Content After', 'mai-archive-pages' );
		} else {
			$link_title = $before ? __( 'Add Content Before', 'mai-archive-pages' ) : __( 'Add Content After', 'mai-archive-pages' );
		}

		if ( is_post_type_archive() ) {
			// If has Genesis CPT archives settings.
			if ( post_type_supports( get_post_type(), 'genesis-cpt-archives-settings' ) ) {
				$parent = 'cpt-archive-settings';
			}
			// If Woo Shop page.
			elseif ( maiap_is_shop() ) {
				$parent = 'mai-woocommerce-shop-page';
			}
			// Any other post type.
			else {
				$parent = 'mai-archive-page';

				static $has_parent_node = false;

				if ( ! $has_parent_node ) {
					if ( $page_id ) {
						$parent_title = __( 'Edit Archive Content', 'mai-archive-pages' );
					} else {
						$parent_title = __( 'Add Archive Content', 'mai-archive-pages' );
					}

					$wp_admin_bar->add_node( [
						'id'     => $parent,
						'title'  => sprintf( '<span class="ab-icon dashicons dashicons-edit" style="margin-top:2px;"></span><span class="ab-label">%s</span>', $parent_title ),
						'href'   => '#',
					] );

					$has_parent_node = true;
				}
			}
		}

		if ( $page_id ) {
			$wp_admin_bar->add_node( [
				'id'     => $node_id,
				'parent' => $parent,
				'title'  => $link_title,
				'href'   => get_edit_post_link( $page_id, false ),
			] );

			return;
		}

		$type = $id = '';

		if ( maiap_is_taxonomy() ) {
			$type = 'taxonomy';
			$id   = get_queried_object_id();

			if ( ! $id ) {
				return;
			}

		} elseif ( maiap_is_post_type() ) {
			$type = 'post_type';
			$id   = get_post_type();

			if ( ! $id ) {
				return;
			}
		}

		if ( ! ( $type && $id ) ) {
			return;
		}

		$url = $this->get_create_new_archive_page_url( $id, $type, $before );

		if ( ! $url ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'     => $node_id,
			'parent' => $parent,
			'title'  => $link_title,
			'href'   => $url,
		] );
	}

	/**
	 * Gets the url used to create a new archive page.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $id   The content ID.
	 * @param string     $type The content type.
	 *
	 * @return void
	 */
	function get_create_new_archive_page_url( $id, $type, $before ) {
		if ( ! ( $id && $type ) ) {
			return;
		}

		switch ( $type ) {
			case 'taxonomy':
				$title = $this->get_term_title( $id, $before );
				$slug  = maiap_get_archive_term_slug( $id, $before );
			break;
			case 'post_type':
				$title = $this->get_post_type_title( $id, $before );
				$slug  = maiap_get_archive_post_type_slug( $id, $before );
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
			admin_url( 'edit.php?post_type=' . $this->post_type )
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

		add_action( "{$taxonomy}_edit_form", [ $this, 'add_edit_archive_buttons' ], 2, 2 );
	}

	/**
	 * Adds edit archive button to term edit page.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Term $term     The term you are editing.
	 * @param string  $taxonomy The taxonomy the term belongs to.
	 *
	 * @return void
	 */
	function add_edit_archive_buttons( $term, $taxonomy ) {
		$before_slug = maiap_get_archive_term_slug( $term->term_id, true );
		$after_slug  = maiap_get_archive_term_slug( $term->term_id, false );

		if ( ! ( $before_slug || $after_slug ) ) {
			return;
		}

		$before = $this->get_edit_archive_button( $term, $before_slug, true );
		$after  = $this->get_edit_archive_button( $term, $after_slug, false );

		if ( ! ( $before || $after ) ) {
			return;
		}

		echo '<table class="form-table" role="presentation">';
			echo '<tbody>';
				echo '<tr class="form-field term-content-wrap">';
					printf( '<th scope="row">%s</th>', __( 'Content', 'mai-archive-pages' ) );
					echo '<td>';
					if ( $before ) {
						echo $before;
					}
					if ( $after ) {
						echo $after;
					}
					echo '<td>';
				echo '</tr>';
			echo '</body>';
		echo '</table>';
	}

	/**
	 * Gets a button link to the term edit page.
	 *
	 * @since TBD
	 *
	 * @param WP_Term $term The term you are editing.
	 * @param string  $slug The slug of the existing post, if available.
	 * @param bool    $before If before or after content.
	 *
	 * @return string
	 */
	function get_edit_archive_button( $term, $slug, $before ) {
		$existing = get_page_by_path( $slug, OBJECT, $this->post_type );
		$append   = $before ? __( 'Before', 'mai-archive-pages' ) : __( 'After', 'mai-archive-pages' );

		if ( $existing ) {
			$link = get_edit_post_link( $existing, false );
			$text = sprintf( '%s %s', __( 'Edit Archive Content', 'mai-archive-pages' ), $append );
		} else {
			$link = $this->get_create_new_archive_page_url( $term->term_id, 'taxonomy', $before );
			$text = sprintf( '%s %s', __( 'Add Archive Content', 'mai-archive-pages' ), $append );
		}

		if ( ! $link ) {
			return;
		}

		return sprintf( '<a href="%s" class="button button-secondary" style="margin-right:12px;"><span class="dashicons dashicons-edit" style="margin-top:4px;margin-left:-4px;"></span> %s</a>', $link, $text );
	}

	/**
	 * Creates a new archive page when loading the edit page.
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

		$maiap = isset( $_GET['maiap'] ) ? sanitize_key( $_GET['maiap'] ) : '';
		$title = isset( $_GET['maiap_title'] ) ? esc_html( $_GET['maiap_title'] ) : '';
		$slug  = isset( $_GET['maiap_slug'] ) ? esc_html( $_GET['maiap_slug'] ) : '';

		if ( 'new' !== $maiap ) {
			return;
		}

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
	 * Render the archive page content outside of the container.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function do_content_outside_before() {
		if ( ! ( maiap_is_post_type() || maiap_is_taxonomy() ) ) {
			return;
		}

		if ( 'outside' !== $this->get_content_location( true ) ) {
			return;
		}

		$this->do_content( true );
	}

	/**
	 * Render the archive page content inside of the container.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function do_content_inside_before() {
		if ( ! ( maiap_is_post_type() || maiap_is_taxonomy() ) ) {
			return;
		}

		if ( maiap_is_shop() ) {
			return;
		}

		if ( 'inside' !== $this->get_content_location( true ) ) {
			return;
		}

		$this->do_content( true );
	}

	/**
	 * Render the archive page content inside of the container.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function do_content_inside_after() {
		if ( ! ( maiap_is_post_type() || maiap_is_taxonomy() ) ) {
			return;
		}

		if ( maiap_is_shop() ) {
			return;
		}

		if ( 'inside' !== $this->get_content_location( false ) ) {
			return;
		}

		$this->do_content( false );
	}

	/**
	 * Render the archive page content outside of the container.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function do_content_outside_after() {
		if ( ! ( maiap_is_post_type() || maiap_is_taxonomy() ) ) {
			return;
		}

		if ( 'outside' !== $this->get_content_location( false ) ) {
			return;
		}

		$this->do_content( false );
	}

	/**
	 * Output the archive page content.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function do_shop_content_before() {
		if ( ! ( maiap_is_post_type() && maiap_is_shop() ) ) {
			return;
		}

		if ( 'outside' === $this->get_content_location( true ) ) {
			return;
		}

		$this->do_content( true );
	}

	/**
	 * Output the archive page content.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function do_shop_content_after() {
		if ( ! ( maiap_is_post_type() && maiap_is_shop() ) ) {
			return;
		}

		if ( 'outside' === $this->get_content_location( false ) ) {
			return;
		}

		$this->do_content( false );
	}

	/**
	 * Output the archive page content.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $before If before or after content.
	 *
	 * @return void
	 */
	function do_content( $before ) {
		$post = maiap_get_archive_page( $before );

		if ( ! $post ) {
			return;
		}

		$status  = $post->post_status;
		$content = trim( $post->post_content );

		if ( ! $content ) {
			return;
		}

		if ( 'publish' === $status || ( 'private' === $status && current_user_can( 'edit_posts' ) ) ) {
			$content = $this->get_processed_content( $post->post_content );
		}

		if ( ! $content ) {
			return;
		}

		$location = $before ? 'before' : 'after';
		$atts     = [
			'class' => sprintf( 'archive-page-content archive-page-content-%s', $location ),
		];

		genesis_markup(
			[
				'open'    => '<div %s>',
				'close'   => '</div>',
				'content' => $content,
				'context' => 'archive-page-content',
				'echo'    => true,
				'atts'    => $atts,
				'params'  => [
					'location' => $location,
				]
			]
		);
	}

	/**
	 * Gets content location, with fallback for sites prior to this setting being added.
	 *
	 *
	 * @since TBD
	 *
	 * @param bool $before If before or after content.
	 *
	 * @return string
	 */
	function get_content_location( $before ) {
		static $cache = null;

		if ( ! is_null( $cache ) && isset( $cache[ $before ] ) ) {
			return $cache[ $before ];
		}

		$cache = ! is_array( $cache ) ? [] : $cache;
		$post  = maiap_get_archive_page( $before );

		if ( ! $post ) {
			$cache[ $before ] = '';
			return;
		}

		// Get location, with fallback for sites prior to this setting being added.
		$location         = get_post_meta( $post->ID, 'maiap_location', true );
		$cache[ $before ] = $location ?: 'inside';

		return $cache[ $before ];
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
		$slugs = [
			maiap_get_archive_term_slug( $term_id, true ),
			maiap_get_archive_term_slug( $term_id, false ),
		];

		if ( ! $slugs ) {
			return;
		}

		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, $this->post_type );

			if ( ! $page ) {
				continue;
			}

			wp_delete_post( $page->ID );
		}
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
	 * @param string $slug The post slug.
	 *
	 * @return string|int
	 */
	function get_id_from_slug( $slug ) {
		$type   = $this->get_type_from_slug( $slug );
		$before = sprintf( '%s_%s_', $this->prefix, $type );
		$after  = sprintf( '%s_%s_after_', $this->prefix, $type );

		if ( mai_has_string( $after, $slug ) ) {
			$id = str_replace( $after, '', $slug );
		} else {
			$id = str_replace( $before, '', $slug );
		}

		return $id;
	}

	/**
	 * Gets the type from a post slug.
	 *
	 * @since 0.1.0
	 *
	 * @param string $slug The post slug.
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
	 * @param bool $before If before or after content.
	 *
	 * @return int
	 */
	function get_archive_page_id( $before ) {
		$slug = $this->get_archive_page_slug( $before );
		$page = $slug ? get_page_by_path( $slug, OBJECT, $this->post_type ) : false;

		return $page ? $page->ID : 0;
	}

	/**
	 * Gets an archive page slug.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $before If before or after content.
	 *
	 * @return string
	 */
	function get_archive_page_slug( $before ) {
		$slug = '';

		if ( maiap_is_taxonomy() ) {
			$slug = maiap_get_archive_term_slug( get_queried_object_id(), $before );
		} elseif ( maiap_is_post_type() ) {
			$slug = maiap_get_archive_post_type_slug( get_post_type(), $before );
		}

		return $slug;
	}

	/**
	 * Builds a post type archive title from the post type name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $post_type The post type name.
	 * @param bool   $before    If before or after content.
	 *
	 * @return string
	 */
	function get_post_type_title( $post_type, $before ) {
		$post_type = get_post_type_object( $post_type );

		return $this->get_title( $post_type->label, '', $before );
	}

	/**
	 * Builds a term title from the term ID.
	 *
	 * @since 0.1.0
	 *
	 * @param int  $term_id The term ID.
	 * @param bool $before  If before or after content.
	 *
	 * @return string
	 */
	function get_term_title( $term_id, $before ) {
		$term     = get_term( $term_id );
		$taxonomy = get_taxonomy( $term->taxonomy );

		return $this->get_title( $term->name, $taxonomy->label, $before );
	}

	/**
	 * Builds a title from values.
	 *
	 * {Post type label} [Archive] - {Before/After}
	 * {Term name} [{Taxonomy label}] - {Before/After}
	 *
	 * @since 0.1.0
	 *
	 * @param string $name   The content name.
	 * @param string $label  The content label.
	 * @param bool   $before If before or after content.
	 *
	 * @return string
	 */
	function get_title( $name, $label, $before ) {
		$label  = $label ?: __( 'Archive', 'mai-archive-pages' );
		$append = $before ? __( 'Before', 'mai-archive-pages' ) : __( 'After', 'mai-archive-pages' );

		return sprintf( '%s [%s] - %s', $name, $label, $append );
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
