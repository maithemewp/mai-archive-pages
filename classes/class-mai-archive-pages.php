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

		add_action( 'admin_bar_menu',                  [ $this, 'admin_bar_link_front' ], 99 );
		add_action( 'admin_bar_menu',                  [ $this, 'admin_bar_link_back' ], 99 );
		add_action( 'load-edit.php',                   [ $this, 'load_archive_pages' ] );
		add_action( 'load-term.php',                   [ $this, 'load_term_edit' ] );
		add_action( 'genesis_loop',                    [ $this, 'do_content_before' ], 5 );
		add_action( 'genesis_loop',                    [ $this, 'do_content_after' ], 15 );
		add_action( 'woocommerce_archive_description', [ $this, 'do_shop_content_before' ], 12 );
		add_action( 'woocommerce_after_main_content',  [ $this, 'do_shop_content_after' ], 12 );
		add_action( 'delete_term',                     [ $this, 'delete_archive_page' ] );
		add_filter( 'post_type_link',                  [ $this, 'permalink' ], 10, 2 );
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

		if ( ! ( $this->is_taxonomy() || $this->is_post_type() ) ) {
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
			elseif ( $this->is_shop() ) {
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
				$slug  = $this->get_archive_term_slug( $id, $before );
			break;
			case 'post_type':
				$title = $this->get_post_type_title( $id, $before );
				$slug  = $this->get_archive_post_type_slug( $id, $before );
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
		$before_slug = $this->get_archive_term_slug( $term->term_id, true );
		$after_slug  = $this->get_archive_term_slug( $term->term_id, false );

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
	 * @since TBD
	 *
	 * @return void
	 */
	function do_content_before() {
		if ( ! ( $this->is_post_type() || $this->is_taxonomy() ) ) {
			return;
		}

		if ( $this->is_shop() ) {
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
	function do_content_after() {
		if ( ! ( $this->is_post_type() || $this->is_taxonomy() ) ) {
			return;
		}

		if ( $this->is_shop() ) {
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
		if ( ! ( $this->is_post_type() && $this->is_shop() ) ) {
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
		if ( ! ( $this->is_post_type() && $this->is_shop() ) ) {
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
		$slug = '';

		if ( $this->is_taxonomy() && ! is_paged() ) {

			$term = get_queried_object();
			$slug = $term ? $this->get_archive_term_slug( $term->term_id, $before ) : '';

		} elseif ( $this->is_post_type() && ! is_paged() ) {

			$slug = $this->get_archive_post_type_slug( get_post_type(), $before );
		}

		if ( ! $slug ) {
			return;
		}

		$post = get_page_by_path( $slug, OBJECT, 'mai_archive_page' );

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
			$this->get_archive_term_slug( $term_id, true ),
			$this->get_archive_term_slug( $term_id, false ),
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
	 * @return int
	 */
	function get_archive_page_id( $before ) {
		$slug = $this->get_archive_page_slug( $before );
		return $slug ? get_page_by_path( $slug, OBJECT, $this->post_type ) : 0;
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
		if ( $this->is_taxonomy() ) {
			$slug = $this->get_archive_term_slug( get_queried_object_id(), $before );
		} elseif ( $this->is_post_type() ) {
			$slug = $this->get_archive_post_type_slug( get_post_type(), $before );
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
	 * Builds a post type archive slug from the post type name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $post_type The post type name.
	 * @param bool   $before    If before or after content.
	 *
	 * @return string
	 */
	function get_archive_post_type_slug( $post_type, $before ) {
		return $this->get_slug( 'post_type', $post_type, $before );
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
	 * Builds a term slug from the term ID.
	 *
	 * @since 0.1.0
	 *
	 * @param int  $term_id The term ID.
	 * @param bool $before  If before or after content.
	 *
	 * @return string
	 */
	function get_archive_term_slug( $term_id, $before ) {
		return $this->get_slug( 'taxonomy', $term_id, $before );
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
	 * Builds a slug name from the content type and content name.
	 *
	 * mai_post_type_{post_type_name}  or  mai_post_type_after_{post_type_name}
	 * mai_taxonomy_{term_id}          or  mai_taxonomy_{term_id}
	 *
	 * @since 0.1.0
	 *
	 * @param string     $type   The content type, 'post_type' or 'taxonomy'.
	 * @param string|int $id     The content name or id, either post type name or term id.
	 * @param bool       $before If before or after content.
	 *
	 * @return string
	 */
	function get_slug( $type, $id, $before ) {
		$append = $before ? '' : '_after';
		return sprintf( '%s_%s%s_%s', $this->prefix, $type, $append, $id );
	}

	/**
	 * Checks if current page is the WooCommerce Shop page.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	function is_shop() {
		static $shop = null;
		if ( ! is_null( $shop ) ) {
			return $shop;
		}
		$shop = class_exists( 'WooCommerce' ) && function_exists( 'is_shop' ) && is_shop();
		return $shop;
	}

	/**
	 * Checks if current page is a post type archive.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	function is_post_type() {
		static $post_type = null;
		if ( ! is_null( $post_type ) ) {
			return $post_type;
		}
		$post_type = is_home() || is_post_type_archive();
		return $post_type;
	}

	/**
	 * Checks if current page is a taxonomy archive.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	function is_taxonomy() {
		static $taxonomy = null;
		if ( ! is_null( $taxonomy ) ) {
			return $taxonomy;
		}
		$taxonomy = is_category() || is_tag() || is_tax();
		return $taxonomy;
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
