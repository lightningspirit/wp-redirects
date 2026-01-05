<?php
/**
 * Redirects Runtime Hooks
 * 
 * @package WP_Redirects
 * @since 0.1.0
 */

/**
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace WP_Redirects;

use function is_string;

// Don't load directly.
if ( ! \defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Perform redirects
 */
add_action(
	'template_redirect',
	function () {
		if ( is_admin() ) {
			return;
		}

		$path  = request_path_only();
		$query = request_query_string();

		$match = match_redirect( $path . ( $query !== '' ? ( '?' . $query ) : '' ) );
		if ( ! $match ) {
			return;
		}

		wp_redirect( $match['target'], (int) $match['type'] );
		exit;
	},
	1
);

function request_path_only(): string {
	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
	$path = is_string( $path ) ? $path : '/';
	return '/' . ltrim( $path, '/' );
}

function request_query_string(): string {
	$q = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY );
	return is_string( $q ) ? $q : '';
}

function is_wildcard( string $from ): bool {
	return strpos( $from, '*' ) !== false;
}

/**
 * Convert wildcard pattern to regex with capture groups for each '*'
 * Example: /old*\/x/* -> #^/old/(.*)/x/(.*)$#
 */
function wildcard_to_regex( string $pattern ): string {
	$quoted = preg_quote( $pattern, '#' );
	$regex  = str_replace( '\*', '(.*)', $quoted );
	return '#^' . $regex . '$#';
}

/**
 * Apply $1, $2... replacements into "to"
 */
function apply_captures( string $to, array $matches ): string {
	for ( $i = 1; $i < count( $matches ); $i++ ) {
		$to = str_replace( '$' . $i, $matches[ $i ], $to );
	}
	return $to;
}

/**
 * Preserve request query string by default.
 * - If target has no "?" -> append "?{query}"
 * - If target already has "?" -> append "&{query}"
 */
function append_query_string( string $target, string $requestQuery ): string {
	$requestQuery = ltrim( (string) $requestQuery, '?' );
	if ( $requestQuery === '' ) {
		return $target;
	}

	return ( strpos( $target, '?' ) === false )
		? ( $target . '?' . $requestQuery )
		: ( $target . '&' . $requestQuery );
}

/**
 * Find a matching redirect for a given URL (path + optional query).
 * Returns: ['type' => int, 'target' => string, 'matched_from' => string] or null.
 */
function match_redirect( string $urlOrPath ): ?array {
	$storage = new Storage();
    $rules  = $storage->get_rules();

	if ( ! $rules ) {
		return null;
	}

	// allow either "/path?x=1" or full URL
	$path = parse_url( $urlOrPath, PHP_URL_PATH );
	$path = is_string( $path ) ? $path : ( is_string( $urlOrPath ) ? $urlOrPath : '/' );
	$path = '/' . ltrim( $path, '/' );

	$query = parse_url( $urlOrPath, PHP_URL_QUERY );
	$query = is_string( $query ) ? $query : '';

	// exact map
	$exact = [];
	foreach ( $rules as $r ) {
		if ( ! isset( $r['from'], $r['to'], $r['type'] ) ) {
			continue;
		}
		if ( ! is_wildcard( $r['from'] ) ) {
			$exact[ $r['from'] ] = $r;
		}
	}
	if ( isset( $exact[ $path ] ) ) {
		$r      = $exact[ $path ];
		$target = append_query_string( $r['to'], $query );
		return [
            'type' => (int) $r['type'],
            'target' => $target,
            'matched_from' => $r['from'],
        ];
	}

	// wildcard in saved order
	foreach ( $rules as $r ) {
		if ( ! isset( $r['from'], $r['to'], $r['type'] ) ) {
			continue;
		}
		if ( ! is_wildcard( $r['from'] ) ) {
			continue;
		}

		$regex   = wildcard_to_regex( $r['from'] );
		$matches = [];
		if ( preg_match( $regex, $path, $matches ) ) {
			$target = apply_captures( $r['to'], $matches );
			$target = append_query_string( $target, $query );
			return [
                'type' => (int) $r['type'],
                'target' => $target,
                'matched_from' => $r['from'],
            ];
		}
	}

	return null;
}
