<?php

/**
 * Output the archive page content.
 *
 * @since 1.3.0
 *
 * @param bool $before If before or after content.
 *
 * @return string|WP_Post
 */
function maiap_get_archive_page( $before ) {
	static $cache = null;

	if ( ! is_null( $cache ) && isset( $cache[ $before ] ) ) {
		return $cache[ $before ];
	}

	$cache = ! is_array( $cache ) ? [] : $cache;
	$slug  = '';

	if ( maiap_is_taxonomy() && ! is_paged() ) {
		$term = get_queried_object();
		$slug = $term && 'WP_Term' === get_class( $term ) ? maiap_get_archive_term_slug( $term->term_id, $before ) : '';

	} elseif ( maiap_is_post_type() && ! is_paged() ) {
		$type = is_home() ? 'post' : get_post_type();
		$slug = $type ? maiap_get_archive_post_type_slug( $type, $before ) : '';
	} elseif ( is_author() && ! is_paged() ) {
		$type = (int) get_query_var( 'author' );
		$slug = $type ? maiap_get_archive_author_slug( $type, $before ) : '';
	} elseif ( is_search() && ! is_paged() ) {
		$type = 'results';
		$slug = $type ? maiap_get_archive_search_slug( $type, $before ) : '';
	}

	if ( ! $slug ) {
		$cache[ $before ] = $slug;
		return;
	}

	$cache[ $before ] = get_page_by_path( $slug, OBJECT, 'mai_archive_page' );

	return $cache[ $before ];
}

/**
 * Checks if current page is the WooCommerce Shop page.
 *
 * @since 1.3.0
 *
 * @return bool
 */
function maiap_is_shop() {
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
 * @since 1.3.0
 *
 * @return bool
 */
function maiap_is_post_type() {
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
 * @since 1.3.0
 *
 * @return bool
 */
function maiap_is_taxonomy() {
	static $taxonomy = null;

	if ( ! is_null( $taxonomy ) ) {
		return $taxonomy;
	}

	$taxonomy = is_category() || is_tag() || is_tax();

	return $taxonomy;
}

/**
 * Builds a term slug from the term ID.
 *
 * @access private
 *
 * @since 1.3.0
 *
 * @param int  $term_id The term ID.
 * @param bool $before  If before or after content.
 *
 * @return string
 */
function maiap_get_archive_term_slug( $term_id, $before ) {
	return maiap_get_slug( 'taxonomy', $term_id, $before );
}

/**
 * Builds a post type archive slug from the post type name.
 *
 * @access private
 *
 * @since 1.3.0
 *
 * @param string $post_type The post type name.
 * @param bool   $before    If before or after content.
 *
 * @return string
 */
function maiap_get_archive_post_type_slug( $post_type, $before ) {
	return maiap_get_slug( 'post_type', $post_type, $before );
}

/**
 * Builds an author archive slug from the author ID.
 *
 * @access private
 *
 * @since 1.4.0
 *
 * @param int  $author_id The author ID.
 * @param bool $before    If before or after content.
 *
 * @return string
 */
function maiap_get_archive_author_slug( $author_id, $before ) {
	return maiap_get_slug( 'author', $author_id, $before );
}

/**
 * Builds an search archive slug.
 *
 * @access private
 *
 * @since 1.4.0
 *
 * @param string $slug   The search slug.
 * @param bool   $before If before or after content.
 *
 * @return string
 */
function maiap_get_archive_search_slug( $slug, $before ) {
	return maiap_get_slug( 'search', $slug, $before );
}

/**
 * Builds a slug name from the content type and content name.
 *
 * mai_post_type_{post_type_name}  or  mai_post_type_after_{post_type_name}
 * mai_taxonomy_{term_id}          or  mai_taxonomy_{term_id}
 *
 * @access private
 *
 * @since 1.3.0
 *
 * @param string     $type   The content type, 'post_type' or 'taxonomy'.
 * @param string|int $id     The content name or id, either post type name or term id.
 * @param bool       $before If before or after content.
 *
 * @return string
 */
function maiap_get_slug( $type, $id, $before ) {
	$append = $before ? '' : '_after';

	return sprintf( '%s_%s%s_%s', 'mai', $type, $append, $id );
}